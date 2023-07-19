<?php
//wp-plugin can be a url, no no!
if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
	die();
}

require_once(dirname(__FILE__) . '/domain-check-whois-data.php');

class DomainCheckWhois {

	private static $m_data = null;
	private static $m_init = false;

	public static function init() {
		if (!static::$m_init) {
			if (!static::$m_data) {
				static::$m_data = simplexml_load_file(dirname(__FILE__) . '/../db/whois-server-list.xml');
			}
			static::$m_init = true;
		}
	}

	public static function dolookup($domain, $raw = false) {
		static::init();
		//echo 'CHECKING FOR....... ' . $domain;
		$connectiontimeout = 5;
		$sockettimeout = 15;
		$domain = strtolower($domain);
		$domain_len = strlen($domain);
		$possible_tld = '';
		$whois_servers = array();
		$data = '';
		$extension = '';
		//if( $domain == '' || $server == '' ) {
		//	return false;
		//}

		if (!static::$m_data) {
			static::$m_data = simplexml_load_file(dirname(__FILE__) . '/../db/whois-server-list.xml');
		}

		foreach (static::$m_data as $domain_xml_obj) {
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
					if ( $domain_attr_idx == 'name' && substr($domain, $domain_len - strlen($domain_attr) - 1, strlen($domain_attr) + 1) == '.' . $domain_attr ) {
						if ( strlen($domain_attr) >= strlen($possible_tld) ) {
							$possible_tld = $domain_attr;
							$whois_servers[] = $domain_xml_obj->whoisServer;
							$extension = strtolower(trim($domain_attr));
						}
					}
				}
				foreach ( $domain_xml_obj->domain as $inner_domain_obj ) {
					if ( isset($inner_domain_obj->whoisServer) ) {
						foreach ( $inner_domain_obj->attributes() as $domain_attr_idx => $domain_attr ) {
							if ( $domain_attr_idx == 'name' && substr($domain, $domain_len - strlen($domain_attr) - 1, strlen($domain_attr) + 1) == '.' . $domain_attr ) {
								if ( strlen($domain_attr) >= strlen($possible_tld) ) {
									$possible_tld = $domain_attr;
									$whois_servers[] = $inner_domain_obj->whoisServer;
									$extension = strtolower(trim($domain_attr));
								}
							}
						}
					}
				}
			}
		}

		$server = null;
		if (is_array($whois_servers)) {
			foreach ($whois_servers as $whois_server) {
				if ($server) {
					continue;
				}
				if ($whois_server && $whois_server->attributes()) {
					foreach ($whois_server->attributes() as $whois_server_attr_idx => $whois_server_attr) {
						if ($whois_server_attr_idx == 'host') {
							$server = $whois_server_attr;
						}
					}
				}
			}
		} else {
			DomainCheckAdmin::admin_notices_add('No WHOIS servers exist for domain extension <strong>' . $domain . '</strong>.', 'error', null, 'circle-x');
			return array('error' => 'No WHOIS servers exist for domain extension ' . $domain);
		}


		$fp = @fsockopen(
			$server,
			43,
			$errno,
			$errstr,
			$connectiontimeout
		);
		$starttime = time();
		if ( $fp ){
			fputs($fp, $domain . "\r\n");
			socket_set_timeout($fp, $sockettimeout);
			while( !feof($fp) ){
				$data .= fread($fp, 4096);
				if (time() - $starttime > 30) {
					break;
				}
			}
			fclose($fp);

			if ($raw) {
				return $data;
			}

			//echo $data;
			$ret = array(
				'data' => $data,
				'extension' => $extension,
				'status' => 0,
				'domain_expires' => 0
			);

			$ret['status'] = DomainCheckWhoisData::get_status($extension, $data);

			if ($ret['status']) {
				$ret['domain_expires'] = DomainCheckWhoisData::get_expiration($extension, $data);
			}

			return $ret;
		} else {
			DomainCheckAdmin::admin_notices_add('Error - could not open a connection to ' . $server, 'error', null, 'circle-x');
			return array('error' => 'Error - could not open a connection to ' . $server);
		}
	}

	public static function getextension($domain) {
		static::init();
		$domain = strtolower($domain);
		$domain_len = strlen($domain);
		$possible_tld = '';
		foreach (static::$m_data as $domain_xml_obj) {
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
					if ( $domain_attr_idx == 'name' && substr($domain, $domain_len - strlen($domain_attr) - 1, strlen($domain_attr) + 1) == '.' . $domain_attr ) {
						if ( strlen($domain_attr) >= strlen($possible_tld) ) {
							$possible_tld = $domain_attr;
						}
					}
				}
				foreach ( $domain_xml_obj->domain as $inner_domain_obj ) {
					if ( isset($inner_domain_obj->whoisServer) ) {
						foreach ( $inner_domain_obj->attributes() as $domain_attr_idx => $domain_attr ) {
							if ( $domain_attr_idx == 'name' && substr($domain, $domain_len - strlen($domain_attr) - 1, strlen($domain_attr) + 1) == '.' . $domain_attr ) {
								if ( strlen($domain_attr) >= strlen($possible_tld) ) {
									$possible_tld = $domain_attr;
								}
							}
						}
					}
				}
			}
		}
		return $possible_tld;
	}

	public static function getextensions() {
		static::init();
		$extensions = array();
		foreach (static::$m_data as $domain_xml_obj) {
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
					if ( $domain_attr_idx == 'name' ) {
						$extensions[] = $domain_attr;
					}
				}
				foreach ( $domain_xml_obj->domain as $inner_domain_obj ) {
					if ( isset($inner_domain_obj->whoisServer) ) {
						foreach ( $inner_domain_obj->attributes() as $domain_attr_idx => $domain_attr ) {
							if ( $domain_attr_idx == 'name' ) {
								$extensions[] = $domain_attr;
							}
						}
					}
				}

				/*
				if ( is_array($domain_arr) ) {
					foreach ( $domain_xml_obj->domain as $inner_domain_obj ) {
						if ( isset($inner_domain_obj->whoisServer) ) {
							foreach ( $inner_domain_obj->attributes() as $domain_attr_idx => $domain_attr ) {
								if ( $domain_attr_idx == 'name' ) {
									$extensions[] = $domain_attr;
								}
							}
						}
					}
				} else {
					if ( isset($domain_xml_obj->domain->whoisServer) ) {
						foreach ( $domain_xml_obj->domain->attributes() as $domain_attr_idx => $domain_attr ) {
							if ( $domain_attr_idx == 'name' ) {
								$extensions[] = $domain_attr;
							}
						}
					}
				}
				*/
			}
		}
		return $extensions;
	}

	public static function validdomain($domain) {
		$valid_domain = false;
		$domain_extension = null;
		if ( strpos( $domain, '.' ) !== false ) {
			$domain_parse = parse_url(strtolower(trim($domain)));
			if (isset($domain_parse['path'])) {
				$domain_parse = $domain_parse['path'];
				$domain_parse = preg_replace("/[^a-z0-9.-]+/i", '', $domain_parse);
				if ($domain_parse && strpos($domain_parse, '.') !== false) {
					$domain_extension = DomainCheckWhois::getextension($domain_parse);
					$domain_preface = str_replace('.' . $domain_extension, '', $domain_parse);
					if ($domain_extension && $domain_preface && $domain_preface != '.' && $domain_preface != '-' && $domain_preface != '' ) {
						$valid_domain = array(
							'domain_extension' => $domain_extension,
							'domain' => $domain_parse
						);
					}
				}
			} else if (isset($domain_parse['host'])) {
				$domain_parse = $domain_parse['host'];
				$domain_parse = preg_replace("/[^a-z0-9.-]+/i", '', $domain_parse);
				if ($domain_parse && strpos($domain_parse, '.') !== false) {
					$domain_extension = DomainCheckWhois::getextension($domain_parse);
					$domain_preface = str_replace('.' . $domain_extension, '', $domain_parse);
					if ($domain_extension && $domain_preface && $domain_preface != '.' && $domain_preface != '-' && $domain_preface != '' ) {
						$valid_domain = array(
							'domain_extension' => $domain_extension,
							'domain' => $domain_parse
						);
					}
				}
			}
		}
		return $valid_domain;
	}

}