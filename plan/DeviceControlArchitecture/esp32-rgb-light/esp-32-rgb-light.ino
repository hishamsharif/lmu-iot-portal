
/**
 * ESP32-S3 RGB LED Controller — IoT Platform Device Firmware Template (with working effects)
 *
 * Placeholder variables replaced by the platform:
 *   - {{DEVICE_ID}}
 *   - {{MQTT_CLIENT_ID}}
 *   - {{CONTROL_TOPIC}}
 *   - {{STATE_TOPIC}}
 *   - {{PRESENCE_TOPIC}} (defaults to devices/{{DEVICE_ID}}/presence)
 *
 * Payload example:
 * {
 *   "power": true,
 *   "brightness": 75,
 *   "color_hex": "#FF6600",
 *   "effect": "solid",        // solid | blink | breathe | rainbow
 *   "apply_changes": true
 * }
 */

#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>

/* ----------------------- Configuration ----------------------- */

// WiFi credentials — change to match your network
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
const char* TOPIC_PRESENCE = "devices/{{DEVICE_ID}}/presence";

// NOTE (ESP32-S3 LEDC):
// We'll use LEDC channels 0,1,2 and attach pins to those channels.
const uint8_t PIN_RED               = 15;
const uint8_t PIN_GREEN             = 16;
const uint8_t PIN_BLUE              = 17;
const uint8_t PIN_WIFI_STATUS_LED   = 12;
const uint8_t PIN_MQTT_STATUS_LED   = 13;
const uint8_t PIN_BUTTON_POWER      = 4;
const uint8_t PIN_BUTTON_COLOR      = 5;
const uint8_t PIN_BUTTON_EFFECT     = 6;
const uint8_t PIN_BUTTON_BRIGHTNESS = 7;

const bool WIFI_LED_ACTIVE_HIGH = true;
const bool MQTT_LED_ACTIVE_HIGH = true;

const uint8_t CH_RED      = 0;
const uint8_t CH_GREEN    = 1;
const uint8_t CH_BLUE     = 2;

const uint32_t PWM_FREQ       = 5000;
const uint8_t PWM_RESOLUTION  = 8;   // 0..255

const uint8_t BRIGHTNESS_MIN  = 0;
const uint8_t BRIGHTNESS_MAX  = 100;
const uint8_t BRIGHTNESS_STEP = 20;

const unsigned long BUTTON_DEBOUNCE_MS = 50;
const unsigned long PUBLISH_DELAY_MS   = 1500;
const unsigned long MQTT_CONNECTING_INDICATION_MS = 600;
const unsigned long MQTT_SUBSCRIPTION_REFRESH_MS = 15000;
const unsigned long MQTT_SUBSCRIPTION_RETRY_MS = 2000;

const char* COLOR_PRESETS[] = {
  "#FF0000",
  "#FF6600",
  "#FFFF00",
  "#00FF00",
  "#00FFFF",
  "#0000FF",
  "#8B00FF",
  "#FF00FF",
  "#FFFFFF"
};
const uint8_t COLOR_PRESET_COUNT = sizeof(COLOR_PRESETS) / sizeof(COLOR_PRESETS[0]);

const char* EFFECT_PRESETS[] = {
  "solid",
  "blink",
  "breathe",
  "rainbow"
};
const uint8_t EFFECT_PRESET_COUNT = sizeof(EFFECT_PRESETS) / sizeof(EFFECT_PRESETS[0]);

/* ----------------------- Runtime State ----------------------- */

WiFiClient wifiClient;
PubSubClient mqttClient(wifiClient);

bool powerState = true;
uint8_t brightness = 50;     // 0..100
String colorHex = "#FF0000";
String effect = "solid";    // solid | blink | breathe | rainbow
String pendingCommandId = "";

struct ButtonState {
  bool lastReading;
  bool currentState;
  unsigned long lastDebounceTime;
};

ButtonState btnPower      = { HIGH, HIGH, 0 };
ButtonState btnColor      = { HIGH, HIGH, 0 };
ButtonState btnEffect     = { HIGH, HIGH, 0 };
ButtonState btnBrightness = { HIGH, HIGH, 0 };

bool publishPending = false;
unsigned long lastPressTime = 0;
bool controlTopicSubscribed = false;
unsigned long lastControlSubscriptionAttemptMs = 0;

enum class WifiIndicatorState : uint8_t {
  Off,
  Connecting,
  Connected
};

enum class MqttIndicatorState : uint8_t {
  Off,
  Connecting,
  Backoff,
  Connected
};

