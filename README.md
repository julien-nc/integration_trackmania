# Trackmania integration into Nextcloud

Display statistics of your Trackmania (TM2020) account.

It is also possible to search for other accounts and compare yourself with them.
This account search (by name) uses the trackmania.io API so it will only find accounts that connected once to https://trackmania.io .

:warn: This is just a prototype. Contributions are very welcome.

Your Trackmania credentials are not stored.
After authenticating, a session token is stored and used.
This token is valid for an hour and is refreshed by this app.
If this token is not refreshed for 24 hours,
it is not possible to refresh it anymore and you have to provide your credentials again.
