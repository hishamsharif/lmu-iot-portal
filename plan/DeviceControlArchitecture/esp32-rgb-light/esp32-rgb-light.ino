/**
 * ESP32-S3 RGB LED Controller — IoT Platform Device Firmware Template
 *
 * Placeholder variables replaced by the platform:
 *   - {{DEVICE_ID}}
 *   - {{MQTT_CLIENT_ID}}
 *   - {{CONTROL_TOPIC}}
 *   - {{STATE_TOPIC}}
 *
 * Payload example:
 * {
 *   "power": true,
 *   "brightness": 75,
 *   "color_hex": "#FF6600",
 *   "effect": "solid",
 *   "apply_changes": true
 * }
 */

#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>

/* ----------------------- Configuration ----------------------- */

const char* WIFI_SSID     = "YOUR_WIFI_SSID";
const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";

const char* MQTT_HOST     = "10.0.0.42";
const uint16_t MQTT_PORT  = 1883;
const char* MQTT_USER     = "";
const char* MQTT_PASS     = "";
const char* MQTT_CLIENT   = "{{MQTT_CLIENT_ID}}";

const char* DEVICE_ID     = "{{DEVICE_ID}}";
const char* TOPIC_CONTROL = "{{CONTROL_TOPIC}}";
const char* TOPIC_STATE   = "{{STATE_TOPIC}}";

const uint8_t PIN_RED     = 15;
const uint8_t PIN_GREEN   = 16;
const uint8_t PIN_BLUE    = 17;
const uint8_t PIN_BUTTON  = 4;

const uint32_t PWM_FREQ       = 5000;
const uint8_t PWM_RESOLUTION  = 8;

const uint8_t BRIGHTNESS_MIN  = 0;
const uint8_t BRIGHTNESS_MAX  = 100;

const unsigned long BUTTON_DEBOUNCE_MS = 50;
const unsigned long PUBLISH_DELAY_MS   = 1500;

/* ----------------------- Runtime State ----------------------- */

WiFiClient wifiClient;
PubSubClient mqttClient(wifiClient);

bool powerState = true;
uint8_t brightness = 50;
String colorHex = "#FF0000";
String effect = "solid";

bool lastButtonState = HIGH;
unsigned long lastDebounceTime = 0;
bool publishPending = false;
unsigned long lastPressTime = 0;

/* ----------------------- Helpers ----------------------- */

uint8_t hexPairToByte(char high, char low) {
  char value[3] = { high, low, '\0' };
  return (uint8_t) strtol(value, nullptr, 16);
}

bool parseHexColor(const String& hex, uint8_t& red, uint8_t& green, uint8_t& blue) {
  if (hex.length() != 7 || hex.charAt(0) != '#') {
    return false;
  }

  red = hexPairToByte(hex.charAt(1), hex.charAt(2));
  green = hexPairToByte(hex.charAt(3), hex.charAt(4));
  blue = hexPairToByte(hex.charAt(5), hex.charAt(6));

  return true;
}

void applyLighting() {
  uint8_t red = 0;
  uint8_t green = 0;
  uint8_t blue = 0;

  if (powerState && parseHexColor(colorHex, red, green, blue)) {
    red = map(brightness, 0, 100, 0, red);
    green = map(brightness, 0, 100, 0, green);
    blue = map(brightness, 0, 100, 0, blue);
  }

  ledcWrite(PIN_RED, red);
  ledcWrite(PIN_GREEN, green);
  ledcWrite(PIN_BLUE, blue);

  Serial.printf("[LED] power=%d brightness=%d color=%s effect=%s\n",
                powerState, brightness, colorHex.c_str(), effect.c_str());
}

void publishState() {
  JsonDocument doc;
  doc["power"] = powerState;
  doc["brightness"] = brightness;
  doc["color_hex"] = colorHex;
  doc["effect"] = effect;

  char payload[192];
  serializeJson(doc, payload, sizeof(payload));

  bool ok = mqttClient.publish(TOPIC_STATE, payload, true);

  Serial.printf("[MQTT] Publish -> %s payload=%s %s\n",
                TOPIC_STATE, payload, ok ? "OK" : "FAIL");
}

