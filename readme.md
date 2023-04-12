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

## Create a P2 post (WIP)

> NOTE: Zapier hook is currently disabled, outputs html in terminal to paste to Gutenberg editor.

```sh
# php
php ./script.php --id 123 --week 12 --metrics 404s,errors,warnings

# composer

## Gets all metrics (NR) with Title heading for P2.
composer nr-metrics

## Top errors (NR), no heading.
composer nr-errors -- --title false

## Swap out suffix as needed.
composer nr-404s
```

### Available metrics

**New Relic Metrics**
- 404s
- 500s
- errors
- warnings
- cwv