<?php
/**
 * Plugin Name: GF Views Analytics
 * Plugin URI:  https://simpliweb.com.au
 * Description: Analytics dashboard for Gravity Forms views and entries with charts, filtering, comparison, and PDF export.
 * Version:     1.0.2
 * Author:      SimpliWeb
 * Author URI:  https://simpliweb.com.au
 * License:     GPL-2.0+
 * Text Domain: gf-views-analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GFVA_VERSION', '1.0.2' );
define( 'GFVA_PATH', plugin_dir_path( __FILE__ ) );
define( 'GFVA_URL', plugin_dir_url( __FILE__ ) );

require_once GFVA_PATH . 'github-updater.php';

if ( class_exists( 'SimpliWeb_GitHub_Updater' ) ) {
	$updater = new SimpliWeb_GitHub_Updater( __FILE__ );
	$updater->set_username( 'westcoastdigital' );
	$updater->set_repository( 'GF-Views-Analytics' );

	if ( defined( 'GITHUB_ACCESS_TOKEN' ) ) {
		$updater->authorize( GITHUB_ACCESS_TOKEN );
	}

	$updater->initialize();
}

add_action( 'admin_init', 'gfva_check_dependencies' );
function gfva_check_dependencies() {
	if ( ! class_exists( 'GFForms' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>GF Views Analytics</strong> requires Gravity Forms to be installed and active.</p></div>';
		} );
	}
}

add_action( 'admin_menu', 'gfva_register_menu' );
function gfva_register_menu() {
	if ( ! class_exists( 'GFForms' ) ) {
		return;
	}
	add_submenu_page(
		'tools.php',
		'Views Analytics',
		'Views Analytics',
		'manage_options',
		'gf-views-analytics',
		'gfva_render_page'
	);
}

add_action( 'admin_enqueue_scripts', 'gfva_enqueue_assets' );
function gfva_enqueue_assets( $hook ) {
	if ( strpos( $hook, 'gf-views-analytics' ) === false ) {
		return;
	}

	wp_enqueue_script(
		'chartjs',
		'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js',
		[],
		'4.4.3',
		true
	);

	wp_enqueue_style( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13' );
	wp_enqueue_script( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true );

	wp_enqueue_style(
		'gfva-admin',
		GFVA_URL . 'assets/admin.css',
		[ 'flatpickr' ],
		GFVA_VERSION
	);

	wp_enqueue_script(
		'gfva-admin',
		GFVA_URL . 'assets/admin.js',
		[ 'jquery', 'chartjs', 'flatpickr' ],
		GFVA_VERSION,
		true
	);

	wp_localize_script( 'gfva-admin', 'GFVA', [
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'gfva_nonce' ),
	] );
}

function gfva_render_page() {
	if ( ! current_user_can( 'gform_full_access' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to view this page.' );
	}
	include GFVA_PATH . 'templates/page.php';
}

add_action( 'wp_ajax_gfva_get_forms', 'gfva_ajax_get_forms' );
function gfva_ajax_get_forms() {
	check_ajax_referer( 'gfva_nonce', 'nonce' );
	if ( ! current_user_can( 'gform_full_access' ) && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Insufficient permissions.' );
	}

	$forms = GFAPI::get_forms();
	$data  = [];
	foreach ( $forms as $form ) {
		$data[] = [
			'id'    => $form['id'],
			'title' => $form['title'],
		];
	}
	wp_send_json_success( $data );
}

add_action( 'wp_ajax_gfva_get_data', 'gfva_ajax_get_data' );
function gfva_ajax_get_data() {
	check_ajax_referer( 'gfva_nonce', 'nonce' );
	if ( ! current_user_can( 'gform_full_access' ) && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Insufficient permissions.' );
	}

	global $wpdb;

	$form_ids        = isset( $_POST['form_ids'] ) ? array_map( 'intval', (array) $_POST['form_ids'] ) : [];
	$date_from       = sanitize_text_field( $_POST['date_from'] ?? '' );
	$date_to         = sanitize_text_field( $_POST['date_to'] ?? '' );
	$compare_from    = sanitize_text_field( $_POST['compare_from'] ?? '' );
	$compare_to      = sanitize_text_field( $_POST['compare_to'] ?? '' );
	$granularity     = in_array( $_POST['granularity'] ?? 'day', [ 'hour', 'day', 'week', 'month' ], true )
		? $_POST['granularity']
		: 'day';
	$include_entries = ! empty( $_POST['include_entries'] );

	if ( ! $date_from || ! $date_to ) {
		wp_send_json_error( 'Date range is required.' );
	}

	// Auto hourly when a single day is selected
	if ( $date_from === $date_to && $granularity === 'day' ) {
		$granularity = 'hour';
	}

	$result = [
		'primary'     => gfva_fetch_period( $form_ids, $date_from, $date_to, $granularity, $include_entries ),
		'compare'     => null,
		'summary'     => gfva_fetch_summary( $form_ids, $date_from, $date_to, $include_entries ),
		'granularity' => $granularity,
	];

	if ( $compare_from && $compare_to ) {
		$compare_granularity = ( $compare_from === $compare_to && $granularity === 'hour' ) ? 'hour' : $granularity;
		$result['compare']         = gfva_fetch_period( $form_ids, $compare_from, $compare_to, $compare_granularity, $include_entries );
		$result['compare_summary'] = gfva_fetch_summary( $form_ids, $compare_from, $compare_to, $include_entries );
	}

	wp_send_json_success( $result );
}

/**
 * Convert a Y-m-d date string from site timezone to UTC datetime strings
 * suitable for use in database queries.
 */
