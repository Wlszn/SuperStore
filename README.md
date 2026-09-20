# SuperStore

A smart-store simulation built for the **Internet of Things** course (420-521-VA, Vanier College) final project.

## Overview

SuperStore simulates a physical retail store where sensors, actuators, and single-board computers connect the customer experience to store management. The system is built in phases across the semester and has two main sides:

- **Customer side** — a self-checkout station where customers scan and pay for items on their own.
- **Management side** — a dashboard for managing inventory, sales, equipment maintenance, and reports.

Each phase adds functionality on top of the last, moving from data capture, to data communication, to data presentation.

## Tech Stack

| Layer | Choice |
|---|---|
| Backend | PHP (MVC) |
| Database | MySQL, hosted on Railway |
| Hardware | Raspberry Pi 5 (per team member) |
| Hardware control | Python |
| Front-end | HTML/CSS/JS |

The web app and GPIO scripts run locally on each Raspberry Pi, while the MySQL database is centralized on Railway so the whole team works against the same shared data.

## Phase 1 — Customer Registration

Scope of the current phase:

- Design the customer database (name, address, phone, email, etc.)
- Build a page to add new customers
- On successful insert:
  - Store the data in the database
  - Show a confirmation notification
  - Turn on a **blue LED**
- On failure:
  - Trigger a **red LED** and a **buzzer**

**Hardware used:** LED (blue + red), resistors, wires, buzzer, breadboard, Raspberry Pi 5.

## Project Structure

```
SuperStore/
├── config/
│   └── db.php              # Railway MySQL connection (via .env)
├── controllers/
│   └── CustomerController.php
├── models/
│   └── Customer.php
├── views/
│   └── add_customer.php
├── scripts/
│   └── gpio_signal.py      # Drives blue/red LED + buzzer via gpiozero
├── .env.example
├── .gitignore
└── README.md
```

## Setup

1. Clone the repo and copy `.env.example` to `.env`, filling in the Railway MySQL credentials.
2. Install PHP dependencies (if any) and ensure `python3-gpiozero` is installed on the Pi.
3. Run the app locally via Apache/PHP's built-in server on the Pi.
4. Wire the LEDs and buzzer per the pinout in `scripts/gpio_signal.py`.

## Team

Group of 4 — each member has a Raspberry Pi 5.

## Project Phases

| Phase | Focus |
|---|---|
| Phase 1 | Full functionality — customer registration |
