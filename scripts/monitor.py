#!/usr/bin/env python3
"""Listens to the fridge sensors over MQTT, sends every reading to the website,
and emails an alert when a fridge goes over its temperature limit.

    python3 scripts/monitor.py

Each ESP32 publishes {"temp": 4.0, "hum": 55.0} to its own topic (Frig1, Frig2).
This script forwards each message to POST /api/readings, which stores it and says
whether the temperature is over that fridge's threshold. When it is, an email is sent
(at most one per fridge every COOLDOWN seconds). Every CHECK_EVERY seconds the inbox of
GMAIL_USER is checked for an unread reply from ALERT_TO to such an email: a reply that
starts with "yes" turns the fan on (POST /api/fan {"on": true}).

Gmail settings are read from the .env file in the project root:
    GMAIL_USER=the sending Gmail address
    GMAIL_APP_PASSWORD=its 16-character app password
    ALERT_TO=the address that receives the alert (defaults to GMAIL_USER)
"""
import email
import imaplib
import json
import os
import smtplib
import time
import urllib.request
from email import policy
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
CHECK_EVERY = 15          # seconds between two checks of the inbox for a YES reply

last_alert = {}           # topic -> time the last alert email was attempted


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
client.loop_start()       # MQTT runs in the background; this thread watches the inbox

while True:
    time.sleep(CHECK_EVERY)
    if not (GMAIL_USER and GMAIL_APP_PASSWORD and ALERT_TO):
        continue
    try:
        inbox = imaplib.IMAP4_SSL("imap.gmail.com")
        inbox.login(GMAIL_USER, GMAIL_APP_PASSWORD)
        inbox.select("INBOX")
        _, found = inbox.search(None, "UNSEEN", "FROM", ALERT_TO, "SUBJECT", '"too warm"')
        for number in found[0].split():
            _, data = inbox.fetch(number, "(RFC822)")   # fetching also marks it as read
            reply = email.message_from_bytes(data[0][1], policy=policy.default)
            part = reply.get_body(preferencelist=("plain",))
            text = part.get_content().strip().lower() if part else ""
            if text.startswith("yes"):
                request = urllib.request.Request(
                    API_URL + "/api/fan", json.dumps({"on": True}).encode(),
                    {"Content-Type": "application/json"},
                )
                urllib.request.urlopen(request, timeout=10).close()
                print("Received YES: the fan is turned on")
            else:
                print(f"Reply ignored (it does not start with yes): {text[:40]!r}")
        inbox.logout()
    except (OSError, imaplib.IMAP4.error) as error:
        print(f"Could not check the inbox ({error})")