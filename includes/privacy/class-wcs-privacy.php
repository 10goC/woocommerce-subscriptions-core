<?php
/**
 * Privacy/GDPR related functionality which ties into WordPress functionality.
 *
 * @author   Prospress
 * @category Class
 * @package  WooCommerce Subscriptions\Privacy
 * @version  2.2.20
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WCS_Privacy extends WC_Abstract_Privacy {


	/**
	 * WCS_Privacy constructor.
	 */
	public function __construct() {
		parent::__construct( __( 'WooCommerce Subscriptions', 'woocommerce-subscriptions' ) );

		// include our exporters and erasers.
		include_once 'class-wcs-privacy-erasers.php';
		include_once 'class-wcs-privacy-exporters.php';

		$this->add_exporter( 'woocommerce-subscriptions-data', __( 'Subscriptions Data', 'woocommerce-subscriptions' ), array( 'WCS_Privacy_Exporters', 'subscription_data_exporter' ) );
		$this->add_eraser( 'woocommerce-subscriptions-data', __( 'Subscriptions Data', 'woocommerce-subscriptions' ), array( 'WCS_Privacy_Erasers', 'subscription_data_eraser' ) );
	}

	/**
	 * Attach callbacks.
	 */
	public function init() {
		parent::init();

		add_filter( 'woocommerce_subscription_bulk_actions', array( __CLASS__, 'add_remove_personal_data_bulk_action' ) );
		add_action( 'load-edit.php', array( __CLASS__, 'process_bulk_action' ) );
		add_action( 'woocommerce_remove_subscription_personal_data', array( 'WCS_Privacy_Erasers', 'remove_subscription_personal_data' ) );
		add_action( 'admin_notices', array( __CLASS__, 'bulk_admin_notices' ) );

		add_filter( 'woocommerce_account_settings', array( __CLASS__, 'add_caveat_to_order_data_retention_settings' ) );
		add_filter( 'woocommerce_account_settings', array( __CLASS__, 'add_subscription_data_retention_settings' ) );
	}

	/**
	 * Add the option to remove personal data from subscription via a bulk action.
	 *
	 * @since 2.2.20
	 * @param array $bulk_actions Subscription bulk actions.
	 */
	public static function add_remove_personal_data_bulk_action( $bulk_actions ) {
		$bulk_actions['remove_personal_data'] = __( 'Remove personal data', 'woocommerce-subscriptons' );
		return $bulk_actions;
	}

	/**
	 * Process the request to delete personal data from subscriptions via admin bulk action.
	 *
	 * @since 2.2.20
	 */
	public static function process_bulk_action() {

		// We only want to deal with shop_subscription bulk actions.
		if ( ! isset( $_REQUEST['post_type'] ) || 'shop_subscription' !== $_REQUEST['post_type'] || ! isset( $_REQUEST['post'] ) ) {
			return;
		}

		$action = '';

		if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] ) {
			$action = $_REQUEST['action'];
		} else if ( isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] ) {
			$action = $_REQUEST['action2'];
		}

		if ( 'remove_personal_data' !== $action ) {
			return;
		}

		$subscription_ids = array_map( 'absint', (array) $_REQUEST['post'] );
		$changed          = 0;
		$sendback_args    = array(
			'post_type'    => 'shop_subscription',
			'bulk_action'  => 'remove_personal_data',
			'ids'          => join( ',', $subscription_ids ),
			'error_count'  => 0,
		);

		foreach ( $subscription_ids as $subscription_id ) {
			$subscription = wcs_get_subscription( $subscription_id );

			if ( is_a( $subscription, 'WC_Subscription' ) ) {
				do_action( 'woocommerce_remove_subscription_personal_data', $subscription );
				$changed++;
			}
		}

		$sendback_args['changed'] = $changed;
		$sendback = add_query_arg( $sendback_args, wp_get_referer() ? wp_get_referer() : '' );

		wp_safe_redirect( esc_url_raw( $sendback ) );
		exit();
	}

	/**
	 * Add admin notice after processing personal data removal bulk action.
	 *
	 * @since 2.2.20
	 */
	public static function bulk_admin_notices() {
		global $post_type, $pagenow;

		if ( 'edit.php' !== $pagenow || 'shop_subscription' !== $post_type || ! isset( $_REQUEST['bulk_action'] ) || 'remove_personal_data' !== wc_clean( $_REQUEST['bulk_action'] ) ) {
			return;
		}

		$changed = isset( $_REQUEST['changed'] ) ? absint( $_REQUEST['changed'] ) : 0;
		$message = sprintf( _n( 'Removed personal data from %d subscription.', 'Removed personal data from %d subscriptions.', $changed, 'woocommerce-subscriptions' ), number_format_i18n( $changed ) );
		echo '<div class="updated"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Add a note to WC Personal Data Retention settings explaining that subscription orders aren't affected.
	 *
	 * @since 2.2.20
	 * @param array $settings WooCommerce Account and Privacy settings.
	 * @return array Account and Privacy settings.
	 */
	public static function add_caveat_to_order_data_retention_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		foreach ( $settings as &$setting ) {
			if ( isset( $setting['id'] ) && 'personal_data_retention' === $setting['id'] ) {
				// translators: placeholders are opening and closing tags.
				$setting['desc'] .= '<br>' . sprintf( __( '%sNote:%s Orders which are related to subscriptions will not be included in the orders affected by these settings.', 'woocommerce-subscriptions' ), '<b>', '</b>' );
			}
		}

		return $settings;
	}

	/**
	 * Add admin setting to turn subscription data removal when processing erasure requests on or off.
	 *
	 * @since 2.2.20
	 * @param array $settings WooCommerce Account and Privacy settings.
	 * @return array Account and Privacy settings.
	 */
	public static function add_subscription_data_retention_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		WC_Subscriptions_Admin::insert_setting_after( $settings, 'woocommerce_erasure_request_removes_order_data', array(
			'desc'          => __( 'Remove personal data from subscriptions', 'woocommerce-subscriptions' ),
			/* Translators: placeholders are opening and closing link tags linking to the erasure request screen. */
			'desc_tip'      => sprintf( __( 'When handling an %saccount erasure request%s, should personal data within subscriptions be retained or removed?', 'woocommerce-subscriptions' ), '<a href="' . esc_url( admin_url( 'tools.php?page=remove_personal_data' ) ) . '">' , '</a>' ),
			'id'            => 'woocommerce_erasure_request_removes_subscription_data',
			'type'          => 'checkbox',
			'default'       => 'no',
			'checkboxgroup' => '',
			'autoload'      => false,
		) );

		return $settings;
	}
}
