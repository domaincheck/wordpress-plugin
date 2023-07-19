<?php

//wp-plugin can be a url, no no!
if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
	die();
}

//define wp-plugin class, must be self contained!
if(!class_exists('DomainCheckConfig')) {
	class DomainCheckConfig {

		const OPTIONS_PREFIX = 'domain_check_';

		static public $options = array(
			'version' => '1.0.0',
			'coupons_update' => 0,
			'settings' => array(
				'email_additional_emails' => array(),
				'email_primary_email' => '',
				'domain_extension_favorites' => array(
					'com',
					'net',
					'org',
					'co',
					'io',
					'biz'
				)
			)
		);

		public function __construct() {
			//attach prefix to options
			foreach (static::$options as $key => $val) {
				static::$options[static::OPTIONS_PREFIX . $key] = $val;
				unset(static::$options[$key]);
			}
		}
	}

	$domain_check_config = new DomainCheckConfig();
}