/* globals jQuery, GFVA, Chart, flatpickr */
(function ($) {
	'use strict';

	const state = {
		forms:         [],
		selectedForms: [],
		dateFrom:      '',
		dateTo:        '',
		compareFrom:   '',
		compareTo:     '',
		granularity:   'day',
		includeEntries: true,
		compareEnabled: false,
		lastData:      null,
		activePreset:  null,
	};

	let mainChart      = null;
	let breakdownChart = null;

	$(document).ready(function () {
		initDatePickers();
		initGranularity();
		initToggles();
		initPresets();
		initFormSelect();
		readUrlParams();
		loadForms();

		$('#gfva-run').on('click', runReport);
		$('#gfva-export-pdf').on('click', exportPdf);
		$('#gfva-export-csv').on('click', exportCsv);

		if (!new URLSearchParams(window.location.search).get('date_from')) {
			setPreset('30d');
		}
	});

	/* ── Date pickers ───────────────────────────────── */
	function initDatePickers() {
		const opts = { dateFormat: 'Y-m-d', allowInput: true };
		flatpickr('#gfva-date-from',    { ...opts, onChange: (d, s) => { state.dateFrom = s; } });
		flatpickr('#gfva-date-to',      { ...opts, onChange: (d, s) => { state.dateTo   = s; } });
		flatpickr('#gfva-compare-from', { ...opts, onChange: (d, s) => { state.compareFrom = s; } });
		flatpickr('#gfva-compare-to',   { ...opts, onChange: (d, s) => { state.compareTo   = s; } });
	}

	/* ── Granularity ────────────────────────────────── */
	function initGranularity() {
		$('#gfva-granularity').on('click', '.gfva-seg-btn', function () {
			$('#gfva-granularity .gfva-seg-btn').removeClass('active');
			$(this).addClass('active');
			state.granularity = $(this).data('value');
		});
	}

	/* ── Toggles ────────────────────────────────────── */
	function initToggles() {
		$('#gfva-compare-toggle').on('change', function () {
			state.compareEnabled = this.checked;
			$('#gfva-compare-dates').css({
				opacity:        this.checked ? 1 : 0.4,
				'pointer-events': this.checked ? 'auto' : 'none',
			});
		});
		$('#gfva-entries-toggle').on('change', function () {
			state.includeEntries = this.checked;
		});
	}

	/* ── Presets ────────────────────────────────────── */
	function initPresets() {
		$('.gfva-preset').on('click', function () {
			$('.gfva-preset').removeClass('active');
			$(this).addClass('active');
			setPreset($(this).data('preset'));
		});
	}

	function setPreset(preset) {
		const today = new Date();
		let from;

		if (preset === '7d')       { from = daysAgo(7); }
		else if (preset === '30d') { from = daysAgo(30); }
		else if (preset === '90d') { from = daysAgo(90); }
		else if (preset === 'mtd') { from = new Date(today.getFullYear(), today.getMonth(), 1); }
		else if (preset === 'ytd') { from = new Date(today.getFullYear(), 0, 1); }

		const fromStr = formatDate(from);
		const toStr   = formatDate(today);

		setFlatpickr('#gfva-date-from', fromStr);
		setFlatpickr('#gfva-date-to',   toStr);
		state.dateFrom     = fromStr;
		state.dateTo       = toStr;
		state.activePreset = preset;

		$('.gfva-preset').removeClass('active');
		$(`.gfva-preset[data-preset="${preset}"]`).addClass('active');
	}

	function daysAgo(n) {
		const d = new Date();
		d.setDate(d.getDate() - n);
		return d;
	}

	function formatDate(d) {
		return d.toISOString().split('T')[0];
	}

	function setFlatpickr(selector, dateStr) {
		const el = document.querySelector(selector);
		if (el && el._flatpickr) {
			el._flatpickr.setDate(dateStr, true);
		} else {
			$(selector).val(dateStr);
		}
	}

	/* ── Form select ────────────────────────────────── */
	function initFormSelect() {
		$('#gfva-forms-trigger').on('click', function (e) {
			e.stopPropagation();
			$('#gfva-forms-dropdown').toggleClass('open');
		});

		$(document).on('click', function (e) {
			if (!$(e.target).closest('#gfva-forms-select-wrap').length) {
				$('#gfva-forms-dropdown').removeClass('open');
			}
		});

		$('#gfva-forms-search').on('input', function () {
			const q = this.value.toLowerCase();
			$('#gfva-forms-options .gfva-multiselect__option:not(.gfva-multiselect__option--all)').each(function () {
				$(this).toggle($(this).text().toLowerCase().includes(q));
			});
		});
	}

	function loadForms() {
		$.post(GFVA.ajax_url, { action: 'gfva_get_forms', nonce: GFVA.nonce }, function (res) {
			if (!res.success) return;
			state.forms = res.data;
			renderFormOptions(res.data);

			if (state.selectedForms.length) {
				$('#gfva-form-all').prop('checked', false);
				state.selectedForms.forEach(function (id) {
					$(`.gfva-form-cb[value="${id}"]`).prop('checked', true);
				});
				updateFormLabel();
			}

			if (state._autoRun) {
				state._autoRun = false;
				runReport();
			}
		});
	}

	function renderFormOptions(forms) {
		const $opts = $('#gfva-forms-options').empty();

		$opts.append(
			$('<label class="gfva-multiselect__option gfva-multiselect__option--all">').append(
				$('<input type="checkbox" id="gfva-form-all" checked>'),
				$('<span>All forms</span>')
			)
		);

		forms.forEach(function (form) {
			$opts.append(
				$('<label class="gfva-multiselect__option">').append(
					$(`<input type="checkbox" class="gfva-form-cb" value="${form.id}">`),
					$(`<span>${escHtml(form.title)} <small style="opacity:.6">#${form.id}</small></span>`)
				)
			);
		});

		$('#gfva-form-all').on('change', function () {
			$('.gfva-form-cb').prop('checked', false);
			state.selectedForms = [];
			updateFormLabel();
		});

		$opts.on('change', '.gfva-form-cb', function () {
			if (this.checked) {
				$('#gfva-form-all').prop('checked', false);
			}
			state.selectedForms = $('.gfva-form-cb:checked').map(function () {
				return parseInt(this.value);
			}).get();
			if (!state.selectedForms.length) {
				$('#gfva-form-all').prop('checked', true);
			}
			updateFormLabel();
		});
	}

	function updateFormLabel() {
		const n = state.selectedForms.length;
		if (!n) {
			$('#gfva-forms-label').text('All forms');
		} else if (n === 1) {
			const form = state.forms.find(f => f.id === state.selectedForms[0]);
			$('#gfva-forms-label').text(form ? form.title : '1 form');
		} else {
			$('#gfva-forms-label').text(`${n} forms selected`);
		}
	}

	/* ── URL params ─────────────────────────────────── */
	function readUrlParams() {
		const params = new URLSearchParams(window.location.search);

		if (params.get('date_from')) {
			state.dateFrom = params.get('date_from');
			setFlatpickr('#gfva-date-from', state.dateFrom);
		}
		if (params.get('date_to')) {
			state.dateTo = params.get('date_to');
			setFlatpickr('#gfva-date-to', state.dateTo);
		}
		if (params.get('granularity')) {
			state.granularity = params.get('granularity');
			$('#gfva-granularity .gfva-seg-btn').removeClass('active');
			$(`#gfva-granularity .gfva-seg-btn[data-value="${state.granularity}"]`).addClass('active');
		}
		if (params.get('entries') !== null) {
			state.includeEntries = params.get('entries') === '1';
			$('#gfva-entries-toggle').prop('checked', state.includeEntries);
		}
		if (params.get('compare_from') && params.get('compare_to')) {
			state.compareFrom    = params.get('compare_from');
			state.compareTo      = params.get('compare_to');
			state.compareEnabled = true;
			$('#gfva-compare-toggle').prop('checked', true).trigger('change');
			setFlatpickr('#gfva-compare-from', state.compareFrom);
			setFlatpickr('#gfva-compare-to',   state.compareTo);
		}
		if (params.getAll('forms[]').length) {
			state.selectedForms = params.getAll('forms[]').map(Number);
		}

		if (params.get('date_from') && params.get('date_to')) {
			state._autoRun = true;
		}
	}

	/* ── Run report ─────────────────────────────────── */
	function runReport() {
		if (!state.dateFrom || !state.dateTo) {
			alert('Please select a date range first.');
			return;
		}

		const params = new URLSearchParams({
			date_from:   state.dateFrom,
			date_to:     state.dateTo,
			granularity: state.granularity,
			entries:     state.includeEntries ? '1' : '0',
		});
		if (state.selectedForms.length) {
			state.selectedForms.forEach(id => params.append('forms[]', id));
		}
		if (state.compareEnabled && state.compareFrom && state.compareTo) {
			params.set('compare_from', state.compareFrom);
			params.set('compare_to',   state.compareTo);
		}
		history.pushState(null, '', '?' + params.toString() + '&page=gf-views-analytics');

		$('#gfva-empty').hide();
		$('#gfva-results').hide();
		$('#gfva-loading').show();
		$('#gfva-run').prop('disabled', true);

		const payload = {
			action:         'gfva_get_data',
			nonce:           GFVA.nonce,
			form_ids:        state.selectedForms,
			date_from:       state.dateFrom,
			date_to:         state.dateTo,
			granularity:     state.granularity,
			include_entries: state.includeEntries ? 1 : 0,
		};

		if (state.compareEnabled && state.compareFrom && state.compareTo) {
			payload.compare_from = state.compareFrom;
			payload.compare_to   = state.compareTo;
		}

		$.post(GFVA.ajax_url, payload, function (res) {
			$('#gfva-loading').hide();
			$('#gfva-run').prop('disabled', false);

			if (!res.success) {
				alert('Error: ' + (res.data || 'Unknown error'));
				$('#gfva-empty').show();
				return;
			}

			state.lastData = res.data;
			renderResults(res.data);
			$('#gfva-results').show();
			$('#gfva-export-pdf').prop('disabled', false);
		}).fail(function () {
			$('#gfva-loading').hide();
			$('#gfva-run').prop('disabled', false);
			alert('AJAX request failed. Please try again.');
			$('#gfva-empty').show();
		});
	}

	/* ── Render ─────────────────────────────────────── */
	function renderResults(data) {
		renderStats(data);
		renderMainChart(data);
		renderBreakdownChart(data);
		renderTable(data);
		renderPrintHeader();
	}

	function renderStats(data) {
		const p = data.summary;
		const c = data.compare_summary || null;

		$('#stat-total-views').text(formatNumber(p.total_views));

		if (state.includeEntries) {
			$('#stat-card-entries').show();
			$('#stat-card-conversion').show();
			$('#stat-total-entries').text(formatNumber(p.total_entries));
			$('#stat-conversion').text(p.conversion + '%');
		} else {
			$('#stat-card-entries').hide();
			$('#stat-card-conversion').hide();
		}

		if (c) {
			renderDelta('#stat-total-views-cmp', p.total_views, c.total_views);
			if (state.includeEntries) {
				renderDelta('#stat-total-entries-cmp', p.total_entries, c.total_entries);
				renderDelta('#stat-conversion-cmp',    p.conversion,    c.conversion, true);
			}
		} else {
			$('.gfva-stat-card__compare').text('');
		}
	}

	function renderDelta(selector, current, previous, isPct) {
		if (!previous && !current) { $(selector).text('').removeClass('up down flat'); return; }
		const diff  = current - previous;
		const pct   = previous > 0 ? ((diff / previous) * 100).toFixed(1) : '∞';
		const arrow = diff > 0 ? '↑' : diff < 0 ? '↓' : '→';
		const cls   = diff > 0 ? 'up' : diff < 0 ? 'down' : 'flat';
		const label = isPct ? `${diff > 0 ? '+' : ''}${diff.toFixed(1)}pp` : `${arrow} ${Math.abs(pct)}%`;
		$(selector).text(label).removeClass('up down flat').addClass(cls);
	}

	function renderMainChart(data) {
		const canvas = document.getElementById('gfva-main-chart');
		if (mainChart) { mainChart.destroy(); }

		const primary  = data.primary;
		const compare  = data.compare || null;
		const isHourly = data.granularity === 'hour';

		const allPeriods = mergeKeys([
			primary.views,
			compare ? compare.views : {},
			primary.entries || {},
			compare ? (compare.entries || {}) : {},
		]);

		const datasets = [];

		datasets.push({
			label:           'Views',
			data:            allPeriods.map(p => primary.views[p] || 0),
			borderColor:     'rgba(91,79,207,1)',
			backgroundColor: 'rgba(91,79,207,0.15)',
			fill:            true,
			tension:         0.35,
			borderWidth:     2,
			pointRadius:     allPeriods.length > 60 ? 0 : 3,
		});

		if (compare) {
			datasets.push({
				label:           'Views (compare)',
				data:            allPeriods.map(p => compare.views[p] || 0),
				borderColor:     'rgba(232,71,76,1)',
				backgroundColor: 'rgba(232,71,76,0.12)',
				fill:            true,
				tension:         0.35,
				borderWidth:     2,
				borderDash:      [5, 3],
				pointRadius:     allPeriods.length > 60 ? 0 : 3,
			});
		}

		if (state.includeEntries && primary.entries) {
			datasets.push({
				label:           'Entries',
				data:            allPeriods.map(p => (primary.entries[p] || 0)),
				borderColor:     'rgba(47,184,160,1)',
				backgroundColor: 'rgba(47,184,160,0.12)',
				fill:            true,
				tension:         0.35,
				borderWidth:     2,
				pointRadius:     allPeriods.length > 60 ? 0 : 3,
			});

			if (compare && compare.entries) {
				datasets.push({
					label:           'Entries (compare)',
					data:            allPeriods.map(p => (compare.entries[p] || 0)),
					borderColor:     'rgba(245,166,35,0.9)',
					backgroundColor: 'rgba(245,166,35,0.1)',
					fill:            true,
					tension:         0.35,
					borderWidth:     2,
					borderDash:      [5, 3],
					pointRadius:     allPeriods.length > 60 ? 0 : 3,
				});
			}
		}

		// For hourly view, strip the date prefix from labels so they read as "09:00" etc
		const labels = isHourly
			? allPeriods.map(p => p.split(' ')[1] || p)
			: allPeriods;

		mainChart = new Chart(canvas, {
			type: 'line',
			data: { labels, datasets },
			options: {
				responsive:          true,
				maintainAspectRatio: false,
				interaction:         { mode: 'index', intersect: false },
				plugins: {
					legend: { display: false },
					tooltip: {
						backgroundColor: '#fff',
						borderColor:     '#e2e6ea',
						borderWidth:     1,
						titleColor:      '#1a202c',
						bodyColor:       '#718096',
						padding:         12,
					},
				},
				scales: {
					x: {
						grid:  { color: '#f0f2f5' },
						ticks: { color: '#718096', font: { size: 11 }, maxTicksLimit: isHourly ? 24 : 12 },
					},
					y: {
						grid:        { color: '#f0f2f5' },
						ticks:       { color: '#718096', font: { size: 11 } },
						beginAtZero: true,
					},
				},
			},
		});

		const colours = ['rgba(91,79,207,1)', 'rgba(232,71,76,1)', 'rgba(47,184,160,1)', 'rgba(245,166,35,0.9)'];
		const $legend = $('#gfva-chart-legend').empty();
		datasets.forEach(function (ds, i) {
			$legend.append(
				$('<div class="gfva-legend-item">').append(
					$('<div class="gfva-legend-dot">').css('background', colours[i] || '#999'),
					$('<span>').text(ds.label)
				)
			);
		});

		const title = isHourly
			? 'Views by hour' + (compare ? ' (with comparison)' : '')
			: 'Views over time' + (compare ? ' (with comparison)' : '');
		$('#gfva-chart-title').text(title);
	}

	function renderBreakdownChart(data) {
		const byForm  = data.primary.by_form || {};
		const formIds = Object.keys(byForm).map(Number);

		if (formIds.length <= 1) {
			$('#gfva-breakdown-card').hide();
			return;
		}

		$('#gfva-breakdown-card').show();
		const canvas = document.getElementById('gfva-breakdown-chart');
		if (breakdownChart) breakdownChart.destroy();

		const formTotals = {};
		formIds.forEach(function (fid) {
			formTotals[fid] = Object.values(byForm[fid]).reduce((a, b) => a + b, 0);
		});

		const sorted = formIds.sort((a, b) => formTotals[b] - formTotals[a]);
		const labels  = sorted.map(fid => {
			const form = state.forms.find(f => f.id === fid);
			return form ? form.title : `Form #${fid}`;
		});
		const values  = sorted.map(fid => formTotals[fid]);

		const palette = [
			'rgba(91,79,207,0.8)',  'rgba(47,184,160,0.8)', 'rgba(232,71,76,0.8)',
			'rgba(245,166,35,0.8)', 'rgba(99,179,237,0.8)', 'rgba(184,107,232,0.8)',
			'rgba(237,137,54,0.8)', 'rgba(72,187,120,0.8)',
		];

		breakdownChart = new Chart(canvas, {
			type: 'bar',
			data: {
				labels,
				datasets: [{
					label:           'Total Views',
					data:            values,
					backgroundColor: sorted.map((_, i) => palette[i % palette.length]),
					borderRadius:    5,
					borderSkipped:   false,
				}],
			},
			options: {
				indexAxis:           'y',
				responsive:          true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false },
					tooltip: {
						backgroundColor: '#fff',
						borderColor:     '#e2e6ea',
						borderWidth:     1,
						titleColor:      '#1a202c',
						bodyColor:       '#718096',
						padding:         10,
					},
				},
				scales: {
					x: { grid: { color: '#f0f2f5' }, ticks: { color: '#718096', font: { size: 11 } }, beginAtZero: true },
					y: { grid: { display: false },   ticks: { color: '#718096', font: { size: 12 } } },
				},
			},
		});
	}

	function renderTable(data) {
		const primary  = data.primary;
		const compare  = data.compare || null;
		const isHourly = data.granularity === 'hour';

		const allPeriods = mergeKeys([
			primary.views,
			compare ? compare.views : {},
			primary.entries || {},
		]);

		const cols = [ isHourly ? 'Hour' : 'Period', 'Views' ];
		if (compare) cols.push('Views (compare)', 'Views Δ');
		if (state.includeEntries && primary.entries) {
			cols.push('Entries');
			if (compare && compare.entries) cols.push('Entries (compare)', 'Entries Δ');
			cols.push('Conv. %');
		}

		const $thead = $('#gfva-table-head').empty();
		const $hrow  = $('<tr>');
		cols.forEach((c, i) => {
			$hrow.append(`<th class="${i === 0 ? 'col-left' : 'col-right'}">${escHtml(c)}</th>`);
		});
		$thead.append($hrow);

		const $tbody = $('#gfva-table-body').empty();
		allPeriods.forEach(function (period) {
			const views    = primary.views[period] || 0;
			const cViews   = compare ? (compare.views[period] || 0) : null;
			const entries  = primary.entries ? (primary.entries[period] || 0) : null;
			const cEntries = compare && compare.entries ? (compare.entries[period] || 0) : null;
			const conv     = views > 0 && entries !== null ? ((entries / views) * 100).toFixed(1) : null;
			const vDelta   = cViews !== null ? views - cViews : null;
			const eDelta   = cEntries !== null && entries !== null ? entries - cEntries : null;

			// For hourly, show just the time portion
			const periodLabel = isHourly ? (period.split(' ')[1] || period) : period;

			const $tr = $('<tr>');
			$tr.append(`<td class="col-left">${escHtml(periodLabel)}</td>`);
			$tr.append(`<td class="col-right">${formatNumber(views)}</td>`);
			if (compare !== null) {
				$tr.append(`<td class="col-right">${formatNumber(cViews)}</td>`);
				$tr.append(deltaCell(vDelta));
			}
			if (state.includeEntries && entries !== null) {
				$tr.append(`<td class="col-right">${formatNumber(entries)}</td>`);
				if (cEntries !== null) {
					$tr.append(`<td class="col-right">${formatNumber(cEntries)}</td>`);
					$tr.append(deltaCell(eDelta));
				}
				$tr.append(`<td class="col-right">${conv !== null ? conv + '%' : '—'}</td>`);
			}
			$tbody.append($tr);
		});
	}

	function deltaCell(delta) {
		if (delta === null) return '<td class="col-right">—</td>';
		const sign = delta > 0 ? '+' : '';
		const cls  = delta > 0 ? 'delta up' : delta < 0 ? 'delta down' : 'delta flat';
		return `<td class="col-right ${cls}">${sign}${formatNumber(delta)}</td>`;
	}

	function renderPrintHeader() {
		$('.gfva-print-header').remove();
		const forms  = state.selectedForms.length
			? state.forms.filter(f => state.selectedForms.includes(f.id)).map(f => f.title).join(', ')
			: 'All forms';
		const header = $('<div class="gfva-print-header">').html(`
			<h2 style="margin:0 0 6px;font-size:16px;">GF Views Analytics Report</h2>
			<p>Period: ${escHtml(state.dateFrom)} – ${escHtml(state.dateTo)}</p>
			<p>Forms: ${escHtml(forms)}</p>
			<p>Generated: ${new Date().toLocaleString()}</p>
		`);
		$('#gfva-results').prepend(header);
	}

	/* ── Exports ────────────────────────────────────── */
	function exportPdf() {
		window.print();
	}

	function exportCsv() {
		if (!state.lastData) return;
		const data     = state.lastData;
		const primary  = data.primary;
		const compare  = data.compare || null;
		const isHourly = data.granularity === 'hour';

		const allPeriods = mergeKeys([
			primary.views,
			compare ? compare.views : {},
			primary.entries || {},
		]);

		const cols = [ isHourly ? 'Hour' : 'Period', 'Views' ];
		if (compare) cols.push('Views_Compare', 'Views_Delta');
		if (state.includeEntries && primary.entries) {
			cols.push('Entries');
			if (compare && compare.entries) cols.push('Entries_Compare', 'Entries_Delta');
			cols.push('Conversion_Pct');
		}

		const rows = [cols.join(',')];
		allPeriods.forEach(function (period) {
			const views    = primary.views[period] || 0;
			const cViews   = compare ? (compare.views[period] || 0) : null;
			const entries  = primary.entries ? (primary.entries[period] || 0) : null;
			const cEntries = compare && compare.entries ? (compare.entries[period] || 0) : null;
			const conv     = views > 0 && entries !== null ? ((entries / views) * 100).toFixed(1) : '';
			const periodLabel = isHourly ? (period.split(' ')[1] || period) : period;

			const row = [periodLabel, views];
			if (cViews !== null)  { row.push(cViews, views - cViews); }
			if (entries !== null) {
				row.push(entries);
				if (cEntries !== null) row.push(cEntries, entries - cEntries);
				row.push(conv);
			}
			rows.push(row.join(','));
		});

		const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
		const url  = URL.createObjectURL(blob);
		const a    = document.createElement('a');
		a.href     = url;
		a.download = `gf-views-analytics-${state.dateFrom}-${state.dateTo}.csv`;
		a.click();
		URL.revokeObjectURL(url);
	}

	/* ── Helpers ────────────────────────────────────── */
	function mergeKeys(objects) {
		const set = new Set();
		objects.forEach(obj => obj && Object.keys(obj).forEach(k => set.add(k)));
		return Array.from(set).sort();
	}

	function formatNumber(n) {
		if (n === null || n === undefined) return '—';
		return Number(n).toLocaleString();
	}

	function escHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

}(jQuery));