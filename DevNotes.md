# GF Views Analytics — Developer Notes

Internal reference for maintainers. Covers file structure, every function, where it lives, what it does, and what to watch out for when updating.

---

## File Structure

```
gf-views-analytics/
├── gf-views-analytics.php   # Main plugin file — all PHP hooks, AJAX handlers, DB queries
├── github-updater.php        # SimpliWeb_GitHub_Updater class (shared, do not edit here)
├── assets/
│   ├── admin.js              # All front-end logic — state, charts, filters, exports
│   └── admin.css             # All styles including print/PDF stylesheet
└── templates/
    └── page.php              # Admin page HTML shell — no logic, only markup
```

---

## gf-views-analytics.php

### Constants / Bootstrap (lines 17–33)

```php
define( 'GFVA_VERSION', '1.0.5' );
define( 'GFVA_PATH', plugin_dir_path( __FILE__ ) );
define( 'GFVA_URL',  plugin_dir_url( __FILE__ ) );
```

`GFVA_VERSION` is used as the cache-busting version string on enqueued assets. **Bump this on every release** — forgetting means browsers serve stale JS/CSS.

The GitHub updater is instantiated here pointing at `westcoastdigital/GF-Views-Analytics`. The `GITHUB_ACCESS_TOKEN` constant is optional; define it in `wp-config.php` if the repo is private.

---

### `gfva_check_dependencies()` — `admin_init`

Checks that `GFForms` class exists. If not, outputs an admin notice. Does not deactivate the plugin — all other hooks guard themselves with the same class check where needed.

---

### `gfva_register_menu()` — `admin_menu`

Registers the page as a submenu of `tools.php` (i.e. **Tools > Views Analytics**). Capability required: `manage_options`. Callback is `gfva_render_page`.

If you want to move the menu location (e.g. under a custom top-level menu) change the first argument to `add_submenu_page`. If you move it, also update the screen ID check inside `gfva_screen_options` — it is currently hardcoded to `tools_page_gf-views-analytics`.

---

### `gfva_enqueue_assets()` — `admin_enqueue_scripts`

Only runs on pages where the hook string contains `gf-views-analytics`. Enqueues:

| Handle | Source | Notes |
|---|---|---|
| `chartjs` | jsDelivr CDN | Chart.js 4.4.3 UMD build |
| `flatpickr` (style + script) | jsDelivr CDN | Date picker |
| `gfva-admin` (style) | `assets/admin.css` | |
| `gfva-admin` (script) | `assets/admin.js` | Depends on jquery, chartjs, flatpickr |

Also calls `wp_enqueue_media()` so the WP media library picker works in Screen Options.

`wp_localize_script` passes the `GFVA` JS object with:
- `ajax_url` — standard WP AJAX endpoint
- `nonce` — action `gfva_nonce`, used for data requests
- `date_format` — current user's saved format (PHP format string)
- `date_format_nonce` — separate nonce for date format and white label saves
- `report_title` / `report_logo` — current user's white label settings

Two separate nonces are in use. `gfva_nonce` covers data fetching. `gfva_date_format_nonce` covers Screen Options saves (date format + white label). If you add new AJAX actions, decide which nonce group they belong to or add a third.

---

### `gfva_render_page()`

Capability check (either `gform_full_access` or `manage_options`), then includes `templates/page.php`. No output of its own.

---

### `gfva_screen_options()` — `screen_settings` filter

Appends two fieldsets to the Screen Options panel (the slide-down above the page). Only runs when `$screen->id === 'tools_page_gf-views-analytics'`. **If you move the menu, update this ID.**

Fieldset 1 — Date Format: renders radio buttons for `d/m/Y`, `m/d/Y`, `Y-m-d`, `d M Y`, `d F Y`, plus the site's WordPress default if it differs. Saved via AJAX (`gfva_save_date_format`), stored as user meta `gfva_date_format`.

Fieldset 2 — PDF White Label: text input for report title, URL input + media picker button for logo. Saved via AJAX (`gfva_save_white_label`), stored as user meta `gfva_report_title` and `gfva_report_logo`.

All settings are per-user (user meta), not site-wide.

