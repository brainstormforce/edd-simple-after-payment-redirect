# EDD Redirect after payment #
**Contributors:** pratikchaskar
**Donate link:** https://www.paypal.me/BrainstormForce
**Tags:** edd, payment, redirect
**Requires at least:** 4.4
**Tested up to:** 6.6
**Stable tag:** 1.0.5
**License:** GPLv2 or later
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html

Redirect to a custom URL after successful purchase.

## Description ##

Redirect user to a custom URL after the order is successfully processed.

## Installation ##

This section describes how to install the plugin and get it working.

e.g.

1. Upload `plugin-name.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `<?php do_action('plugin_name_hook'); ?>` in your templates


## Changelog ##

## 1.0.5 ###
- Fix: Fatal error on front end when Easy digital downloads plugin is not active. 

### 1.0.4 ###
- Improvement: Added compatibility to WordPress 6.1

### 1.0.3 ###
- Security: Use escaping for displaying purchase details string.

### 1.0.2 ###
- Passing payment ID parameter to redirect URL after successful purchase.

### 1.0.1 ###
- Add support for Paypal and other off site payment gateways.

### 1.0.0 ###
- Initial Release
