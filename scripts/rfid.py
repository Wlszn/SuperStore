import argparse
import ctypes as ct
import json
import os
import platform
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path


class TagInfo(ct.Structure):
    _fields_ = [
        ("number", ct.c_ushort), ("rssi", ct.c_short),
        ("antenna", ct.c_ubyte), ("channel", ct.c_ubyte),
        ("crc", ct.c_ubyte * 2), ("pc", ct.c_ubyte * 2),
        ("length", ct.c_ubyte), ("code", ct.c_ubyte * 255),
    ]


def library_path(sdk):
    if sys.platform == "win32":
        arch = "ARM64" if platform.machine().lower() == "arm64" else ("x64" if ct.sizeof(ct.c_void_p) == 8 else "x86")
        return sdk / "API" / arch / "UHFPrimeReader.dll"
    machine = platform.machine().lower()
    arch = {"aarch64": "ARM64", "arm64": "ARM64", "armv7l": "ARM", "x86_64": "x64", "i686": "x86"}.get(machine)
    if arch is None:
        raise RuntimeError("Unsupported architecture: " + machine)
    return sdk / "API" / "Linux" / arch / "libCFApi.so"


class Reader:
    def __init__(self, sdk):
        path = library_path(sdk)
        if not path.is_file():
            raise RuntimeError("SDK library not found: " + str(path))
        self.dll_directory = os.add_dll_directory(str(path.parent)) if sys.platform == "win32" else None
        self.api = ct.WinDLL(str(path)) if sys.platform == "win32" else ct.CDLL(str(path))
        handle_type = ct.c_void_p if sys.platform == "win32" else ct.c_int64
        self.handle = handle_type()
        signatures = {
            "CFHid_GetUsbCount": [],
            "OpenHidConnection": [ct.POINTER(handle_type), ct.c_ushort],
            "CloseDevice": [handle_type],
            "InventoryContinue": [handle_type, ct.c_ubyte, ct.c_uint32 if sys.platform == "win32" else ct.c_ulong],
            "InventoryStop": [handle_type, ct.c_ushort],
            "GetTagUii": [handle_type, ct.POINTER(TagInfo), ct.c_ushort],
        }
        for name, arguments in signatures.items():
            function = getattr(self.api, name)
            function.argtypes = arguments
            function.restype = ct.c_int

    @staticmethod
    def require_success(result, operation):
        if result != 0:
            raise RuntimeError(operation + " failed (SDK code " + hex(result & 0xffffffff) + ").")

    def open(self, index):
        self.require_success(self.api.OpenHidConnection(ct.byref(self.handle), index), "Opening reader")
        try:
            self.require_success(self.api.InventoryContinue(self.handle, 0, 0), "Starting inventory")
        except Exception:
            self.close()
            raise

    def close(self):
        if self.handle.value:
            try:
                self.api.InventoryStop(self.handle, 2000)
            finally:
                self.api.CloseDevice(self.handle)
                self.handle.value = None if sys.platform == "win32" else 0

    def batch(self):
        tags = set()
        deadline = time.monotonic() + 0.5
        while time.monotonic() < deadline and len(tags) < 100:
            tag = TagInfo()
            result = self.api.GetTagUii(self.handle, ct.byref(tag), 200) & 0xffffffff
            if result == 0:
                if 1 <= tag.length <= 12:
                    tags.add(bytes(tag.code[:tag.length]).hex().upper())
                else:
                    print("Skipped tag: EPC exceeds the assignment's 24-character limit.", flush=True)
            elif result in (0xffffff08, 0xffffff12, 0xffffff15):
                continue
            elif result == 0xffffff07:
                self.require_success(self.api.InventoryContinue(self.handle, 0, 0), "Restarting inventory")
                break
            else:
                self.require_success(result, "Reading tags")
        return sorted(tags)


def publish(url, tags):
    request = urllib.request.Request(
        url.rstrip("/") + "/api/scans",
        data=json.dumps({"epcs": tags}).encode(),
        headers={"Content-Type": "application/json"}, method="POST",
    )
    with urllib.request.urlopen(request, timeout=10) as response:
        result = json.load(response)
        if result.get("status") != "success":
            raise RuntimeError("SuperStore rejected the scan batch.")


def main():
    parser = argparse.ArgumentParser(description="CF600 USB reader bridge for SuperStore")
    parser.add_argument("--sdk", required=True, type=Path, help="UHF Desk Reader SDK folder")
    parser.add_argument("--url", default="http://127.0.0.1:8000")
    parser.add_argument("--index", default=0, type=int)
    parser.add_argument("--list", action="store_true", help="List detected reader count and exit")
    parser.add_argument("--seconds", type=float, default=0, help="Stop after this duration; zero runs continuously")
    parser.add_argument("--read-only", action="store_true", help="Display EPCs without sending scans to SuperStore")
    args = parser.parse_args()
    reader = Reader(args.sdk)
    count = reader.api.CFHid_GetUsbCount()
    if count < 0:
        raise RuntimeError("USB enumeration failed.")
    print("USB readers detected:", count, flush=True)
    if args.list:
        return
    if args.index < 0 or args.index >= count:
        raise RuntimeError("Reader unavailable. Connect USB and close the vendor reader application.")
    deadline = time.monotonic() + args.seconds if args.seconds > 0 else float("inf")
    try:
        reader.open(args.index)
        print("Reader running. Press Ctrl+C to stop.", flush=True)
        while time.monotonic() < deadline:
            tags = reader.batch()
            if not tags:
                continue
            print("EPCs:", ", ".join(tags), flush=True)
            if not args.read_only:
                try:
                    publish(args.url, tags)
                except (OSError, ValueError, RuntimeError):
                    print("Scan delivery failed. Keep tags on the reader while the connection recovers.", flush=True)
                    time.sleep(1)
    finally:
        reader.close()


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("Reader stopped.")
    except (OSError, RuntimeError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
