=== LiKoToN Debug Logs ===
Contributors: likoton
Tags: logs, debug, developer, tools, php, rest-api
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 1.1.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0-standalone.html

A lightweight debugging plugin that collects PHP, WordPress, REST API and login logs with filters, dark mode, infinite scroll and CSV export.

== Description ==

LiKoToN Debug Logs is a lightweight, modern debugging plugin for WordPress that automatically collects:

- PHP errors and warnings  
- WordPress errors (`wp_error_added`)  
- REST API requests (route, method, params)  
- User login events  
- Custom logs via `Likoton_Debug_Logs_Logger::log()`  

It includes a clean log viewer with:

- AJAX live filtering  
- Infinite scroll  
- Client-side sorting  
- Search, level, source and "last X logs" filters  
- Color-coded badges  
- Dark mode  
- CSV export (with UTC and local timestamps)  
- Automatic cleanup based on retention settings  

Perfect for developers, administrators and anyone who needs a clear view of what happens inside WordPress.

== Description (pl_PL) ==

LiKoToN Debug Logs to lekka i nowoczesna wtyczka debugująca dla WordPressa, która automatycznie zbiera:

- błędy i ostrzeżenia PHP  
- błędy WordPressa (`wp_error_added`)  
- wywołania REST API (ścieżka, metoda, parametry)  
- logowania użytkowników  
- własne logi przez `Likoton_Debug_Logs_Logger::log()`  

Wtyczka oferuje czytelny panel logów z:

- filtrowaniem AJAX  
- nieskończonym przewijaniem (infinite scroll)  
- sortowaniem po kolumnach  
- wyszukiwaniem, filtrem poziomu, źródła i ostatnich X logów  
- kolorowymi znacznikami poziomów i źródeł  
- trybem ciemnym  
- eksportem CSV (czas UTC + lokalny)  
- automatycznym czyszczeniem logów  

Idealna dla deweloperów i administratorów, którzy chcą mieć pełną kontrolę nad tym, co dzieje się w WordPressie.

---

## Features

### Log Collection
- PHP errors & warnings  
- WordPress errors (`wp_error_added`)  
- REST API requests (route, method, params)  
- User login events  
- Custom logs via `Likoton_Debug_Logs_Logger::log()`  

### Logs Viewer
- AJAX live filtering  
- Infinite scroll  
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
- Selectable log levels (only enabled levels are shown in the viewer)  

### Automatic Cleanup
- WP-Cron based  
- Configurable retention  
- Table optimization  

### Export
- Export all logs to CSV  
- Includes `created_at_utc` and `created_at_local`  
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
│   ├── class-likoton_debug_logs-admin.php  
│   ├── class-likoton_debug_logs-assets.php  
│   ├── class-likoton_debug_logs-installer.php  
│   └── class-likoton_debug_logs-logger.php  
├── languages/  
│   ├── likoton-debug-logs-en_US.po/mo  
│   ├── likoton-debug-logs-pl_PL.po/mo  
│   └── likoton-debug-logs.pot  
└── likoton-debug-logs.php  

---

## Developer API

### Custom logs
```php
Likoton_Debug_Logs_Logger::log( 'info', 'custom_source', 'Something happened', [ 'extra' => 'data' ] );

## Supported log levels (PSR-3 + extended PHP levels):

- debug,
- info,
- notice,
- warning,
- error,
- critical,
- alert,
- emergency,
- deprecated,
- user_deprecated,
- strict,
- parse,
- core_error,
- core_warning,
- compile_error,
- compile_warning,
- recoverable_error,
- user_error,
- user_warning,
- user_notice

## FAQ

**Does this slow down my site?**

No, logs are stored in a dedicated table and inserted efficiently.

**Can I export logs?**

Yes, via the Export logs (CSV) button.

**Can I restrict access?**

Yes, choose the required capability in Settings.

**Multisite support?**

Yes, each site has its own logs table.

## Changelog ##

1.1.0
- Persistent table sort order
- Per-level colors in the Log Levels settings control
- Configurable source badge colors via REST API

1.0.0
- Initial release
- PHP/WP/REST/login logging
- AJAX filters
- Infinite scroll
- Dark mode
- CSV export (UTC + local time)
- Automatic cleanup
- Capability control
- Selectable log levels