---

### `gfva_ajax_save_date_format()` — `wp_ajax_gfva_save_date_format`

Validates the submitted format string against an allowlist, then saves to `gfva_date_format` user meta. Returns `wp_send_json_success` with the saved format so the JS can update `GFVA.date_format` in place and re-init the date pickers without a page reload.

---

### `gfva_ajax_save_white_label()` — `wp_ajax_gfva_save_white_label`

Sanitises title (text) and logo (URL), saves to user meta. Uses the same `gfva_date_format_nonce` as the date format save — not a separate nonce.

---

### `gfva_get_date_format()` — helper

Returns the current user's saved date format or falls back to the site's `date_format` option. Called in `gfva_enqueue_assets` and `gfva_screen_options`.

---

### `gfva_ajax_get_forms()` — `wp_ajax_gfva_get_forms`

Returns all Gravity Forms via `GFAPI::get_forms()` as `[{ id, title }]`. Used to populate the forms multi-select dropdown on page load. No form filtering at this point — all forms are returned and filtering happens client-side in the dropdown.

---

### `gfva_ajax_get_data()` — `wp_ajax_gfva_get_data`

Main data endpoint. Accepts POST fields:

| Field | Type | Notes |
|---|---|---|
| `form_ids` | int[] | Empty array = all forms |
| `date_from` | string | Y-m-d |
| `date_to` | string | Y-m-d |
| `compare_from` | string | Y-m-d, optional |
| `compare_to` | string | Y-m-d, optional |
| `granularity` | string | `day`, `week`, `month` (auto-switches to `hour` if from === to) |
| `include_entries` | int | 1 or 0 |

Auto-upgrades granularity to `hour` when `date_from === date_to`. Calls `gfva_fetch_period` and `gfva_fetch_summary` for both primary and compare ranges. Returns:

```json
{
  "primary":         { "views": {}, "entries": {}, "by_form": {}, "by_form_entries": {} },
  "compare":         null | { same shape as primary },
  "summary":         { "total_views": 0, "total_entries": 0, "conversion": 0.0 },
  "compare_summary": null | { same shape as summary },
  "granularity":     "day"
}
```

---

### `gfva_local_to_utc()` — helper

Converts a `Y-m-d` date string to a UTC datetime string, accounting for the site timezone (`wp_timezone()`). Pass `$end_of_day = true` for the upper bound to get `23:59:59` in local time converted to UTC. Used to build `BETWEEN` bounds for all DB queries.

---

### `gfva_get_tz_offset()` — helper

Returns the site timezone offset as a `+HH:MM` / `-HH:MM` string suitable for MySQL's `CONVERT_TZ()`. Used in all `DATE_FORMAT` queries so period grouping reflects local time rather than the UTC timestamps stored in the DB.

---

### `gfva_fetch_period()` — DB query helper

Runs up to four queries for a single date range:

1. **Views time-series** — `wp_gf_form_view`, grouped by period using `DATE_FORMAT(CONVERT_TZ(...))`. Returns `{ "2025-01-01": 42, ... }`.
2. **Views per-form breakdown** — same table, grouped by `form_id, period`. Returns `{ formId: { "2025-01-01": 12, ... } }`.
3. **Entries per-form breakdown** — `wp_gf_entry` where `status = 'active'`, grouped by `form_id, period`. Only run when `$include_entries = true`.
4. **Entries time-series** — same table, grouped by period only. Only run when `$include_entries = true`.

All queries use `$wpdb->prepare()`. Form ID filtering appends `AND form_id IN (...)` dynamically.

**To add a new data series** (e.g. unique views), add a query here and include the result in the returned array. The AJAX handler passes the array through unchanged.

---

### `gfva_fetch_summary()` — DB query helper

Runs aggregate (non-grouped) totals for the stat cards — total views, total entries, and computed conversion rate. Does not group by period or form. Called once per range (primary and compare).

---

### `gfva_add_menu_to_entries_menu()` — `admin_footer`

