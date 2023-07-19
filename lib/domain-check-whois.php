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
		if (!self::$m_init) {
			DomainCheckWhoisData::init();
			self::$m_init = true;
		}
	}

	public static function dolookup($domain, $raw = false) {
		self::init();
		//echo 'CHECKING FOR....... ' . $domain;
		$connectiontimeout = 5;
		$sockettimeout = 15;
		$domain = strtolower($domain);
		$domain_len = strlen($domain);
		$possible_tld = '';
		$whois_servers = array();
		$data = '';
		$extension = '';
		$extension_data = null;

		foreach ( DomainCheckWhoisData::$whoisData as $whois_extension => $whois_extension_data ) {
			if ( substr($domain, $domain_len - strlen($whois_extension) - 1, strlen($whois_extension) + 1) == '.' . $whois_extension ) {
				$extension = $whois_extension;
				$extension_data = $whois_extension_data;
			}
		}

		if ( $extension_data && isset( $extension_data['whois'] ) ) {
			$server = $extension_data['whois'];
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

			//if we generated pattern before
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
		self::init();
		$domain = strtolower($domain);
		$domain_len = strlen($domain);
		$possible_tld = '';

		$extension = '';
		$extension_data = null;

		foreach ( DomainCheckWhoisData::$whoisData as $whois_extension => $whois_extension_data ) {
			if ( substr($domain, $domain_len - strlen($whois_extension) - 1, strlen($whois_extension) + 1) == '.' . $whois_extension ) {
				$extension = $whois_extension;
				$extension_data = $whois_extension_data;
			}
		}

		return $extension;

	}

	public static function getextensions() {
		self::init();

		return DomainCheckWhoisData::$whoisData;
	}

	public static function validdomain($domain) {
		$valid_domain = false;
		$domain_extension = null;
		if ( mb_strpos( $domain, '.' ) !== false ) {
			$domain_parse = parse_url(mb_strtolower(trim($domain)));

			if (isset($domain_parse['path']) && $domain_parse['path'] != '/') {
				$domain_parse = $domain_parse['path'];
				$domain_parse = preg_replace("/[^a-z0-9.-]+/i", '', $domain_parse);
				if ($domain_parse && mb_strpos($domain_parse, '.') !== false) {
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
				if ($domain_parse && mb_strpos($domain_parse, '.') !== false) {
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