WifiIndicatorState wifiIndicatorState = WifiIndicatorState::Off;
MqttIndicatorState mqttIndicatorState = MqttIndicatorState::Off;

unsigned long wifiIndicatorLastTick = 0;
bool wifiIndicatorBlinkOn = false;

unsigned long mqttIndicatorLastTick = 0;
bool mqttIndicatorBlinkOn = false;
uint8_t mqttBackoffPhase = 0;

/* ----------------------- Effect State ----------------------- */

unsigned long lastEffectTick = 0;
bool blinkOn = false;
uint16_t rainbowHue = 0;      // 0..359
uint8_t breatheValue = 0;     // 0..255
int breatheDirection = 1;     // +1 or -1

/* ----------------------- Helpers ----------------------- */

void writeWifiLed(bool on) {
  bool gpioLevel = WIFI_LED_ACTIVE_HIGH ? on : !on;
  digitalWrite(PIN_WIFI_STATUS_LED, gpioLevel ? HIGH : LOW);
}

void writeMqttLed(bool on) {
  bool gpioLevel = MQTT_LED_ACTIVE_HIGH ? on : !on;
  digitalWrite(PIN_MQTT_STATUS_LED, gpioLevel ? HIGH : LOW);
}

void setWifiIndicatorState(WifiIndicatorState state, bool resetPhase = true) {
  if (!resetPhase && wifiIndicatorState == state) {
    return;
  }

  wifiIndicatorState = state;

  if (resetPhase) {
    wifiIndicatorLastTick = millis();
    wifiIndicatorBlinkOn = true;
  }

  if (wifiIndicatorState == WifiIndicatorState::Off) {
    writeWifiLed(false);
    return;
  }

  if (wifiIndicatorState == WifiIndicatorState::Connected) {
    writeWifiLed(true);
    return;
  }

  writeWifiLed(wifiIndicatorBlinkOn);
}

void setMqttIndicatorState(MqttIndicatorState state, bool resetPhase = true) {
  if (!resetPhase && mqttIndicatorState == state) {
    return;
  }

  mqttIndicatorState = state;

  if (resetPhase) {
    mqttIndicatorLastTick = millis();
    mqttIndicatorBlinkOn = (state == MqttIndicatorState::Connecting) ? false : true;
    mqttBackoffPhase = 0;
  }

  if (mqttIndicatorState == MqttIndicatorState::Off) {
    writeMqttLed(false);
    return;
  }

  if (mqttIndicatorState == MqttIndicatorState::Connected) {
    writeMqttLed(true);
    return;
  }

  if (mqttIndicatorState == MqttIndicatorState::Connecting) {
    writeMqttLed(mqttIndicatorBlinkOn);
    return;
  }

  writeMqttLed(true);
}

void updateWifiIndicator() {
  if (wifiIndicatorState != WifiIndicatorState::Connecting) {
    return;
  }

  const unsigned long intervalMs = 500;
  unsigned long now = millis();

  if (now - wifiIndicatorLastTick >= intervalMs) {
    wifiIndicatorLastTick = now;
    wifiIndicatorBlinkOn = !wifiIndicatorBlinkOn;
    writeWifiLed(wifiIndicatorBlinkOn);
  }
}

void updateMqttIndicator() {
  unsigned long now = millis();

  if (mqttIndicatorState == MqttIndicatorState::Connecting) {
    const unsigned long intervalMs = 200;
    if (now - mqttIndicatorLastTick >= intervalMs) {
      mqttIndicatorLastTick = now;
      mqttIndicatorBlinkOn = !mqttIndicatorBlinkOn;
      writeMqttLed(mqttIndicatorBlinkOn);
    }
    return;
  }

  if (mqttIndicatorState == MqttIndicatorState::Backoff) {
    const unsigned long phaseDurations[4] = { 120, 120, 120, 640 };
    const bool phaseOutputs[4] = { true, false, true, false };

    if (now - mqttIndicatorLastTick >= phaseDurations[mqttBackoffPhase]) {
      mqttIndicatorLastTick = now;
      mqttBackoffPhase = (mqttBackoffPhase + 1) % 4;
      writeMqttLed(phaseOutputs[mqttBackoffPhase]);
    }
  }
}

void updateConnectivityIndicators() {
  updateWifiIndicator();
  updateMqttIndicator();
}

void delayWithIndicators(unsigned long durationMs) {
  unsigned long start = millis();

  while (millis() - start < durationMs) {
    updateConnectivityIndicators();
    delay(20);
  }
}

