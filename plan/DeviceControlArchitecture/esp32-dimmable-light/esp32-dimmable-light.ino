/**
 * ESP32-S3 Dimmable Light — IoT Platform Device Firmware
 *
 * Hardware:
 *   - Board: Freenove ESP32-S3 WROOM
 *   - LED:   Connected to GPIO 15 (PWM output)
 *   - Button: Connected to GPIO 4 (INPUT_PULLUP, active LOW)
 *
 * Behavior:
 *   - The LED brightness is controlled via PWM mapped from brightness_level 0–10.
 *   - MQTT commands on the control topic set the brightness remotely.
 *   - A physical button increments brightness_level by 1, wrapping from 10→0.
 *   - After 1.5 seconds of button inactivity, the device publishes its state.
 *     Each press resets the 1.5s countdown.
 *
 * MQTT Topics:
 *   Subscribe: devices/dimmable-light/{DEVICE_ID}/control  (Platform → Device)
 *   Publish:   devices/dimmable-light/{DEVICE_ID}/state    (Device → Platform)
 *
 * Payload format: {"brightness_level": <0-10>}
 */

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>

/* ─────────────────────── Configuration ─────────────────────── */

// WiFi credentials — change to match your network
const char* WIFI_SSID     = "YOUR_WIFI_SSID";
const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";

// MQTT broker — the NATS MQTT bridge address
const char* MQTT_HOST     = "{{MQTT_HOST}}";
const uint16_t MQTT_PORT  = {{MQTT_PORT}};
const char* MQTT_USER     = "{{MQTT_USER}}";
const char* MQTT_PASS     = "{{MQTT_PASS}}";
const char* MQTT_CLIENT   = "{{MQTT_CLIENT_ID}}";

// Device identity — must match the platform's external_id
const char* DEVICE_ID     = "{{DEVICE_ID}}";

// MQTT topics (built from the schema: baseTopic/identifier/suffix)
const char* TOPIC_CONTROL  = "{{CONTROL_TOPIC}}";
const char* TOPIC_STATE    = "{{STATE_TOPIC}}";
const char* TOPIC_PRESENCE = "{{PRESENCE_TOPIC}}";
const char* MQTT_CA_CERT = R"PEM(
{{MQTT_TLS_CA_CERT_PEM}}
)PEM";
const char* MQTT_CLIENT_CERT = R"PEM(
{{MQTT_TLS_CLIENT_CERT_PEM}}
)PEM";
const char* MQTT_CLIENT_KEY = R"PEM(
{{MQTT_TLS_CLIENT_KEY_PEM}}
)PEM";

// Hardware pins
const uint8_t PIN_LED    = 15;
const uint8_t PIN_BUTTON = 4;

// PWM configuration
const uint8_t  PWM_CHANNEL    = 0;
const uint32_t PWM_FREQ       = 5000;   // 5 kHz
const uint8_t  PWM_RESOLUTION = 8;      // 8-bit (0–255)

// Brightness range
const uint8_t BRIGHTNESS_MIN = 0;
const uint8_t BRIGHTNESS_MAX = 10;

// Debounce / publish delay
const unsigned long PUBLISH_DELAY_MS     = 1500; // 1.5s after last press
const unsigned long BUTTON_DEBOUNCE_MS   = 50;   // hardware debounce

/* ─────────────────────── State ─────────────────────── */

WiFiClientSecure wifiClient;
PubSubClient mqttClient(wifiClient);

uint8_t brightnessLevel = 0;

// Button state
bool     lastButtonState       = HIGH;  // pulled up = HIGH when not pressed
unsigned long lastDebounceTime = 0;

// Publish timer
bool          publishPending   = false;
unsigned long lastPressTime    = 0;

/* ─────────────────────── LED Control ─────────────────────── */

/**
 * Map brightness_level (0–10) to PWM duty cycle (0–255)
 * and apply it to the LED.
 */
void applyBrightness() {
  uint32_t duty = map(brightnessLevel, BRIGHTNESS_MIN, BRIGHTNESS_MAX, 0, 255);
  ledcWrite(PIN_LED, duty);

  Serial.printf("[LED] brightness_level=%d  duty=%lu\n", brightnessLevel, duty);
}

/* ─────────────────────── MQTT ─────────────────────── */

/**
 * Publish the current state as a retained message.
 */
void publishState() {
  JsonDocument doc;
  doc["brightness_level"] = brightnessLevel;

  char payload[64];
  serializeJson(doc, payload, sizeof(payload));

  bool ok = mqttClient.publish(TOPIC_STATE, payload, /* retained */ true);

  Serial.printf("[MQTT] Publish → %s  payload=%s  %s\n",
                TOPIC_STATE, payload, ok ? "OK" : "FAIL");
}

/**
 * Handle an incoming control command from the platform.
 */
