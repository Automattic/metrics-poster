⚠️ This repository is a work in progress and is not yet ready for production use. Use at your own risk. Contributions are welcome and encouraged! ⚠️

# Metric Poster
This script will help to automate a P2 post with weekly metrics for a given week. 

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
php ./script.php --week 12 --metrics 404s,errors,warnings
```