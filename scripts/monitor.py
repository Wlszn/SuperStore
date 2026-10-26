#!/usr/bin/env python3

import json
import os
import smtplib
import time
import urllib.request
from email.mime.text import MIMEText
from pathlib import Path

import paho.mqtt.client as mqtt
from dotenv import load_dotenv

load_dotenv(Path(__file__).resolve().parent.parent / ".env")

MQTT_HOST = "localhost"   # the Pi running Mosquitto (use its IP address when testing from the PC)
API_URL = "http://localhost:8000"
TOPICS = ["Frig1", "Frig2"]

GMAIL_USER = os.getenv("GMAIL_USER")
GMAIL_APP_PASSWORD = os.getenv("GMAIL_APP_PASSWORD")
ALERT_TO = os.getenv("ALERT_TO") or GMAIL_USER
COOLDOWN = 120            # seconds between two alert emails for the same fridge

last_alert = {}           # time the last alert email was attempted


def on_connect(client, userdata, flags, reason_code, properties):
    # Subscribing here also restores the topics after a reconnect.
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

    if (result.get("over_threshold") and GMAIL_USER and GMAIL_APP_PASSWORD
            and time.time() - last_alert.get(msg.topic, 0) > COOLDOWN):
        last_alert[msg.topic] = time.time()   # set first, so a failure is not retried on every reading
        mail = MIMEText(f"The current temperature is {data['temp']} °C. Would you like to turn on the fan?")
        mail["Subject"] = f"{msg.topic} is too warm"
        mail["From"] = GMAIL_USER
        mail["To"] = ALERT_TO
        try:
            with smtplib.SMTP("smtp.gmail.com", 587, timeout=20) as server:
                server.starttls()
                server.login(GMAIL_USER, GMAIL_APP_PASSWORD)
                server.send_message(mail)
            print(f"{msg.topic}: alert email sent to {ALERT_TO}")
        except OSError as error:
            print(f"{msg.topic}: could not send the alert email ({error})")


if not GMAIL_USER or not GMAIL_APP_PASSWORD:
    print("GMAIL_USER / GMAIL_APP_PASSWORD are missing in .env: alert emails are disabled.")

client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2)
client.on_connect = on_connect
client.on_message = on_message
client.connect(MQTT_HOST, 1883)
client.loop_forever()