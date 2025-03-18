<?php
/**
 * Google Analytics
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Google Analytics to newer
 * versions in the future. If you wish to customize Google Analytics for your
 * needs please refer to https://help.godaddy.com/help/40882 for more information.
 *
 * @author      SkyVerge
 * @copyright   Copyright (c) 2015-2024, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace GoDaddy\WordPress\MWC\GoogleAnalytics\Tracking\Events\Universal_Analytics;

use GoDaddy\WordPress\MWC\GoogleAnalytics\Tracking;
use GoDaddy\WordPress\MWC\GoogleAnalytics\Tracking\Events\Universal_Analytics_Event;

defined( 'ABSPATH' ) or exit;

/**
 * The "viewed order" event.
 *
 * @since 3.0.0
 */
class Viewed_Order_Event extends Universal_Analytics_Event {


	/** @var string the event ID */
	public const ID = 'viewed_order';

	/** @var string the event trigger action hook  */
	protected string $trigger_hook = 'woocommerce_view_order';


	/**
	 * @inheritdoc
	 */
	public function get_form_field_title(): string {

		return __( 'Viewed Order', 'woocommerce-google-analytics-pro' );
	}


	/**
	 * @inheritdoc
	 */
	public function get_form_field_description(): string {

		return __( 'Triggered when a customer views an order.', 'woocommerce-google-analytics-pro' );
	}


	/**
	 * @inheritdoc
	 */
	public function get_default_name(): string {

		return 'viewed order';
	}


	/**
	 * @inheritdoc
	 *
	 * @param int $order_id the order ID
	 */
	public function track( $order_id = null ): void {

		if ( Tracking::not_page_reload() && $order = wc_get_order( $order_id ) ) {

			$this->record_via_api( [
				'eventCategory'  => 'Orders',
				'eventLabel'     => $order->get_order_number(),
				'nonInteraction' => true,
			] );
		}
	}


}
