#!/usr/bin/env python3
"""Reads the Minew MSP01 BLE sensor through Pareto Anywhere and sends each new
reading to the website (POST /api/ble-readings).

    python3 scripts/ble.py

Pareto Anywhere (running on this Pi) listens to the sensor and decodes its packets;
this script never connects to the sensor itself. Every POLL_SECONDS it asks the
context API for the sensor's latest decoded data ("dynamb"). The MSP01 sends its
values in separate packets (temperature/humidity, light, motion), so the script keeps
the last known value of each and sends them together whenever a new packet arrives.

Settings are read from the .env file in the project root:
    BLE_SENSOR_MAC=C3:00:00:45:5D:A5:12   the sensor's MAC address (all 6 pairs)
    BLE_ID_TYPE=3                      3 = random address (starts with C0 to FF), 2 = public
"""
import json
import os
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

from dotenv import load_dotenv

load_dotenv(Path(__file__).resolve().parent.parent / ".env")

PARETO_URL = "http://localhost:3001"   # Pareto Anywhere on this Pi
API_URL = "http://localhost:8000"      # the PHP website
POLL_SECONDS = 2

MAC = (os.getenv("BLE_SENSOR_MAC") or "").replace(":", "").replace("-", "").strip().lower()
ID_TYPE = os.getenv("BLE_ID_TYPE", "3")

# Pareto Anywhere property name -> field name expected by POST /api/ble-readings
PROPERTIES = {
    "temperature": "temp",
    "relativeHumidity": "hum",
    "illuminance": "lux",
    "batteryPercentage": "battery",
}

known = {"temp": None, "hum": None, "lux": None, "motion": None, "battery": None}


def read_dynamb():
    """The sensor's latest decoded data from Pareto Anywhere, or None if it has none."""
    url = f"{PARETO_URL}/context/device/{MAC}/{ID_TYPE}"
    request = urllib.request.Request(url, headers={"Accept": "application/json"})
    with urllib.request.urlopen(request, timeout=10) as response:
        context = json.load(response)
    for device in (context.get("devices") or {}).values():
        if device.get("dynamb"):
            return device["dynamb"]
    return None


def update_known(dynamb):
    """Keep the newest value of each property; a packet only carries some of them."""
    for prop, field in PROPERTIES.items():
        if isinstance(dynamb.get(prop), (int, float)):
            known[field] = dynamb[prop]
    if known["battery"] is not None:
        known["battery"] = round(known["battery"])

    motion = dynamb.get("isMotionDetected")   # a list of booleans, one per motion sensor
    if isinstance(motion, list) and motion:
        known["motion"] = any(motion)
    elif isinstance(motion, bool):
        known["motion"] = motion


def send_reading():
    body = json.dumps({"mac": MAC, **known}).encode()
    request = urllib.request.Request(
        API_URL + "/api/ble-readings", body, {"Content-Type": "application/json"}
    )
    with urllib.request.urlopen(request, timeout=10) as response:
        return json.load(response)


if len(MAC) != 12:
    sys.exit("BLE_SENSOR_MAC is missing or incomplete in .env (it needs all 6 pairs, e.g. C3:00:45:5D:A5:12).")

print(f"Watching {MAC}/{ID_TYPE} on {PARETO_URL}...")
last_seen = None    # timestamp of the last packet already sent
online = True

while True:
    try:
        dynamb = read_dynamb()
        online = True
    except (OSError, ValueError) as error:   # Pareto Anywhere not running, or bad answer
        if online:
            print(f"Cannot read {PARETO_URL} ({error}); retrying...")
        online = False
        time.sleep(POLL_SECONDS)
        continue

    # Only send when the sensor has sent a new packet since the last time.
    stamp = dynamb.get("timestamp") if dynamb else None
    if dynamb and stamp != last_seen:
        last_seen = stamp
        update_known(dynamb)
        try:
            send_reading()
            print(f"{known['temp']} C, {known['hum']} %, {known['lux']} lux, "
                  f"motion: {known['motion']}, battery: {known['battery']} %")
        except urllib.error.HTTPError as error:   # the website rejected the reading
            print(f"The website refused the reading ({error.code}): {error.read().decode(errors='replace')}")
        except OSError as error:                  # website unreachable
            print(f"Could not send the reading to the website ({error})")

    time.sleep(POLL_SECONDS)