Only fires on GF pages where `$_GET['page']` is `gf_entries` or `gf_edit_forms`. Injects a small jQuery snippet that appends a "View Analytics" link to the `.subsubsub` navigation list GF renders on those pages. This is a JS DOM injection rather than a PHP menu registration because GF's subsubsub is built dynamically and not a standard WP menu hook.

---

### `gfva_admin_bar_menu()` — `admin_bar_menu` (priority 100)

Adds a top-level "Form Analytics" node to the WP admin bar pointing to the plugin page. Priority 100 places it after most other nodes. If you want it nested under an existing admin bar node (e.g. a GF parent node if one exists), use `'parent' => 'gravityforms-forms'` in the `add_node` args.

---

## assets/admin.js

All JS runs inside an IIFE `(function ($) { ... }(jQuery))`. No globals are exposed.

### State object (top of file)

```js
const state = {
    forms,           // Full form list from AJAX
    selectedForms,   // Array of selected form IDs (empty = all)
    dateFrom,
    dateTo,
    compareFrom,
    compareTo,
    granularity,     // 'day' | 'week' | 'month'
    includeEntries,
    compareEnabled,
    lastData,        // Last successful AJAX response, used for re-renders
    activePreset,
};
```

Chart instances are kept as module-level vars (`mainChart`, `breakdownChart`, `entriesBreakdownChart`) so they can be destroyed before re-rendering.

---

### `$(document).ready`

Calls all `init*` functions in order, binds button click handlers, and calls `setPreset('30d')` as the default if no `date_from` URL param is present.

---

### `initScreenOptions()`

Binds three delegated event handlers to `document`:

- `.gfva-date-format-radio` `change` — POSTs new format to `gfva_save_date_format`, updates `GFVA.date_format`, destroys and re-inits all four flatpickr instances, and re-renders results if they are currently visible.
- `#gfva-wl-save` `click` — POSTs title and logo to `gfva_save_white_label`, updates `GFVA.report_title` and `GFVA.report_logo`, shows a "Saved." flash, and re-renders the print header if results are visible.
- `#gfva-logo-pick` `click` — Opens the WP media library frame (`wp.media`). Stores the frame on `window._gfvaMediaFrame` so it is only created once. On selection, puts the attachment URL into `#gfva_report_logo` and updates `#gfva-logo-preview`.
- `#gfva_report_logo` `input` — Live-updates the logo preview img as the user types a URL manually.

---

### `initDatePickers()`

Creates four flatpickr instances (`#gfva-date-from`, `#gfva-date-to`, `#gfva-compare-from`, `#gfva-compare-to`). Each uses `dateFormat: 'Y-m-d'` internally (so state values are always ISO) with `altInput: true` and an `altFormat` derived from `GFVA.date_format` via `phpFormatToFlatpickr()` for display.

Called on boot and again after a date format change.

---

### `phpFormatToFlatpickr()` (defined twice — bug, both are identical)

Maps PHP date format characters to flatpickr format characters. Only covers the characters used in the plugin's format options. There are two identical copies of this function in the file (inside the IIFE) — safe to deduplicate on cleanup.

---

### `toYmd(dateObj)`

Converts a JS `Date` object to a `YYYY-MM-DD` string for use in state and AJAX payloads.

---

### `initGranularity()`

Delegates a `click` handler to `#gfva-granularity`. Toggles `.active` on the clicked `.gfva-seg-btn` and writes `state.granularity`.

---

### `initToggles()`

- `#gfva-compare-toggle` — Writes `state.compareEnabled`, toggles opacity and `pointer-events` on `#gfva-compare-dates`.
- `#gfva-entries-toggle` — Writes `state.includeEntries`.

---

### `initPresets()`

Binds `click` on `.gfva-preset`. Calls `setPreset()` with the button's `data-preset` value.

### `setPreset(preset)`

Computes `from` and `to` dates for the five presets (`7d`, `30d`, `90d`, `mtd`, `ytd`), sets both flatpickr instances, and updates `state.dateFrom`, `state.dateTo`, `state.activePreset`.

### `daysAgo(n)`

Returns a `Date` object for `n` days before today.

### `setFlatpickr(selector, dateStr)`

Sets a flatpickr date programmatically. Falls back to setting the raw input value if the flatpickr instance isn't found.

