#include <WiFi.h>
#include <PubSubClient.h>
#include <DHT.h>

const char* WIFI_SSID = "";
const char* WIFI_PASS = "";
const char* MQTT_HOST = "10.0.0.93"; // the Pi's IP address
const char* TOPIC = "Frig1"; // "Frig2" on the second board

#define DHT_PIN 4
DHT dht(DHT_PIN, DHT11);
WiFiClient wifi;
PubSubClient mqtt(wifi);

void setup() {
Serial.begin(115200);
dht.begin();
Serial.print("Connecting to wifi");
WiFi.begin(WIFI_SSID, WIFI_PASS);
while (WiFi.status() != WL_CONNECTED) {
delay(500);
Serial.print(".");
}
Serial.print("\nWifi connected, ESP32 IP: ");
Serial.println(WiFi.localIP());
mqtt.setServer(MQTT_HOST, 1883);
}

void loop() {
if (!mqtt.connected()) {
Serial.print("Connecting to the broker... ");
if (mqtt.connect(TOPIC)) {
Serial.println("connected");
} else {
Serial.print("failed, state=");
Serial.println(mqtt.state());
delay(2000);
return;
}
}

float t = dht.readTemperature();
float h = dht.readHumidity();
if (isnan(t) || isnan(h)) {
Serial.println("Sensor read failed (nan): check the wiring and DHT_PIN");
} else {
char msg[64];
snprintf(msg, sizeof(msg), "{\"temp\": %.1f, \"hum\": %.1f}", t, h);
mqtt.publish(TOPIC, msg);
Serial.println(msg);
}
mqtt.loop();
delay(5000);
}