function gfva_local_to_utc( string $date, bool $end_of_day = false ): string {
	$time  = $end_of_day ? ' 23:59:59' : ' 00:00:00';
	$local = new DateTime( $date . $time, wp_timezone() );
	$local->setTimezone( new DateTimeZone( 'UTC' ) );
	return $local->format( 'Y-m-d H:i:s' );
}

/**
 * Build time-series data for a date range.
 */
function gfva_fetch_period( array $form_ids, string $from, string $to, string $granularity, bool $include_entries ): array {
	global $wpdb;

	$date_format = match ( $granularity ) {
		'hour'  => '%Y-%m-%d %H:00',
		'week'  => '%Y-%u',
		'month' => '%Y-%m',
		default => '%Y-%m-%d',
	};

	// ---- Views ----
	$views_sql = "
		SELECT DATE_FORMAT(CONVERT_TZ(date_created, '+00:00', %s), %s) AS period, SUM(count) AS total
		FROM {$wpdb->prefix}gf_form_view
		WHERE date_created BETWEEN %s AND %s
	";
	$tz_offset = gfva_get_tz_offset();
	$params    = [ $tz_offset, $date_format, gfva_local_to_utc( $from ), gfva_local_to_utc( $to, true ) ];

	if ( ! empty( $form_ids ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $form_ids ), '%d' ) );
		$views_sql   .= " AND form_id IN ($placeholders)";
		$params       = array_merge( $params, $form_ids );
	}

	$views_sql .= ' GROUP BY period ORDER BY period ASC';
	$views_rows = $wpdb->get_results( $wpdb->prepare( $views_sql, ...$params ) );

	$views_data = [];
	foreach ( $views_rows as $row ) {
		$views_data[ $row->period ] = (int) $row->total;
	}

	$result = [ 'views' => $views_data ];

	// ---- Per-form breakdown ----
	$breakdown_sql = "
		SELECT form_id, DATE_FORMAT(CONVERT_TZ(date_created, '+00:00', %s), %s) AS period, SUM(count) AS total
		FROM {$wpdb->prefix}gf_form_view
		WHERE date_created BETWEEN %s AND %s
	";
	$bp = [ $tz_offset, $date_format, gfva_local_to_utc( $from ), gfva_local_to_utc( $to, true ) ];

	if ( ! empty( $form_ids ) ) {
		$placeholders   = implode( ',', array_fill( 0, count( $form_ids ), '%d' ) );
		$breakdown_sql .= " AND form_id IN ($placeholders)";
		$bp             = array_merge( $bp, $form_ids );
	}

	$breakdown_sql .= ' GROUP BY form_id, period ORDER BY period ASC';
	$breakdown_rows = $wpdb->get_results( $wpdb->prepare( $breakdown_sql, ...$bp ) );

	$by_form = [];
	foreach ( $breakdown_rows as $row ) {
		$fid = (int) $row->form_id;
		if ( ! isset( $by_form[ $fid ] ) ) {
			$by_form[ $fid ] = [];
		}
		$by_form[ $fid ][ $row->period ] = (int) $row->total;
	}
	$result['by_form'] = $by_form;

	// ---- Entries ----
	if ( $include_entries ) {
		$entries_sql = "
			SELECT DATE_FORMAT(CONVERT_TZ(date_created, '+00:00', %s), %s) AS period, COUNT(*) AS total
			FROM {$wpdb->prefix}gf_entry
			WHERE status = 'active'
			  AND date_created BETWEEN %s AND %s
		";
		$ep = [ $tz_offset, $date_format, gfva_local_to_utc( $from ), gfva_local_to_utc( $to, true ) ];

		if ( ! empty( $form_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $form_ids ), '%d' ) );
			$entries_sql .= " AND form_id IN ($placeholders)";
			$ep           = array_merge( $ep, $form_ids );
		}

		$entries_sql .= ' GROUP BY period ORDER BY period ASC';
		$entries_rows = $wpdb->get_results( $wpdb->prepare( $entries_sql, ...$ep ) );

		$entries_data = [];
		foreach ( $entries_rows as $row ) {
			$entries_data[ $row->period ] = (int) $row->total;
		}
		$result['entries'] = $entries_data;
	}

	return $result;
}

