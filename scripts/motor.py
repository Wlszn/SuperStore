import RPi.GPIO as GPIO

GPIO.setmode(GPIO.BCM)
GPIO.setwarnings(false)
Motor1 = 22 #Enable pin
Motor2 = 27 #Input pin
Motor3 = 17 #Input pin
GPIO.setup(Motor1,GPIO.OUT)
GPIO.setup(Motor2,GPIO.OUT)
GPIO.setup(Motor3,GPIO.OUT)

def motor_on():
    GPIO.output(Motor1, GPIO.HIGH)
    GPIO.output(Motor2, GPIO.LOW)
    GPIO.output(Motor3, GPIO.HIGH)

def motor_off():
    GPIO.output(Motor1, GPIO.LOW)
    GPIO.output(Motor2, GPIO.LOW)
    GPIO.output(Motor3, GPIO.LOW)

def motor_status():
    if GPIO.input(Motor1) == GPIO.HIGH:
        return "On"
    else:
        return "Off"

    