bool ensureControlTopicSubscription(bool forceAttempt = false) {
  if (!mqttClient.connected()) {
    controlTopicSubscribed = false;
    return false;
  }

  unsigned long now = millis();
  if (!forceAttempt) {
    unsigned long minIntervalMs = controlTopicSubscribed
      ? MQTT_SUBSCRIPTION_REFRESH_MS
      : MQTT_SUBSCRIPTION_RETRY_MS;

    if (now - lastControlSubscriptionAttemptMs < minIntervalMs) {
      return controlTopicSubscribed;
    }
  }

  lastControlSubscriptionAttemptMs = now;

  bool subscribed = mqttClient.subscribe(TOPIC_CONTROL, 1);
  if (subscribed) {
    if (!controlTopicSubscribed) {
      Serial.printf("[MQTT] Subscribed -> %s\n", TOPIC_CONTROL);
    }
    controlTopicSubscribed = true;
    return true;
  }

  Serial.printf("[MQTT] Subscribe failed -> %s (state=%d)\n", TOPIC_CONTROL, mqttClient.state());
  controlTopicSubscribed = false;
  return false;
}

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

// HSV (0..359, 0..255, 0..255) -> RGB (0..255)
void hsvToRgb(uint16_t h, uint8_t s, uint8_t v,
              uint8_t &r, uint8_t &g, uint8_t &b) {
  uint8_t region = h / 60;
  uint16_t remainder = (h - (region * 60)) * 255 / 60;

  uint8_t p = (uint16_t(v) * (255 - s)) >> 8;
  uint8_t q = (uint16_t(v) * (255 - ((uint16_t(s) * remainder) >> 8))) >> 8;
  uint8_t t = (uint16_t(v) * (255 - ((uint16_t(s) * (255 - remainder)) >> 8))) >> 8;

  switch (region) {
    case 0: r = v; g = t; b = p; break;
    case 1: r = q; g = v; b = p; break;
    case 2: r = p; g = v; b = t; break;
    case 3: r = p; g = q; b = v; break;
    case 4: r = t; g = p; b = v; break;
    default: r = v; g = p; b = q; break;
  }
}

void writeRgb(uint8_t r, uint8_t g, uint8_t b) {
  ledcWrite(PIN_RED, r);
  ledcWrite(PIN_GREEN, g);
  ledcWrite(PIN_BLUE, b);
}

// Apply solid color based on colorHex + brightness + powerState
void applySolid() {
  uint8_t r = 0, g = 0, b = 0;

  if (powerState && parseHexColor(colorHex, r, g, b)) {
    // scale each channel by brightness
    r = map(brightness, 0, 100, 0, r);
    g = map(brightness, 0, 100, 0, g);
    b = map(brightness, 0, 100, 0, b);
  } else {
    r = g = b = 0;
  }

  writeRgb(r, g, b);
}

void publishState() {
  JsonDocument doc;
  doc["power"] = powerState;
  doc["brightness"] = brightness;
  doc["color_hex"] = colorHex;
  doc["effect"] = effect;

  if (pendingCommandId.length() > 0) {
    doc["_meta"]["command_id"] = pendingCommandId;
    pendingCommandId = "";
  }

  char payload[256];
  serializeJson(doc, payload, sizeof(payload));

  bool ok = mqttClient.publish(TOPIC_STATE, payload, true);

  Serial.printf("[MQTT] Publish -> %s payload=%s %s\n",
                TOPIC_STATE, payload, ok ? "OK" : "FAIL");
}

void resetEffectTiming() {
  lastEffectTick = 0;
  blinkOn = false;
  rainbowHue = 0;
  breatheValue = 0;
  breatheDirection = 1;
}

