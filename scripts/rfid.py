#!/usr/bin/env python3
"""Reads RFID tags with the Chafon CF600 (CDC-COM mode) and sends them to the website.

    python3 scripts/rfid.py                       # normal use: read tags, send them to POST /api/scans
    python3 scripts/rfid.py --port /dev/ttyACM0   # if the reader shows up under another name
    python3 scripts/rfid.py --raw                 # diagnostic: print every byte the reader sends
    python3 scripts/rfid.py --send E28011700000020A1B2C3D01   # test without the reader

Setup on the Pi (once): set the reader to CDC-COM mode with Chafon's Windows tool, then
    sudo modprobe usbserial vendor=0x0483 product=0x5750
    ls /dev/ttyUSB* /dev/ttyACM*
    sudo apt install python3-serial

How it works:
  * The script asks the reader for its info (command 0x21) to learn which Chafon
    protocol it speaks, trying the common baud rates until the reader answers.
  * It then runs an inventory (command 0x01) several times per second. Every tag in
    the field answers, so all the products on the reader are read at once.
  * If the reader never answers commands but sends tags by itself (auto-output
    mode), the script just listens and decodes what arrives.
  * A tag is sent to the website once when it appears, and again only after it has
    left the reader for a moment, so a tag lying on the reader is not counted twice.

Chafon frame format (UHFReader18 / UHFReader288 protocols):
    Len | Adr | Cmd | Data... | CRC-LSB | CRC-MSB
    Len = number of bytes after Len; CRC-16 with preset 0xFFFF, polynomial 0x8408.
    Answers: Len | Adr | reCmd | Status | Data... | CRC-LSB | CRC-MSB
"""
import argparse
import json
import re
import sys
import time
import urllib.request

DEFAULT_PORTS = ["/dev/ttyUSB0", "/dev/ttyACM0", "/dev/ttyUSB1", "/dev/ttyACM1"]
BAUD_RATES = [57600, 115200, 9600, 38400, 19200]

CMD_INVENTORY = 0x01
CMD_READER_INFO = 0x21
STATUS_MORE_FRAMES = 0x03

# Reader types (byte 2 of the reader info) that use the newer "288" inventory format.
TYPES_288 = {0x0C, 0x0F, 0x10, 0x20, 0x8B}

GONE_AFTER = 3.0         # seconds without seeing a tag before it counts as removed
INVENTORY_PAUSE = 0.1    # seconds between the end of an inventory and the next one
ANSWER_TIMEOUT = 2.0     # resend the inventory if the reader hasn't finished answering


# ---------------------------------------------------------------- protocol

def crc16(data):
    value = 0xFFFF
    for byte in data:
        value ^= byte
        for _ in range(8):
            value = (value >> 1) ^ 0x8408 if value & 1 else value >> 1
    return value


def build_command(cmd, data=b"", address=0xFF):
    body = bytes([4 + len(data), address, cmd]) + bytes(data)
    crc = crc16(body)
    return body + bytes([crc & 0xFF, crc >> 8])


def inventory_command(reader_type):
    if reader_type in TYPES_288:
        # Q=4, session S0, mask on EPC (no mask), target A, antenna 1, scan time 2 s.
        return build_command(CMD_INVENTORY, bytes([0x04, 0x00, 0x01, 0, 0, 0, 0x00, 0x80, 0x14]))
    return build_command(CMD_INVENTORY)


def pop_frames(buffer):
    """Takes every complete frame with a valid CRC out of `buffer` (a bytearray).
    Bytes that can't start a valid frame are dropped, so the stream resynchronises."""
    frames = []
    while len(buffer) >= 5:
        length = buffer[0]
        if length < 4:
            del buffer[0]
            continue
        if len(buffer) < length + 1:
            break  # wait for the rest
        frame = bytes(buffer[:length + 1])
        crc = crc16(frame[:-2])
        if frame[-2] == crc & 0xFF and frame[-1] == crc >> 8:
            frames.append(frame)
            del buffer[:length + 1]
        else:
            del buffer[0]
    return frames


def tags_in(data, prefix, suffix):
    """Reads `num, (len, [prefix], EPC, [suffix])*`. Returns None if the bytes don't fit."""
    if not data:
        return None
    count, pos, tags = data[0], 1, []
    for _ in range(count):
        if pos >= len(data):
            return None
        epc_len = data[pos]
        start = pos + 1 + prefix
        end = start + epc_len
        if epc_len == 0 or end + suffix > len(data):
            return None
        tags.append(data[start:end].hex().upper())
        pos = end + suffix
    return tags if pos == len(data) else None


