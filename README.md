# GF Views Analytics

A WordPress plugin that provides an analytics dashboard for Gravity Forms views and entries.

## Description

GF Views Analytics adds a reporting page under **Tools > Views Analytics** that lets you visualise and analyse Gravity Forms view and entry data over time. Filter by form and date range, compare periods side by side, overlay entries against views, and export reports as PDF or CSV. The dashboard is also accessible via **Forms > Views Analytics** in the Gravity Forms admin menu and via the admin bar.

## Requirements

- WordPress 5.8+
- PHP 8.0+
- Gravity Forms (any current version)

## Installation

1. Upload the `gf-views-analytics` folder to `/wp-content/plugins/`
2. Activate the plugin through **Plugins > Installed Plugins**
3. Navigate to **Tools > Views Analytics**, **Forms > Views Analytics**, or use the admin bar shortcut

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

### PDF White Label

The PDF report header can be customised per user via **Screen Options**. Options include:

- **Report title** — replaces the default "GF Views Analytics Report" heading
- **Logo** — replaces the plugin icon and page heading; accepts a URL or choose from the media library. Recommended height: 40px.

White label settings are saved per user and take effect immediately when running a new report.

## Changelog

### 1.0.6
- Added **Views Analytics** link to the Gravity Forms admin menu under **Forms > Views Analytics**
- Added **Views Analytics** shortcut to the WordPress admin bar under the Gravity Forms node
- Fixed PDF export charts being cut off — charts now resize to fit the full A4 page width before the print dialog opens, and all chart canvases are constrained within their containers in the print stylesheet
- Added data labels to all charts: values appear above each point on the line chart (suppressed when the dataset has more than 60 points to avoid crowding) and right-aligned inside each bar on the Views by form and Entries by form bar charts

## Data Source

Views are read from the `wp_gf_form_view` table that Gravity Forms maintains natively, summing the `count` column for accurate view totals. Entries are read from `wp_gf_entry` where `status = 'active'`. All database queries are timezone-aware and offset against the site timezone configured under Settings > General, so date boundaries reflect local time rather than UTC.