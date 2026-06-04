<?php

/**
 * Plugin Name: GF Views Analytics
 * Plugin URI:  https://simpliweb.com.au
 * Description: Analytics dashboard for Gravity Forms views and entries with charts, filtering, comparison, and PDF export.
 * Version:     1.0.0
 * Author:      SimpliWeb
 * Author URI:  https://simpliweb.com.au
 * License:     GPL-2.0+
 * Text Domain: gf-views-analytics
 */

if (! defined('ABSPATH')) {
	exit;
}

define('GFVA_VERSION', '1.0.0');
define('GFVA_PATH', plugin_dir_path(__FILE__));
define('GFVA_URL', plugin_dir_url(__FILE__));

// Include the updater class
require_once GFVA_PATH . 'github-updater.php';

if (class_exists('SimpliWeb_GitHub_Updater')) {
	$updater = new SimpliWeb_GitHub_Updater(__FILE__);
	$updater->set_username('westcoastdigital'); // Update Username
	$updater->set_repository('GF-Views-Analytics'); // Update plugin slug

	if (defined('GITHUB_ACCESS_TOKEN')) {
		$updater->authorize(SW_GITHUB_ACCESS_TOKEN);
	}

	$updater->initialize();
}

/**
 * Check Gravity Forms is active before doing anything.
 */
add_action('admin_init', 'gfva_check_dependencies');
function gfva_check_dependencies()
{
	if (! class_exists('GFForms')) {
		add_action('admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>GF Views Analytics</strong> requires Gravity Forms to be installed and active.</p></div>';
		});
	}
}

/**
 * Register the admin menu page.
 */
add_action('admin_menu', 'gfva_register_menu');
function gfva_register_menu()
{
	if (! class_exists('GFForms')) {
		return;
	}
	add_submenu_page(
		'tools.php',
		'GF Views Analytics',
		'GF Views Analytics',
		'manage_options',
		'gf-views-analytics',
		'gfva_render_page'
	);
}

/**
 * Enqueue scripts and styles on our admin page only.
 */
add_action('admin_enqueue_scripts', 'gfva_enqueue_assets');
function gfva_enqueue_assets($hook)
{
	if (strpos($hook, 'gf-views-analytics') === false) {
		return;
	}

	// Chart.js from CDN
	wp_enqueue_script(
		'chartjs',
		'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js',
		[],
		'4.4.3',
		true
	);

	// Flatpickr date picker
	wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
	wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true);

	wp_enqueue_style(
		'gfva-admin',
		GFVA_URL . 'assets/admin.css',
		['flatpickr'],
		GFVA_VERSION
	);

	wp_enqueue_script(
		'gfva-admin',
		GFVA_URL . 'assets/admin.js',
		['jquery', 'chartjs', 'flatpickr'],
		GFVA_VERSION,
		true
	);

	wp_localize_script('gfva-admin', 'GFVA', [
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce'    => wp_create_nonce('gfva_nonce'),
	]);
}

/**
 * Render the admin page HTML shell — JS takes it from here.
 */
function gfva_render_page()
{
	if (! current_user_can('gform_full_access') && ! current_user_can('manage_options')) {
		wp_die('You do not have permission to view this page.');
	}
	include GFVA_PATH . 'templates/page.php';
}

/**
 * AJAX: get the list of all forms for the filter dropdowns.
 */
add_action('wp_ajax_gfva_get_forms', 'gfva_ajax_get_forms');
function gfva_ajax_get_forms()
{
	check_ajax_referer('gfva_nonce', 'nonce');
	if (! current_user_can('gform_full_access') && ! current_user_can('manage_options')) {
		wp_send_json_error('Insufficient permissions.');
	}

	$forms = GFAPI::get_forms();
	$data  = [];
	foreach ($forms as $form) {
		$data[] = [
			'id'    => $form['id'],
			'title' => $form['title'],
		];
	}
	wp_send_json_success($data);
}