def tags_from_frame(frame):
    """Every EPC in an answer frame (inventory answer, or an auto-output frame)."""
    data = frame[4:-2]
    candidates = [
        tags_in(data, 0, 0),        # UHFReader18: num, (len, EPC)*
        tags_in(data[1:], 0, 1),    # UHFReader288: antenna, num, (len, EPC, RSSI)*
        tags_in(data, 0, 1),        # num, (len, EPC, RSSI)*
    ]
    for tags in candidates:
        if tags:
            return tags
    # Auto-output frames often hold a single tag: [antenna,] len, EPC [, RSSI]
    for start in (0, 1):
        if len(data) > start and 0 < data[start] <= len(data) - start - 1:
            epc = data[start + 1:start + 1 + data[start]]
            if len(epc) in (8, 12, 16, 24, 32):
                return [epc.hex().upper()]
    return []


HEX_RUN = re.compile(rb"[0-9A-Fa-f]{8,32}")


def tags_from_text(text_buffer):
    """Auto-output as text: one EPC per line (maybe with a prefix such as 'EPC:').
    Takes the complete lines out of `text_buffer`; an unfinished line stays for later."""
    *lines, rest = re.split(rb"[\r\n]", bytes(text_buffer))
    text_buffer[:] = rest
    tags = []
    for line in lines:
        runs = HEX_RUN.findall(line.replace(b" ", b""))
        if runs:
            tags.append(max(runs, key=len).decode().upper())
    return tags


def is_text(chunk):
    return all(32 <= byte < 127 or byte in (10, 13) for byte in chunk)


# ---------------------------------------------------------------- website

def send_tags(api_url, epcs):
    """POST /api/scans. Returns True if the website saved them."""
    body = json.dumps({"epcs": [epc[:24] for epc in epcs]}).encode()
    request = urllib.request.Request(api_url + "/api/scans", body, {"Content-Type": "application/json"})
    try:
        with urllib.request.urlopen(request, timeout=5) as response:
            return json.load(response).get("status") == "success"
    except OSError as error:
        print(f"  ! Could not send to the website ({error}). Is 'php -S 0.0.0.0:8000 -t public' running?")
        return False


class TagTracker:
    """Remembers which tags are on the reader, to send each one once."""

    def __init__(self):
        self.last_seen = {}

    def seen(self, epcs):
        now = time.time()
        new = [epc for epc in dict.fromkeys(epcs) if epc not in self.last_seen]
        for epc in epcs:
            self.last_seen[epc] = now
        for epc, when in list(self.last_seen.items()):
            if now - when > GONE_AFTER:
                del self.last_seen[epc]
                print(f"  - {epc} left the reader")
        return new


# ---------------------------------------------------------------- reader

def open_port(serial, port, baud):
    return serial.Serial(port, baud, timeout=0.05)


def ask_reader_info(link):
    """Sends 'get reader info'; returns the reader type byte, or None if no answer."""
    link.reset_input_buffer()
    link.write(build_command(CMD_READER_INFO))
    buffer = bytearray()
    deadline = time.time() + 0.6
    while time.time() < deadline:
        buffer += link.read(64)
        for frame in pop_frames(buffer):
            if frame[2] == CMD_READER_INFO and frame[3] == 0x00 and len(frame) >= 13:
                info = frame[4:-2]
                print(f"Reader found: firmware {info[0]}.{info[1]}, type 0x{info[2]:02X}, power {info[6]} dBm")
                return info[2]
    return None


def connect(serial, port_arg, baud_arg):
    """Finds the port and baud rate. Returns (link, reader_type or None)."""
    ports = [port_arg] if port_arg else DEFAULT_PORTS
    bauds = [baud_arg] if baud_arg else BAUD_RATES
    for port in ports:
        for baud in bauds:
            try:
                link = open_port(serial, port, baud)
            except (OSError, serial.SerialException):
                break  # this port doesn't exist: try the next port
            reader_type = ask_reader_info(link)
            if reader_type is not None:
                print(f"Connected on {port} at {baud} baud (command mode).")
                return link, reader_type
            link.close()
    # Nobody answered: listen on the first port that opens (auto-output mode).
    for port in ports:
        try:
            link = open_port(serial, port, bauds[0])
            print(f"The reader on {port} does not answer commands: listening for tags it sends by itself.")
            print("  (If nothing appears, run with --raw and check the reader is in CDC-COM mode.)")
            return link, None
        except (OSError, serial.SerialException):
            continue
    return None, None


