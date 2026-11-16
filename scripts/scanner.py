#!/usr/bin/env python3
"""
Barcode scanner for the Raspberry Pi.

Reads barcodes from a USB HID scanner (acts like a keyboard, emits the code +
Enter) and POSTs them to /api/scans on the PHP backend.

Usage:
    python3 barcode.py                    # read from stdin (HID scanner)
    python3 barcode.py --url http://api.example.com
    python3 barcode.py --device /dev/hidraw0   # raw HID device (optional)

Environment variables:
    API_URL      base URL of the PHP app, e.g. http://localhost:8080
    API_TOKEN    optional bearer token if you add auth later
"""

import argparse
import json
import os
import sys
import time
import urllib.error
import urllib.request

DEFAULT_URL = os.environ.get("API_URL", "http://localhost:8080")
SCAN_ENDPOINT = "/api/scans"


def post_scan(base_url: str, code: str, token: str | None = None) -> bool:
    """POST a single barcode to /api/scans. Returns True on 2xx."""
    body = json.dumps({"epc": code}).encode("utf-8")
    req = urllib.request.Request(
        base_url.rstrip("/") + SCAN_ENDPOINT,
        data=body,
        method="POST",
        headers={
            "Content-Type": "application/json",
            **({"Authorization": f"Bearer {token}"} if token else {}),
        },
    )
    try:
        with urllib.request.urlopen(req, timeout=5) as resp:
            if 200 <= resp.status < 300:
                data = json.loads(resp.read().decode("utf-8"))
                print(f"  saved id={data['scans'][0]['id'] if data.get('scans') else '?'}")
                return True
            print(f"  HTTP {resp.status}: {resp.read().decode('utf-8', 'replace')}", file=sys.stderr)
    except urllib.error.HTTPError as e:
        print(f"  HTTP {e.code}: {e.read().decode('utf-8', 'replace')}", file=sys.stderr)
    except urllib.error.URLError as e:
        print(f"  network error: {e.reason}", file=sys.stderr)
    return False


def read_hid_stdin() -> None:
    """Read barcodes line-by-line from stdin (typical HID scanner output)."""
    print("Ready — scan a barcode (Ctrl+C to quit).", file=sys.stderr)
    for line in sys.stdin:
        code = line.strip()
        if code:
            yield code


def read_hidraw(path: str) -> None:
    """Read raw key events from a hidraw device and decode into barcodes."""
    # HID keyboard scancodes → characters (US layout, digits + basic chars)
    # A USB barcode scanner in keyboard emulation mode sends scancodes.
    keymap = {
        0x04: "a", 0x05: "b", 0x06: "c", 0x07: "d", 0x08: "e", 0x09: "f",
        0x0A: "g", 0x0B: "h", 0x0C: "i", 0x0D: "j", 0x0E: "k", 0x0F: "l",
        0x10: "m", 0x11: "n", 0x12: "o", 0x13: "p", 0x14: "q", 0x15: "r",
        0x16: "s", 0x17: "t", 0x18: "u", 0x19: "v", 0x1A: "w", 0x1B: "x",
        0x1C: "y", 0x1D: "z", 0x1E: "1", 0x1F: "2", 0x20: "3", 0x21: "4",
        0x22: "5", 0x23: "6", 0x24: "7", 0x25: "8", 0x26: "9", 0x27: "0",
        0x2D: "-", 0x2E: "=", 0x2F: "[", 0x30: "]", 0x31: "\\", 0x33: ";",
        0x34: "'", 0x35: "`", 0x36: ",", 0x37: ".", 0x38: "/", 0x2C: " ",
    }
    buf: list[str] = []
    print(f"Listening on {path} (Ctrl+C to quit).", file=sys.stderr)
    with open(path, "rb") as dev:
        while True:
            report = dev.read(8)
            if not report:
                time.sleep(0.01)
                continue
            modifier, keycode = report[0], report[2]
            if keycode == 0x28:  # Enter
                if buf:
                    yield "".join(buf)
                    buf.clear()
            elif keycode == 0x2A:  # Backspace
                if buf:
                    buf.pop()
            elif keycode in keymap:
                ch = keymap[keycode]
                if modifier & 0x02:  # Shift
                    ch = ch.upper() if ch.isalpha() else {
                        "1": "!", "2": "@", "3": "#", "4": "$", "5": "%",
                        "6": "^", "7": "&", "8": "*", "9": "(", "0": ")",
                        "-": "_", "=": "+", "[": "{", "]": "}", "\\": "|",
                        ";": ":", "'": '"', "`": "~", ",": "<", ".": ">", "/": "?",
                    }.get(ch, ch)
                buf.append(ch)


def main() -> int:
    ap = argparse.ArgumentParser(description="Scan barcodes and POST them to the API.")
    ap.add_argument("--url", default=DEFAULT_URL, help="Base URL of the PHP app")
    ap.add_argument("--token", default=os.environ.get("API_TOKEN"), help="Optional bearer token")
    ap.add_argument("--device", help="hidraw device path (e.g. /dev/hidraw0)")
    args = ap.parse_args()

    reader = read_hidraw(args.device) if args.device else read_hid_stdin()

    ok = bad = 0
    try:
        for code in reader:
            print(f"scan: {code}")
            if post_scan(args.url, code, args.token):
                ok += 1
            else:
                bad += 1
    except KeyboardInterrupt:
        print(f"\nDone. {ok} saved, {bad} failed.", file=sys.stderr)
    return 0 if bad == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())