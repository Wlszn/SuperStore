## Willkerdy Elveus 6236388
import smtplib
import imaplib
import email
import os
import time
from email.mime.text import MIMEText
from dotenv import load_dotenv

load_dotenv("/../../superstore/.env")
USER = os.getenv("GMAIL_USER")
PASSWORD = os.getenv("GMAIL_APP_PASSWORD")

def send_email(subject, body, to_addr):
    msg = MIMEText(body)
    msg["Subject"] = subject
    msg["From"] = USER
    msg["To"] = to_addr
    try:
        with smtplib.SMTP("smtp.gmail.com", 587) as server:
            server.starttls()
            server.login(USER, PASSWORD)
            server.send_message(msg)
        print("Sent.")
    except Exception as e:
        print("Send failed:", e)

def check_inbox():
    try:
        mail = imaplib.IMAP4_SSL("imap.gmail.com")
        mail.login(USER, PASSWORD)
        mail.select("inbox")
        status, messages = mail.search(None, "UNSEEN")
        for num in messages[0].split():
            status, data = mail.fetch(num, "(RFC822)")
            msg = email.message_from_bytes(data[0][1])
            print(f"From: {msg['From']} | Subject: {msg['Subject']}")
        mail.logout()
    except Exception as e:
        print("Check failed:", e)

if __name__ == "__main__":
    send_email(
        subject="Test from Raspberry Pi",
        body="This is a test email sent from my Pi script.",
        to_addr= USER
    )
    while True:
        check_inbox()
        time.sleep(60)