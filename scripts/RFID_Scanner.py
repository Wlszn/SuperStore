import argparse
import sys
import time

import serial

from rfid import publish

PORT = "/dev/ttyUSB0"  # Change to /dev/ttyACM0 if needed
BAUD = 115200  # CF600 serial baud rate

# Chafon CF600 inventory command
READ_TAGS = bytes([0x04, 0xFF, 0x01, 0x1B, 0xB4])


def parse_epcs(buf):
    """
    Pull EPCs out of CF600 tag frames.

    Frame:
    CF | addr | cmd(2) | len | status | rssi(2) |
    ant | ? | epc_len | epc | crc(2)

    Returns:
        (list of EPC hex strings, leftover bytes)
    """
    epcs = []
    i = 0

    while True:
        i = buf.find(0xCF, i)

        if i < 0 or i + 5 > len(buf):
            return epcs, buf[i:] if i >= 0 else b""

        length = buf[i + 4]
        end = i + 5 + length + 2

        if end > len(buf):
            return epcs, buf[i:]

        data = buf[i + 5:i + 5 + length]

        if length >= 6 and data[0] == 0x00:
            epc_len = data[5]
            epc = data[6:6 + epc_len]

            if 1 <= epc_len <= 12 and len(epc) == epc_len:
                epcs.append(epc.hex().upper())

        i = end


def main():
    parser = argparse.ArgumentParser(description="CF600 serial bridge for SuperStore")
    parser.add_argument("--port", default=PORT, help="Reader port, for example /dev/ttyUSB0 or COM3")
    parser.add_argument("--url", default="http://127.0.0.1:8000", help="SuperStore base URL")
    args = parser.parse_args()

    with serial.Serial(args.port, BAUD, timeout=0.5) as reader:
        print(f"Reader connected to {args.port}. Sending scans to {args.url}.", flush=True)
        buffer = b""
        while True:
            reader.write(READ_TAGS)
            time.sleep(0.5)
            buffer += reader.read(reader.in_waiting)
            epcs, buffer = parse_epcs(buffer)
            tags = list(dict.fromkeys(epcs))
            for offset in range(0, len(tags), 100):
                batch = tags[offset:offset + 100]
                try:
                    publish(args.url, batch)
                    print(f"Sent {len(batch)} tag(s) to SuperStore.", flush=True)
                except (OSError, ValueError, RuntimeError) as error:
                    print(f"Could not send scans to {args.url}: {error}", file=sys.stderr, flush=True)
                    time.sleep(1)


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("Reader stopped.")
    except OSError as error:
        print(f"Could not open or read the RFID reader: {error}", file=sys.stderr)
        sys.exit(1)
