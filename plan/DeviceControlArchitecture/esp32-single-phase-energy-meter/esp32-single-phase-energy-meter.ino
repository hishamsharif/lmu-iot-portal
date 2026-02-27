/**
 * ESP32 Single-Phase Energy Meter (PZEM-014/016) - IoT Platform Firmware Template
 *
 * Placeholder variables replaced by the platform:
 *   - {{DEVICE_ID}}
 *   - {{MQTT_CLIENT_ID}}
 *   - {{MQTT_HOST}}
 *   - {{MQTT_FALLBACK_HOST}}
 *   - {{MQTT_PORT}}
 *   - {{MQTT_USE_TLS}}
 *   - {{MQTT_SECURITY_MODE}}
 *   - {{MQTT_USER}}
 *   - {{MQTT_PASS}}
 *   - {{CONTROL_TOPIC}}
 *   - {{TELEMETRY_TOPIC}}
 *   - {{STATE_TOPIC}}
 *   - {{PRESENCE_TOPIC}}
 *   - {{MQTT_TLS_CA_CERT_PEM}}
 *   - {{MQTT_TLS_CLIENT_CERT_PEM}}
 *   - {{MQTT_TLS_CLIENT_KEY_PEM}}
 */

#include <WiFi.h>
#include <WiFiClient.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>

/* ----------------------- Configuration ----------------------- */

const char* WIFI_SSID = "YOUR_WIFI_SSID";
const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";

const char* MQTT_HOST = "{{MQTT_HOST}}";
const char* MQTT_FALLBACK_HOST = "{{MQTT_FALLBACK_HOST}}";
const uint16_t MQTT_PORT = {{MQTT_PORT}};
const bool MQTT_USE_TLS = {{MQTT_USE_TLS}};
const char* MQTT_SECURITY_MODE = "{{MQTT_SECURITY_MODE}}";
const char* MQTT_USER = "{{MQTT_USER}}";
const char* MQTT_PASS = "{{MQTT_PASS}}";
const char* MQTT_CLIENT = "{{MQTT_CLIENT_ID}}";

const char* DEVICE_ID = "{{DEVICE_ID}}";
const char* TOPIC_CONTROL = "{{CONTROL_TOPIC}}";
const char* TOPIC_TELEMETRY = "{{TELEMETRY_TOPIC}}";
const char* TOPIC_STATE = "{{STATE_TOPIC}}";
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

// Use a dedicated UART pair for RS485. Do NOT use top-right RX/TX (GPIO44/43),
// because those pins are used by the serial console / uploader on ESP32-S3 boards.
const int RS485_RX_PIN = 17;
const int RS485_TX_PIN = 16;

// If your RS485 module has DE+RE tied together, use this pin and keep RS485_DE_PIN/RS485_RE_PIN = -1.
const int RS485_DE_RE_PIN = -1;
const bool RS485_DE_RE_ACTIVE_HIGH = true;

// If your RS485 module exposes DE and RE separately, configure both pins here.
// Example (MAX485-style):
//   DI -> ESP32 TX (RS485_TX_PIN)
//   RO -> ESP32 RX (RS485_RX_PIN)
//   DE -> RS485_DE_PIN
//   RE -> RS485_RE_PIN
const int RS485_DE_PIN = 4;
const int RS485_RE_PIN = 5;
const bool RS485_DE_ACTIVE_HIGH = true;
const bool RS485_RE_ACTIVE_HIGH = false;

const uint32_t RS485_BAUD = 9600;
const uint32_t RS485_SERIAL_CONFIG = SERIAL_8N1;

const uint8_t MODBUS_PRIMARY_SLAVE_ADDRESS = 0x01;
const uint8_t MODBUS_FALLBACK_SLAVE_ADDRESS = 0xF8;
const uint8_t MODBUS_READ_INPUT_REGISTERS = 0x04;
const uint16_t MODBUS_START_REGISTER = 0x0000;
const uint16_t MODBUS_REGISTER_COUNT = 0x000A;