void onMqttMessage(char* topic, byte* payload, unsigned int length) {
  char json[256];
  unsigned int copyLen = min(length, (unsigned int) (sizeof(json) - 1));
  memcpy(json, payload, copyLen);
  json[copyLen] = '\0';

  Serial.printf("[MQTT] Received <- %s payload=%s\n", topic, json);

  JsonDocument doc;
  DeserializationError err = deserializeJson(doc, json);

  if (err) {
    Serial.printf("[MQTT] JSON parse error: %s\n", err.c_str());
    return;
  }

  if (doc.containsKey("power")) {
    powerState = doc["power"].as<bool>();
  }

  if (doc.containsKey("brightness")) {
    int incomingBrightness = doc["brightness"].as<int>();
    if (incomingBrightness < BRIGHTNESS_MIN) incomingBrightness = BRIGHTNESS_MIN;
    if (incomingBrightness > BRIGHTNESS_MAX) incomingBrightness = BRIGHTNESS_MAX;
    brightness = (uint8_t) incomingBrightness;
  }

  if (doc.containsKey("color_hex")) {
    String incomingColor = doc["color_hex"].as<String>();
    incomingColor.toUpperCase();

    uint8_t red = 0;
    uint8_t green = 0;
    uint8_t blue = 0;

    if (parseHexColor(incomingColor, red, green, blue)) {
      colorHex = incomingColor;
    }
  }

  if (doc.containsKey("effect")) {
    effect = doc["effect"].as<String>();
  }

  bool applyChanges = true;
  if (doc.containsKey("apply_changes")) {
    applyChanges = doc["apply_changes"].as<bool>();
  }

  if (applyChanges) {
    applyLighting();
    publishState();
  }
}

void connectWiFi() {
  Serial.printf("[WiFi] Connecting to %s", WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.printf("\n[WiFi] Connected - IP: %s\n", WiFi.localIP().toString().c_str());
}

void connectMQTT() {
  mqttClient.setServer(MQTT_HOST, MQTT_PORT);
  mqttClient.setCallback(onMqttMessage);

  while (!mqttClient.connected()) {
    Serial.printf("[MQTT] Connecting to %s:%d as %s\n", MQTT_HOST, MQTT_PORT, MQTT_CLIENT);

    bool connected = MQTT_USER[0] == '\0'
      ? mqttClient.connect(MQTT_CLIENT)
      : mqttClient.connect(MQTT_CLIENT, MQTT_USER, MQTT_PASS);

    if (connected) {
      Serial.println("[MQTT] Connected");
      mqttClient.subscribe(TOPIC_CONTROL, 1);
      Serial.printf("[MQTT] Subscribed -> %s\n", TOPIC_CONTROL);
      publishState();
    } else {
      Serial.printf("[MQTT] Failed (rc=%d) retrying in 3s\n", mqttClient.state());
      delay(3000);
    }
  }
}

void handleButton() {
  bool reading = digitalRead(PIN_BUTTON);

  if (reading != lastButtonState) {
    lastDebounceTime = millis();
  }

  if ((millis() - lastDebounceTime) > BUTTON_DEBOUNCE_MS) {
    static bool currentState = HIGH;

    if (reading != currentState) {
      currentState = reading;

      if (currentState == LOW) {
        powerState = !powerState;
        applyLighting();
        publishPending = true;
        lastPressTime = millis();
      }
    }
  }

  lastButtonState = reading;
}

void handleDeferredPublish() {
  if (publishPending && (millis() - lastPressTime >= PUBLISH_DELAY_MS)) {
    publishPending = false;
    publishState();
  }
}

void setup() {
  Serial.begin(115200);
  delay(500);

  ledcAttach(PIN_RED, PWM_FREQ, PWM_RESOLUTION);
  ledcAttach(PIN_GREEN, PWM_FREQ, PWM_RESOLUTION);
  ledcAttach(PIN_BLUE, PWM_FREQ, PWM_RESOLUTION);
  pinMode(PIN_BUTTON, INPUT_PULLUP);

  applyLighting();
  connectWiFi();
  connectMQTT();
}

void loop() {
  if (!mqttClient.connected()) {
    connectMQTT();
  }

  mqttClient.loop();
  handleButton();
  handleDeferredPublish();
}
