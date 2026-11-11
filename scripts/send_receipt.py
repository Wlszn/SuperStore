#!/usr/bin/env python3
"""Emails a checkout receipt. Run by the website (app/services/ReceiptMailer.php),
which sends the receipt as JSON on stdin:

    {"lang": "en", "receipt": {"id": 12, "date": "2026-11-10 21:30", "total": 9.97,
     "points_earned": 9, "payment_method": "card",
     "items": [{"name": "...", "quantity": 2, "unit_price": 2.49, "line_total": 4.98}],
     "customer": {"name": "Will", "email": "...", "membership_number": "SS100003", "points": 21}}}

Uses the same Gmail settings as monitor.py, from the .env file in the project root:
    GMAIL_USER, GMAIL_APP_PASSWORD
Optional (for testing with another mail server): SMTP_HOST, SMTP_PORT, SMTP_STARTTLS=0

Exit code 0 = sent, 1 = the mail server refused it, 2 = Gmail is not configured.
"""
import html
import json
import os
import smtplib
import sys
from email.message import EmailMessage
from pathlib import Path

TEXT = {
    "en": {
        "subject": "Your SuperStore receipt #{id}",
        "hello": "Hi {name}, thank you for shopping at SuperStore!",
        "receipt": "Receipt #{id}",
        "date": "Date and time",
        "item": "Item", "qty": "Qty", "price": "Price", "amount": "Amount",
        "total": "Total",
        "payment": "Payment",
        "card": "Card", "cash": "Cash",
        "earned": "Points earned",
        "balance": "Your points balance",
        "member": "Membership card",
    },
    "fr": {
        "subject": "Votre reçu SuperStore no {id}",
        "hello": "Bonjour {name}, merci d'avoir magasiné chez SuperStore!",
        "receipt": "Reçu no {id}",
        "date": "Date et heure",
        "item": "Article", "qty": "Qté", "price": "Prix", "amount": "Montant",
        "total": "Total",
        "payment": "Paiement",
        "card": "Carte", "cash": "Comptant",
        "earned": "Points gagnés",
        "balance": "Votre solde de points",
        "member": "Carte de membre",
    },
}


def load_env():
    """Reads KEY=value lines from .env (no extra package needed)."""
    env_file = Path(__file__).resolve().parent.parent / ".env"
    if env_file.exists():
        for line in env_file.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if line and not line.startswith("#") and "=" in line:
                key, value = line.split("=", 1)
                os.environ.setdefault(key.strip(), value.strip())


def money(value):
    return f"${value:,.2f}"


def build_message(receipt, lang):
    t = TEXT.get(lang, TEXT["en"])
    customer = receipt["customer"]
    first_name = customer["name"].split()[0]

    lines = [t["hello"].format(name=first_name), "", t["receipt"].format(id=receipt["id"]),
             f"{t['date']}: {receipt['date']}", ""]
    for item in receipt["items"]:
        lines.append(f"{item['quantity']} x {item['name']} @ {money(item['unit_price'])} = {money(item['line_total'])}")
    lines += ["", f"{t['total']}: {money(receipt['total'])}",
              f"{t['payment']}: {t.get(receipt['payment_method'], receipt['payment_method'])}",
              f"{t['earned']}: {receipt['points_earned']}",
              f"{t['balance']}: {customer['points']}",
              f"{t['member']}: {customer['membership_number']}"]

    rows = "".join(
        "<tr>"
        f"<td style='padding:6px 0'>{html.escape(item['name'])}</td>"
        f"<td style='padding:6px 8px;text-align:center'>{item['quantity']}</td>"
        f"<td style='padding:6px 8px;text-align:right'>{money(item['unit_price'])}</td>"
        f"<td style='padding:6px 0;text-align:right'>{money(item['line_total'])}</td>"
        "</tr>"
        for item in receipt["items"]
    )
    body_html = f"""\
<div style="font-family:Arial,sans-serif;color:#253634;max-width:520px">
  <h2 style="color:#24766a;margin:0 0 4px">SuperStore</h2>
  <p>{html.escape(t['hello'].format(name=first_name))}</p>
  <p style="margin:0"><b>{t['receipt'].format(id=receipt['id'])}</b><br>{t['date']}: {receipt['date']}</p>
  <table style="width:100%;border-collapse:collapse;margin:14px 0;border-top:2px solid #253634">
    <tr style="color:#687874;font-size:13px">
      <th style="text-align:left;padding:6px 0">{t['item']}</th><th>{t['qty']}</th>
      <th style="text-align:right">{t['price']}</th><th style="text-align:right">{t['amount']}</th>
    </tr>
    {rows}
    <tr style="border-top:1px solid #dce5e1;font-weight:bold">
      <td colspan="3" style="padding:8px 0">{t['total']}</td>
      <td style="text-align:right">{money(receipt['total'])}</td>
    </tr>
  </table>
  <p style="margin:0">{t['payment']}: {t.get(receipt['payment_method'], receipt['payment_method'])}<br>
     {t['earned']}: <b>{receipt['points_earned']}</b><br>
     {t['balance']}: <b>{customer['points']}</b><br>
     {t['member']}: {customer['membership_number']}</p>
</div>"""

    message = EmailMessage()
    message["Subject"] = t["subject"].format(id=receipt["id"])
    message["To"] = customer["email"]
    message.set_content("\n".join(lines))
    message.add_alternative(body_html, subtype="html")
    return message


def main():
    load_env()
    data = json.load(sys.stdin)
    receipt = data["receipt"]

    user = os.getenv("GMAIL_USER")
    password = os.getenv("GMAIL_APP_PASSWORD")
    host = os.getenv("SMTP_HOST", "smtp.gmail.com")
    if host == "smtp.gmail.com" and not (user and password):
        print("GMAIL_USER / GMAIL_APP_PASSWORD are not set in .env")
        return 2

    message = build_message(receipt, data.get("lang", "en"))
    message["From"] = user or "superstore@localhost"

    try:
        with smtplib.SMTP(host, int(os.getenv("SMTP_PORT", "587")), timeout=20) as server:
            if os.getenv("SMTP_STARTTLS", "1") != "0":
                server.starttls()
            if user and password:
                server.login(user, password)
            server.send_message(message)
    except (OSError, smtplib.SMTPException) as error:
        print(f"Could not send the receipt: {error}")
        return 1

    print(f"Receipt #{receipt['id']} sent to {receipt['customer']['email']}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