/**
 * AJAX: fetch analytics data.
 *
 * Accepts:
 *   form_ids[]        array of form IDs (empty = all)
 *   date_from         Y-m-d
 *   date_to           Y-m-d
 *   compare_from      Y-m-d (optional)
 *   compare_to        Y-m-d (optional)
 *   granularity       day|week|month
 *   include_entries   1|0
 */
add_action('wp_ajax_gfva_get_data', 'gfva_ajax_get_data');
function gfva_ajax_get_data()
{
	check_ajax_referer('gfva_nonce', 'nonce');
	if (! current_user_can('gform_full_access') && ! current_user_can('manage_options')) {
		wp_send_json_error('Insufficient permissions.');
	}

	global $wpdb;

	$form_ids        = isset($_POST['form_ids']) ? array_map('intval', (array) $_POST['form_ids']) : [];
	$date_from       = sanitize_text_field($_POST['date_from'] ?? '');
	$date_to         = sanitize_text_field($_POST['date_to'] ?? '');
	$compare_from    = sanitize_text_field($_POST['compare_from'] ?? '');
	$compare_to      = sanitize_text_field($_POST['compare_to'] ?? '');
	$granularity     = in_array($_POST['granularity'] ?? 'day', ['day', 'week', 'month'], true)
		? $_POST['granularity']
		: 'day';
	$include_entries = ! empty($_POST['include_entries']);

	// Validate dates
	if (! $date_from || ! $date_to) {
		wp_send_json_error('Date range is required.');
	}

	$result = [
		'primary'  => gfva_fetch_period($form_ids, $date_from, $date_to, $granularity, $include_entries),
		'compare'  => null,
		'summary'  => gfva_fetch_summary($form_ids, $date_from, $date_to, $include_entries),
	];

	if ($compare_from && $compare_to) {
		$result['compare'] = gfva_fetch_period($form_ids, $compare_from, $compare_to, $granularity, $include_entries);
		$result['compare_summary'] = gfva_fetch_summary($form_ids, $compare_from, $compare_to, $include_entries);
	}

	wp_send_json_success($result);
}

/**
 * Build time-series data for a date range.
 */
function gfva_fetch_period(array $form_ids, string $from, string $to, string $granularity, bool $include_entries): array
{
	global $wpdb;

	$date_format = match ($granularity) {
		'week'  => '%Y-%u',
		'month' => '%Y-%m',
		default => '%Y-%m-%d',
	};

	// ---- Views ----
	$views_sql = "
		SELECT DATE_FORMAT(date_created, %s) AS period, COUNT(*) AS total
		FROM {$wpdb->prefix}gf_form_view
		WHERE date_created BETWEEN %s AND %s
	";
	$params = [$date_format, $from . ' 00:00:00', $to . ' 23:59:59'];

	if (! empty($form_ids)) {
		$placeholders = implode(',', array_fill(0, count($form_ids), '%d'));
		$views_sql   .= " AND form_id IN ($placeholders)";
		$params       = array_merge($params, $form_ids);
	}

	$views_sql .= ' GROUP BY period ORDER BY period ASC';
	$views_rows = $wpdb->get_results($wpdb->prepare($views_sql, ...$params));

	$views_data = [];
	foreach ($views_rows as $row) {
		$views_data[$row->period] = (int) $row->total;
	}

	$result = ['views' => $views_data];

	// ---- Per-form breakdown ----
	$breakdown_sql = "
		SELECT form_id, DATE_FORMAT(date_created, %s) AS period, COUNT(*) AS total
		FROM {$wpdb->prefix}gf_form_view
		WHERE date_created BETWEEN %s AND %s
	";
	$bp = [$date_format, $from . ' 00:00:00', $to . ' 23:59:59'];

	if (! empty($form_ids)) {
		$placeholders   = implode(',', array_fill(0, count($form_ids), '%d'));
		$breakdown_sql .= " AND form_id IN ($placeholders)";
		$bp             = array_merge($bp, $form_ids);
	}
	$breakdown_sql .= ' GROUP BY form_id, period ORDER BY period ASC';
	$breakdown_rows = $wpdb->get_results($wpdb->prepare($breakdown_sql, ...$bp));

	$by_form = [];
	foreach ($breakdown_rows as $row) {
		$fid = (int) $row->form_id;
		if (! isset($by_form[$fid])) {
			$by_form[$fid] = [];
		}
		$by_form[$fid][$row->period] = (int) $row->total;
	}
	$result['by_form'] = $by_form;

	// ---- Entries ----
	if ($include_entries) {
		$entries_sql = "
			SELECT DATE_FORMAT(date_created, %s) AS period, COUNT(*) AS total
			FROM {$wpdb->prefix}gf_entry
			WHERE status = 'active'
			  AND date_created BETWEEN %s AND %s
		";
		$ep = [$date_format, $from . ' 00:00:00', $to . ' 23:59:59'];

		if (! empty($form_ids)) {
			$placeholders  = implode(',', array_fill(0, count($form_ids), '%d'));
			$entries_sql  .= " AND form_id IN ($placeholders)";
			$ep            = array_merge($ep, $form_ids);
		}
		$entries_sql .= ' GROUP BY period ORDER BY period ASC';
		$entries_rows = $wpdb->get_results($wpdb->prepare($entries_sql, ...$ep));

		$entries_data = [];
		foreach ($entries_rows as $row) {
			$entries_data[$row->period] = (int) $row->total;
		}
		$result['entries'] = $entries_data;
	}

	return $result;
}

