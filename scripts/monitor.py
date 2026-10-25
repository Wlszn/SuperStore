#!/usr/bin/env python3
"""Listens to the fridge sensors over MQTT and sends every reading to the website.

    python3 scripts/monitor.py

Each ESP32 publishes {"temp": 4.0, "hum": 55.0} to its own topic (Frig1, Frig2).
This script forwards each message to POST /api/readings, which stores it and says
whether the temperature is over that fridge's threshold.
"""
import json
import urllib.request

import paho.mqtt.client as mqtt

MQTT_HOST = "localhost"   # the Pi running Mosquitto (use its IP address when testing from the PC)
API_URL = "http://localhost:8000"
TOPICS = ["Frig1", "Frig2"]


def on_connect(client, userdata, flags, reason_code, properties):
    # Subscribing here (not once at startup) also restores the topics after a reconnect.
    client.subscribe([(topic, 0) for topic in TOPICS])
    print("Connected to the broker, waiting for readings...")


def on_message(client, userdata, msg):
    try:
        data = json.loads(msg.payload)
        body = json.dumps({"fridge": msg.topic, "temp": data["temp"], "hum": data["hum"]}).encode()
        request = urllib.request.Request(
            API_URL + "/api/readings", body, {"Content-Type": "application/json"}
        )
        with urllib.request.urlopen(request, timeout=10) as response:
            result = json.load(response)
    except (ValueError, KeyError, TypeError):
        print(f"{msg.topic}: ignored a message that is not {{\"temp\": ..., \"hum\": ...}}: {msg.payload!r}")
        return
    except OSError as error:   # website unreachable, or it answered with an error
        print(f"{msg.topic}: could not send the reading to the website ({error})")
        return

    print(f"{msg.topic}: {data['temp']} C, {data['hum']} %, over threshold: {result.get('over_threshold')}")


client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2)
client.on_connect = on_connect
client.on_message = on_message
client.connect(MQTT_HOST, 1883)
client.loop_forever()