const unsigned long MODBUS_TIMEOUT_MS = 350;
const unsigned long POLL_INTERVAL_MS = 3000;
const unsigned long TELEMETRY_INTERVAL_MS = 3000;
const unsigned long PRESENCE_HEARTBEAT_MS = 60000;

const uint16_t MQTT_KEEPALIVE_SECONDS = 30;
const uint16_t MQTT_SOCKET_TIMEOUT_SECONDS = 5;
const uint16_t MQTT_PACKET_BUFFER_SIZE = 1024;
const uint16_t MQTT_PUBLISH_OVERHEAD_BYTES = 7;

const char* FW_VERSION = "pzem-single-phase-v1.0.0";

/* ----------------------- Runtime State ----------------------- */

WiFiClient wifiClient;
WiFiClientSecure wifiSecureClient;
PubSubClient mqttClient(wifiClient);
HardwareSerial rs485(2);

const char* mqttConnectHost = MQTT_HOST;

struct MeterReadings {
  float voltage_v = 0.0f;
  float current_a = 0.0f;
  float active_power_w = 0.0f;
  float frequency_hz = 0.0f;
  float power_factor = 0.0f;
  float total_energy_kwh = 0.0f;
  uint16_t alarm_status = 0;
  bool read_ok = false;
  String modbus_error = "";
  uint32_t poll_ms = 0;
};

MeterReadings latestReadings;

unsigned long lastPollAtMs = 0;
unsigned long lastTelemetryPublishAtMs = 0;
unsigned long lastPresencePublishAtMs = 0;

/* ----------------------- Utility: RS485 Direction ----------------------- */

void setRs485TxMode(bool txMode)
{
  if (RS485_DE_RE_PIN >= 0) {
    bool active = RS485_DE_RE_ACTIVE_HIGH ? txMode : !txMode;
    digitalWrite(RS485_DE_RE_PIN, active ? HIGH : LOW);
    return;
  }

  if (RS485_DE_PIN >= 0) {
    bool deActive = RS485_DE_ACTIVE_HIGH ? txMode : !txMode;
    digitalWrite(RS485_DE_PIN, deActive ? HIGH : LOW);
  }

  if (RS485_RE_PIN >= 0) {
    bool reEnable = !txMode;
    bool reActive = RS485_RE_ACTIVE_HIGH ? reEnable : !reEnable;
    digitalWrite(RS485_RE_PIN, reActive ? HIGH : LOW);
  }
}

/* ----------------------- Utility: Modbus CRC16 ----------------------- */

uint16_t modbusCrc16(const uint8_t* data, size_t length)
{
  uint16_t crc = 0xFFFF;

  for (size_t i = 0; i < length; i++) {
    crc ^= data[i];

    for (uint8_t bit = 0; bit < 8; bit++) {
      if ((crc & 0x0001) != 0U) {
        crc = (crc >> 1) ^ 0xA001;
      } else {
        crc >>= 1;
      }
    }
  }

  return crc;
}

uint16_t u16be(const uint8_t* p)
{
  return ((uint16_t)p[0] << 8) | p[1];
}

uint32_t combineHighLowWords(uint16_t lowWord, uint16_t highWord)
{
  return ((uint32_t)highWord << 16) | lowWord;
}

bool canPublishMqttPayload(const char* topic, size_t payloadLength)
{
  size_t requiredPacketSize = strlen(topic) + payloadLength + MQTT_PUBLISH_OVERHEAD_BYTES;
  uint16_t configuredBufferSize = mqttClient.getBufferSize();

  return requiredPacketSize <= configuredBufferSize;
}

/* ----------------------- WiFi + MQTT Common Runtime ----------------------- */

void connectWiFi()
{
  if (WiFi.status() == WL_CONNECTED) {
    return;
  }

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  Serial.printf("[WiFi] Connecting to %s", WIFI_SSID);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print('.');
  }

  Serial.printf("\n[WiFi] Connected. IP=%s\n", WiFi.localIP().toString().c_str());
}

