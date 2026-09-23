from gpiozero import LED
from time import sleep
import sys


blue_led = LED(17)
red_led = LED(18)


signal = sys.argv[1] if len(sys.argv) > 1 else ""


if signal == "success":
    blue_led.on()
    sleep(2)
    blue_led.off()


elif signal == "fail":
    red_led.on()
    sleep(2)
    red_led.off()