/**
 * Fetch summary totals for the stats bar.
 */
function gfva_fetch_summary(array $form_ids, string $from, string $to, bool $include_entries): array
{
	global $wpdb;

	// Total views
	$views_sql = "
		SELECT COUNT(*) FROM {$wpdb->prefix}gf_form_view
		WHERE date_created BETWEEN %s AND %s
	";
	$vp = [$from . ' 00:00:00', $to . ' 23:59:59'];
	if (! empty($form_ids)) {
		$placeholders = implode(',', array_fill(0, count($form_ids), '%d'));
		$views_sql   .= " AND form_id IN ($placeholders)";
		$vp           = array_merge($vp, $form_ids);
	}
	$total_views = (int) $wpdb->get_var($wpdb->prepare($views_sql, ...$vp));

	// Unique visitors (distinct IP)
	$unique_sql = "
		SELECT COUNT(DISTINCT ip) FROM {$wpdb->prefix}gf_form_view
		WHERE date_created BETWEEN %s AND %s
	";
	$up = [$from . ' 00:00:00', $to . ' 23:59:59'];
	if (! empty($form_ids)) {
		$placeholders = implode(',', array_fill(0, count($form_ids), '%d'));
		$unique_sql  .= " AND form_id IN ($placeholders)";
		$up           = array_merge($up, $form_ids);
	}
	$unique_views = (int) $wpdb->get_var($wpdb->prepare($unique_sql, ...$up));

	$summary = [
		'total_views'  => $total_views,
		'unique_views' => $unique_views,
		'total_entries' => 0,
		'conversion'    => 0,
	];

	if ($include_entries) {
		$entries_sql = "
			SELECT COUNT(*) FROM {$wpdb->prefix}gf_entry
			WHERE status = 'active' AND date_created BETWEEN %s AND %s
		";
		$enp = [$from . ' 00:00:00', $to . ' 23:59:59'];
		if (! empty($form_ids)) {
			$placeholders = implode(',', array_fill(0, count($form_ids), '%d'));
			$entries_sql .= " AND form_id IN ($placeholders)";
			$enp          = array_merge($enp, $form_ids);
		}
		$total_entries = (int) $wpdb->get_var($wpdb->prepare($entries_sql, ...$enp));
		$summary['total_entries'] = $total_entries;
		$summary['conversion']    = $total_views > 0 ? round(($total_entries / $total_views) * 100, 1) : 0;
	}

	return $summary;
}