void publishPresenceOnline()
{
  bool ok = mqttClient.publish(TOPIC_PRESENCE, "online", true);
  Serial.printf("[MQTT] Presence online publish %s (%s)\n", ok ? "OK" : "FAIL", TOPIC_PRESENCE);

  if (ok) {
    lastPresencePublishAtMs = millis();
  }
}

void onControlMessage(const String& payload)
{
  Serial.printf("[CTRL] Received and ignored payload (read-only firmware): %s\n", payload.c_str());
}

void onMqttMessage(char* topic, byte* payload, unsigned int length)
{
  if (strcmp(topic, TOPIC_CONTROL) != 0) {
    return;
  }

  char message[512];
  unsigned int copyLength = min(length, (unsigned int) (sizeof(message) - 1));
  memcpy(message, payload, copyLength);
  message[copyLength] = '\0';

  onControlMessage(String(message));
}

void configureMqttClientTransport()
{
  bool useMutualTls = strcmp(MQTT_SECURITY_MODE, "x509_mtls") == 0;
  bool useTlsTransport = MQTT_USE_TLS || useMutualTls;

  if (useTlsTransport) {
    mqttClient.setClient(wifiSecureClient);

    if (MQTT_CA_CERT[0] != '\0') {
      wifiSecureClient.setCACert(MQTT_CA_CERT);
    } else {
      wifiSecureClient.setInsecure();
      Serial.println("[MQTT] Warning: no CA cert configured, TLS peer verification disabled");
    }

    if (useMutualTls) {
      if (MQTT_CLIENT_CERT[0] == '\0' || MQTT_CLIENT_KEY[0] == '\0') {
        Serial.println("[MQTT] Warning: X.509 mTLS selected but certificate/key is missing");
      } else {
        wifiSecureClient.setCertificate(MQTT_CLIENT_CERT);
        wifiSecureClient.setPrivateKey(MQTT_CLIENT_KEY);
      }
    }
  } else {
    mqttClient.setClient(wifiClient);
  }
}

void connectMqtt()
{
  configureMqttClientTransport();
  bool mqttBufferConfigured = mqttClient.setBufferSize(MQTT_PACKET_BUFFER_SIZE);
  Serial.printf(
      "[MQTT] Packet buffer size=%u target=%u status=%s\n",
      mqttClient.getBufferSize(),
      MQTT_PACKET_BUFFER_SIZE,
      mqttBufferConfigured ? "OK" : "FAIL");
  mqttClient.setKeepAlive(MQTT_KEEPALIVE_SECONDS);
  mqttClient.setSocketTimeout(MQTT_SOCKET_TIMEOUT_SECONDS);
  mqttClient.setCallback(onMqttMessage);

  while (!mqttClient.connected()) {
    mqttClient.setServer(mqttConnectHost, MQTT_PORT);
    Serial.printf("[MQTT] Connecting %s:%u as %s\n", mqttConnectHost, MQTT_PORT, MQTT_CLIENT);

    bool connected = false;

    if (MQTT_USER[0] == '\0') {
      connected = mqttClient.connect(MQTT_CLIENT, nullptr, nullptr, TOPIC_PRESENCE, 1, true, "offline");
    } else {
      connected = mqttClient.connect(MQTT_CLIENT, MQTT_USER, MQTT_PASS, TOPIC_PRESENCE, 1, true, "offline");
    }

    if (connected) {
      Serial.println("[MQTT] Connected");
      mqttClient.subscribe(TOPIC_CONTROL, 1);
      Serial.printf("[MQTT] Subscribed to control topic %s\n", TOPIC_CONTROL);
      publishPresenceOnline();
      return;
    }

    Serial.printf("[MQTT] Connect failed rc=%d\n", mqttClient.state());

    if (MQTT_FALLBACK_HOST[0] != '\0' && strcmp(mqttConnectHost, MQTT_FALLBACK_HOST) != 0) {
      mqttConnectHost = MQTT_FALLBACK_HOST;
      Serial.printf("[MQTT] Switching to fallback host %s\n", mqttConnectHost);
    } else {
      mqttConnectHost = MQTT_HOST;
      delay(3000);
    }
  }
}

