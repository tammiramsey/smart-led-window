#!/usr/bin/env python3

# ------------------------------------------------------------
# File:   window.py
# Author: Dan King
#
# This script needs pigpiod to be running (http://abyz.co.uk/rpi/pigpio/)
# ------------------------------------------------------------

##### Configuration #####

# GPIO pin number
pin = 21

# Latitude/longitude for location
lat = 41.7103872
lon = -83.7091328

# Maximum brightness level
max_brightness = 65

# Config file, persistent configs
config_file = '/var/www/html/window.conf'

# Debug, show output
debug = True

##### End configuration #####

import requests, time, pigpio, json, datetime, os
from suntime import Sun, SunTimeException

# Load config file for cache/settings
with open(config_file, 'r') as f:
    settings = json.loads(f.read())

if not int(settings.get('auto', 0)):
    if debug:
        print('Auto brightness disabled, exiting...')
    exit()

# Constants
TRANSITION_DURATION = 5400  # 90 minutes in seconds
SUNRISE_OFFSET = 1200       # 20 minutes in seconds
SUNSET_OFFSET = 4500        # 75 minutes in seconds

# Current time
now = time.time()

# Initialize Sun object
sun = Sun(lat, lon)

# Get today's sunrise and sunset times
sunrise = sun.get_sunrise_time().timestamp()
sunset = sun.get_sunset_time().timestamp()

# Calculate transition periods
sunrise_start = sunrise - SUNRISE_OFFSET
sunrise_end = sunrise_start + TRANSITION_DURATION
sunset_start = sunset - SUNSET_OFFSET
sunset_end = sunset_start + TRANSITION_DURATION

if debug:
    print(f"sunrise_start: {sunrise_start}, sunrise_end: {sunrise_end}")
    print(f"sunset_start: {sunset_start}, sunset_end: {sunset_end}")
    print(f"now: {now}")

# Determine the current brightness and time of day
def calculate_brightness(now):
    if sunrise_start <= now <= sunrise_end:
        elapsed = now - sunrise_start
        percent = elapsed / TRANSITION_DURATION
        return max_brightness * percent, "Sunrise"
    elif sunrise_end < now < sunset_start:
        return max_brightness, "Day"
    elif sunset_start <= now <= sunset_end:
        elapsed = sunset_end - now
        percent = elapsed / TRANSITION_DURATION
        return max_brightness * percent, "Sunset"
    else:
        return 0, "Night"

brightness, time_of_day = calculate_brightness(now)

if debug:
    print(f"{time_of_day}, Brightness: {brightness * 2.55}")

# Adjust brightness gradually
def adjust_brightness(pi, pin, current_brightness, target_brightness):
    step = 1 if target_brightness > current_brightness else -1
    while current_brightness != target_brightness:
        pi.set_PWM_dutycycle(pin, current_brightness)
        change_amt = max(1, abs(target_brightness - current_brightness) // 10)
        current_brightness = max(0, min(255, current_brightness + step * change_amt))
        time.sleep(0.05)

# Initialize pigpio and set brightness
try:
    pi = pigpio.pi()
    current_brightness = pi.get_PWM_dutycycle(pin)
except pigpio.error as e:
    if debug:
        print(f"Pigpio error: {e}")
    os.system("/usr/bin/pigs p 21 255")
    current_brightness = 255

target_brightness = int(brightness * 2.55)
adjust_brightness(pi, pin, current_brightness, target_brightness)
