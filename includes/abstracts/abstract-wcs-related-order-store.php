<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define requirements for a related order data store and provide method for accessing active data store.
 *
 * Orders can have a special relationship with a subscription if they are used to record a subscription related
 * transaction, like a renewal, upgrade/downgrade (switch) or resubscribe. The related order data store provides
 * a set of public APIs that can be used to query and manage that relationship.
 *
 * Parent orders are not managed via this data store as the order data stores inherited by Subscriptions already
 * provide APIs for managing the parent relationship.
 *
 * @version  2.3.0
 * @since    2.3.0
 * @category Class
 * @author   Prospress
 */
abstract class WCS_Related_Order_Store {

	/** @var WCS_Related_Order_Store */
	private static $instance = null;

	/**
	 * Types of relationships the data store supports.
	 *
	 * @var array
	 */
	private $relation_types = array(
		'renewal',
		'switch',
		'resubscribe',
	);

	/**
	 * Get the active related order data store.
	 *
	 * @return WCS_Related_Order_Store
	 */
	final public static function instance() {

		if ( empty( self::$instance ) ) {
			if ( ! did_action( 'plugins_loaded' ) ) {
				wcs_doing_it_wrong( __METHOD__, 'This method was called before the "plugins_loaded" hook. It applies a filter to the related order data store instantiated. For that to work, it should first be called after all plugins are loaded.', '2.3.0' );
			}
			$class = apply_filters( 'wcs_related_order_store_class', 'WCS_Related_Order_Store_Cached_CPT' );
			self::$instance = new $class();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Stub for initialising the class outside the constructor, for things like attaching callbacks to hooks.
	 */
	protected function init() {}

	/**
	 * Get orders related to a given subscription with a given relationship type.
	 *
	 * @param WC_Order $subscription The order or subscription for which calling code wants to find related orders.
	 * @param string $relation_type The relationship between the subscription and the orders. Must be 'renewal', 'switch' or 'resubscribe' unless custom relationships are implemented.
	 *
	 * @return array
	 */
	abstract public function get_related_order_ids( WC_Order $subscription, $relation_type );

	/**
	 * Find subscriptions related to a given order in a given way, if any.
	 *
	 * @param WC_Order $order An order that may be linked with subscriptions.
	 * @param string $relation_type The relationship between the subscription and the orders. Must be 'renewal', 'switch' or 'resubscribe.
	 * @return array
	 */
	abstract public function get_related_subscription_ids( WC_Order $order, $relation_type );

	/**
	 * Helper function for linking an order to a subscription via a given relationship.
	 *
	 * @param WC_Order $order The order to link with the subscription.
	 * @param WC_Order $subscription The order or subscription to link the order to.
	 * @param string $relation_type The relationship between the subscription and the order. Must be 'renewal', 'switch' or 'resubscribe' unless custom relationships are implemented.
	 */
	abstract public function add_relation( WC_Order $order, WC_Order $subscription, $relation_type );

	/**
	 * Remove the relationship between a given order and subscription.
	 *
	 * @param WC_Order $order An order that may be linked with subscriptions.
	 * @param WC_Order $subscription A subscription or order to unlink the order with, if a relation exists.
	 * @param string $relation_type The relationship between the subscription and the order. Must be 'renewal', 'switch' or 'resubscribe' unless custom relationships are implemented.
	 */
	abstract public function delete_relation( WC_Order $order, WC_Order $subscription, $relation_type );

	/**
	 * Remove all related orders/subscriptions of a given type from an order.
	 *
	 * @param WC_Order $order An order that may be linked with subscriptions.
	 * @param string $relation_type The relationship between the subscription and the order. Must be 'renewal', 'switch' or 'resubscribe' unless custom relationships are implemented.
	 */
	abstract public function delete_relations( WC_Order $order, $relation_type );

	/**
	 * Types of relationships the data store supports.
	 *
	 * @return array The possible relationships between a subscription and orders. Includes 'renewal', 'switch' or 'resubscribe' by default.
	 */
	protected function get_relation_types() {
		return $this->relation_types;
	}

	/**
	 * Check if a given relationship is supported by the data store.
	 *
	 * @param string $relation_type The relationship between the subscription and the order. Must be 'renewal', 'switch' or 'resubscribe' unless custom relationships are implemented.
	 *
	 * @throws InvalidArgumentException If the given order relation is not a known type.
	 */
	protected function check_relation_type( $relation_type ) {
		if ( ! in_array( $relation_type, $this->get_relation_types() ) ) {
			throw new InvalidArgumentException( sprintf( __( 'Invalid relation type: %s. Order relationship type must be one of: %s.', 'woocommerce-subscriptions' ), $relation_type, implode( ', ', $this->get_relation_types() ) ) );
		}
	}
}
