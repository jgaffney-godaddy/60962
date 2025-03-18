<?php

namespace WPaaS\Admin;

use WP_Admin_Bar;
use WPaaS\Helpers;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Admin_Notices {

	use Helpers;

	/**
	 * Admin bar object.
	 *
	 * @var WP_Admin_Bar
	 */
	private $admin_bar;

	/**
	 * Class constructor.
	 */
	public function __construct() {

		add_action( 'init', [ $this, 'init' ] );
	}


	/**
	 * Initialize the script.
	 *
	 * @action init
	 */
	public function init() {
		/**
		 * Update php version admin notice.
		 *
		 * @since php version is less than 8.0
		 */
		if ( version_compare( PHP_VERSION, 8.0 ) < 0 ) {
			$update_php_version_alert_text = self::get_update_php_alert_text();
			new Notice( $update_php_version_alert_text, [ 'error' ] );
		}

		/**
		 * Staging site admin notice.
		 *
		 * @since 2.0.11
		 */
		if ( self::is_staging_site() ) {
			new Notice( __( 'Note: This is a staging site.', 'gd-system-plugin' ), [ 'error' ] );
		}

		$cdn_full_page = defined( 'GD_CDN_FULLPAGE' ) ? GD_CDN_FULLPAGE : false;

		if ( is_admin() && false === $cdn_full_page && $GLOBALS['wpaas_feature_flag']->get_feature_flag_value( 'cdn_cohort_2',
				false ) ) {
			new Notice(
				__( 'Within the next 14 days, we are updating our Content Delivery Network (CDN). You can expect to see improved performance, with faster load times and a more secure environment for your data. We are committed to providing you with the best possible experience when using WordPress.',
					'gd-system-plugin' ),
				[ 'info' ], 'activate_plugins', true
			);
		}

		$cdn_static = defined( 'GD_CDN_ENABLED' ) ? GD_CDN_ENABLED : false;
		if ( is_admin() && false === $cdn_static && $GLOBALS['wpaas_feature_flag']->get_feature_flag_value( 'cdn_static_enable',
				false ) ) {
			new Notice(
				__( 'Within the next 30 days, we are enabling our improved Content Delivery Network (CDN) on your site. You can expect to see improved performance, specifically with the image assets on your site. We are committed to providing you with the best possible experience when using WordPress.',
					'gd-system-plugin' ),
				[ 'info' ], 'activate_plugins', true
			);
		}


	}

}