void ensureConnectivity()
{
  if (WiFi.status() != WL_CONNECTED) {
    connectWiFi();
  }

  if (!mqttClient.connected()) {
    connectMqtt();
  }

  mqttClient.loop();

  unsigned long now = millis();

  if (mqttClient.connected() && (now - lastPresencePublishAtMs >= PRESENCE_HEARTBEAT_MS)) {
    publishPresenceOnline();
  }
}

/* ----------------------- Device Setup ----------------------- */

void deviceSetup()
{
  rs485.begin(RS485_BAUD, RS485_SERIAL_CONFIG, RS485_RX_PIN, RS485_TX_PIN);

  if (RS485_DE_RE_PIN >= 0) {
    pinMode(RS485_DE_RE_PIN, OUTPUT);
  }

  if (RS485_DE_PIN >= 0) {
    pinMode(RS485_DE_PIN, OUTPUT);
  }

  if (RS485_RE_PIN >= 0) {
    pinMode(RS485_RE_PIN, OUTPUT);
  }

  setRs485TxMode(false);

  Serial.printf("[RS485] UART2 ready RX=%d TX=%d baud=%lu\n", RS485_RX_PIN, RS485_TX_PIN, RS485_BAUD);
  Serial.printf("[RS485] Direction pins DE_RE=%d DE=%d RE=%d\n", RS485_DE_RE_PIN, RS485_DE_PIN, RS485_RE_PIN);
}

/* ----------------------- Modbus Polling ----------------------- */

bool readMeasurementRegisters(uint16_t* outRegisters, uint16_t registerCount, String& error)
{
  if (registerCount != MODBUS_REGISTER_COUNT) {
    error = "invalid_register_count";
    return false;
  }

  uint8_t request[8];
  request[0] = MODBUS_PRIMARY_SLAVE_ADDRESS;
  request[1] = MODBUS_READ_INPUT_REGISTERS;
  request[2] = (uint8_t) (MODBUS_START_REGISTER >> 8);
  request[3] = (uint8_t) (MODBUS_START_REGISTER & 0xFF);
  request[4] = (uint8_t) (MODBUS_REGISTER_COUNT >> 8);
  request[5] = (uint8_t) (MODBUS_REGISTER_COUNT & 0xFF);

  uint16_t requestCrc = modbusCrc16(request, 6);
  request[6] = (uint8_t) (requestCrc & 0xFF);
  request[7] = (uint8_t) ((requestCrc >> 8) & 0xFF);

  while (rs485.available() > 0) {
    rs485.read();
  }

  setRs485TxMode(true);
  delayMicroseconds(300);
  rs485.write(request, sizeof(request));
  rs485.flush();
  delayMicroseconds(300);
  setRs485TxMode(false);

  const uint8_t expectedDataBytes = registerCount * 2;
  const uint8_t expectedResponseLength = 3 + expectedDataBytes + 2;

  uint8_t response[64];
  uint8_t responseLength = 0;
  unsigned long startedAt = millis();

  while ((millis() - startedAt) < MODBUS_TIMEOUT_MS && responseLength < sizeof(response)) {
    while (rs485.available() > 0 && responseLength < sizeof(response)) {
      response[responseLength++] = (uint8_t) rs485.read();
    }
  }

  if (responseLength < expectedResponseLength) {
    error = "timeout_or_short_frame_" + String(responseLength);
    return false;
  }

  int frameStart = -1;
  const uint8_t frameLength = (uint8_t) (3 + expectedDataBytes + 2);

  for (uint8_t i = 0; i + 5 <= responseLength; i++) {
    if (response[i] != MODBUS_PRIMARY_SLAVE_ADDRESS) {
      continue;
    }

    uint8_t functionCode = response[i + 1];

    if (functionCode == (MODBUS_READ_INPUT_REGISTERS | 0x80)) {
      uint16_t expectedExceptionCrc = modbusCrc16(&response[i], 3);
      uint16_t actualExceptionCrc = (uint16_t) response[i + 3] | ((uint16_t) response[i + 4] << 8);
      if (expectedExceptionCrc == actualExceptionCrc) {
        error = "modbus_exception_" + String(response[i + 2]);
        return false;
      }
      continue;
    }

    if (functionCode != MODBUS_READ_INPUT_REGISTERS) {
      continue;
    }

    if (response[i + 2] != expectedDataBytes) {
      continue;
    }

    if ((int) i + frameLength > responseLength) {
      continue;
    }

    uint16_t expectedFrameCrc = modbusCrc16(&response[i], frameLength - 2);
    uint16_t actualFrameCrc = (uint16_t) response[i + frameLength - 2] | ((uint16_t) response[i + frameLength - 1] << 8);

    if (expectedFrameCrc == actualFrameCrc) {
      frameStart = i;
      break;
    }
  }

  if (frameStart < 0) {
    error = "frame_not_found";
    return false;
  }

  for (uint16_t index = 0; index < registerCount; index++) {
    outRegisters[index] = u16be(&response[frameStart + 3 + index * 2]);
  }

  return true;
}