void updateEffect() {
  if (!powerState) {
    writeRgb(0, 0, 0);
    return;
  }

  unsigned long now = millis();

  // SOLID
  if (effect == "solid") {
    applySolid();
    return;
  }

  // BLINK
  if (effect == "blink") {
    const unsigned long intervalMs = 500;
    if (now - lastEffectTick >= intervalMs) {
      lastEffectTick = now;
      blinkOn = !blinkOn;
      if (blinkOn) applySolid();
      else writeRgb(0, 0, 0);
    }
    return;
  }

  // BREATHE
  if (effect == "breathe") {
    const unsigned long intervalMs = 20;
    if (now - lastEffectTick >= intervalMs) {
      lastEffectTick = now;

      // triangle wave 0..255
      breatheValue = uint8_t(int(breatheValue) + breatheDirection);
      if (breatheValue == 0 || breatheValue == 255) {
        breatheDirection *= -1;
      }

      uint8_t rBase = 0, gBase = 0, bBase = 0;
      if (!parseHexColor(colorHex, rBase, gBase, bBase)) {
        rBase = 255; gBase = 0; bBase = 0;
      }

      // brightness factor (0..255)
      uint16_t br = map(brightness, 0, 100, 0, 255);

      // Apply both breatheValue and brightness
      uint8_t r = (uint32_t(rBase) * breatheValue * br) / (255UL * 255UL);
      uint8_t g = (uint32_t(gBase) * breatheValue * br) / (255UL * 255UL);
      uint8_t b = (uint32_t(bBase) * breatheValue * br) / (255UL * 255UL);

      writeRgb(r, g, b);
    }
    return;
  }

  // RAINBOW
  if (effect == "rainbow") {
    const unsigned long intervalMs = 30;
    if (now - lastEffectTick >= intervalMs) {
      lastEffectTick = now;
      rainbowHue = (rainbowHue + 2) % 360;

      uint8_t r, g, b;
      uint8_t v = map(brightness, 0, 100, 0, 255);
      hsvToRgb(rainbowHue, 255, v, r, g, b);
      writeRgb(r, g, b);
    }
    return;
  }

  // Unknown effect -> fallback to solid
  applySolid();
}

void onMqttMessage(char* topic, byte* payload, unsigned int length) {
  char json[256];
  unsigned int copyLen = min(length, (unsigned int)(sizeof(json) - 1));
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

    uint8_t r = 0, g = 0, b = 0;
    if (parseHexColor(incomingColor, r, g, b)) {
      colorHex = incomingColor;
    }
  }

  if (doc.containsKey("effect")) {
    effect = doc["effect"].as<String>();
    effect.toLowerCase();
  }

  if (doc["_meta"]["command_id"].is<const char*>()) {
    pendingCommandId = doc["_meta"]["command_id"].as<String>();
  }

  bool applyChanges = true;
  if (doc.containsKey("apply_changes")) {
    applyChanges = doc["apply_changes"].as<bool>();
  }

  if (applyChanges) {
    resetEffectTiming();
    // Immediate visual response
    updateEffect();
    publishState();
  }
}