def read_loop(link, reader_type, api_url):
    tracker = TagTracker()
    buffer = bytearray()       # binary frames
    text_buffer = bytearray()  # text output (auto-output mode)
    command = inventory_command(reader_type) if reader_type is not None else None
    next_inventory = 0.0
    sent_at = None  # when the inventory still being answered was sent

    print("Ready. Place tagged products on the reader (Ctrl+C to stop).")
    while True:
        now = time.time()
        if command and (sent_at is None and now >= next_inventory or sent_at and now - sent_at > ANSWER_TIMEOUT):
            link.write(command)
            sent_at = now

        chunk = link.read(256)
        epcs = []
        if chunk and command is None and is_text(chunk):
            text_buffer += chunk
            epcs += tags_from_text(text_buffer)
            if len(text_buffer) > 4096:
                text_buffer.clear()
        elif chunk:
            buffer += chunk
            for frame in pop_frames(buffer):
                if frame[2] in (CMD_INVENTORY, 0xEE) or command is None:
                    epcs += tags_from_frame(frame)
                # The inventory is finished unless the reader says more frames follow.
                if command and frame[2] == CMD_INVENTORY and frame[3] != STATUS_MORE_FRAMES:
                    sent_at = None
                    next_inventory = time.time() + INVENTORY_PAUSE
            if len(buffer) > 4096:
                buffer.clear()

        new = tracker.seen(epcs)
        if new:
            for epc in new:
                print(f"  + {epc}")
            if send_tags(api_url, new):
                print(f"    sent {len(new)} tag(s) to the website")


def raw_dump(serial, port, baud):
    port = port or DEFAULT_PORTS[0]
    baud = baud or BAUD_RATES[0]
    link = open_port(serial, port, baud)
    print(f"Raw bytes from {port} at {baud} baud. Sending an inventory command every second.")
    print("Copy what appears here if the normal mode doesn't find your tags.\n")
    next_command = 0.0
    while True:
        if time.time() >= next_command:
            command = build_command(CMD_INVENTORY)
            print(f">> {command.hex(' ').upper()}")
            link.write(command)
            next_command = time.time() + 1.0
        chunk = link.read(256)
        if chunk:
            printable = "".join(chr(b) if 32 <= b < 127 else "." for b in chunk)
            print(f"<< {chunk.hex(' ').upper()}    |{printable}|")


def main():
    parser = argparse.ArgumentParser(description="Chafon CF600 RFID reader -> SuperStore website")
    parser.add_argument("--port", help="serial port (default: try /dev/ttyUSB0, /dev/ttyACM0, ...)")
    parser.add_argument("--baud", type=int, help="baud rate (default: try 57600, 115200, ...)")
    parser.add_argument("--api", default="http://localhost:8000", help="website address")
    parser.add_argument("--raw", action="store_true", help="print the raw bytes from the reader")
    parser.add_argument("--send", nargs="+", metavar="EPC", help="send these EPCs without a reader, then exit")
    args = parser.parse_args()

    if args.send:
        ok = send_tags(args.api, [epc.upper().replace(" ", "") for epc in args.send])
        print("Sent." if ok else "Not sent.")
        return 0 if ok else 1

    try:
        import serial
    except ImportError:
        print("pyserial is missing: sudo apt install python3-serial")
        return 1

    try:
        if args.raw:
            raw_dump(serial, args.port, args.baud)
            return 0
        while True:
            link, reader_type = connect(serial, args.port, args.baud)
            if link is None:
                print("No reader found. Check: ls /dev/ttyUSB* /dev/ttyACM*  (retrying in 3 s)")
                time.sleep(3)
                continue
            try:
                read_loop(link, reader_type, args.api)
            except (OSError, serial.SerialException) as error:
                print(f"Reader disconnected ({error}). Reconnecting...")
                link.close()
                time.sleep(2)
    except KeyboardInterrupt:
        print("\nStopped.")
        return 0


if __name__ == "__main__":
    sys.exit(main())
