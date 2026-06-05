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

### GitHub Updates

This plugin supports automatic updates via GitHub. Updates will appear in the standard WordPress **Plugins > Installed Plugins** update flow whenever a new release is published to the repository.

## Features

### Filters

- **Forms** — multi-select dropdown with search; choose one, many, or all forms
- **Date range** — primary date range with a date picker
- **Quick presets** — Last 7 days, Last 30 days, Last 90 days, Month to date, Year to date
- **Granularity** — group data by Day, Week, or Month
- **Entries overlay** — toggle entries data on or off
- **Compare range** — enable a second date range to compare periods side by side

### Dashboard

- **Stat cards** — Total Views, Unique Visitors, Total Entries, Conversion Rate; each card shows a delta badge when a comparison period is active
- **Line chart** — views over time with optional entries overlay and dashed comparison lines
- **Bar chart** — views broken down by form (shown when multiple forms are in the result set)
- **Data table** — full period-by-period breakdown including deltas and conversion rate

### Exports

- **PDF** — opens the browser print dialog with a print stylesheet optimised for A4 portrait; includes a report header with period, forms, and generated timestamp
- **CSV** — downloads directly in the browser with all visible columns including deltas

### URL State

Filters are written to the URL when you run a report, so you can bookmark, share, or refresh a specific report view and it will restore automatically.

## Data Source

Views are read from the `wp_gf_form_view` table that Gravity Forms maintains natively. Unique visitors are counted as distinct IP addresses within the selected period. Entries are read from `wp_gf_entry` where `status = 'active'`.

## Changelog

### 1.0.3
- Update the date format so uses default setting or allows to be set in Screen Options

### 1.0.2
- Remove the unique visitors as will always be one, and fix the table alignments

### 1.0.1
- Fix the views as was counting rows instead of getting total from database

### 1.0.0
- Initial release