bool readMeasurementRegistersWithAddress(uint8_t slaveAddress, uint16_t* outRegisters, uint16_t registerCount, String& error)
{
  if (registerCount != MODBUS_REGISTER_COUNT) {
    error = "invalid_register_count";
    return false;
  }

  uint8_t request[8];
  request[0] = slaveAddress;
  request[1] = MODBUS_READ_INPUT_REGISTERS;
  request[2] = (uint8_t) (MODBUS_START_REGISTER >> 8);
  request[3] = (uint8_t) (MODBUS_START_REGISTER & 0xFF);
  request[4] = (uint8_t) (MODBUS_REGISTER_COUNT >> 8);
  request[5] = (uint8_t) (MODBUS_REGISTER_COUNT & 0xFF);

  uint16_t requestCrc = modbusCrc16(request, 6);
  request[6] = (uint8_t) (requestCrc & 0xFF);
  request[7] = (uint8_t) ((requestCrc >> 8) & 0xFF);

  while (rs485.available() > 0) {
    rs485.read();
  }

  setRs485TxMode(true);
  delayMicroseconds(300);
  rs485.write(request, sizeof(request));
  rs485.flush();
  delayMicroseconds(300);
  setRs485TxMode(false);

  const uint8_t expectedDataBytes = registerCount * 2;
  const uint8_t expectedResponseLength = 3 + expectedDataBytes + 2;

  uint8_t response[64];
  uint8_t responseLength = 0;
  unsigned long startedAt = millis();

  while ((millis() - startedAt) < MODBUS_TIMEOUT_MS && responseLength < sizeof(response)) {
    while (rs485.available() > 0 && responseLength < sizeof(response)) {
      response[responseLength++] = (uint8_t) rs485.read();
    }
  }

  if (responseLength < expectedResponseLength) {
    error = "timeout_or_short_frame_" + String(responseLength);
    return false;
  }

  int frameStart = -1;
  const uint8_t frameLength = (uint8_t) (3 + expectedDataBytes + 2);

  for (uint8_t i = 0; i + 5 <= responseLength; i++) {
    if (response[i] != slaveAddress) {
      continue;
    }

    uint8_t functionCode = response[i + 1];

    if (functionCode == (MODBUS_READ_INPUT_REGISTERS | 0x80)) {
      uint16_t expectedExceptionCrc = modbusCrc16(&response[i], 3);
      uint16_t actualExceptionCrc = (uint16_t) response[i + 3] | ((uint16_t) response[i + 4] << 8);
      if (expectedExceptionCrc == actualExceptionCrc) {
        error = "modbus_exception_" + String(response[i + 2]);
        return false;
      }
      continue;
    }

    if (functionCode != MODBUS_READ_INPUT_REGISTERS) {
      continue;
    }

    if (response[i + 2] != expectedDataBytes) {
      continue;
    }

    if ((int) i + frameLength > responseLength) {
      continue;
    }

    uint16_t expectedFrameCrc = modbusCrc16(&response[i], frameLength - 2);
    uint16_t actualFrameCrc = (uint16_t) response[i + frameLength - 2] | ((uint16_t) response[i + frameLength - 1] << 8);

    if (expectedFrameCrc == actualFrameCrc) {
      frameStart = i;
      break;
    }
  }

  if (frameStart < 0) {
    error = "frame_not_found";
    return false;
  }

  for (uint16_t index = 0; index < registerCount; index++) {
    outRegisters[index] = u16be(&response[frameStart + 3 + index * 2]);
  }

  return true;
}

