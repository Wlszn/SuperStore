import time
import paho.mqtt.client as mqtt

# --- Configuration ---
SSID        = "REPLACE_WITH_YOUR_SSID"          # only used on a Pi if you script wifi
PASSWORD    = "REPLACE_WITH_YOUR_PASSWORD"
MQTT_SERVER = "YOUR_MQTT_BROKER_IP_ADDRESS"
MQTT_PORT   = 1883
CLIENT_ID   = "vanieriot"

SUB_TOPIC   = "room/light"
PUB_TOPIC   = "IoTlab/ESP32"
PUB_PAYLOAD = "Hello I'm ESP32"
PUB_INTERVAL = 5  # seconds

LED_PIN = 4       # only meaningful on a Pi / board with GPIO
led_state = False # we track state in software if no GPIO


# --- Callbacks ---
def on_connect(client, userdata, flags, rc):
    if rc == 0:
        print("Connected to broker")
        client.subscribe(SUB_TOPIC)
        print(f"Subscribed to {SUB_TOPIC}")
    else:
        print(f"Connect failed, rc={rc}")


def on_message(client, userdata, msg):
    global led_state
    topic   = msg.topic
    payload = msg.payload.decode()
    print(f"Message arrived on topic: {topic}. Message: {payload}")

    if topic == SUB_TOPIC:
        if payload == "ON":
            led_state = True
            # On a Pi: GPIO.output(LED_PIN, GPIO.HIGH)
            print("Light is ON")
        else:
            led_state = False
            # On a Pi: GPIO.output(LED_PIN, GPIO.LOW)
            print("Light is OFF")


def on_disconnect(client, userdata, rc):
    print(f"Disconnected (rc={rc}) — will retry automatically")


# --- Setup ---
client = mqtt.Client(client_id=CLIENT_ID)
client.on_connect    = on_connect
client.on_message    = on_message
client.on_disconnect = on_disconnect

print(f"Connecting to broker {MQTT_SERVER}:{MQTT_PORT} ...")
client.connect(MQTT_SERVER, MQTT_PORT, keepalive=60)

# Start the network loop in a background thread so we can publish in the main loop
client.loop_start()

# --- Main loop: publish every 5 seconds ---
try:
    while True:
        client.publish(PUB_TOPIC, PUB_PAYLOAD)
        print(f"Published '{PUB_PAYLOAD}' to {PUB_TOPIC}")
        time.sleep(PUB_INTERVAL)
except KeyboardInterrupt:
    print("\nShutting down...")
    client.loop_stop()
    client.disconnect()