<?php
//wp-plugin can be a url, no no!
if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
	die();
}

class DomainCheckWhoisData {

	private static $class_init = false;

	public static $whoisData = array(
		'app' => array(
			'available' => 'Domain not found.',
			'expires' => ''
		),
		//'arpa' => array(),
		'biz' => array(
			'available' => 'Not found: ',
			'expires' => 'Domain Expiration Date:',
			'expires_func' => 'co'
		),
		'ca' => array(
			'available' => 'Domain status:         available',
			'expires' => 'Expiry date: '
		),
		'cc' => array(
			'available' => 'No match for ',
			'expires' => 'Registry Expiry Date: '
		),
		'co' => array(
			'available' => 'Not found: ',
			'expires' => 'Domain Expiration Date: ',
		),
		'co.uk' => array(
			'available' => 'This domain name has not been registered.',
			'expires' => 'Expiry date: ',
			'expires_func' => 'com'
		),
		'com' => array(
			'available' => 'No match for ',
			'expires' => 'Expiration Date: ',
		),
		'com.au' => array(
			'available' => 'No Data Found',
			'expires' => '',
		),
		'de' => array(
			'available' => 'Status: free',
			'expires' => '',
		),
		'edu' => array(
			'available' => 'No Match',
			'expires' => 'Domain expires: ',
			'expires_func' => 'com'
		),
		//'fm' => array(),
		'fr' => array(
			'available' => 'No entries found',
			'expires' => 'Expiry Date: '
		),
		'gov' => array(
			'available' => 'No match for ',
			'expires' => '',
		),
		'io' => array(
			'available' => 'is available for purchase',
			'expires' => 'Expiry : ',
		),
		'info' => array(
			'available' => 'NOT FOUND',
			'expires' => 'Registry Expiry Date: ',
			'expires_func' => 'cc'
		),
		//'int' => array(),
		//'jobs' => array(),
		//'la' => array(),
		'ltd.uk' => array(
			'available' => 'No match for ',
			'expires' => 'Expiry date: ',
			'expires_func' => 'com'
		),
		//'marketing' => array(),
		//'me' => array(),
		//'mil' => array(),
		//'mobi' => array(),
		//'name' => array(),
		'net' => array(
			'available' => 'No match for ',
			'expires' => 'Expiration Date: ',
			'expires_func' => 'com'
		),
		'net.au' => array(
			'available' => 'No Data Found',
			'expires' => '',
		),
		//'nu' => array(),
		'org' => array(
			'available' => 'NOT FOUND',
			'expires' => 'Registry Expiry Date: ',
			'expires_func' => 'cc'
		),
		'org.uk' => array(
			'available' => 'No match for ',
			'expires' => 'Expiry date: ',
			'expires_func' => 'com',
		),
		//'pro' => array(),
		'sc' => array(
			'available' => 'NOT FOUND',
			'expires' => 'Expiration Date:',
			'expires_func' => 'com'
		),
		//'tel' => array(),
		//'travel' => array(),
		'us' => array(
			'available' => 'Not found: ',
			'expires' => 'Domain Expiration Date: ',
		),
		'ws' => array(
			'available' => 'No match for ',
			'expires' => 'Expiration Date: ',
			'expires_func' => 'io'
		),
		//'xxx' => array()
	);

	public static function ext_ca_expires($expiry_date) {
		$dateArr = explode('/', $expiry_date);
		return mktime(0, 0, 0, $dateArr[1], $dateArr[2], $dateArr[0]);
	}

	public static function ext_cc_expires($expiry_date) {
		$dateArr = explode('-', $expiry_date);
		$dayArr = explode('T', $dateArr[2]);
		return mktime(0, 0, 0, $dateArr[1], $dayArr[0], $dateArr[0]);
	}

	public static function ext_co_expires($expiry_date) {
		$dateArr = explode(' ', $expiry_date);
		return mktime(0, 0, 0, date('m', strtotime(ucfirst($dateArr[1]))), $dateArr[2], $dateArr[5]);
	}

	public static function ext_com_expires($expiry_date) {
		$dateArr = explode('-', $expiry_date);
		return mktime(0, 0, 0, date('m', strtotime(ucfirst($dateArr[1]))), $dateArr[0], $dateArr[2]);
	}

	public static function ext_io_expires($expiry_date) {
		$dateArr = explode('-', $expiry_date);
		return mktime(0, 0, 0, (int)$dateArr[1], (int)$dateArr[2], (int)$dateArr[0]);
	}

	public static function ext_us_expires($expiry_date) {
		$dateArr = explode(' ', $expiry_date);
		return mktime(0, 0, 0, date('m', strtotime(ucfirst($dateArr[1]))), $dateArr[2], $dateArr[5]);
	}

	public static function ext_co_in_expires($expiry_date) {
		$dateArr = explode(' ', $expiry_date);
		$dateArr = explode('-', $dateArr[0]);
		return mktime(0, 0, 0, date('m', strtotime(ucfirst($dateArr[1]))), $dateArr[0], $dateArr[2]);
	}

	public static function extension_supported($extension) {
		static::init();
		if (isset(static::$whoisData[$extension])) {
			return 1;
		} else {
			return 0;
		}
	}

	public static function get_expiration($extension, $data) {
		static::init();
		$replaced = str_replace('[[domain_check]]', '', DomainCheckWhoisData::$whoisData[$extension]['expires']);
		if ($replaced) {
			$whois_arr = explode("\n", $data);
			foreach ($whois_arr as $whois_arr_idx => $whois_arr_line) {
				if (strpos($whois_arr_line, $replaced) !== false) {
					$expiry_date = trim(str_replace($replaced, '', $whois_arr_line));
					$extension_func = $extension;
					if (isset(DomainCheckWhoisData::$whoisData[$extension]['expires_func'])) {
						$extension_func = DomainCheckWhoisData::$whoisData[$extension]['expires_func'];
					}
					if (method_exists('DomainCheckWhoisData', 'ext_' . strtolower(str_replace('.', '_', $extension_func)) . '_expires')) {
						return call_user_func_array(
							'DomainCheckWhoisData' . '::' . 'ext_' . strtolower(str_replace('.', '_', $extension_func)) . '_expires',
							array(
								$expiry_date
							)
						);
					}
				}
			}
		}
		return 0;
	}

	public static function get_status($extension, $data) {
		static::init();
		if (!$data || !static::$whoisData[$extension]['available']) {
			return 1;
		}
		if (strpos($data, static::$whoisData[$extension]['available']) !== false) {
			return 0;
		} else {
			return 1;
		}
	}

	public static function get_data() {
		static::init();
		return static::$whoisData;
	}

	public static function init() {
		if (!static::$class_init) {
			static::json_import();
			static::$class_init = true;
		}
	}

	public static function json_import() {
		if (is_file(dirname(__FILE__) . '/../db/whois.formats.json')) {
			ob_start();
			include (dirname(__FILE__) . '/../db/whois.formats.json');
			$whois_data = ob_get_contents();
			ob_end_clean();
			$whois_data = json_decode($whois_data, true);
			if ($whois_data && is_array($whois_data)) {
				static::$whoisData = array_merge($whois_data, static::$whoisData);
			}
		}
	}
}