---

### `initFormSelect()`

- `#gfva-forms-trigger` `click` — Toggles `.open` on `#gfva-forms-dropdown`, stops propagation.
- `document` `click` — Closes the dropdown when clicking outside.
- `#gfva-forms-search` `input` — Filters visible options by text match.

### `loadForms()`

POSTs to `gfva_get_forms`, populates `state.forms`, calls `renderFormOptions()`. After populating, checks if `state.selectedForms` was pre-loaded from URL params and re-checks the relevant checkboxes. If `state._autoRun` is set (from URL params), triggers `runReport()` once.

### `renderFormOptions(forms)`

Empties `#gfva-forms-options` and re-renders "All forms" plus one checkbox per form. Binds:
- `#gfva-form-all` `change` — Unchecks all individual forms, resets `state.selectedForms`.
- `.gfva-form-cb` `change` — Maintains `state.selectedForms` array, unchecks "All" when any form is checked.

### `updateFormLabel()`

Updates the trigger button label: "All forms", the form title (if one selected), or "N forms selected".

---

### `readUrlParams()`

Reads `date_from`, `date_to`, `granularity`, `entries`, `compare_from`, `compare_to`, `forms[]` from the URL query string on load. Populates state and UI controls. Sets `state._autoRun = true` if date params are present so the report fires automatically after forms load.

---

### `runReport()`

1. Validates `state.dateFrom` and `state.dateTo`.
2. Writes current filter state to the URL via `history.pushState` (enables bookmark/share).
3. Shows loading spinner, hides results, disables Run button.
4. POSTs to `gfva_get_data` with all filter params.
5. On success: stores response in `state.lastData`, calls `renderResults()`, shows results, enables Export PDF.
6. On error: shows the empty state with an alert.

---

### `renderResults(data)`

Orchestrator — calls `renderStats`, `renderMainChart`, `renderBreakdownChart`, `renderTable`, `renderPrintHeader` in that order.

---

### `renderStats(data)`

Populates the three stat cards from `data.summary`. Shows/hides the Entries and Conversion cards based on `state.includeEntries`. If `data.compare_summary` exists, calls `renderDelta` for each card.

### `renderDelta(selector, current, previous, isPct)`

Computes the percentage change between two values, formats as `↑ 12.3%` (or `+1.5pp` for percentages), and applies `.up`, `.down`, or `.flat` class to the element.

---

### `gfvaDataLabels` — inline Chart.js plugin

Defined before `renderMainChart`. Registered directly on chart instances (not globally via `Chart.register`). Implements `afterDatasetsDraw`:

- **Line charts** — draws each data value above its point, centred, in the dataset's border colour.
- **Bar charts (horizontal `indexAxis: 'y'`)** — draws each value right-aligned inside the bar in white. If the bar is too short to fit, falls back to drawing outside the bar in dark text.

To change label font size or colour, edit this plugin. It is the single source of truth for data labels across all three charts.

---

### `renderMainChart(data)`

Destroys the existing `mainChart` instance if present. Builds up to four datasets depending on what data is available and what toggles are active:

| Dataset | Condition | Colour |
|---|---|---|
| Views | Always | `rgba(91,79,207,1)` — purple |
| Views (compare) | Compare enabled | `rgba(232,71,76,1)` — red |
| Entries | `state.includeEntries` | `rgba(47,184,160,1)` — teal |
| Entries (compare) | Both compare and entries | `rgba(245,166,35,0.9)` — amber |

Point dots are suppressed (`pointRadius: 0`) when the dataset has more than 60 periods. Data labels (`gfvaDataLabels` plugin) are also suppressed at that threshold.

Builds the legend by iterating `datasets` and reading `ds.borderColor` — this must stay in sync with the actual dataset colours. Do not revert to the old positional `colours` array.

Chart title is set dynamically from `data.granularity`.

---

### `renderBreakdownChart(data)`

Handles both bar charts. If there is only one form in the result set, both cards are hidden.

**Views by form** — sums each form's view data across all periods, sorts descending, renders as a horizontal bar chart (`indexAxis: 'y'`). Uses the `gfvaDataLabels` plugin. Registered on `breakdownChart`.

