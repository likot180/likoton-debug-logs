=== LiKoToN Debug Logs ===
Contributors: likoton
Tags: logs, debug, developer, tools
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0-standalone.html

A lightweight debugging plugin that collects PHP, WordPress, REST API and login logs with filters, dark mode and CSV export.

== Description ==
# LiKoToN Debug Logs

A lightweight, modern debugging plugin for WordPress that automatically collects PHP errors, WordPress errors, REST API calls, and user login events.  
Includes a clean log viewer with filters, sorting, dark mode, CSV export, and automatic cleanup.

---

## Features

### Log Collection
- PHP errors & warnings  
- WordPress errors (`wp_error_added`)  
- REST API requests (route, method, params)  
- User login events  
- Custom logs via `LWD_Logger::log()`  

### Logs Viewer
- AJAX live filtering  
- Search, level, source, last X logs  
- Client-side sorting  
- Color-coded badges  
- Responsive table  

### Dark Mode
- WordPress 7-style toggle  
- Applies to all plugin pages  

### Settings
- Dark mode  
- Log retention (30m → 1 month)  
- Capability required to view logs  
- Auto-save via AJAX with toast notification  

### Automatic Cleanup
- WP-Cron based  
- Configurable retention  
- Table optimization  

### Export
- Export all logs to CSV  
- Secured with nonce & capability check  

---

## Installation

### From WordPress Dashboard
1. Upload the plugin ZIP  
2. Activate **LiKoToN Debug Logs**  
3. Go to **LiKoToN Debug Logs → Logs**

### Manual Installation
1. Upload folder `likoton-debug-logs` to `/wp-content/plugins/`  
2. Activate plugin  
3. Logs appear in the main admin menu  

---

## Directory Structure

likoton-debug-logs/
├── assets/
│   ├── admin.css
│   ├── admin-dark.css
│   └── admin.js
├── images/
│   ├── buycoffee.png
│   └── revolut.png
├── includes/
│   ├── class-ldl-admin.php
│   ├── class-ldl-assets.php
│   ├── class-ldl-installer.php
│   └── class-ldl-logger.php
├── languages/
│   ├── likoton-debug-logs-en_US.po/mo
│   ├── likoton-debug-logs-pl_PL.po/mo
│   └── likoton-debug-logs.pot
└── likoton-debug-logs.php


---

## Developer API

### Custom logs
`php
LWD_Logger::log( 'info', 'custom_source', 'Something happened', [ 'extra' => 'data' ] );`

Supported log levels
PSR-3 + extended PHP levels:

debug, info, notice, warning, error,
critical, alert, emergency,
deprecated, user_deprecated, strict, parse,
core_error, core_warning, compile_error, compile_warning,
recoverable_error, user_error, user_warning, user_notice

FAQ
Does this slow down my site?
No — logs are stored in a dedicated table and inserted efficiently.

Can I export logs?
Yes — via the Export logs (CSV) button.

Can I restrict access?
Yes — choose the required capability in Settings.

Multisite support?
Yes — each site has its own logs table.

== Changelog ==
1.0.0
Initial release

PHP/WP/REST/login logging

AJAX filters

Dark mode

CSV export

Automatic cleanup

Capability control
