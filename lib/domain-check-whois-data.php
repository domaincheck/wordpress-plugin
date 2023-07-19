<?php
//wp-plugin can be a url, no no!
if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
	die();
}

class DomainCheckWhoisData {

	private static $class_init = false;

	public static $whoisDataXml = null;

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
		'cn' => array (
			'expires' => 'Expiration Time: ',
			'expires_func' => 'cn'
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
		'me' => array(
			'expires' => 'Registry Expiry Date: ',
			'expires_func' => 'cc'
		),
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
		'top' => array(
			'expires' => 'Registry Expiry Date: ',
			'expires_func' => 'cc'
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
		)
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

	public static function ext_cn_expires($expiry_date) {
		$dateArr = explode(' ', $expiry_date);
		$dateDayArr = explode('-', $dateArr[0]);
		$dayMinArr = explode(':', $dateArr[1]);
		return mktime($dayMinArr[0], $dayMinArr[1], $dayMinArr[2], (int)$dateDayArr[1], (int)$dateDayArr[0], (int)$dateDayArr[2]);
	}

	public static function extension_supported($extension) {
		self::init();
		if (isset(self::$whoisData[$extension])) {
			return 1;
		} else {
			return 0;
		}
	}

	public static function get_expiration($extension, $data) {
		self::init();

		$replaced = null;
		if ( isset( DomainCheckWhoisData::$whoisData[$extension]['expires'] ) ) {
			$replaced = str_replace('[[domain_check]]', '', DomainCheckWhoisData::$whoisData[$extension]['expires']);
		}

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
		self::init();
		if (!$data || !self::$whoisData[$extension]['available']) {
			return 1;
		}
		$available = self::$whoisData[$extension]['available'];
		$data = strtolower($data);
		$res = strpos($data, $available);

		if (strpos($data, self::$whoisData[$extension]['available']) !== false) {
			return 0;
		} else {
			return 1;
		}
	}

	public static function get_data() {
		self::init();
		return self::$whoisData;
	}

	public static function init() {
		if (!self::$class_init) {

			self::xml_import();

			self::json_import();

			ksort(self::$whoisData);

			self::$class_init = true;
		}
	}

	public static function xml_import() {
		if (!self::$whoisDataXml) {
			self::$whoisDataXml = simplexml_load_file(dirname(__FILE__) . '/../db/whois-server-list.xml');
		}

		foreach (self::$whoisDataXml as $domain_xml_obj) {
			$name = null;
			$whois = null;
			$country_code = null;
			$registrar = null;

			if (
				count($domain_xml_obj->domain) ||
				isset($domain_xml_obj->registrationService) ||
				isset($domain_xml_obj->state) ||
				isset($domain_xml_obj->countryCode)
			) {
				foreach ( $domain_xml_obj->attributes() as $domain_attr_idx => $domain_attr) {
					//echo 'checking ' . substr($domain, $domain_len - strlen($domain_attr) - 1, strlen($domain_attr)) . ' ';
					if ( $domain_attr_idx == 'name' ) {

						$whois_server = $domain_xml_obj->whoisServer;
						$extension = strtolower(trim($domain_attr));
						if ($whois_server && $whois_server->attributes()) {
							foreach ($whois_server->attributes() as $whois_server_attr_idx => $whois_server_attr) {
								if ($whois_server_attr_idx == 'host') {
									if ( !isset( self::$whoisData[$extension] ) ) {
										self::$whoisData[$extension] = array();
									}
									if ( !isset(self::$whoisData[$extension]['whois']) ) {
										self::$whoisData[$extension]['whois'] = $whois_server_attr;
									}
									if ( $whois_server !== null && isset( $whois_server->availablePattern ) && $whois_server->availablePattern ) {
										//if we have pattern for a server prefer that server for lookups
										self::$whoisData[$extension]['whois'] = $whois_server_attr;
										$found_pattern = $whois_server->availablePattern;
										$found_pattern = str_replace('\Q', '', $found_pattern);
										$found_pattern = str_replace('\E', '', $found_pattern);
										self::$whoisData[$extension]['available'] = $found_pattern;
									}
								}
							}
						}
					}
				}
				foreach ( $domain_xml_obj->domain as $inner_domain_obj ) {
					if ( isset($inner_domain_obj->whoisServer) ) {
						foreach ( $inner_domain_obj->attributes() as $domain_attr_idx => $domain_attr ) {
							if ( $domain_attr_idx == 'name' ) {

								$whois_server = $domain_xml_obj->whoisServer;
								$extension = strtolower(trim($domain_attr));
								if ($whois_server && $whois_server->attributes()) {
									foreach ($whois_server->attributes() as $whois_server_attr_idx => $whois_server_attr) {
										if ($whois_server_attr_idx == 'host') {
											if ( !isset( self::$whoisData[$extension] ) ) {
												self::$whoisData[$extension] = array();
											}
											self::$whoisData[$extension]['whois'] = $whois_server_attr;
											if ( $whois_server !== null && isset( $whois_server->availablePattern ) && $whois_server->availablePattern ) {
												$found_pattern = $whois_server->availablePattern;
												$found_pattern = str_replace('\Q', '', $found_pattern);
												$found_pattern = str_replace('\E', '', $found_pattern);
												$found_pattern = trim($found_pattern);

												if ( $found_pattern && !isset( self::$whoisData[$extension]['available'] ) ) {
													self::$whoisData[$extension]['available'] = $found_pattern;
												}
											}
										}
									}
								}

							}
						}
					}
				}
			}
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
				foreach ($whois_data as $extension => $extension_data) {
					if ( !isset( self::$whoisData[$extension] ) ) {
						self::$whoisData[$extension] = array();
					}
					//available
					if (
						isset($extension_data['available'])
						&& $extension_data['available']
						&& (
							!isset( self::$whoisData[$extension]['available'] )
							|| !self::$whoisData[$extension]['available']
						)
					) {
						self::$whoisData[$extension]['available'] = $extension_data['available'];
					}
					//expires
					if (
						isset($extension_data['expires'])
						&& $extension_data['expires']
						&& (
							!isset( self::$whoisData[$extension]['expires'] )
							|| !self::$whoisData[$extension]['expires']
						)
					) {
						self::$whoisData[$extension]['expires'] = $extension_data['expires'];
					}
					//self::$whoisData = array_merge($whois_data, self::$whoisData);
				}
			}
		}
	}

}