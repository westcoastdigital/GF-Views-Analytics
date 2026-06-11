<?php
/**
 * Plugin Name: GF Views Analytics
 * Plugin URI:  https://simpliweb.com.au
 * Description: Analytics dashboard for Gravity Forms views and entries with charts, filtering, comparison, and PDF export.
 * Version:     1.1.3
 * Author:      SimpliWeb
 * Author URI:  https://simpliweb.com.au
 * License:     GPL-2.0+
 * Text Domain: gf-views-analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GFVA_VERSION', '1.1.3' );
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

	wp_enqueue_media();

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
		'ajax_url'          => admin_url( 'admin-ajax.php' ),
		'nonce'             => wp_create_nonce( 'gfva_nonce' ),
		'date_format'       => gfva_get_date_format(),
		'date_format_nonce' => wp_create_nonce( 'gfva_date_format_nonce' ),
		'report_title'      => get_user_meta( get_current_user_id(), 'gfva_report_title', true ) ?: '',
		'report_logo'       => get_user_meta( get_current_user_id(), 'gfva_report_logo', true ) ?: '',
	] );
}

function gfva_render_page() {
	if ( ! current_user_can( 'gform_full_access' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to view this page.' );
	}
	include GFVA_PATH . 'templates/page.php';
}

add_filter( 'screen_settings', 'gfva_screen_options', 10, 2 );
function gfva_screen_options( string $settings, WP_Screen $screen ): string {
	if ( $screen->id !== 'tools_page_gf-views-analytics' ) {
		return $settings;
	}

	$current      = gfva_get_date_format();
	$wp_format    = get_option( 'date_format' );
	$custom_title = get_user_meta( get_current_user_id(), 'gfva_report_title', true );
	$custom_logo  = get_user_meta( get_current_user_id(), 'gfva_report_logo', true );

	$options = [
		'd/m/Y' => 'DD/MM/YYYY (' . date( 'd/m/Y' ) . ')',
		'm/d/Y' => 'MM/DD/YYYY (' . date( 'm/d/Y' ) . ')',
		'Y-m-d' => 'YYYY-MM-DD (' . date( 'Y-m-d' ) . ')',
		'd M Y' => 'DD Mon YYYY (' . date( 'd M Y' ) . ')',
		'd F Y' => 'DD Month YYYY (' . date( 'd F Y' ) . ')',
	];

	if ( ! isset( $options[ $wp_format ] ) ) {
		$options = [ $wp_format => 'WordPress default (' . date( $wp_format ) . ')' ] + $options;
	}

	// Date format
	$settings .= '<fieldset id="gfva-screen-options"><legend><strong>' . __( 'Views Analytics: Date Format', 'gf-views-analytics' ) . '</strong></legend>';
	$settings .= '<div style="display:flex;flex-direction:column;gap:6px;margin-top:8px;">';
	foreach ( $options as $value => $label ) {
		$checked   = checked( $current, $value, false );
		$settings .= sprintf(
			'<label style="font-weight:normal;"><input type="radio" name="gfva_date_format" class="gfva-date-format-radio" value="%s" %s> %s</label>',
			esc_attr( $value ),
			$checked,
			esc_html( $label )
		);
	}
	$settings .= '</div></fieldset>';

	// White label
	$settings .= '<fieldset id="gfva-screen-options-wl" style="margin-top:16px;"><legend><strong>' . __( 'Views Analytics: PDF White Label', 'gf-views-analytics' ) . '</strong></legend>';
	$settings .= '<div style="display:flex;flex-direction:column;gap:10px;margin-top:8px;">';

	$settings .= '<div>';
	$settings .= '<label style="font-weight:normal;display:block;margin-bottom:4px;" for="gfva_report_title">Report title</label>';
	$settings .= sprintf(
		'<input type="text" id="gfva_report_title" class="gfva-wl-field" data-field="report_title" value="%s" placeholder="GF Views Analytics Report" style="width:100%%;max-width:280px;">',
		esc_attr( $custom_title )
	);
	$settings .= '</div>';

	$settings .= '<div>';
	$settings .= '<label style="font-weight:normal;display:block;margin-bottom:4px;" for="gfva_report_logo">Logo</label>';
	$settings .= '<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">';
	$settings .= sprintf(
		'<input type="url" id="gfva_report_logo" class="gfva-wl-field" data-field="report_logo" value="%s" placeholder="https://example.com/logo.png" style="width:100%%;max-width:220px;">',
		esc_attr( $custom_logo )
	);
	$settings .= '<button type="button" class="button" id="gfva-logo-pick">Choose image</button>';
	$settings .= '</div>';
	if ( $custom_logo ) {
		$settings .= sprintf(
			'<img id="gfva-logo-preview" src="%s" style="display:block;margin-top:8px;max-height:40px;max-width:200px;">',
			esc_url( $custom_logo )
		);
	} else {
		$settings .= '<img id="gfva-logo-preview" src="" style="display:none;margin-top:8px;max-height:40px;max-width:200px;">';
	}
	$settings .= '<p style="margin:4px 0 0;font-size:11px;color:#757575;">Replaces the plugin logo and Views Analytics heading. Recommended height: 40px.</p>';
	$settings .= '</div>';

	$settings .= '<button type="button" class="button" id="gfva-wl-save">Save</button>';
	$settings .= '<span id="gfva-wl-saved" style="display:none;color:#46b450;font-size:12px;margin-left:8px;">Saved.</span>';
	$settings .= '</div></fieldset>';

	return $settings;
}

add_action( 'wp_ajax_gfva_save_date_format', 'gfva_ajax_save_date_format' );
function gfva_ajax_save_date_format() {
	check_ajax_referer( 'gfva_date_format_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Insufficient permissions.' );
	}

	$allowed = [ get_option( 'date_format' ), 'd/m/Y', 'm/d/Y', 'Y-m-d', 'd M Y', 'd F Y' ];
	$format  = sanitize_text_field( $_POST['format'] ?? '' );

	if ( ! in_array( $format, $allowed, true ) ) {
		wp_send_json_error( 'Invalid format.' );
	}

	update_user_meta( get_current_user_id(), 'gfva_date_format', $format );
	wp_send_json_success( [ 'format' => $format ] );
}

add_action( 'wp_ajax_gfva_save_white_label', 'gfva_ajax_save_white_label' );
function gfva_ajax_save_white_label() {
	check_ajax_referer( 'gfva_date_format_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Insufficient permissions.' );
	}

	$title = sanitize_text_field( $_POST['report_title'] ?? '' );
	$logo  = esc_url_raw( $_POST['report_logo'] ?? '' );

	update_user_meta( get_current_user_id(), 'gfva_report_title', $title );
	update_user_meta( get_current_user_id(), 'gfva_report_logo', $logo );

	wp_send_json_success( [ 'report_title' => $title, 'report_logo' => $logo ] );
}

function gfva_get_date_format(): string {
	$saved = get_user_meta( get_current_user_id(), 'gfva_date_format', true );
	return $saved ?: get_option( 'date_format' );
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
		$compare_granularity       = ( $compare_from === $compare_to ) ? 'hour' : $granularity;
		$result['compare']         = gfva_fetch_period( $form_ids, $compare_from, $compare_to, $compare_granularity, $include_entries );
		$result['compare_summary'] = gfva_fetch_summary( $form_ids, $compare_from, $compare_to, $include_entries );
	}

	wp_send_json_success( $result );
}

function gfva_local_to_utc( string $date, bool $end_of_day = false ): string {
	$time  = $end_of_day ? ' 23:59:59' : ' 00:00:00';
	$local = new DateTime( $date . $time, wp_timezone() );
	$local->setTimezone( new DateTimeZone( 'UTC' ) );
	return $local->format( 'Y-m-d H:i:s' );
}

function gfva_get_tz_offset(): string {
	$offset  = wp_timezone()->getOffset( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) );
	$hours   = intdiv( abs( $offset ), 3600 );
	$minutes = ( abs( $offset ) % 3600 ) / 60;
	$sign    = $offset >= 0 ? '+' : '-';
	return sprintf( '%s%02d:%02d', $sign, $hours, $minutes );
}

function gfva_fetch_period( array $form_ids, string $from, string $to, string $granularity, bool $include_entries ): array {
	global $wpdb;

	$date_format = match ( $granularity ) {
		'hour'  => '%Y-%m-%d %H:00',
		'week'  => '%Y-%u',
		'month' => '%Y-%m',
		default => '%Y-%m-%d',
	};

	$tz_offset = gfva_get_tz_offset();

	// ---- Views ----
	$views_sql = "
		SELECT DATE_FORMAT(CONVERT_TZ(date_created, '+00:00', %s), %s) AS period, SUM(count) AS total
		FROM {$wpdb->prefix}gf_form_view
		WHERE date_created BETWEEN %s AND %s
	";
	$params = [ $tz_offset, $date_format, gfva_local_to_utc( $from ), gfva_local_to_utc( $to, true ) ];

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

	// ---- Per-form views breakdown ----
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

	// ---- Per-form entries breakdown ----
	if ( $include_entries ) {
		$entry_breakdown_sql = "
			SELECT form_id, DATE_FORMAT(CONVERT_TZ(date_created, '+00:00', %s), %s) AS period, COUNT(*) AS total
			FROM {$wpdb->prefix}gf_entry
			WHERE status = 'active'
			  AND date_created BETWEEN %s AND %s
		";
		$ebp = [ $tz_offset, $date_format, gfva_local_to_utc( $from ), gfva_local_to_utc( $to, true ) ];

		if ( ! empty( $form_ids ) ) {
			$placeholders        = implode( ',', array_fill( 0, count( $form_ids ), '%d' ) );
			$entry_breakdown_sql .= " AND form_id IN ($placeholders)";
			$ebp                  = array_merge( $ebp, $form_ids );
		}

		$entry_breakdown_sql .= ' GROUP BY form_id, period ORDER BY period ASC';
		$entry_breakdown_rows = $wpdb->get_results( $wpdb->prepare( $entry_breakdown_sql, ...$ebp ) );

		$by_form_entries = [];
		foreach ( $entry_breakdown_rows as $row ) {
			$fid = (int) $row->form_id;
			if ( ! isset( $by_form_entries[ $fid ] ) ) {
				$by_form_entries[ $fid ] = [];
			}
			$by_form_entries[ $fid ][ $row->period ] = (int) $row->total;
		}
		$result['by_form_entries'] = $by_form_entries;
	}

	// ---- Entries time-series ----
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
 * Add admin links
 */
function gfva_add_menu_to_entries_menu() {
    if (
        ! isset( $_GET['page'] ) ||
        ! in_array( $_GET['page'], [ 'gf_entries', 'gf_edit_forms' ], true )
    ) {
        return;
    }

    ?>
    <script>
    jQuery(function($) {
        $('.subsubsub').append(
            '<li>| <a href="<?php echo admin_url( 'tools.php?page=gf-views-analytics' ); ?>">View Analytics</a></li>'
        );
    });
    </script>
    <?php
}
add_action( 'admin_footer', 'gfva_add_menu_to_entries_menu');

function gfva_admin_bar_menu( $wp_admin_bar ) {

    $wp_admin_bar->add_node( [
        'id'    => 'gfva_analytics',
        'title' => 'Form Analytics',
        'href'  => admin_url( 'tools.php?page=gf-views-analytics' ),
    ] );

}
add_action( 'admin_bar_menu', 'gfva_admin_bar_menu', 100 );