**Entries by form** — same pattern using `data.primary.by_form_entries`. Only shown if `state.includeEntries` is true and there is entry data. Registered on `entriesBreakdownChart`.

Both charts share a `barOpts` config object defined locally in this function. If you need to change bar chart options (tick size, grid lines, tooltip style), edit `barOpts` and it affects both.

The colour palette for bars:

```js
const palette = [
    'rgba(91,79,207,0.8)',  'rgba(47,184,160,0.8)', 'rgba(232,71,76,0.8)',
    'rgba(245,166,35,0.8)', 'rgba(99,179,237,0.8)', 'rgba(184,107,232,0.8)',
    'rgba(237,137,54,0.8)', 'rgba(72,187,120,0.8)',
];
```

Colours cycle if there are more than 8 forms.

---

### `renderTable(data)`

Builds the detailed data table dynamically. Column set varies based on whether compare is active and whether entries are included. Uses `mergeKeys()` to union the period keys from all data objects so every period appears as a row even if one dataset has a gap.

Delta cells are coloured green (`.up`) or red (`.down`) via `deltaCell()`.

---

### `renderPrintHeader()`

Removes any existing `.gfva-print-header` element and prepends a new one to `#gfva-results`. Contains the logo or title (from white label settings), report title, period, forms, and generation timestamp. Only visible in print via CSS `display: none` / `display: block !important`.

---

### `exportPdf()`

Resizes all three chart instances to `794px` wide (approximate A4 content width at screen DPI) before calling `window.print()`. This forces Chart.js to re-render at the correct dimensions so nothing gets clipped in the print dialog. After a 1s delay (to allow the print dialog to open), charts are restored to their natural size via `chart.resize()` with no arguments.

The 150ms `setTimeout` before `window.print()` gives Chart.js time to complete the resize render before the browser snapshots the page.

---

### `exportCsv()`

Reads `state.lastData` directly (no extra request). Builds a CSV string with the same column logic as `renderTable`. Triggers a browser download via a temporary `<a>` with a `blob:` URL. Filename includes the primary date range.

---

### `mergeKeys(objects)`

Takes an array of plain objects and returns a sorted array of all unique keys across them. Used to union time periods from views, entries, and compare datasets.

### `formatNumber(n)`

`Number.toLocaleString()` wrapper. Returns `—` for null/undefined.

### `formatPeriod(period, isHourly)`

Formats a period key string for display. Hourly periods strip the date prefix and return just the time. Date periods are formatted according to `GFVA.date_format` using a manual token-replacement approach (no `Date` parsing, to avoid timezone shifting on date-only strings).

### `escHtml(str)`

Basic HTML entity encoding for `&`, `<`, `>`, `"`. Used before inserting any data-derived strings into jQuery HTML methods.

---

## assets/admin.css

### CSS custom properties (`:root`)

All colours and the shadow scale are defined here. The chart colour variables (`--chart-views`, `--chart-compare`, etc.) are defined here but not actually consumed by Chart.js — Chart.js takes its colours as JS strings in `admin.js`. The CSS vars are for any future native-CSS usage.

---

### Print stylesheet (`@media print`)

There are **two `@media print` blocks** in the file — one around line 622 and one around line 743. This is a legacy duplication. Both are active and override each other on overlapping properties. The second block wins for anything declared in both. On cleanup, merge into one block.

Key print rules to know about:

- `.gfva-chart-card__body canvas` — `max-width: 100%; width: 100%` — prevents Chart.js canvas overflow. Must stay, as Chart.js stamps pixel dimensions on the element at render time.
- `.gfva-chart-card__body` — `overflow: hidden; box-sizing: border-box` — clips any canvas overhang at the container level.
- `#adminmenumain`, `#wpadminbar`, `#wpfooter`, etc. — hides all WP chrome.
- `.gfva-print-header` — `display: block !important` — reveals the report header that is hidden in the normal view.
- `.gfva-table` — `font-size: 10px; table-layout: fixed` — shrinks the table to fit A4 width.

---

## templates/page.php

