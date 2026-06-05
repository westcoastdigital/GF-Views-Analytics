# GF Views Analytics

A WordPress plugin that provides an analytics dashboard for Gravity Forms views and entries.

## Description

GF Views Analytics adds a reporting page under **Tools > Views Analytics** that lets you visualise and analyse Gravity Forms view and entry data over time. Filter by form and date range, compare periods side by side, overlay entries against views, and export reports as PDF or CSV.

## Requirements

- WordPress 5.8+
- PHP 8.0+
- Gravity Forms (any current version)

## Installation

1. Upload the `gf-views-analytics` folder to `/wp-content/plugins/`
2. Activate the plugin through **Plugins > Installed Plugins**
3. Navigate to **Tools > Views Analytics**

## Features

### Filters

- **Forms** — multi-select dropdown with search; choose one, many, or all forms
- **Date range** — primary date range with a date picker
- **Quick presets** — Last 7 days, Last 30 days, Last 90 days, Month to date, Year to date
- **Granularity** — group data by Day, Week, or Month; automatically switches to hourly when a single day is selected
- **Entries overlay** — toggle entries data on or off
- **Compare range** — enable a second date range to compare periods side by side

### Dashboard

- **Stat cards** — Total Views, Total Entries, Conversion Rate; each card shows a delta badge when a comparison period is active
- **Line chart** — views over time with optional entries overlay and dashed comparison lines; switches to hourly breakdown for single-day reports
- **Views by form bar chart** — total views broken down per form (shown when multiple forms are in the result set)
- **Entries by form bar chart** — total entries broken down per form (shown when multiple forms are in the result set and entries overlay is on)
- **Data table** — full period-by-period breakdown including deltas and conversion rate

### Exports

- **PDF** — opens the browser print dialog with a print stylesheet optimised for A4 portrait; includes a report header with period, forms, and generated timestamp
- **CSV** — downloads directly in the browser with all visible columns including deltas

### URL State

Filters are written to the URL when you run a report, so you can bookmark, share, or refresh a specific report view and it will restore automatically.

### Date Format

The date format used throughout the dashboard and exports can be set per user via **Screen Options** (top right of the page). Available options are:

- DD/MM/YYYY
- MM/DD/YYYY
- YYYY-MM-DD
- DD Mon YYYY
- DD Month YYYY
- WordPress default (inherits the format set under Settings > General)

Each user's preference is saved independently so it does not affect other users.

## Data Source

Views are read from the `wp_gf_form_view` table that Gravity Forms maintains natively, summing the `count` column for accurate view totals. Entries are read from `wp_gf_entry` where `status = 'active'`. All database queries are timezone-aware and offset against the site timezone configured under Settings > General, so date boundaries reflect local time rather than UTC.

## Changelog

### 1.0.4
- Added Entries by form bar chart alongside the existing Views by form chart
- Fixed missing `by_form` data assignment that prevented both breakdown charts from rendering

### 1.0.3
- Fixed date format display in chart and table — tokens now replaced in a single pass to prevent partial replacements corrupting month names
- Added date format preference via Screen Options, saved per user via AJAX
- Date pickers now reflect the chosen display format via Flatpickr's alt input
- Dates in print header now use the chosen display format

### 1.0.2
- Removed unique visitors stat (always returned 1 due to aggregated view rows)
- Fixed table column alignment — period column left-aligned, all data columns right-aligned
- Added hourly granularity, triggered automatically when a single day is selected
- Added timezone offset via `CONVERT_TZ` so period grouping reflects site local time

### 1.0.1
- Fixed view counts — queries now use `SUM(count)` instead of `COUNT(*)` to read the actual view totals stored in `wp_gf_form_view`
- Fixed timezone handling — date range boundaries now convert from site local time to UTC before querying

### 1.0.0
- Initial release