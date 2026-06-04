<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="gfva-wrap" id="gfva-app">

	<div class="gfva-header">
		<div class="gfva-header__inner">
			<h1 class="gfva-header__title">
				<svg class="gfva-logo" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M3 3h18v4H3zM3 10h11v4H3zM3 17h7v4H3zM17 13l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
					<path d="M21 17h-8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
				</svg>
				Views Analytics
			</h1>
			<div class="gfva-header__actions">
				<button id="gfva-export-pdf" class="gfva-btn gfva-btn--outline" disabled>
					<svg viewBox="0 0 24 24" fill="none"><path d="M12 16l-4-4h2.5V4h3v8H16l-4 4z" fill="currentColor"/><path d="M4 18h16v2H4v-2z" fill="currentColor"/></svg>
					Export PDF
				</button>
			</div>
		</div>
	</div>

	<!-- ═══ FILTERS ═══ -->
	<div class="gfva-filters-card" id="gfva-filters">
		<div class="gfva-filters-row">

			<div class="gfva-filter-group gfva-filter-group--forms">
				<label class="gfva-label">Forms</label>
				<div class="gfva-multiselect" id="gfva-forms-select-wrap">
					<button class="gfva-multiselect__trigger" id="gfva-forms-trigger" type="button">
						<span id="gfva-forms-label">All forms</span>
						<svg viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
					</button>
					<div class="gfva-multiselect__dropdown" id="gfva-forms-dropdown">
						<div class="gfva-multiselect__search">
							<input type="text" id="gfva-forms-search" placeholder="Search forms…">
						</div>
						<div class="gfva-multiselect__options" id="gfva-forms-options">
							<div class="gfva-loading-sm">Loading forms…</div>
						</div>
					</div>
				</div>
			</div>

			<div class="gfva-filter-group">
				<label class="gfva-label">Primary date range</label>
				<div class="gfva-date-range">
					<input type="text" id="gfva-date-from" class="gfva-input" placeholder="From">
					<span class="gfva-date-sep">—</span>
					<input type="text" id="gfva-date-to" class="gfva-input" placeholder="To">
				</div>
			</div>

			<div class="gfva-filter-group">
				<label class="gfva-label">
					<span>Compare range</span>
					<label class="gfva-toggle">
						<input type="checkbox" id="gfva-compare-toggle">
						<span class="gfva-toggle__track"></span>
					</label>
				</label>
				<div class="gfva-date-range" id="gfva-compare-dates" style="opacity:.4;pointer-events:none;">
					<input type="text" id="gfva-compare-from" class="gfva-input" placeholder="From">
					<span class="gfva-date-sep">—</span>
					<input type="text" id="gfva-compare-to" class="gfva-input" placeholder="To">
				</div>
			</div>

			<div class="gfva-filter-group gfva-filter-group--sm">
				<label class="gfva-label">Granularity</label>
				<div class="gfva-segmented" id="gfva-granularity">
					<button class="gfva-seg-btn active" data-value="day">Day</button>
					<button class="gfva-seg-btn" data-value="week">Week</button>
					<button class="gfva-seg-btn" data-value="month">Month</button>
				</div>
			</div>

			<div class="gfva-filter-group gfva-filter-group--sm">
				<label class="gfva-label">Entries overlay</label>
				<label class="gfva-toggle">
					<input type="checkbox" id="gfva-entries-toggle" checked>
					<span class="gfva-toggle__track"></span>
				</label>
			</div>

			<div class="gfva-filter-group gfva-filter-group--action">
				<button id="gfva-run" class="gfva-btn gfva-btn--primary">
					<svg viewBox="0 0 24 24" fill="none"><path d="M4 4l16 8-16 8V4z" fill="currentColor"/></svg>
					Run Report
				</button>
			</div>

		</div>

		<!-- Quick range presets -->
		<div class="gfva-presets">
			<span class="gfva-presets__label">Quick:</span>
			<button class="gfva-preset" data-preset="7d">Last 7 days</button>
			<button class="gfva-preset" data-preset="30d">Last 30 days</button>
			<button class="gfva-preset" data-preset="90d">Last 90 days</button>
			<button class="gfva-preset" data-preset="mtd">Month to date</button>
			<button class="gfva-preset" data-preset="ytd">Year to date</button>
		</div>
	</div>

	<!-- ═══ LOADING ═══ -->
	<div id="gfva-loading" class="gfva-loading" style="display:none;">
		<div class="gfva-spinner"></div>
		<p>Fetching data…</p>
	</div>

	<!-- ═══ EMPTY STATE ═══ -->
	<div id="gfva-empty" class="gfva-empty">
		<svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
			<circle cx="40" cy="40" r="36" stroke="currentColor" stroke-width="2" stroke-dasharray="6 4"/>
			<path d="M25 52 L25 38 L35 28 L45 35 L55 22 L55 52 Z" fill="currentColor" opacity=".15"/>
			<path d="M25 52 L25 38 L35 28 L45 35 L55 22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
		</svg>
		<p>Select a date range and click <strong>Run Report</strong></p>
	</div>

	<!-- ═══ RESULTS (hidden until data loads) ═══ -->
	<div id="gfva-results" style="display:none;">

		<!-- STAT CARDS -->
		<div class="gfva-stats" id="gfva-stats">
			<div class="gfva-stat-card" data-stat="total_views">
				<div class="gfva-stat-card__icon gfva-stat-card__icon--views">
					<svg viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/></svg>
				</div>
				<div class="gfva-stat-card__body">
					<div class="gfva-stat-card__value" id="stat-total-views">—</div>
					<div class="gfva-stat-card__label">Total Views</div>
					<div class="gfva-stat-card__compare" id="stat-total-views-cmp"></div>
				</div>
			</div>
			<div class="gfva-stat-card" data-stat="unique_views">
				<div class="gfva-stat-card__icon gfva-stat-card__icon--unique">
					<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.5"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
				</div>
				<div class="gfva-stat-card__body">
					<div class="gfva-stat-card__value" id="stat-unique-views">—</div>
					<div class="gfva-stat-card__label">Unique Visitors</div>
					<div class="gfva-stat-card__compare" id="stat-unique-views-cmp"></div>
				</div>
			</div>
			<div class="gfva-stat-card" id="stat-card-entries" style="display:none;" data-stat="total_entries">
				<div class="gfva-stat-card__icon gfva-stat-card__icon--entries">
					<svg viewBox="0 0 24 24" fill="none"><path d="M9 11l3 3L22 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
				</div>
				<div class="gfva-stat-card__body">
					<div class="gfva-stat-card__value" id="stat-total-entries">—</div>
					<div class="gfva-stat-card__label">Total Entries</div>
					<div class="gfva-stat-card__compare" id="stat-total-entries-cmp"></div>
				</div>
			</div>
			<div class="gfva-stat-card" id="stat-card-conversion" style="display:none;" data-stat="conversion">
				<div class="gfva-stat-card__icon gfva-stat-card__icon--conversion">
					<svg viewBox="0 0 24 24" fill="none"><path d="M22 11.08V12a10 10 0 11-5.93-9.14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><polyline points="22 4 12 14.01 9 11.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</div>
				<div class="gfva-stat-card__body">
					<div class="gfva-stat-card__value" id="stat-conversion">—</div>
					<div class="gfva-stat-card__label">Conversion Rate</div>
					<div class="gfva-stat-card__compare" id="stat-conversion-cmp"></div>
				</div>
			</div>
		</div>

		<!-- MAIN CHART -->
		<div class="gfva-chart-card">
			<div class="gfva-chart-card__header">
				<h2 class="gfva-chart-card__title" id="gfva-chart-title">Views over time</h2>
				<div class="gfva-chart-legend" id="gfva-chart-legend"></div>
			</div>
			<div class="gfva-chart-card__body">
				<canvas id="gfva-main-chart"></canvas>
			</div>
		</div>

		<!-- PER-FORM BREAKDOWN (only shown when multiple forms or all forms) -->
		<div class="gfva-chart-card" id="gfva-breakdown-card">
			<div class="gfva-chart-card__header">
				<h2 class="gfva-chart-card__title">Views by form</h2>
			</div>
			<div class="gfva-chart-card__body gfva-chart-card__body--short">
				<canvas id="gfva-breakdown-chart"></canvas>
			</div>
		</div>

		<!-- DATA TABLE -->
		<div class="gfva-table-card">
			<div class="gfva-table-card__header">
				<h2 class="gfva-table-card__title">Detailed data</h2>
				<button class="gfva-btn gfva-btn--sm gfva-btn--outline" id="gfva-export-csv">Export CSV</button>
			</div>
			<div class="gfva-table-wrap">
				<table class="gfva-table" id="gfva-data-table">
					<thead id="gfva-table-head"></thead>
					<tbody id="gfva-table-body"></tbody>
				</table>
			</div>
		</div>

	</div><!-- /results -->

</div><!-- /gfva-wrap -->
