#!/bin/bash
# Make sure this file has executable permissions, run `chmod +x run-cron.sh`

# This block of code runs the notification processor every minute
while [ true ]
    do
        echo "Running scheduled notifications processor..."
        php bin/console app:process-notifications
        sleep 120
    done