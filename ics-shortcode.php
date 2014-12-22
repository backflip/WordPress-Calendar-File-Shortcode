<?php
/**
 * Plugin Name: Calendar File Shortcode
 * Plugin URI: -
 * Description: Shortcode to render a link to an .ics calendar file. Usage: [calendar start="31.12.2014 22:00" end="01.01.2015 05:00" title="Party" location="Kugl, St. Gallen" description="Lorem ipsum" link="http://www.kugl.ch"]
 * Version: 0.0.1
 * Author: Thomas Jaggi
 * Author URI: http://responsive.ch
 * License: MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_ICS_Shortcode {
	protected $plugin_name;

	protected $download_url;
	protected $download_url_param;

	public function __construct() {
		global $wpdb;

		$this->plugin_name = 'ics-shortcode';

		$this->download_url = '/download/entry.ics';
		$this->download_url_param = 'id';

		$this->db_table_name = $wpdb->prefix . 'plugins_' . str_replace('-', '', $this->plugin_name);

		// Add shortcode
		add_shortcode('calendar', array( $this, 'add_shortcode' ));

		// Add redirect handler for download links
		add_action( 'template_redirect', array( $this, 'template_redirect' ));
	}

	public function activate() {
		$this->create_table();
	}

	public function deactivate() {
		$this->remove_table();
	}


	/**
	 * Main functionality (shortcode output and download URL)
	 */

	public function add_shortcode( $atts ) {
		$options = shortcode_atts( array(
			'start' => null,
			'end' => null,
			'title' => null,
			'description' => '',
			'location' => '',
			'link' => '',
			'filename' => 'entry.ics',
			'linkclass' => 'calendar',
			'linktext' => 'Add to calendar'
		), $atts );

		$mandatory_options = array( 'start', 'end', 'title' );
		$date_options = array( 'start', 'end' );

		$missing_option = null;
		$invalid_option = null;

		foreach ( $mandatory_options as $param ) {
			if ( ! $options[$param] ) {
				$missing_option = $param;
				break;
			}
		}

		foreach ( $date_options as $param ) {
			if ( ! $this->parse_date( $options[$param] ) ) {
				$invalid_option = $param;
				break;
			}
		}

		// Render warning if mandatory options are missing
		if ( $missing_option ) {
			return 'Calendar Shortcode: Missing "' . $missing_option . '" parameter';
		}

		// Render warning if start or end date are not parseable by strtotime
		if ( $invalid_option ) {
			return 'Calendar Shortcode: Parameter "' . $invalid_option . '" not parseable by strtotime. Please use valid format.';
		}

		// Save to database and get row ID
		$id = $this->get_id( $options );

		// Render link
		return '<a href="' . $this->get_download_url( $id ) . '" class="' . $options['linkclass'] . '">' . $options['linktext'] . '</a>';
	}
	
	public function template_redirect( ) {
		$url = strtok( $_SERVER['REQUEST_URI'], '?' );
		$id = $_GET['id'];

		// Do nothing if URL does not correspond to setting
		if ( $url = $this->download_url ) {
			// Do nothing if no row with this ID is found
			if ( $options = $this->get_options( $id ) ) {
				header( 'Content-type: text/calendar; charset=utf-8', true, 200 );
				header( 'Content-Disposition: attachment; filename=' . $options['filename'] );

				echo $this->get_download_content( $options );

				exit;
			}
		}
	}
	

	/**
	 * Database helpers
	 */

	protected function create_table() {
		$sql = "CREATE TABLE $this->db_table_name (
			id int(11) NOT NULL AUTO_INCREMENT,
			hash char(32) NOT NULL,
			options text NOT NULL,
			UNIQUE KEY id (id)
		);";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		dbDelta( $sql );
	}

	protected function remove_table() {
		global $wpdb;

		$wpdb->query("DROP TABLE IF EXISTS `$this->db_table_name`");
	}

	protected function get_id( $options ) {
		global $wpdb;

		$serialized = serialize( $options );

		$hash = md5( $serialized );

		if ( ! ( $row = $this->get_row( null, $hash ) ) ) {
			$wpdb->insert(
				$this->db_table_name,
				array(
					'hash' => $hash,
					'options' => $serialized
				),
				array(
					'%s',
					'%s' 
				)
			);

			$id = $wpdb->insert_id;
		} else {
			$id = $row->id;
		}

		return $id;
	}

	protected function get_options( $id ) {
		global $wpdb;

		$options = null;

		if ( $row = $this->get_row( $id ) ) {
			$options = unserialize( $row->options );
		}

		return $options;
	}

	protected function get_row( $id, $hash = null ) {
		global $wpdb;

		if ( $hash ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->db_table_name WHERE hash = %s", $hash ) );
		} else {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->db_table_name WHERE id = %d", $id ) );
		}

		return $row;
	}


	/**
	 * .ics download helpers
	 */

	protected function get_download_url( $id ) {
		$url = add_query_arg( array(
			$this->download_url_param => $id
		), $this->download_url );

		return $url;
	}

	protected function get_download_content( $options ) {
		$uniqid = uniqid();

		// Based on https://gist.github.com/jakebellacera/635416
		return <<<EOT
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//hacksw/handcal//NONSGML v1.0//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
DTEND:{$this->format_date( $options["end"] )}
UID:{$uniqid}
DTSTAMP:{$this->format_date( time() )}
LOCATION:{$this->escape_string( $options['location'] )}
DESCRIPTION:{$this->escape_string( $options['description'] )}
URL;VALUE=URI:{$this->escape_string( $options['link'] )}
SUMMARY:{$this->escape_string( $options['title'] )}
DTSTART:{$this->format_date( $options['start'] )}
END:VEVENT
END:VCALENDAR
EOT;
	}

	protected function escape_string( $string ) {
		return preg_replace( '/( [\,;] )/','\\\$1', $string );
	}

	protected function parse_date( $date_string ) {
		return strtotime( $date_string );
	}

	protected function format_date( $date_string ) {
		date_default_timezone_set( $this->get_timezone_string() );

		$date = $this->parse_date( $date_string );
		$format = 'Ymd\THis\Z';

		return gmdate( $format, $date );
	}

	// http://www.skyverge.com/blog/down-the-rabbit-hole-wordpress-and-timezones/
	protected function get_timezone_string() {
		// if site timezone string exists, return it
		if ( $timezone = get_option( 'timezone_string' ) ) {
			return $timezone;
		}
	 
		// get UTC offset, if it isn't set then return UTC
		if ( 0 === ( $utc_offset = get_option( 'gmt_offset', 0 ) ) ) {
			return 'UTC';
		}
	 
		// adjust UTC offset from hours to seconds
		$utc_offset *= 3600;
	 
		// attempt to guess the timezone string from the UTC offset
		if ( $timezone = timezone_name_from_abbr( '', $utc_offset, 0 ) ) {
			return $timezone;
		}
	 
		// last try, guess timezone string manually
		$is_dst = date( 'I' );
	 
		foreach ( timezone_abbreviations_list() as $abbr ) {
			foreach ( $abbr as $city ) {
				if ( $city['dst'] == $is_dst && $city['offset'] == $utc_offset )
					return $city['timezone_id'];
			}
		}
		 
		// fallback to UTC
		return 'UTC';
	}
}

$wp_ics_shortcode = new WP_ICS_Shortcode();

register_activation_hook( __FILE__, array( $wp_ics_shortcode, 'activate' ) );
register_deactivation_hook( __FILE__, array( $wp_ics_shortcode, 'deactivate' ) );
