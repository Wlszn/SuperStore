#!/usr/bin/env python3
"""Drives the fan (the DC motor on the motor driver) from the state stored by the website.

Run it on the Raspberry Pi and leave it running:

    python3 scripts/fan.py

About once a second it asks GET /api/fan whether the fan should be on and sets the
GPIO pins to match. It has to keep running: on a Pi 5 the pins go back to off as soon
as the script that set them ends. Press Ctrl+C to stop it (the motor is switched off).
"""
import json
import time
import urllib.request

from gpiozero import DigitalOutputDevice

API_URL = "http://localhost:8000/api/fan"
POLL_SECONDS = 1

# BCM pin numbers wired to the motor driver
enable = DigitalOutputDevice(22)  # enable pin
in1 = DigitalOutputDevice(27)     # input 1
in2 = DigitalOutputDevice(17)     # input 2


def set_fan(on):
    """ON = enable high, input 1 low, input 2 high. OFF = everything low."""
    if on:
        enable.on()
        in1.off()
        in2.on()
    else:
        enable.off()
        in1.off()
        in2.off()


def read_fan_state():
    """True or False as stored by the website, or None if it can't be reached."""
    try:
        with urllib.request.urlopen(API_URL, timeout=3) as response:
            return bool(json.load(response)["fan"]["is_on"])
    except (OSError, ValueError, KeyError):
        return None


last = None      # what the pins are set to right now
online = True

try:
    while True:
        wanted = read_fan_state()

        if wanted is None:
            if online:
                print(f"Cannot reach {API_URL}; keeping the fan as it is.")
            online = False
        else:
            online = True
            if wanted != last:
                set_fan(wanted)
                last = wanted
                print("Fan ON" if wanted else "Fan OFF")

        time.sleep(POLL_SECONDS)
except KeyboardInterrupt:
    pass
finally:
    set_fan(False)