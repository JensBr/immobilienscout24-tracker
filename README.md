Immobilienscout24 Tracker
=============
Use this PHP script to track the immobilienscout24.de website for 
new entries to send them to you as E-Mail.

Prerequisites
-------
Create 'config.php' file using the config template 'config.sample.php' file.

Installation
-------
Set up cronjob or runwhen service to use 'tracker.php' to check for new entries every x minutes for example.

Console
-------
It's possible to run the 'tracker.php' PHP script in the conole.

```
php tracker.php -console
```

The output are the found entries array using the *print_r()* function.

License
-------
Copyright (c) 2014 Arkadius Jonczek