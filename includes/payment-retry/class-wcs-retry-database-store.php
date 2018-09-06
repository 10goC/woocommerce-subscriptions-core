<?php
/**
 * Store retry details in the WordPress custom table.
 *
 * @package        WooCommerce Subscriptions
 * @subpackage     WCS_Retry_Store
 * @category       Class
 * @author         Prospress
 * @since          2.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WCS_Retry_Database_Store extends WCS_Retry_Store {

	/**
	 * Custom table name we're using to store our retries data.
	 *
	 * @var string
	 */
	const TABLE_NAME = 'wcs_payment_retries';

	/**
	 * Init method.
	 */
	public function init() {
		add_filter( 'date_query_valid_columns', array( $this, 'add_date_valid_column' ) );
	}

	/**
	 * Save the details of a retry to the database
	 *
	 * @param WCS_Retry $retry the Retry we want to save.
	 *
	 * @return int the retry's ID
	 * @since 2.4
	 */
	public function save( WCS_Retry $retry ) {
		global $wpdb;

		$query_data   = array(
			'order_id' => $retry->get_order_id(),
			'status'   => $retry->get_status(),
			'date_gmt' => $retry->get_date_gmt(),
			'rule_raw' => wp_json_encode( $retry->get_rule()->get_raw_data() ),
		);
		$query_format = array(
			'%d',
			'%s',
			'%s',
			'%s',
		);

		if ( $retry->get_id() > 0 ) {
			$query_data['retry_id'] = $retry->get_id();
			$query_format[]         = '%d';
		}

		if ( $retry->get_id() && $this->get_retry( $retry->get_id() ) ) {
			$wpdb->update(
				$this->get_full_table_name(),
				$query_data,
				array( 'retry_id' => $retry->get_id() ),
				$query_format
			);

			$retry_id = absint( $retry->get_id() );
		} else {
			$wpdb->insert(
				$this->get_full_table_name(),
				$query_data,
				$query_format
			);

			$retry_id = absint( $wpdb->insert_id );
		}

		return $retry_id;
	}

	/**
	 * Get the details of a retry from the database
	 *
	 * @param int $retry_id The retry we want to get.
	 *
	 * @return null|WCS_Retry
	 * @since 2.4
	 */
	public function get_retry( $retry_id ) {
		global $wpdb;

		$retry     = null;
		$raw_retry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_full_table_name()} WHERE retry_id = %d LIMIT 1",
				$retry_id
			)
		);

		if ( $raw_retry ) {
			$retry = new WCS_Retry( array(
				'id'       => $raw_retry->retry_id,
				'order_id' => $raw_retry->order_id,
				'status'   => $raw_retry->status,
				'date_gmt' => $raw_retry->date_gmt,
				'rule_raw' => json_decode( $raw_retry->rule_raw ),
			) );
		}

		return $retry;
	}

	/**
	 * Deletes a retry.
	 *
	 * @param int $retry_id
	 *
	 * @return bool
	 * @since 2.4
	 */
	public function delete_retry( $retry_id ) {
		global $wpdb;

		return (bool) $wpdb->delete( $this->get_full_table_name(), array( 'retry_id' => $retry_id ), array( '%d' ) );
	}

	/**
	 * Get a set of retries from the database
	 *
	 * @param array $args A set of filters.
	 *
	 * @return array An array of WCS_Retry objects
	 * @since 2.4
	 */
	public function get_retries( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'status'     => 'any',
			'date_query' => array(),
		) );

		$where = '';
		if ( 'any' !== $args['status'] ) {
			$where .= $wpdb->prepare(
				' WHERE status = %s',
				$args['status']
			);
		}
		if ( ! empty( $args['date_query'] ) ) {
			$date_query = new WP_Date_Query( $args['date_query'], 'date_gmt' );
			$where      .= $date_query->get_sql();
		}

		$raw_retries = $wpdb->get_results( "SELECT * FROM {$this->get_full_table_name()} {$where} ORDER BY date_gmt DESC" );

		foreach ( $raw_retries as $raw_retry ) {
			$raw_retries[ $raw_retry->retry_id ] = new WCS_Retry( array(
				'id'       => $raw_retry->retry_id,
				'order_id' => $raw_retry->order_id,
				'status'   => $raw_retry->status,
				'date_gmt' => $raw_retry->date_gmt,
				'rule_raw' => json_decode( $raw_retry->rule_raw ),
			) );
		}

		return $raw_retries;
	}

	/**
	 * Get the IDs of all retries from the database for a given order
	 *
	 * @param int $order_id the order we want to get the retries for.
	 *
	 * @return array
	 * @since 2.4
	 */
	public function get_retry_ids_for_order( $order_id ) {
		global $wpdb;

		$retry_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT retry_id FROM {$this->get_full_table_name()} WHERE order_id = %d ORDER BY retry_id ASC",
				$order_id
			)
		);

		return $retry_ids;
	}

	/**
	 * Adds our table column to WP_Date_Query valid columns.
	 *
	 * @param array $columns Columns array we want to modify.
	 *
	 * @return array
	 * @since 2.4
	 */
	public function add_date_valid_column( $columns ) {
		$columns[] = 'date_gmt';

		return $columns;
	}

	/**
	 * Returns the table name for public use.
	 *
	 * @return string
	 * @since 2.4
	 */
	public static function get_full_table_name() {
		global $wpdb;

		return $wpdb->prefix . self::TABLE_NAME;
	}
}
