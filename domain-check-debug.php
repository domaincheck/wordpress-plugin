<?php

class DomainCheckDebug {
	public static function debug() {

		if ( (defined( 'WP_INSTALLING' ) && WP_INSTALLING === true) || !is_admin() ) {
			return;
		}

		if ( isset($_GET['test_cron']) ) {
			error_log('hellO!');
			wp_clear_scheduled_hook('domain_check_cron_email');
			wp_clear_scheduled_hook('domain_check_cron_email');
			wp_clear_scheduled_hook('domain_check_cron_coupons');
			wp_clear_scheduled_hook('domain_check_cron_coupons');
			wp_clear_scheduled_hook('domain_check_cron_watch');
			wp_clear_scheduled_hook('domain_check_cron_watch');
			wp_clear_scheduled_hook('domain_check_cron_watch');

			wp_clear_scheduled_hook('domain_check');
			wp_clear_scheduled_hook('domain_check');
			wp_clear_scheduled_hook('domain_check_cron');
			wp_clear_scheduled_hook('domain_check_cron');

			if (!wp_get_schedule('domain_check_cron_email')) {
				$res = wp_schedule_event(time(), 'daily', 'domain_check_cron_email');
				error_log('email scheduled '.print_r($res, true));
			} else {
				error_log('email already scheduled');
			}

			if (!wp_get_schedule('domain_check_cron_coupons')) {
				$res = wp_schedule_event(time() + 10, 'daily', 'domain_check_cron_coupons');
				error_log('coupon scheduled '.print_r($res, true));
			} else {
				error_log('coupons already scheduled');
			}

			if (!wp_get_schedule('domain_check_cron_watch')) {
				$res = wp_schedule_event(time() + 20, 'daily', 'domain_check_cron_watch');
				error_log('watch schedule '.print_r($res, true));
			} else {
				error_log('watch already scheduled');
			}

			error_log('hello!');
		}

		if (isset($_GET['test_option'])) {
			//add wp-plugin options...
			foreach(DomainCheckConfig::$options as $key => $value) {
				delete_option($key);
			}
			if (isset(DomainCheckConfig::$options)) {
				foreach(DomainCheckConfig::$options as $key => $value) {
					delete_option($key);
				}
			}

			//add wp-plugin options...
			foreach(DomainCheckConfig::$options as $key => $value) {
				add_option($key, $value);
			}

			//add wp-plugin options...
			foreach(DomainCheckConfig::$options[DomainCheckConfig::OPTIONS_PREFIX . 'settings'] as $key => $value) {
				echo $key . ': ' . print_r(get_option(DomainCheckConfig::OPTIONS_PREFIX . $key), true) . '<br>';
			}
		}

		if (isset($_GET['test_show_option'])) {
			//add wp-plugin options...
			foreach(DomainCheckConfig::$options[DomainCheckConfig::OPTIONS_PREFIX . 'settings'] as $key => $value) {
				echo $key . ': ' . print_r(get_option(DomainCheckConfig::OPTIONS_PREFIX . $key), true) . '<br>';
			}
		}


	}
}