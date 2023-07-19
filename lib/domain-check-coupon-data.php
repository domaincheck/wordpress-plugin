<?php
//wp-plugin can be a url, no no!
if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
	die();
}

class DomainCheckCouponData {

	private static $data = array();
	public static $class_init = false;
	public static $api_url = 'http://static.domaincheckplugin.com/';

	public static function get_data() {
		static::init();
		return static::$data;
	}

	public static function init() {
		if (!static::$class_init) {
			static::_db_import();
			$coupons_found = false;
			foreach (static::$data as $site => $site_data) {
				if (count($site_data['links']['link'])) {
					$coupons_found = true;
					break;
				}
			}

			//last resort
			if (!$coupons_found) {
				static::_json_import();
			}
		}
	}

	private static function _json_import() {
		$return_data = array();
		//if (!is_file(dirname(__FILE__) . '/../admin/cache/coupons.json')) {
		//	static::update();
		//}
		if (is_file(dirname(__FILE__) . '/../admin/cache/coupons.json')) {
			ob_start();
			include(dirname(__FILE__) . '/../admin/cache/coupons.json');
			$data = ob_get_contents();
			ob_end_clean();
			$data = json_decode($data, true);
			if ($data) {
				$return_data = $data;
			}
		}
		static::$data = $return_data;
		return static::$data;
	}

	private static function _db_import() {
		global $wpdb;

		$return_data = array();

		$sql = 'SELECT * FROM ' . DomainCheck::$db_prefix . '_coupons';

		$res = $wpdb->get_results( $sql, 'ARRAY_A' );
		if ($res && count($res)) {
			foreach ($res as $res_idx => $res_data) {
				if (isset($res_data['coupon_site']) && $res_data['coupon_site']) {
					if (!isset($return_data[$res_data['coupon_site']])) {
						$return_data[$res_data['coupon_site']] = array('links' => array('link'=> array()));
					} else {

					}
					$decoded_data = json_decode(gzuncompress($res_data['cache']), true);
					if ($decoded_data) {
						array_push($return_data[$res_data['coupon_site']]['links']['link'], $decoded_data);
					} else {
					}
				} else {
				}
			}
		} else {
		}

		static::$data = $return_data;
	}

	public static function search($needle, $language = 'all') {
		static::init();

		$ret = array();
		$needle_lower = strtolower($needle);
		$found = 0;
		foreach (static::$data as $coupon_site => $coupon_data) {
			$ret[$coupon_site] = array();
			$ret[$coupon_site]['links'] = array();
			$ret[$coupon_site]['links']['link'] = array();
			if (isset($coupon_data['links']['link'])) {
				foreach ($coupon_data['links']['link'] as $coupon_link_idx => $coupon_link_data) {
					if (isset($coupon_link_data['link-code-html'])) {
						if (strpos(strtolower($coupon_link_data['description']), $needle_lower) !== false) {
							if ($language != 'all' && $coupon_link_data['language'] != $language) {
								continue;
							}
							$ret[$coupon_site]['links']['link'][] = $coupon_link_data;
							$found++;
						}
					}
				}
			}
		}
		return $ret;
	}

	public static function update() {
		global $wpdb;

		//curl to get coupon data from S3...
		$url = static::$api_url . 'coupons.json';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		$coupons_raw = curl_exec($ch);
		curl_close($ch);

		$coupons_json = json_decode($coupons_raw, true);
		if ($coupons_json && count($coupons_json)) {

			//(too complicated in WP to do file caching for coupons)
			//$fp = fopen(dirname(__FILE__) . '/../admin/cache/coupons.json', 'w+' );
			//fwrite($fp, $coupons_raw);
			//fclose($fp);

			//clear DB
			$wpdb->query('DELETE FROM ' . DomainCheck::$db_prefix . '_coupons');
			$coupon_count = 0;
			foreach ($coupons_json as $coupon_idx => $coupon_data) {
				$coupon_data['updated'] = time();
				foreach ($coupon_data['links']['link'] as $coupon_link_idx => $coupon_link) {
					$valarr = array(
						'coupon_id' => $coupon_count,
						'coupon_site' => $coupon_idx,
						'cache' => gzcompress(json_encode($coupon_link))
					);
					$wpdb->insert(
						DomainCheck::$db_prefix . '_coupons',
						$valarr
					);
					$coupon_count++;
				}
			}

			static::$data = $coupons_json;

			if (get_option(DomainCheckConfig::OPTIONS_PREFIX . 'coupons_updated')) {
				update_option(DomainCheckConfig::OPTIONS_PREFIX . 'coupons_updated', time());
			} else {
				add_option(DomainCheckConfig::OPTIONS_PREFIX . 'coupons_updated', time());
			}

			return true;
		}
		return false;
	}

	public static function last_updated() {
		return get_option(DomainCheckConfig::OPTIONS_PREFIX . 'coupons_updated');
	}

	public static function valid_site($site) {
		static::init();
		if (isset(static::$data[$site])) {
			return true;
		}
		return false;
	}
}