void connectWiFi() {
  setWifiIndicatorState(WifiIndicatorState::Connecting);
  setMqttIndicatorState(MqttIndicatorState::Off);
  controlTopicSubscribed = false;

  Serial.printf("[WiFi] Connecting to %s", WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  unsigned long lastDotTick = 0;
  while (WiFi.status() != WL_CONNECTED) {
    unsigned long now = millis();
    if (now - lastDotTick >= 500) {
      lastDotTick = now;
      Serial.print(".");
    }

    updateConnectivityIndicators();
    delay(20);
  }

  setWifiIndicatorState(WifiIndicatorState::Connected);
  setMqttIndicatorState(MqttIndicatorState::Off);
  Serial.printf("\n[WiFi] Connected - IP: %s\n", WiFi.localIP().toString().c_str());
}

void connectMQTT() {
  mqttClient.setServer(MQTT_HOST, MQTT_PORT);
  mqttClient.setBufferSize(512);
  mqttClient.setCallback(onMqttMessage);
  controlTopicSubscribed = false;

  while (!mqttClient.connected()) {
    if (WiFi.status() != WL_CONNECTED) {
      setMqttIndicatorState(MqttIndicatorState::Off);
      controlTopicSubscribed = false;
      return;
    }

    setMqttIndicatorState(MqttIndicatorState::Connecting);
    delayWithIndicators(MQTT_CONNECTING_INDICATION_MS);
    Serial.printf("[MQTT] Connecting to %s:%d as %s\n", MQTT_HOST, MQTT_PORT, MQTT_CLIENT);

    // Configure Last Will and Testament (LWT):
    // If the broker loses contact, it publishes "offline" to the presence topic.
    bool connected = (MQTT_USER[0] == '\0')
      ? mqttClient.connect(MQTT_CLIENT, nullptr, nullptr, TOPIC_PRESENCE, 1, true, "offline")
      : mqttClient.connect(MQTT_CLIENT, MQTT_USER, MQTT_PASS, TOPIC_PRESENCE, 1, true, "offline");

    if (connected) {
      setMqttIndicatorState(MqttIndicatorState::Connected);
      Serial.println("[MQTT] Connected");
      controlTopicSubscribed = false;
      lastControlSubscriptionAttemptMs = 0;

      // Announce online presence (retained so new subscribers see it immediately)
      mqttClient.publish(TOPIC_PRESENCE, "online", true);
      Serial.printf("[MQTT] Published presence -> %s payload=online\n", TOPIC_PRESENCE);

      if (!ensureControlTopicSubscription(true)) {
        setMqttIndicatorState(MqttIndicatorState::Backoff);
        Serial.println("[MQTT] Control topic subscription failed, retrying MQTT connect in 3s");
        mqttClient.disconnect();
        delayWithIndicators(3000);
        continue;
      }

      publishState();
    } else {
      setMqttIndicatorState(MqttIndicatorState::Backoff);
      controlTopicSubscribed = false;
      Serial.printf("[MQTT] Failed (rc=%d) retrying in 3s\n", mqttClient.state());
      delayWithIndicators(3000);
    }
  }
}

bool debounceButton(uint8_t pin, ButtonState& btn) {
  bool reading = digitalRead(pin);

  if (reading != btn.lastReading) {
    btn.lastDebounceTime = millis();
  }

  btn.lastReading = reading;

  if ((millis() - btn.lastDebounceTime) > BUTTON_DEBOUNCE_MS) {
    if (reading != btn.currentState) {
      btn.currentState = reading;
      return (btn.currentState == LOW);
    }
  }

  return false;
}

void schedulePublish() {
  publishPending = true;
  lastPressTime = millis();
}

uint8_t nextBrightnessStep(uint8_t current) {
  uint8_t next = ((current / BRIGHTNESS_STEP) + 1) * BRIGHTNESS_STEP;
  return (next > BRIGHTNESS_MAX) ? BRIGHTNESS_STEP : next;
}

void handleButtons() {
  if (debounceButton(PIN_BUTTON_POWER, btnPower)) {
    powerState = !powerState;
    resetEffectTiming();
    updateEffect();
    schedulePublish();
  }

  if (debounceButton(PIN_BUTTON_COLOR, btnColor)) {
    uint8_t nextIndex = 0;
    for (uint8_t i = 0; i < COLOR_PRESET_COUNT; i++) {
      if (colorHex.equalsIgnoreCase(COLOR_PRESETS[i])) {
        nextIndex = (i + 1) % COLOR_PRESET_COUNT;
        break;
      }
    }
    colorHex = COLOR_PRESETS[nextIndex];
    resetEffectTiming();
    updateEffect();
    schedulePublish();
  }

  if (debounceButton(PIN_BUTTON_EFFECT, btnEffect)) {
    uint8_t nextIndex = 0;
    for (uint8_t i = 0; i < EFFECT_PRESET_COUNT; i++) {
      if (effect.equalsIgnoreCase(EFFECT_PRESETS[i])) {
        nextIndex = (i + 1) % EFFECT_PRESET_COUNT;
        break;
      }
    }
    effect = EFFECT_PRESETS[nextIndex];
    resetEffectTiming();
    updateEffect();
    schedulePublish();
  }

  if (debounceButton(PIN_BUTTON_BRIGHTNESS, btnBrightness)) {
    brightness = nextBrightnessStep(brightness);
    resetEffectTiming();
    updateEffect();
    schedulePublish();
  }
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

  // LEDC setup (ESP32-S3):
  // ledcSetup(channel, freq, resolution) then ledcAttachPin(pin, channel)

  ledcAttach(PIN_RED, PWM_FREQ, PWM_RESOLUTION);
  ledcAttach(PIN_GREEN, PWM_FREQ, PWM_RESOLUTION);
  ledcAttach(PIN_BLUE, PWM_FREQ, PWM_RESOLUTION);

  pinMode(PIN_BUTTON_POWER, INPUT_PULLUP);
  pinMode(PIN_BUTTON_COLOR, INPUT_PULLUP);
  pinMode(PIN_BUTTON_EFFECT, INPUT_PULLUP);
  pinMode(PIN_BUTTON_BRIGHTNESS, INPUT_PULLUP);

  pinMode(PIN_WIFI_STATUS_LED, OUTPUT);
  pinMode(PIN_MQTT_STATUS_LED, OUTPUT);
  setWifiIndicatorState(WifiIndicatorState::Off);
  setMqttIndicatorState(MqttIndicatorState::Off);

  resetEffectTiming();
  updateEffect();

  connectWiFi();
  connectMQTT();
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    setMqttIndicatorState(MqttIndicatorState::Off);
    connectWiFi();
  }

  if (!mqttClient.connected()) {
    controlTopicSubscribed = false;
    connectMQTT();
  }

  mqttClient.loop();
  ensureControlTopicSubscription();
  handleButtons();
  handleDeferredPublish();
  updateEffect();
  updateConnectivityIndicators();
}
