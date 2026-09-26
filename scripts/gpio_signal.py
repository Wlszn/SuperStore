from gpiozero import LED, PWMOutputDevice
from time import sleep
import sys

red_led = LED(17)
blue_led = LED(18)
buzzer = PWMOutputDevice(22, frequency=4000)

signal = sys.argv[1] if len(sys.argv) > 1 else ""

if signal == "success":
    blue_led.on()
    sleep(2)
    blue_led.off()

elif signal == "fail":
    red_led.on()
    buzzer.value = 0.5
    sleep(2)
    red_led.off()
    buzzer.value = 0
