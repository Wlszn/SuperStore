#!/usr/bin/env python3
"""Runs all of Phase 2 on the Raspberry Pi: fridge readings, alert emails and the fan.

    python3 scripts/monitor.py

Leave it running. Press Ctrl+C to stop it (the fan is switched off).

1. Readings: each ESP32 publishes {"temp": 4.0, "hum": 55.0} to its own topic (Frig1, Frig2).
   Every message is forwarded to POST /api/readings, which stores it and says whether the
   temperature is over that fridge's threshold.
2. Alerts: when it is, an email is sent (at most one per fridge every COOLDOWN seconds).
   Every CHECK_EVERY seconds the inbox of GMAIL_USER is checked for an unread reply from
   ALERT_TO to such an email: a reply that starts with "yes" turns the fan on (POST /api/fan).
3. Fan: every FAN_EVERY seconds GET /api/fan says whether the fan should be on (the YES reply
   or the dashboard switch set it), and the GPIO pins of the motor driver are set to match.
   On a Pi 5 the pins go back to off when the script that set them ends, which is why this
   runs here, in the script that never stops. On a PC (no GPIO) the fan part is skipped.

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
FAN_EVERY = 1             # seconds between two checks of the fan state

last_alert = {}           # topic -> time the last alert email was attempted

# Motor driver on BCM pins 22 (enable), 27 (input 1) and 17 (input 2).
try:
    from gpiozero import DigitalOutputDevice
    fan_pins = (DigitalOutputDevice(22), DigitalOutputDevice(27), DigitalOutputDevice(17))
except Exception as error:   # not a Pi (no gpiozero or no GPIO chip): run without the fan
    fan_pins = None
    print(f"No GPIO here ({error}): the fan motor is disabled, readings and emails still work.")


def set_fan(on):
    """ON = enable high, input 1 low, input 2 high. OFF = everything low."""
    if fan_pins is None:
        return
    enable, in1, in2 = fan_pins
    in1.off()
    if on:
        enable.on()
        in2.on()
    else:
        enable.off()
        in2.off()


def read_fan_state():
    """True or False as stored by the website, or None if it can't be reached."""
    try:
        with urllib.request.urlopen(API_URL + "/api/fan", timeout=10) as response:
            return bool(json.load(response)["fan"]["is_on"])
    except (OSError, ValueError, KeyError):
        return None


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


def check_inbox():
    """Turns the fan on when an unread reply to an alert email starts with "yes"."""
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


if not GMAIL_USER or not GMAIL_APP_PASSWORD:
    print("GMAIL_USER / GMAIL_APP_PASSWORD are missing in .env: alert emails are disabled.")

client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2)
client.on_connect = on_connect
client.on_message = on_message
client.connect(MQTT_HOST, 1883)
client.loop_start()       # MQTT runs in the background; this loop drives the fan and watches the inbox

fan_on = None             # what the pins are set to right now
online = True
last_inbox_check = time.time()

try:
    while True:
        wanted = read_fan_state()
        if wanted is None:
            if online:
                print(f"Cannot reach {API_URL}/api/fan; keeping the fan as it is.")
            online = False
        else:
            online = True
            if wanted != fan_on:
                set_fan(wanted)
                fan_on = wanted
                print("Fan ON" if wanted else "Fan OFF")

        if GMAIL_USER and GMAIL_APP_PASSWORD and ALERT_TO and time.time() - last_inbox_check >= CHECK_EVERY:
            last_inbox_check = time.time()
            check_inbox()

        time.sleep(FAN_EVERY)
except KeyboardInterrupt:
    pass
finally:
    set_fan(False)
    client.loop_stop()
    print("Stopped: the fan is off.")
