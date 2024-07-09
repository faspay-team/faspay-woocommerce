<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class Faspay_Settings {

	public static $tab_name = 'faspay_settings';
	public static $option_prefix = 'faspay';
	public static function init() {
		$request = $_REQUEST;
		add_filter('woocommerce_settings_tabs_array', array(__CLASS__, 'add_faspay_settings_tab'), 50);
		add_action('woocommerce_settings_tabs_faspay_settings', array(__CLASS__, 'faspay_settings_page'));
		add_action('woocommerce_update_options_faspay_settings', array(__CLASS__, 'update_faspay_settings'));
		//      add_action( 'admin_enqueue_scripts', array(__CLASS__ , 'enqueue_scripts' ) );
	}

	public static function validate_configuration($request) {
		foreach ($request as $k => $v) {
			$key = str_replace('faspay_', '', $k);
			$options[$key] = $v;
		}
		return '';
	}

	public static function add_faspay_settings_tab($woocommerce_tab) {
		$woocommerce_tab[self::$tab_name] = 'Faspay ' . __('Global Configuration', 'wc-faspay');
		return $woocommerce_tab;
	}

	public static function faspay_settings_fields() {
		global $faspay_payments;

		$settings = apply_filters('woocommerce_' . self::$tab_name, array(
			array(
				'title' => 'Faspay ' . __('Global Configuration', 'wc-faspay'),
				'id' => self::$option_prefix . '_global_settings',
				'desc' => __('Ini adalah pengaturan global Faspay Payment Gateway. Mohon mengisi form di bawah ini, untuk dapat menggunakan payment channel yang telah tersedia.
				', 'wc-faspay'),
				'type' => 'title',
				'default' => '',
			),
			array(
				'title' => __('Merchant Name', 'wc_faspay'),
				'desc' => '<br />' . __('masukkan nama toko anda.', 'wc-faspay'),
				'id' => self::$option_prefix . '_merchant_name',
				'type' => 'text',
				'default' => '',
			),
			array(
				'title' => __('Merchant ID', 'wc_faspay'),
				'desc' => '<br />' . __('masukkan kode merchant id.', 'wc-faspay'),
				'id' => self::$option_prefix . '_merchant_code',
				'type' => 'text',
				'default' => '',
			),
			array(
				'title' => __('Debit Mechant Password', 'wc_faspay'),
				'desc' => '<br />' . __('masukkan password debit merchant.', 'wc-faspay'),
				'id' => self::$option_prefix. '_merchant_debit_password',
				'type' => 'password',
				'default' => '',
			),
			array(
				'title' => __('Credit Card Mechant Password', 'wc_faspay'),
				'desc' => '<br />' . __('masukkan password credit card merchant.', 'wc-faspay'),
				'id' => self::$option_prefix. '_merchant_credit_password',
				'type' => 'password',
				'default' => '',
			),
			array(
				'title' => __('Payment Expired Date', 'wc_faspay'),
				'desc' => '<br />' . __('in hours, ex. 12 means 12 hours', 'wc-faspay'),
				'id' => self::$option_prefix . '_merchant_expired',
				'type' => 'text',
				'default' => '12',
			),
			array(
				'title' => __('Environment', 'wc_faspay'),
				'id' => self::$option_prefix . '_merchant_env',
				'type' => 'radio',
				'options' => array(
					'development', 'production'
				),
				'default' => '0'
			),
		));
		return apply_filters('woocommerce_' . self::$tab_name, $settings);
	}

	/**
	 * Adds settings fields to the individual sections
	 * Calls from the hook "woocommerce_settings_tabs_" {tab_name}
	 *
	 * @param none
	 * @return void
	 */
	public static function faspay_settings_page() {
		woocommerce_admin_fields(self::faspay_settings_fields());
	}

	/**
	 * Updates settings fields from individual sections
	 * Calls from the hook "woocommerce_update_options_" {tab_name}
	 *
	 * @param none
	 * @return void
	 */
	public static function update_faspay_settings() {
		woocommerce_update_options(self::faspay_settings_fields());
	}

}

Faspay_Settings::init();
