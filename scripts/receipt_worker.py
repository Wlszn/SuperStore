import json
import os
import smtplib
import ssl
import time
import urllib.request
from datetime import datetime
from decimal import Decimal
from email.message import EmailMessage
from pathlib import Path


def configuration():
    values = dict(os.environ)
    for line in (Path(__file__).resolve().parent.parent / ".env").read_text().splitlines():
        if "=" in line and not line.lstrip().startswith("#"):
            key, value = line.split("=", 1)
            values[key.strip()] = value.strip()
    return values


settings = configuration()


def api(method, path, body=None):
    key = settings.get("SUPABASE_SECRET_KEY") or settings.get("SUPABASE_KEY", "")
    headers = {"apikey": key, "Content-Type": "application/json"}
    if key.count(".") == 2:
        headers["Authorization"] = "Bearer " + key
    request = urllib.request.Request(
        settings["SUPABASE_URL"].rstrip("/") + "/rest/v1/" + path,
        data=json.dumps(body).encode() if body is not None else None,
        headers=headers, method=method,
    )
    with urllib.request.urlopen(request, timeout=20) as response:
        content = response.read()
        return json.loads(content) if content else None


def deliver(job):
    receipt = api("GET", "purchases?id=eq." + str(job["purchase_id"]) +
                  "&select=*,purchase_items(name,unit_price,quantity)")[0]
    # Supabase stores UTC; show the time of the Pi (Montreal), e.g. 2026-11-18 17:39.
    purchased_at = datetime.fromisoformat(receipt["purchased_at"]).astimezone()
    lines = ["SuperStore receipt #" + str(receipt["id"]), purchased_at.strftime("%Y-%m-%d %H:%M"), ""]
    for item in receipt["purchase_items"]:
        amount = Decimal(str(item["unit_price"])) * item["quantity"]
        lines.append(f'{item["name"]} x {item["quantity"]} — CAD {amount:.2f}')
    points_redeemed = receipt.get("points_redeemed", 0)
    if points_redeemed:
        lines.append(f"Points discount ({points_redeemed} points): -CAD {Decimal(points_redeemed) / 100:.2f}")
    lines.extend(["", f'Total: CAD {Decimal(str(receipt["total"])):.2f}',
                  f'Points earned: {receipt["points_earned"]}', "Simulated payment approved."])
    message = EmailMessage()
    message["Subject"] = "SuperStore receipt #" + str(receipt["id"])
    message["From"] = settings["GMAIL_USER"]
    message["To"] = job["recipient"]
    message["Message-ID"] = "<superstore-receipt-" + str(receipt["id"]) + "@" + settings["GMAIL_USER"].split("@")[-1] + ">"
    message.set_content("\n".join(lines))
    with smtplib.SMTP("smtp.gmail.com", 587, timeout=20) as server:
        server.starttls(context=ssl.create_default_context())
        server.login(settings["GMAIL_USER"], settings["GMAIL_APP_PASSWORD"])
        server.send_message(message)


def run():
    if not settings.get("GMAIL_USER") or not settings.get("GMAIL_APP_PASSWORD"):
        raise SystemExit("Configure GMAIL_USER and GMAIL_APP_PASSWORD in .env.")
    while True:
        try:
            jobs = api("POST", "rpc/claim_receipt_email", {})
            for job in jobs:
                status = "sent"
                try:
                    deliver(job)
                except (OSError, smtplib.SMTPException, ValueError, KeyError):
                    status = "failed"
                api("PATCH", "receipt_emails?purchase_id=eq." + str(job["purchase_id"]),
                    {"status": status})
                print("Receipt", job["purchase_id"], status, flush=True)
        except (OSError, ValueError, KeyError):
            print("Receipt queue unavailable. Retrying.", flush=True)
        time.sleep(10)


if __name__ == "__main__":
    run()