void decodeRegistersToReadings(const uint16_t* registers, MeterReadings& readings)
{
  const uint16_t registerVoltage = registers[0];
  const uint16_t registerCurrentLow = registers[1];
  const uint16_t registerCurrentHigh = registers[2];
  const uint16_t registerPowerLow = registers[3];
  const uint16_t registerPowerHigh = registers[4];
  const uint16_t registerEnergyLow = registers[5];
  const uint16_t registerEnergyHigh = registers[6];
  const uint16_t registerFrequency = registers[7];
  const uint16_t registerPowerFactor = registers[8];
  const uint16_t registerAlarm = registers[9];

  uint32_t rawCurrent = combineHighLowWords(registerCurrentLow, registerCurrentHigh);
  uint32_t rawPower = combineHighLowWords(registerPowerLow, registerPowerHigh);
  uint32_t rawEnergyWh = combineHighLowWords(registerEnergyLow, registerEnergyHigh);

  readings.voltage_v = (float) registerVoltage * 0.1f;
  readings.current_a = (float) rawCurrent * 0.001f;
  readings.active_power_w = (float) rawPower * 0.1f;
  readings.frequency_hz = (float) registerFrequency * 0.1f;
  readings.power_factor = (float) registerPowerFactor * 0.01f;
  readings.total_energy_kwh = (float) rawEnergyWh / 1000.0f;
  readings.alarm_status = registerAlarm;
}

/* ----------------------- Publish ----------------------- */

void publishState(const MeterReadings& readings)
{
  JsonDocument document;
  document["device_id"] = DEVICE_ID;
  document["fw_version"] = FW_VERSION;
  document["read_ok"] = readings.read_ok;
  document["modbus_error"] = readings.modbus_error;
  document["poll_ms"] = readings.poll_ms;

  char payload[256];
  size_t length = serializeJson(document, payload, sizeof(payload));

  if (length == 0) {
    Serial.println("[MQTT] State payload serialization failed");
    return;
  }

  if (!canPublishMqttPayload(TOPIC_STATE, length)) {
    Serial.printf(
        "[MQTT] State publish blocked: payload=%u topicLen=%u buffer=%u\n",
        (unsigned int) length,
        (unsigned int) strlen(TOPIC_STATE),
        mqttClient.getBufferSize());
    return;
  }

  bool published = mqttClient.publish(TOPIC_STATE, payload, true);
  Serial.printf(
      "[MQTT] State publish %s (%s) payload=%u buffer=%u\n",
      published ? "OK" : "FAIL",
      TOPIC_STATE,
      (unsigned int) length,
      mqttClient.getBufferSize());
}

