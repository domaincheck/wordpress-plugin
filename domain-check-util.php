<?php

//wp-plugin can be a url, no no!
if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
	die();
}

//define wp-plugin class, must be self contained!
if(!class_exists('DomainCheckUtil')) {
	class DomainCheckUtil {
		public function __construct() {}

		public static function cli($message) {
			echo $message . "\n";
		}
	}

	$domain_check_util = new DomainCheckUtil();
}