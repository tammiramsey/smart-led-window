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

# Check if config file exists and load it
if not os.path.exists(config_file):
    raise FileNotFoundError(f"Config file not found: {config_file}")
try:
    with open(config_file, 'r') as f:
        settings = json.loads(f.read())
except json.JSONDecodeError as e:
    raise ValueError(f"Invalid JSON in config file: {e}")

if debug:
    print("Loaded settings:", settings)

try:
    sun = Sun(lat, lon)
except SunTimeException as e:
    print(f"Error calculating sun times: {e}")

if debug:
    print("Debugging enabled")

if not int(settings.get('auto', 0)):
    if debug:
        print('Auto brightness disabled, exiting...')
    exit
