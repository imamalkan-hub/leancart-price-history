<?php
/**
 * Plugin Name: LeanCart Price History
 * Plugin URI:  https://garagedoorlabs.com/
 * Description: Records the old price each time a product price changes. Show the value of your campaign on the product page with a "Was 500 TL, now 399 TL" display.
 * Version:     1.0.0
 * Author:      LeanCart
 * Author URI:  https://leancart.dev
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: price-history
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LEANCART_PRICE_HISTORY_DEMO_SITE' ) ) {
	define( 'LEANCART_PRICE_HISTORY_DEMO_SITE', 'https://garagedoorlabs.com' );
}

require_once __DIR__ . '/includes/class-leancart-framework.php';

final class LeanCart_Price_History {

	const SLUG = 'price-history';

	public static function init() {
		LeanCart_Framework::boot( array(
			'slug'    => self::SLUG,
			'name'    => 'LeanCart Price History',
			'version' => '1.0.0',
			'file'    => __FILE__,
			'site'    => LEANCART_PRICE_HISTORY_DEMO_SITE,
		) );

		// WooCommerce may load after these plugins; defer the check to runtime.
		add_action( 'admin_notices', array( __CLASS__, 'wc_notice' ) );

		load_plugin_textdomain( 'price-history', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		add_action( 'leancart_' . self::SLUG . '_settings_fields', array( __CLASS__, 'fields' ) );

		// WooCommerce may load after this plugin; hooks are registered once WC is ready.
		if ( LeanCart_Framework::is_wc() ) {
			self::hooks();
		} else {
			add_action( 'woocommerce_loaded', array( __CLASS__, 'hooks' ) );
		}
	}

	public static function wc_notice() {
		if ( LeanCart_Framework::is_wc() ) { return; }
		echo '<div class="notice notice-warning"><p><strong>' . esc_html( 'LeanCart Price History' ) . '</strong>: ';
		esc_html_e( 'This plugin requires WooCommerce to be active.', 'price-history' );
		echo '</p></div>';
	}

	public static function fields() {
		LeanCart_Framework::field_checkbox( 'show_was', __('Show previous price', 'price-history'), __('Show on the product page.', 'price-history') );
	}


	public static function hooks() {
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'record' ), 20 );
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'show' ), 20, 2 );
		add_shortcode( 'lc_lowest', array( __CLASS__, 'shortcode' ) );
	}

	private static function history( $id ) {
		$h = get_option( 'lc_price_history_' . $id, array() );
		return is_array( $h ) ? $h : array();
	}

	public static function record( $id ) {
		$product = wc_get_product( $id );
		if ( ! $product ) { return; }
		$price = (float) $product->get_regular_price();
		if ( $price <= 0 ) { return; }
		$h = self::history( $id );
		$last = end( $h );
		if ( $last && (float) $last['price'] === $price ) { return; }
		$h[] = array( 'price' => $price, 'time' => time() );
		if ( count( $h ) > 30 ) { array_shift( $h ); }
		update_option( 'lc_price_history_' . $id, $h, false );
	}

	public static function show( $html, $product ) {
		if ( 'yes' !== LeanCart_Framework::setting( 'show_was', 'yes' ) || ! function_exists( 'is_product' ) || ! is_product() ) { return $html; }
		$id = $product->get_id();
		$h  = self::history( $id );
		if ( count( $h ) < 2 ) { return $html; }
		$prev = $h[ count( $h ) - 2 ]['price'];
		$now  = (float) $product->get_price();
		if ( $prev <= $now ) { return $html; }
		$html .= '<br><small class="lc-was" style="color:#646970">' . wp_kses_post( sprintf( __( 'Was: %s', 'price-history' ), LeanCart_Framework::money( $prev ) ) ) . '</small>';
		return $html;
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'lc_lowest' );
		$h = self::history( (int) $atts['id'] );
		if ( ! $h ) { return ''; }
		$prices = wp_list_pluck( $h, 'price' );
		if ( ! $prices ) { return ''; }
		return wp_kses_post( LeanCart_Framework::money( min( $prices ) ) );
	}


}

LeanCart_Price_History::init();