void onMqttMessage(char* topic, byte* payload, unsigned int length) {
  char json[256];
  unsigned int copyLen = min(length, (unsigned int)(sizeof(json) - 1));
  memcpy(json, payload, copyLen);
  json[copyLen] = '\0';

  Serial.printf("[MQTT] Received ← %s  payload=%s\n", topic, json);

  JsonDocument doc;
  DeserializationError err = deserializeJson(doc, json);

  if (err) {
    Serial.printf("[MQTT] JSON parse error: %s\n", err.c_str());
    return;
  }

  if (doc.containsKey("brightness_level")) {
    int level = doc["brightness_level"].as<int>();

    // Clamp to valid range
    if (level < BRIGHTNESS_MIN) level = BRIGHTNESS_MIN;
    if (level > BRIGHTNESS_MAX) level = BRIGHTNESS_MAX;

    brightnessLevel = (uint8_t)level;
    applyBrightness();

    // Publish the confirmed state back immediately
    publishState();
  }
}

/* ─────────────────────── WiFi ─────────────────────── */

void connectWiFi() {
  Serial.printf("[WiFi] Connecting to %s", WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.printf("\n[WiFi] Connected — IP: %s\n", WiFi.localIP().toString().c_str());
}

/* ─────────────────────── MQTT Connection ─────────────────────── */

void connectMQTT() {
  if (MQTT_CA_CERT[0] != '\0') {
    wifiClient.setCACert(MQTT_CA_CERT);
  }
  if (MQTT_CLIENT_CERT[0] != '\0') {
    wifiClient.setCertificate(MQTT_CLIENT_CERT);
  }
  if (MQTT_CLIENT_KEY[0] != '\0') {
    wifiClient.setPrivateKey(MQTT_CLIENT_KEY);
  }

  mqttClient.setServer(MQTT_HOST, MQTT_PORT);
  mqttClient.setCallback(onMqttMessage);

  while (!mqttClient.connected()) {
    Serial.printf("[MQTT] Connecting to %s:%d as %s...\n", MQTT_HOST, MQTT_PORT, MQTT_CLIENT);

    // Configure Last Will and Testament (LWT):
    // If the broker loses contact, it publishes "offline" to the presence topic.
    bool connected = false;

    if (MQTT_USER[0] == '\0') {
      connected = mqttClient.connect(MQTT_CLIENT, nullptr, nullptr, TOPIC_PRESENCE, 1, true, "offline");
    } else {
      connected = mqttClient.connect(MQTT_CLIENT, MQTT_USER, MQTT_PASS, TOPIC_PRESENCE, 1, true, "offline");
    }

    if (connected) {
      Serial.println("[MQTT] Connected");

      // Announce online presence (retained so new subscribers see it immediately)
      mqttClient.publish(TOPIC_PRESENCE, "online", true);
      Serial.printf("[MQTT] Published presence → %s payload=online\n", TOPIC_PRESENCE);

      // Subscribe to the control topic (QoS 1)
      mqttClient.subscribe(TOPIC_CONTROL, 1);
      Serial.printf("[MQTT] Subscribed → %s\n", TOPIC_CONTROL);

      // Publish initial state on connect (retained)
      publishState();
    } else {
      Serial.printf("[MQTT] Failed (rc=%d) — retrying in 3s\n", mqttClient.state());
      delay(3000);
    }
  }
}

/* ─────────────────────── Button Handling ─────────────────────── */

/**
 * Read the button with debounce.
 * On each press: increment brightness, reset the 1.5s publish timer.
 */
void handleButton() {
  bool reading = digitalRead(PIN_BUTTON);

  // Reset debounce timer on state change
  if (reading != lastButtonState) {
    lastDebounceTime = millis();
  }

  // Only act after debounce settles
  if ((millis() - lastDebounceTime) > BUTTON_DEBOUNCE_MS) {
    // Detect falling edge (press): HIGH → LOW
    static bool currentState = HIGH;

    if (reading != currentState) {
      currentState = reading;

      if (currentState == LOW) {
        // Button pressed — increment and wrap
        brightnessLevel++;
        if (brightnessLevel > BRIGHTNESS_MAX) {
          brightnessLevel = BRIGHTNESS_MIN;
        }

        applyBrightness();

        // Reset the publish countdown
        publishPending = true;
        lastPressTime  = millis();

        Serial.printf("[BTN] Pressed → brightness_level=%d (publish in %.1fs)\n",
                      brightnessLevel, PUBLISH_DELAY_MS / 1000.0);
      }
    }
  }

  lastButtonState = reading;
}

/**
 * Check if the publish delay has elapsed since the last button press.
 */
void handleDeferredPublish() {
  if (publishPending && (millis() - lastPressTime >= PUBLISH_DELAY_MS)) {
    publishPending = false;
    publishState();
    Serial.println("[BTN] 1.5s inactivity — state published");
  }
}

/* ─────────────────────── Setup & Loop ─────────────────────── */

void setup() {
  Serial.begin(115200);
  delay(500);

  Serial.println("\n════════════════════════════════════════");
  Serial.println("  ESP32-S3 Dimmable Light — IoT Portal");
  Serial.println("════════════════════════════════════════\n");

  // Configure LED PWM
  ledcAttach(PIN_LED, PWM_FREQ, PWM_RESOLUTION);
  applyBrightness();

  // Configure button with internal pull-up
  pinMode(PIN_BUTTON, INPUT_PULLUP);

  connectWiFi();
  connectMQTT();
}

void loop() {
  // Reconnect if needed
  if (!mqttClient.connected()) {
    connectMQTT();
  }

  mqttClient.loop();

  handleButton();
  handleDeferredPublish();
}
