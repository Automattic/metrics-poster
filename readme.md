# Metric Vato
This script will help to automate a P2 post with weekly metrics for the given week. 

## How to use it
Pull down this repo locally and set it up with composer.

### Prerequisites
- PHP 
- Composer

```shell
# setup steps...
git clone <this repo>
cd metric-poster
composer install
```

### Create a P2 post

```
php ./script.php --week 51 --clientid 3 --p2 https://coolp2.wordpress.com
```