=== LiKoToN Debug Logs ===
Contributors: likoton
Tags: logs, debug, developer, tools, php
Requires at least: 6.0
Tested up to: 7.2
Stable tag: 2.1.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0-standalone.html

A lightweight debugging plugin that collects PHP, WordPress, REST API and login logs with filters, date range, bulk actions, an ignore list, dark mode and CSV export.

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
- Search, level, source and time filters  
- Date range filter with a built-in date picker  
- Bulk actions: delete, download (CSV) and copy selected logs  
- Persistent table sort order  
- Ignore list for recurring log entries  
- Color-coded badges  
- Dark mode  
- CSV export (with UTC and local timestamps)  
- Automatic cleanup based on retention settings  

Perfect for developers, administrators and anyone who needs a clear view of what happens inside WordPress.

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
- Search, level, source, time 
- Client-side sorting (remembered between visits)  
- Date range filter with a built-in date picker  
- Bulk actions: delete, download (CSV) and copy selected logs  
- Color-coded badges  
- Responsive table  

### Ignore List
- Select recurring log entries and add them to the ignore list  
- Matching logs are no longer written to the database; only their occurrences are counted  
- Dedicated "Ignored" page with search, level/source filters, sorting and bulk removal  
- Enabled in Settings (the "Ignored" submenu appears only when the option is on)  

### Duplicate Throttling
- Skip writing a new row when the same level, source and message was logged within a chosen interval  

### Dark Mode
- WordPress 7-style toggle  
- Applies to all plugin pages  

### Settings
- Dark mode  
- Log retention (30m → 1 month)  
- Capability required to view logs  
- Auto-save via AJAX with toast notification  
- Selectable log levels (only enabled levels are shown in the viewer)  
- Per-level colors in the Log Levels control  
- Interface language selection (English, Polski, Deutsch, Français, Español, Čeština, Slovenčina, Português), independent of the WordPress locale  
- Duplicate log interval  
- Ignore selected records toggle  

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

## Developer API

### Custom logs
```php
Likoton_Debug_Logs_Logger::log( 'info', 'custom_source', 'Something happened', [ 'extra' => 'data' ] );
```

### Source badge colors (REST API)
Namespace: `likoton-debug-logs/v1`

- `GET /source-colors` - all source colors
- `PUT /source-colors` - set colors, body: `{ "my_plugin": "#aabbcc" }`
- `DELETE /source-colors/<source>` - reset a source to its default

Requires the capability selected in Settings.

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

**What does the ignore list do?**

Logs identical to an ignored record (same level, source and message) are not stored again; the plugin only counts how often they occurred. You can remove a record from the list at any time.

**Can I change the plugin language?**

Yes, choose a language in Settings. It affects only this plugin's texts.

**Multisite support?**

Yes, each site has its own logs table.

== Screenshots ==

1. Logs tab
2. Logs tab in dark mode
3. Settings in dark mode

## Changelog ##

2.1.0
- Added an ignore list: selected recurring log entries are no longer saved to the database
- Added a plugin interface language selector (8 languages)
- Compatibility with PHP 8.4+ (no use of the deprecated E_STRICT constant) and WordPress 7.2
- Hardened SQL escaping in the installer (Plugin Check warnings)

2.0.1
- Improved the appearance of the table on small screens

2.0.0
- Added support for deleting, downloading, and copying selected logs via bulk operations

1.3.0
- Add option to skip writing a new log row when the same level, source and message was already logged

1.2.0
- A date range selection filter has been added

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