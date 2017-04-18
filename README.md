# Monitoring scripts

## check_ad_replication.py

```
Usage: check_ad_replication.py [-A file] [-H host]

Options:
  --version             show program's version number and exit
  -h, --help            show this help message and exit
  -A AUTH, --authentication-file=AUTH
                        Get the credentials from a file
  -H HOST, --hostname=HOST
                        Hostname to perform WMI request
  -V, --verbose         Print status messages to stdout
```

Use wmi command to perform a check about AD replication. Python is used in order to check all values and get a proper exit.

## check_mssql.php

initial version from ![nagiosexchange](https://exchange.nagios.org/directory/Plugins/Databases/SQLServer/check_mssql/details)

## check_route