Pure HTML. No PHP logic beyond the ABSPATH guard. All IDs referenced here are the hook points for JS.

Key IDs:

| ID | Purpose |
|---|---|
| `#gfva-app` | Root wrapper |
| `#gfva-filters` | Filter card |
| `#gfva-forms-trigger` / `#gfva-forms-dropdown` | Multi-select form picker |
| `#gfva-forms-options` | Options container, populated by JS |
| `#gfva-date-from` / `#gfva-date-to` | Primary date inputs (flatpickr targets) |
| `#gfva-compare-toggle` | Enables compare range |
| `#gfva-compare-dates` | Compare date input wrapper (toggled opacity) |
| `#gfva-compare-from` / `#gfva-compare-to` | Compare date inputs |
| `#gfva-granularity` | Segmented control, `data-value` on each button |
| `#gfva-entries-toggle` | Entries overlay toggle |
| `#gfva-run` | Run Report button |
| `#gfva-export-pdf` | Export PDF button (disabled until data loads) |
| `#gfva-loading` | Spinner (hidden by default) |
| `#gfva-empty` | Empty state (visible by default) |
| `#gfva-results` | Results wrapper (hidden until data loads) |
| `#gfva-stats` | Stat cards container |
| `#stat-total-views` / `#stat-total-views-cmp` | Views stat value + delta |
| `#stat-card-entries` / `#stat-total-entries` / `#stat-total-entries-cmp` | Entries stat card |
| `#stat-card-conversion` / `#stat-conversion` / `#stat-conversion-cmp` | Conversion stat card |
| `#gfva-main-chart` | Line chart canvas |
| `#gfva-chart-title` | Chart heading, updated dynamically |
| `#gfva-chart-legend` | Legend container, populated by JS |
| `#gfva-breakdown-card` | Views by form card wrapper |
| `#gfva-breakdown-chart` | Views by form canvas |
| `#gfva-entries-breakdown-card` | Entries by form card wrapper |
| `#gfva-entries-breakdown-chart` | Entries by form canvas |
| `#gfva-table-head` / `#gfva-table-body` | Table sections, populated by JS |
| `#gfva-export-csv` | CSV export button |

---

## Data flow summary

```
Page load
  └─ loadForms() → gfva_get_forms → renderFormOptions()
  └─ readUrlParams() → pre-fill state + _autoRun flag

Run Report click (or _autoRun)
  └─ runReport()
      └─ POST gfva_get_data
          └─ gfva_ajax_get_data()
              └─ gfva_fetch_period()   (×1 or ×2 for compare)
              └─ gfva_fetch_summary()  (×1 or ×2 for compare)
          └─ renderResults(data)
              ├─ renderStats()
              ├─ renderMainChart()      → gfvaDataLabels plugin
              ├─ renderBreakdownChart() → gfvaDataLabels plugin
              ├─ renderTable()
              └─ renderPrintHeader()

Export PDF click
  └─ exportPdf()
      └─ chart.resize(794, h) × 3
      └─ window.print()
      └─ chart.resize() × 3 (restore)

Export CSV click
  └─ exportCsv() — reads state.lastData, no extra request
```

---

## Common update tasks

**Add a new filter** — add it to `state`, wire up the UI in an `init*` function, include it in the `runReport` payload and in `readUrlParams` / `history.pushState`.

**Add a new chart** — create a canvas in `page.php`, add a module-level chart variable, add a render call in `renderResults`, destroy the old instance at the top of the new render function, register `gfvaDataLabels` as a plugin on the new chart instance.

**Add a new DB metric** — add a query in `gfva_fetch_period` or `gfva_fetch_summary`, include the result key in the returned array, and consume it in the appropriate render function.

**Change a chart colour** — update the `borderColor`/`backgroundColor` on the dataset object in `renderMainChart`. The legend reads from `ds.borderColor` automatically so no separate change is needed.

**Change the print layout** — edit `@media print` in `admin.css`. Remember there are currently two blocks; edit both or merge them.

**Bump the version** — update `GFVA_VERSION` in `gf-views-analytics.php` (two places: the constant and the plugin header comment). Asset cache-busting is tied to this constant.