/**
 * Fetch summary totals for the stats bar.
 */
function gfva_fetch_summary( array $form_ids, string $from, string $to, bool $include_entries ): array {
	global $wpdb;

	$views_sql = "
		SELECT SUM(count) FROM {$wpdb->prefix}gf_form_view
		WHERE date_created BETWEEN %s AND %s
	";
	$vp = [ gfva_local_to_utc( $from ), gfva_local_to_utc( $to, true ) ];
	if ( ! empty( $form_ids ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $form_ids ), '%d' ) );
		$views_sql   .= " AND form_id IN ($placeholders)";
		$vp           = array_merge( $vp, $form_ids );
	}
	$total_views = (int) $wpdb->get_var( $wpdb->prepare( $views_sql, ...$vp ) );

	$summary = [
		'total_views'   => $total_views,
		'total_entries' => 0,
		'conversion'    => 0,
	];

	if ( $include_entries ) {
		$entries_sql = "
			SELECT COUNT(*) FROM {$wpdb->prefix}gf_entry
			WHERE status = 'active' AND date_created BETWEEN %s AND %s
		";
		$enp = [ gfva_local_to_utc( $from ), gfva_local_to_utc( $to, true ) ];
		if ( ! empty( $form_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $form_ids ), '%d' ) );
			$entries_sql .= " AND form_id IN ($placeholders)";
			$enp          = array_merge( $enp, $form_ids );
		}
		$total_entries            = (int) $wpdb->get_var( $wpdb->prepare( $entries_sql, ...$enp ) );
		$summary['total_entries'] = $total_entries;
		$summary['conversion']    = $total_views > 0 ? round( ( $total_entries / $total_views ) * 100, 1 ) : 0;
	}

	return $summary;
}

/**
 * Get the site timezone offset string for CONVERT_TZ e.g. +08:00
 */
function gfva_get_tz_offset(): string {
	$offset  = wp_timezone()->getOffset( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) );
	$hours   = intdiv( abs( $offset ), 3600 );
	$minutes = ( abs( $offset ) % 3600 ) / 60;
	$sign    = $offset >= 0 ? '+' : '-';
	return sprintf( '%s%02d:%02d', $sign, $hours, $minutes );
}