void publishTelemetry(const MeterReadings& readings)
{
  JsonDocument document;

  document["device_id"] = DEVICE_ID;
  document["fw_version"] = FW_VERSION;
  document["ts_ms"] = millis();
  document["voltage_v"] = readings.voltage_v;
  document["current_a"] = readings.current_a;
  document["active_power_w"] = readings.active_power_w;
  document["frequency_hz"] = readings.frequency_hz;
  document["power_factor"] = readings.power_factor;
  document["total_energy_kwh"] = readings.total_energy_kwh;
  document["alarm_status"] = readings.alarm_status;
  document["read_ok"] = readings.read_ok;
  document["modbus_error"] = readings.modbus_error;
  document["poll_ms"] = readings.poll_ms;
  document["uptime_s"] = millis() / 1000;

  char payload[512];
  size_t length = serializeJson(document, payload, sizeof(payload));

  if (length == 0) {
    Serial.println("[MQTT] Telemetry payload serialization failed");
    return;
  }

  if (!canPublishMqttPayload(TOPIC_TELEMETRY, length)) {
    Serial.printf(
        "[MQTT] Telemetry publish blocked: payload=%u topicLen=%u buffer=%u\n",
        (unsigned int) length,
        (unsigned int) strlen(TOPIC_TELEMETRY),
        mqttClient.getBufferSize());
    return;
  }

  bool published = mqttClient.publish(TOPIC_TELEMETRY, payload, false);
  Serial.printf(
      "[MQTT] Telemetry publish %s (%s) payload=%u buffer=%u\n",
      published ? "OK" : "FAIL",
      TOPIC_TELEMETRY,
      (unsigned int) length,
      mqttClient.getBufferSize());

  if (published) {
    lastTelemetryPublishAtMs = millis();
  }
}

/* ----------------------- Device Loop ----------------------- */

void deviceLoop(unsigned long nowMs)
{
  if (nowMs - lastPollAtMs < POLL_INTERVAL_MS) {
    return;
  }

  lastPollAtMs = nowMs;

  uint16_t registers[MODBUS_REGISTER_COUNT] = {0};
  String error;
  String primaryError;
  String fallbackError;

  unsigned long pollStartedAt = millis();
  bool readOk = readMeasurementRegistersWithAddress(
      MODBUS_PRIMARY_SLAVE_ADDRESS,
      registers,
      MODBUS_REGISTER_COUNT,
      primaryError);

  uint8_t successfulAddress = MODBUS_PRIMARY_SLAVE_ADDRESS;

  if (!readOk) {
    readOk = readMeasurementRegistersWithAddress(
        MODBUS_FALLBACK_SLAVE_ADDRESS,
        registers,
        MODBUS_REGISTER_COUNT,
        fallbackError);

    if (readOk) {
      successfulAddress = MODBUS_FALLBACK_SLAVE_ADDRESS;
    }
  }

  unsigned long pollFinishedAt = millis();

  latestReadings.poll_ms = (uint32_t) (pollFinishedAt - pollStartedAt);
  latestReadings.read_ok = readOk;
  latestReadings.modbus_error = readOk
      ? ""
      : ("addr_" + String(MODBUS_PRIMARY_SLAVE_ADDRESS) + ":" + primaryError + "|addr_" + String(MODBUS_FALLBACK_SLAVE_ADDRESS) + ":" + fallbackError);

  if (readOk) {
    decodeRegistersToReadings(registers, latestReadings);
    Serial.printf("[MODBUS] Read OK via slave address %u\n", successfulAddress);
  } else {
    Serial.printf("[MODBUS] Read FAIL %s\n", latestReadings.modbus_error.c_str());
  }

  if ((nowMs - lastTelemetryPublishAtMs) >= TELEMETRY_INTERVAL_MS) {
    publishTelemetry(latestReadings);
    publishState(latestReadings);
  }
}

/* ----------------------- Arduino setup / loop ----------------------- */

void setup()
{
  Serial.begin(115200);
  delay(400);

  Serial.println("\n===========================================");
  Serial.println("ESP32 Single-Phase Meter (RS485 Modbus)");
  Serial.println("===========================================");

  deviceSetup();
  connectWiFi();
  connectMqtt();
}

void loop()
{
  ensureConnectivity();
  deviceLoop(millis());
}
