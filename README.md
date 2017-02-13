# fpm-status-passthrough

**A custom, hackable fpm-status page that you can place in front of your framework application.**

<center>

![Example](http://i.imgur.com/GUlCaAe.png)

</center>

### Why

The default fpm-status page can be quite useful for debugging problems, but it lacks certain info. Especially when you use a framework that handles all requests through one script (i.e. `index.php`) you may find some essential info missing:

    ************************
    pid:                  3488
    state:                Idle
    start time:           13/Feb/2017:12:33:46 +0100
    start since:          348
    requests:             54
    request duration:     36478
    request method:       GET
    request URI:          /index.php
    content length:       0
    user:                 -
    script:               /var/www/xxx/public/index.php
    last request cpu:     82.24
    last request memory:  2097152

### What

This is a script that you can place in front of your application. It logs process information to redis, and lets you pull up a list of fpm child processes. You can hack it to your hearts desire to log more specific information.

Example output of this script:

    Passthrough php-fpm process info
    
     [2]	Total process count
     [1]	Running process count
     [1]	Completed process count
    
    ----- Active processes -----
    
    PID:		10159
    Status:		👣 Running for 0.00025 sec
    URL:		http://dev.example.com/passthrough-status
    Method:		GET
    Client:		127.0.0.1 (Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.75 Safari/537.36)
    Last seen:	12:44:19
    
    ----- Completed processes -----
    
    PID:		11048
    Status:		✓ Idle. Completed in 0.04861 sec
    URL:		http://dev.example.com/sample-page
    Method:		GET
    Client:		185.71.206.77 (Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42..2311.135 Safari/537.36 Edge/12.10136)
    Last seen:	12:44:16

### Installation

#### Prerequisites

- PHP 7.1+
- Redis server
- Credis (via composer)
- Your app must use a single primary entry point, e.g. `index.php`, for all its routing

#### Instructions

First, install Credis as a dependency to your project if needed (`composer require colinmollenhour/credis`). Refer to the included `composer.json` for an example.
 
To install the passthrough script, rename your application's current `index.php` file to `index-passthrough.php`, and then put the new `index.php` file provided by this repository in place.

You may wish to modify your `index-passthrough.php` file to block direct requests as well, depending on your server config:

    if (!defined('INDEX_PHP_PASSTHROUGH')) {
        die("This script can only be executed through index.php");
    }
    
#### Viewing the process list

Currently you can view the process list by submitting a request to `/passthrough-status`.