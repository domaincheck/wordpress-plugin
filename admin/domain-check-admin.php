<?php

//wp-plugin can be a url, no no!
if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
	die();
}

if (!function_exists('is_admin') || !is_admin()) {
	die();
}

if (php_sapi_name() !== 'cli') {
	require_once('domain-check-admin-search-list.php');
	require_once('domain-check-admin-ssl-list.php');
	require_once('domain-check-admin-ssl-watch-list.php');
	require_once('domain-check-admin-watch-list.php');
	require_once('domain-check-admin-your-domains-list.php');
}

//define wp-plugin class, must be self contained!
if(!class_exists('DomainCheckAdmin')) {
	class DomainCheckAdmin
	{

		const PLUGIN_CLASSNAME = 'DomainCheck';
		const PLUGIN_NAME = 'domain-check';
		const PLUGIN_OPTION_PREFIX = 'domain_check';
		const PLUGIN_TEXTNAME = 'Domain Check';

		private $m_pluginData = array();

		public $domains_obj;
		public $your_domains_obj;

		public $customers_obj;

		static $instance;

		static $admin_notices = array();

		static $ajax_nonce = null;

		static $scheduled_event_name = 'domain_check_cron';

		static $admin_icon = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxOS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjxzdmcgdmVyc2lvbj0iMS4xIiBpZD0iTGF5ZXJfMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgeD0iMHB4IiB5PSIwcHgiDQoJIHZpZXdCb3g9Ii0yOTUgMjk3IDggOCIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAtMjk1IDI5NyA4IDg7IiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGQ9Ik0tMjkxLDI5Ny4xYy0yLjIsMC0zLjksMS44LTMuOSwzLjlzMS44LDMuOSwzLjksMy45czMuOS0xLjgsMy45LTMuOVMtMjg4LjgsMjk3LjEtMjkxLDI5Ny4xeiBNLTI5Mi44LDMwMS45aC0wLjRsLTAuMy0xLjENCglsLTAuMywxLjFoLTAuNGwtMC41LTEuOGgwLjRsMC4zLDEuMmwwLjMtMS4yaDAuNGwwLjMsMS4ybDAuMy0xLjJoMC40TC0yOTIuOCwzMDEuOXogTS0yOTAuMywzMDEuOWgtMC40bC0wLjMtMS4xbC0wLjMsMS4xaC0wLjQNCglsLTAuNS0xLjhoMC40bDAuMywxLjJsMC4zLTEuMmgwLjRsMC4zLDEuMmwwLjMtMS4yaDAuNEwtMjkwLjMsMzAxLjl6IE0tMjg4LjIsMzAxLjlsLTAuMy0xLjFsLTAuMywxLjFoLTAuNGwtMC41LTEuOGgwLjRsMC4zLDEuMg0KCWwwLjMtMS4yaDAuNGwwLjMsMS4ybDAuMy0xLjJoMC40bC0wLjUsMS44SC0yODguMnoiLz4NCjwvc3ZnPg0K';

		public function __construct()
		{
			//********** WORDPRESS ADMIN HOOKS *******************
			if (function_exists('add_action')) {
				add_action( 'admin_init', array(&$this, 'admin_init') );
				add_action( 'admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts') );
				add_action( 'admin_menu', array(&$this, 'admin_menu') );
				add_action( 'admin_notices', array(&$this, 'admin_notices') );


				add_action( 'wp_ajax_watch_trigger', array(&$this, 'watch_trigger' ) );
				add_action( 'wp_ajax_status_trigger', array(&$this, 'status_trigger' ) );

				add_action( 'wp_ajax_ssl_watch_trigger', array(&$this, 'ssl_watch_trigger' ) );

				add_action( 'wp_ajax_domain_search', array(&$this, 'ajax_domain_search' ) );

				add_action( 'wp_ajax_settings', array(&$this, 'ajax_settings' ) );
			}

			//filters
			if (function_exists('add_filter')) {
				add_filter( 'set-screen-option', array( __CLASS__, 'set_screen' ), 10, 3 );
				add_filter( 'plugin_action_links_' . DomainCheck::$basename, array( $this, 'add_action_links' ), 10, 2 );

				//add_filter( 'plugin_action_links_domaincheck', array( $this, 'add_action_link' ), 10, 2 );
				//add_filter( 'plugin_action_links_domain-check', array( $this, 'add_action_link' ), 10, 2 );
			}
		}

		public function add_action_links( $links ) {
			$faq_link = '<a title="Help" href="' . esc_url( admin_url( 'admin.php?page=domain-check-help' ) ) . '">Help</a>';
			array_unshift( $links, $faq_link );

			$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=domain-check-import-export' ) ) . '">Import</a>';
			array_unshift( $links, $settings_link );

			$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=domain-check-import-export' ) ) . '">Search</a>';
			array_unshift( $links, $settings_link );

			$dashboard_link = '<a href="' . esc_url( admin_url( 'admin.php?page=domain-check' ) ) . '">Dashboard</a>';
			array_unshift( $links, $dashboard_link );

			return $links;
		}

		//includes the necessarry css and js files
		public function admin_enqueue_scripts($hook) {}

		//updated, error, update-nag
		public static function admin_notices_add($message = '', $type = 'updated', $options = null, $icon = null) {
			if ($message) {
				if ($icon) {
					$image = '<img src="' . plugins_url('/images/icons/' . $icon . '.svg', __FILE__) .'" class="svg svg-icon-admin-notice svg-fill-' . $type . '">';
					$message = $image . $message;
					$message = '<h2>' . $message . '</h2>';
				}
				if ($options && is_array($options) && count($options)) {
					$message .= '<br><br>' . "\n";
					$first = true;
					foreach($options as $option_name => $option_url) {
						if (!$first) {
							$message .= ' | ';
						}
						if ($option_name == 'Launch [&raquo]') {
							$message .= '<a href="http://'.$option_url.'" target="_blank">'.$option_name.'</a>';
						} else {
							$message .= '<a href="'.$option_url.'">'.$option_name.'</a>';
						}
						if ($first) {
							$first = false;
						}
					}
				}
				static::$admin_notices[] = array('message' => $message, 'type' => $type);
			}
		}

		public function admin_notices() {
			foreach (static::$admin_notices as $admin_notice_idx => $admin_notice_data) {
				if ($admin_notice_data['type'] !== 'updated'
					&& $admin_notice_data['type'] !== 'error'
					&& $admin_notice_data['type'] !== 'update-nag'
				) {
					$admin_notice_data['type'] = 'updated notice-' . $admin_notice_data['type'];
				}
				?>
			<div class="<?php echo $admin_notice_data['type']; ?> domain-check-admin-notice">
				<p><?php echo $admin_notice_data['message']; ?></p>
			</div>
		<?php
			}
		}

		public function admin_header() {
			?>
			<style type="text/css">
				.svg-fill-red path {
					fill: #ff0000;
				}
				.svg-fill-green path {
					fill: #00AA00;
				}
				.svg-fill-blue path {
					fill: #0000FF;
				}
				.svg-fill-gray path {
					fill: #4D4D4D;
				}

				.svg-fill-disabled path {
					fill: #CDCDCD;
				}

				.svg-fill-error path,
				.svg-fill-status-1 path,
				.svg-fill-taken path {
						fill: #dd3d36;
				}
				.svg-fill-success path,
				.svg-fill-updated path,
				.svg-fill-status-0 path,
				.svg-fill-available path {
					fill: #7ad03a;
				}

				.svg-fill-status-2 path,
				.svg-fill-owned path {
					fill: #0000AA;
				}
				.svg-fill-update-nag path {
					fill: #ffba00;
				}
				.svg-icon-h1 {
					height: 30px;
					width: auto;
					display: inline-block;
					margin-right: 5px;
				}
				.svg-icon-h2 {
					height: 24px;
					width: auto;
					display: inline-block;
					margin-right: 5px;
				}
				.svg-icon-h3 {
					height: 20px;
					width: auto;
					display: inline-block;
					margin-right: 5px;
				}
				.svg-icon-table {
					height: 16px;
					width: auto;
					display: inline-block;
				}
				.svg-icon-table-links {
					margin-left: 6px;
					margin-right: 6px;
				}
				.svg-icon-table-links:hover {
					background-color: #aaaaaa;
				}
				.svg-icon-table-small {
					height: 8px;
				}
				.svg-icon-table-mid {
					height: 14px;
				}
				.svg-icon-admin-notice {
					height: 24px;
					width: auto;
					display: inline-block;
					margin-right: 5px;
				}

				div.notice-owned {
					border-color: #0000AA;
				}

				.setting-div {
					max-width: 450px;
					min-width: 350px;
					float: left;
					padding: 10px;
					margin: 10px;
					background-color: #ffffff;
				}
			</style>
			<script type="text/javascript">
			/*
			 * Replace all SVG images with inline SVG
			 */

			//search
			var jqueryReady = false;
			jQuery(document).ready(function($) {
				jqueryReady = true;

				jQuery('.updated').each(function(){
					var classList = jQuery(this).attr('class').split(/\s+/);
					var plugin_notice = false;
					for (var i in classList) {
						if (classList[i] == 'domain-check-notice') {
							plugin_notice = true;
						}
					}
					if (!plugin_notice) {
						jQuery(this).css('display', 'hidden');
					}
				});

				jQuery('.update-nag').each(function(){
					var classList = jQuery(this).attr('class').split(/\s+/);
					var plugin_notice = false;
					for (var i in classList) {
						if (classList[i] == 'domain-check-notice') {
							plugin_notice = true;
						}
					}
					if (!plugin_notice) {
						jQuery(this).css('display', 'none');
					}
				});

				jQuery('.error').each(function(){
					var classList = jQuery(this).attr('class').split(/\s+/);
					var plugin_notice = false;
					for (var i in classList) {
						if (classList[i] == 'domain-check-notice') {
							plugin_notice = true;
						}
					}
					if (!plugin_notice) {
						jQuery(this).css('display', 'none');
					}
				});

				jQuery('img.svg').each(function(){
				var $img = jQuery(this);
				var imgID = $img.attr('id');
				var imgClass = $img.attr('class');
				var imgURL = $img.attr('src');

				jQuery.get(imgURL, function(data) {
					// Get the SVG tag, ignore the rest
					var $svg = jQuery(data).find('svg');

					// Add replaced image's ID to the new SVG
					if(typeof imgID !== 'undefined') {
						$svg = $svg.attr('id', imgID);
					}
					// Add replaced image's classes to the new SVG
					if(typeof imgClass !== 'undefined') {
						$svg = $svg.attr('class', imgClass+' replaced-svg');
					}

					// Remove any invalid XML tags as per http://validator.w3.org
					$svg = $svg.removeAttr('xmlns:a');

					// Check if the viewport is set, if the viewport is not set the SVG wont't scale.
					if(!$svg.attr('viewBox') && $svg.attr('height') && $svg.attr('width')) {
						$svg.attr('viewBox', '0 0 ' + $svg.attr('height') + ' ' + $svg.attr('width'))
					}

					// Replace image with new SVG
					$img.replaceWith($svg);

				}, 'xml');

			});
			});
			function domain_check_ajax_call(data, callback) {
				jQuery.post(
					ajaxurl,
					data,
					function (response) {
						response = JSON.parse(response);
						if (response.hasOwnProperty('success') && response.hasOwnProperty('data')) {
							//success!
							if (typeof callback === 'function') {
								callback(response.data)
							}
						} else {
							//errors
							if (response.hasOwnProperty('error')) {
								//create div
								//append to page...
								if (response.hasOwnProperty('error')) {
									jQuery('#domain-check-wrapper').append('<div class="notice error domain-check-notice">').append('<p>' + response.error + '</p>');
								}
								callback(response);
							}
						}
					}
				);
			}
			function watch_trigger_callback(data) {
				var htmlDomain = data.domain.replace(/\./g, '-');
				var iconDir = '<?php echo plugins_url('domain-check/images/icons/'); ?>';
				if (data.watch) {
					jQuery('#domain-check-admin-notices').append('<div class="notice updated domain-check-notice"><p>' + data.message + '</p></div>');
					//jQuery('#watch-link-' + htmlDomain).text('Stop Watching');

					var replace = '<img id="watch-image-' + htmlDomain + '" src="' + iconDir + '207-eye.svg" class="svg svg-icon-table svg-icon-table-links svg-fill-gray" onload="paint_svg(\'watch-image-' + htmlDomain + '\')">';
					jQuery('#watch-image-' + htmlDomain).replaceWith(replace);
				} else {
					jQuery('#domain-check-admin-notices').append('<div class="notice error domain-check-notice"><p>' + data.message + '</p></div>');
					//jQuery('#watch-link-' + htmlDomain).text('Watch');
					var replace = '<img id="watch-image-' + htmlDomain + '" src="' + iconDir + '207-eye.svg" class="svg svg-icon-table svg-icon-table-links svg-fill-disabled" onload="paint_svg(\'watch-image-' + htmlDomain + '\')">';
					jQuery('#watch-image-' + htmlDomain).replaceWith(replace);
				}
			}

			function ssl_watch_trigger_callback(data) {
				var htmlDomain = data.domain.replace(/\./g, '-');
				var iconDir = '<?php echo plugins_url('domain-check/images/icons/'); ?>';
				if (data.watch) {
					jQuery('#domain-check-admin-notices').append('<div class="notice updated domain-check-notice"><p>' + data.message + '</p></div>');
					var replace = '<img id="watch-image-' + htmlDomain + '" src="' + iconDir + 'bell.svg" class="svg svg-icon-table svg-icon-table-links svg-fill-gray" onload="paint_svg(\'watch-image-' + htmlDomain + '\')">';
					jQuery('#watch-image-' + htmlDomain).replaceWith(replace);
				} else {
					jQuery('#domain-check-admin-notices').append('<div class="notice error domain-check-notice"><p>' + data.message + '</p></div>');
					var replace = '<img id="watch-image-' + htmlDomain + '" src="' + iconDir + 'bell.svg" class="svg svg-icon-table svg-icon-table-links svg-fill-disabled" onload="paint_svg(\'watch-image-' + htmlDomain + '\')">';
					jQuery('#watch-image-' + htmlDomain).replaceWith(replace);
				}
			}

			function status_trigger_callback(data) {
				var htmlDomain = data.domain.replace('.', '-');
				if (data.status == 2) {
					jQuery('#domain-check-admin-notices').append('<div class="notice updated domain-check-notice"><p>' + data.message + '</p></div>');
					jQuery('#status-link-' + htmlDomain).text('Owned');
				} else if (data.status == 1) {
					jQuery('#domain-check-admin-notices').append('<div class="notice error domain-check-notice"><p>' + data.message + '</p></div>');
					jQuery('#status-link-' + htmlDomain).text('Taken');
				}
			}

			function paint_svg(elem_id) {

				var $img = jQuery('#' + elem_id);
				var imgID = $img.attr('id');
				var imgClass = $img.attr('class');
				var imgURL = $img.attr('src');

				jQuery.get(
					imgURL,
					function(data) {
						// Get the SVG tag, ignore the rest
						var $svg = jQuery(data).find('svg');

						// Add replaced image's ID to the new SVG
						if(typeof imgID !== 'undefined') {
							$svg = $svg.attr('id', imgID);
						}
						// Add replaced image's classes to the new SVG
						if(typeof imgClass !== 'undefined') {
							$svg = $svg.attr('class', imgClass+' replaced-svg');
						}

						// Remove any invalid XML tags as per http://validator.w3.org
						$svg = $svg.removeAttr('xmlns:a');

						// Check if the viewport is set, if the viewport is not set the SVG wont't scale.
						if(!$svg.attr('viewBox') && $svg.attr('height') && $svg.attr('width')) {
							$svg.attr('viewBox', '0 0 ' + $svg.attr('height') + ' ' + $svg.attr('width'))
						}

						// Replace image with new SVG
						$img.replaceWith($svg);
					}
				);
			}
			</script>
			<?php
		}

		public function admin_init() {
			global $wpdb;

			static::$ajax_nonce = wp_create_nonce('domain_check_ajax_nonce');

			//capability
			$role = get_role( 'administrator' );
			$role->add_cap( DomainCheck::CAPABILITY );

			$domain_data = null;
			$domain_extension = null;
			if ( isset( $_GET['domain_check_search'] ) ) {
				DomainCheckSearch::domain_search($_GET['domain_check_search']);
			}

			//SSL CHECK!!!
			if ( isset( $_GET['domain_check_ssl_search'] ) && strpos( $_GET['domain_check_ssl_search'], '.' ) !== false ) {
				$this->ssl_check_init();
			}

			//domain delete
			if ( isset($_GET['domain_check_delete']) && strpos( $_GET['domain_check_delete'], '.' ) !== false ) {
				$this->delete_init();
			}

			//domain status
			if ( isset($_GET['domain_check_status_owned']) && strpos( $_GET['domain_check_status_owned'], '.' ) !== false ) {
				$this->status_owned($_GET['domain_check_status_owned']);
			}
			if ( isset($_GET['domain_check_status_taken']) && strpos( $_GET['domain_check_status_taken'], '.' ) !== false ) {
				$this->status_taken($_GET['domain_check_status_taken']);
			}

			//domain watch
			if ( isset($_GET['domain_check_watch_start']) && strpos( $_GET['domain_check_watch_start'], '.' ) !== false ) {
				$this->watch_start($_GET['domain_check_watch_start']);
			}
			if ( isset($_GET['domain_check_watch_stop']) && strpos( $_GET['domain_check_watch_stop'], '.' ) !== false ) {
				$this->watch_stop($_GET['domain_check_watch_stop']);
			}
			if ( isset($_POST['watch_email_add']) ) {
				$this->watch_email_add($_GET['domain'], $_POST['watch_email_add']);
			}

			//ssl delete
			if ( isset($_GET['domain_check_ssl_delete']) && strpos( $_GET['domain_check_ssl_delete'], '.' ) !== false ) {
				$this->ssl_delete_init();
			}

			//ssl watch
			if ( isset($_GET['domain_check_ssl_watch_start']) && strpos( $_GET['domain_check_ssl_watch_start'], '.' ) !== false ) {
				$this->ssl_watch_start($_GET['domain_check_ssl_watch_start']);
			}
			if ( isset($_GET['domain_check_ssl_watch_stop']) && strpos( $_GET['domain_check_ssl_watch_stop'], '.' ) !== false ) {
				$this->ssl_watch_stop($_GET['domain_check_ssl_watch_stop']);
			}
			if ( isset($_POST['ssl_watch_email_add']) ) {
				$this->ssl_watch_email_add($_GET['domain'], $_POST['ssl_watch_email_add']);
			}

			//coupons
			if ( isset($_GET['domain_check_coupons_search']) && $_GET['domain_check_coupons_search']) {
				$this->coupons_init();
			}
			if ( isset($_GET['domain_check_coupons_update']) && $_GET['domain_check_coupons_update']) {
				$this->coupons_init();
			}

		}

		public function admin_menu() {
			$hook = add_menu_page(
				'Domains',
				'Domains',
				'manage_options',
				'domain-check',
				array(
					$this,
					'dashboard'
				),
				DomainCheckAdmin::$admin_icon //'dashicons-admin-site'
			);
			//add_action( 'load-' . $hook, array( $this, 'screen_option' ) );

			//add submenu items
			$hook = add_submenu_page(
				'domain-check',
				'Your Domains', //__('Fancy Products', MyStyleWpPlugin::PLUGIN_NAME),
				'Your Domains', //__('Fancy Products', MyStyleWpPlugin::PLUGIN_NAME),
				DomainCheck::CAPABILITY,
				'domain-check-your-domains',
				array(
					$this,
					'your_domains'
				)
			);
			add_action( 'load-' . $hook, array( $this, 'your_domains_screen_option' ) );

			//add submenu items
			$hook = add_submenu_page(
				'domain-check',
				'Domain Search', //__('Fancy Products', MyStyleWpPlugin::PLUGIN_NAME),
				'Domain Search', //__('Fancy Products', MyStyleWpPlugin::PLUGIN_NAME),
				DomainCheck::CAPABILITY,
				'domain-check-search',
				array(
					$this,
					'search'
				)
			);
			add_action( 'load-' . $hook, array( $this, 'search_screen_option' ) );

			//add fancy designs sub menu page to products menu
			$hook = add_submenu_page(
				'domain-check',
				'Domain Watch',
				'Domain Watch',
				DomainCheck::CAPABILITY,
				'domain-check-watch',
				array(
					$this,
					'watch'
				)
			);
			add_action( 'load-' . $hook, array( $this, 'watch_screen_option' ) );

			//add fancy designs sub menu page to products menu
			$hook = add_submenu_page(
				'domain-check',
				'SSL Check',
				'SSL Check',
				DomainCheck::CAPABILITY,
				'domain-check-ssl-check',
				array(
					$this,
					'ssl_check'
				)
			);
			add_action( 'load-' . $hook, array( $this, 'ssl_check_screen_option' ) );

			//add fancy designs sub menu page to products menu
			$hook = add_submenu_page(
				'domain-check',
				'SSL Expiration Alerts',
				'SSL Expiration Alerts',
				DomainCheck::CAPABILITY,
				'domain-check-ssl-watch',
				array(
					$this,
					'ssl_watch'
				)
			);
			add_action( 'load-' . $hook, array( $this, 'ssl_watch_screen_option' ) );

			/*
			//add fancy designs sub menu page to products menu
			add_submenu_page(
				'domain-check',
				'Hosting Check',
				'Hosting Check',
				DomainCheck::CAPABILITY,
				'domain-check-hosting-check',
				array(
					$this,
					'hosting_check'
				)
			);

			//add fancy designs sub menu page to products menu
			add_submenu_page(
				'domain-check',
				'Hosting Alerts',
				'Hosting Alerts',
				DomainCheck::CAPABILITY,
				'domain-check-hosting-alerts',
				array(
					$this,
					'hosting_alerts'
				)
			);
			*/

			//add fancy designs sub menu page to products menu
			add_submenu_page(
				'domain-check',
				'Import / Export',
				'Import / Export',
				DomainCheck::CAPABILITY,
				'domain-check-import-export',
				array(
					$this,
					'import_export'
				)
			);

			//add fancy designs sub menu page to products menu
			add_submenu_page(
				'domain-check',
				'Settings',
				'Settings',
				DomainCheck::CAPABILITY,
				'domain-check-settings',
				array(
					$this,
					'settings'
				)
			);

			//add fancy designs sub menu page to products menu
			add_submenu_page(
				'domain-check',
				'Help',
				'Help',
				DomainCheck::CAPABILITY,
				'domain-check-help',
				array(
					$this,
					'help'
				)
			);

			//add fancy designs sub menu page to products menu
			add_submenu_page(
				'domain-check',
				'Coupons & Deals',
				'Coupons & Deals',
				DomainCheck::CAPABILITY,
				'domain-check-coupons',
				array(
					$this,
					'coupons'
				)
			);

			//pages without a submenu link (domain profile mostly...)
			//domain profile
			add_submenu_page(
				'domain-check',
				'',
				'',
				DomainCheck::CAPABILITY,
				'domain-check-profile',
				array(
					$this,
					'profile'
				)
			);

			//ssl profile
			add_submenu_page(
				'domain-check',
				'',
				'',
				DomainCheck::CAPABILITY,
				'domain-check-ssl-profile',
				array(
					$this,
					'ssl_profile'
				)
			);

			//ssl profile
			/*
			add_submenu_page(
				'domain-check',
				'',
				'',
				DomainCheck::CAPABILITY,
				'domain-check-watch-profile',
				array(
					$this,
					'watch_profile'
				)
			);

			global $submenu;
			if ( isset( $submenu['domain-check'] ) ) {
				$submenu['domain-check'][0][0] = 'General';
			}
			*/
		}

		public function ajax_domain_search($action = null, $domain = null) {
			if (isset($_POST['action']) && $_POST['action'] == 'domain_search'
				&& isset($_POST['domain']) && $_POST['domain']) {
				$use_cache = false;
				if (isset($_POST['cache'])) {
					$use_cache = $_POST['cache'];
				}
				$force_owned = false;
				if (isset($_POST['force_owned'])) {
					$force_owned = true;
				}
				$force_watch = false;
				if (isset($_POST['force_watch']) && $_POST['force_watch']) {
					$force_watch = true;
				}
				$ssl = false;
				if (isset($_POST['force_ssl']) && $_POST['force_ssl']) {
					$ssl = true;
				}
				if ($ssl) {
					DomainCheckSearch::ssl_search($_POST['domain'], $force_watch, true);
					return;
				}
				DomainCheckSearch::domain_search($_POST['domain'], $use_cache, $force_owned, $force_watch, true);
			}
		}

		public function ajax_settings() {
			if (isset($_POST['method'])) {
				switch($_POST['method']) {
					case 'email_additional_emails':
						if (isset($_POST['email_additional_emails'])) {
							//check if emails exist...
							$email_arr = explode("\n", $_POST['email_additional_emails']);
							foreach ($email_arr as $email_arr_idx => $email) {
								$email = sanitize_email($email);
								if (is_email($email)) {
									$email_arr[$email_arr_idx] = $email;
								} else {
									unset($email_arr[$email_arr_idx]);
								}
							}
							if (!is_array($email_arr) || !count($email_arr)) {
								$email_arr = array();
							}
							update_option(DomainCheckConfig::OPTIONS_PREFIX . $_POST['method'], $email_arr);
						}
						break;
					case 'email_primary_email':
						if (isset($_POST['email_primary_email'])) {
							//check if emails exist...
							$email = $_POST['email_primary_email'];
							$email = sanitize_email($email);
							if (is_email($email)) {
								update_option(DomainCheckConfig::OPTIONS_PREFIX . $_POST['method'], $email);

							} else {

							}
						}
						break;
					case 'domain_extension_favorites':
						if (isset($_POST['domain_extension_favorites'])) {
							$item_arr = explode("\n", $_POST['domain_extension_favorites']);
							foreach ($item_arr as $item_arr_idx => $item) {
								if (strpos($item, '.') === 0) {
									$item = substr($item, 1, strlen($item) - 1);
								}
								$item = strtolower($item);
								if (DomainCheckWhoisData::extension_supported($item)) {
									$item_arr[$item_arr_idx] = $item;
								} else {
									unset($item_arr[$item_arr_idx]);
								}
							}
							if (!is_array($item_arr) || !count($item_arr)) {
								$item_arr = DomainCheckConfig::$options[DomainCheckConfig::OPTIONS_PREFIX . 'settings']['domain_extension_favorites'];
							}
							update_option(DomainCheckConfig::OPTIONS_PREFIX . $_POST['method'], $item_arr);
						}
						break;
					case 'coupons_primary_site':
						if (isset($_POST['coupons_primary_site'])) {
							//check if emails exist...
							$site = $_POST['coupons_primary_site'];
							$data = DomainCheckCouponData::get_data();
							if (isset($data[$site])) {
								update_option(DomainCheckConfig::OPTIONS_PREFIX . $_POST['method'], $site);
							}
						}
						break;
				}
			}
		}

		public static function ajax_success($data) {
			$data = array(
				'success' => 1,
				'data' => $data
			);
			echo json_encode($data);
			wp_die();
		}

		public static function ajax_error($message, $code = 0) {
			$data = array(
				'error' => $message
			);
			if ($code) {
				$data['code'] = $code;
			}
			echo json_encode($data);
			wp_die();
		}

		public static function callInstance($method, $args) {
			self::$instance->{$method}($args);
		}

		public function coupons() {
			global $wpdb;

			if (isset($_GET['domain_check_coupons_site']) && DomainCheckCouponData::valid_site($_GET['domain_check_coupons_site'])) {
				$this->coupons_site();
				return;
			}

			$coupons = null;
			if (isset($_GET['domain_check_coupons_search']) && $_GET['domain_check_coupons_search']) {
				$coupons = DomainCheckCouponData::search($_GET['domain_check_coupons_search']);
				$found = 0;
				foreach ($coupons as $coupon_site => $coupon_data) {
					if (isset($coupon_data['links']['link'])) {
						$found += count($coupon_data['links']['link']);
					}
				}

			}
			if (!$coupons) {
				$coupons = DomainCheckCouponData::get_data();
			}

			$coupon_last_updated = DomainCheckCouponData::last_updated();

			$this->admin_header();
			?>
			<style type="text/css">
				.domain-check-coupon-ad {
					display: inline-block;
					margin: 5px;
					padding: 10px;
					background-color: #ffffff;
					width: 10%;
					min-height: 200px;
					float: left;
					border: 2px black dashed;
				}
				.domain-check-img-ad {
					display: inline-block;
					margin: 5px;
					padding: 5px;
					background-color: #ffffff;
					max-width: 100%;
					overflow: hidden;
					float: left;
				}
			</style>
			<div class="wrap">
				<h2>
					<img src="<?php echo plugins_url('/images/icons/055-price-tags.svg', __FILE__); ?>" class="svg svg-icon-h1 svg-fill-updated">
					Coupons &amp; Deals
				</h2>
				<?php
				$this->coupons_search_box();
				?>
				<?php if ($coupon_last_updated) { ?>
					<strong>Last Updated: </strong> <?php echo date('m-d-Y H:i:s', $coupon_last_updated); ?>
					<br>
				<?php } ?>
				<a href="admin.php?page=domain-check-coupons&domain_check_coupons_update=1" class="button">
					<img src="<?php echo plugins_url('/images/icons/303-loop2.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-gray">
					Refresh Coupons
				</a>
				<?php
				foreach ($coupons as $coupon_site => $coupon_data) {
					?>
					<div style="clear:both;">
						<h3>
							<a href="admin.php?page=domain-check-coupons&domain_check_coupons_site=<?php echo $coupon_site; ?>">
							<?php echo ucfirst($coupon_site); ?>
							</a>
						</h3>
						<?php
						if (isset($coupon_data['links']['link']) && is_array($coupon_data['links']['link'])) {
							$coupon_ads = array();
							$text_ads = array();
							$img_ads = array();
							foreach ($coupon_data['links']['link'] as $coupon_link_idx => $coupon_link_data) {
								if (isset($coupon_link_data['link-type']) && $coupon_link_data['link-type']) {
									if ($coupon_link_data['link-type'] == 'Text Link') {
										if (isset($coupon_link_data['coupon-code'])
											&& ((is_array($coupon_link_data['coupon-code']) && count($coupon_link_data['coupon-code'])) || $coupon_link_data['coupon-code'])
										) {
											$coupon_ads[] = $coupon_link_idx;
										} else {
											$text_ads[] = $coupon_link_idx;
										}

									} else {
										$img_ads[] = $coupon_link_idx;
									}

								}
							}
						}
						?>
						<div class="coupon-ad-wrapper" style="width: 100%; float: left; display:block; clear: both;">
							<?php
							foreach ($coupon_ads as $coupon_link_idx) {
								$coupon_link_data = $coupon_data['links']['link'][$coupon_link_idx];
								?>
								<div class="domain-check-coupon-ad">
									<p style="text-align: left;">
										<strong>
											<a href="<?php echo $coupon_link_data['clickUrl']; ?>" target="_blank">
											<?php echo $coupon_link_data['link-code-html']; ?>
												</a>
										</strong>
									</p>

									<p style="text-align: center;">

									<div style="text-align: center;">
										Coupon Code:
									</div>
									<div style="text-align: center;">
										<a href="<?php echo $coupon_link_data['clickUrl']; ?>" target="_blank" style="background-color: #00AA00; color: #FFFFFF; font-size: 20px; margin: 10px; padding: 10px;">
											<strong>
												<?php echo $coupon_link_data['coupon-code']; ?>
											</strong>
										</a>
									</div>
									</p>
								</div>
							<?php } ?>
						</div>
						<div class="txt-ad-wrapper" style="width: 100%; float: left; display:inline-block;">
						<?php
						$limit = 10;
						$count = 0;
						shuffle($text_ads);
						foreach ($text_ads as $coupon_link_idx) {
							if ($count >= $limit) {
								break;
							}
							$coupon_link_data = $coupon_data['links']['link'][$coupon_link_idx];
							?>
							<div style="margin: 5px; padding: 5px; background-color: #ffffff; width: 40%; display: inline-block;" alt="<?php echo htmlentities($coupon_link_data['description']); ?>" title="<?php echo htmlentities($coupon_link_data['description']); ?>">
								<a href="<?php echo $coupon_link_data['clickUrl']; ?>" target="_blank">
								<?php
								echo $coupon_link_data['link-code-html'];
								?>
								</a>
							</div>
							<?php
							$count++;
						}
						?>
						</div>
					</div>
				<?php
				}
		}

		public function coupons_site() {
			global $wpdb;

			$site = 'GoDaddy.com';
			if (isset($_GET['domain_check_coupons_site']) && DomainCheckCouponData::valid_site($_GET['domain_check_coupons_site'])) {
				$site = $_GET['domain_check_coupons_site'];
			}

			if (isset($_GET['coupon_search']) && $_GET['coupon_search']) {
				$coupons = DomainCheckCouponData::search($_GET['coupon_search']);
				$found = 0;
				foreach ($coupons as $coupon_site => $coupon_data) {
					if ($coupon_site == $site) {
						if (isset($coupon_data['links']['link'])) {
							$found += count($coupon_data['links']['link']);
						}
					}
				}
			} else {
				$coupons = DomainCheckCouponData::get_data();
			}
			foreach ($coupons as $coupon_site => $coupon_data) {
				if ($coupon_site != $site) {
					unset($coupons[$coupon_site]);
				}
			}

			$this->admin_header();
			?>
			<style type="text/css">
				.domain-check-coupon-ad {
					display: inline-block;
					margin: 5px;
					padding: 10px;
					background-color: #ffffff;
					width: 10%;
					min-height: 200px;
					float: left;
					border: 2px black dashed;
				}
				.domain-check-img-ad {
					display: inline-block;
					margin: 5px;
					padding: 5px;
					background-color: #ffffff;
					max-width: 100%;
					overflow: hidden;
					float: left;
				}
			</style>
			<div class="wrap">
				<h2>
					<img src="<?php echo plugins_url('/images/icons/055-price-tags.svg', __FILE__); ?>" class="svg svg-icon-h1 svg-fill-updated">
					<?php echo $_GET['domain_check_coupons_site']; ?> Coupons
				</h2>
				<?php
				$coupon_last_updated = DomainCheckCouponData::last_updated();
				foreach ($coupons as $coupon_site => $coupon_data) {
					?>
					<div style="clear:both;">
					<h3><?php echo ucfirst($coupon_site); ?></h3>
						<?php if ($coupon_last_updated) { ?>
						<h4>Updated: <?php echo date('m-d-Y', $coupon_last_updated); ?></h4>
						<?php } ?>
					<?php
					if (isset($coupon_data['links']['link']) && is_array($coupon_data['links']['link'])) {
						$coupon_ads = array();
						$text_ads = array();
						$img_ads = array();
						foreach ($coupon_data['links']['link'] as $coupon_link_idx => $coupon_link_data) {
							if (isset($coupon_link_data['link-type']) && $coupon_link_data['link-type']) {
								if ($coupon_link_data['link-type'] == 'Text Link') {
									if (isset($coupon_link_data['coupon-code'])
										&& ((is_array($coupon_link_data['coupon-code']) && count($coupon_link_data['coupon-code'])) || $coupon_link_data['coupon-code'])) {
										$coupon_ads[] = $coupon_link_idx;
									} else {
										$text_ads[] = $coupon_link_idx;
									}

								} else {
									$img_ads[] = $coupon_link_idx;
								}

							}
						}

						?>
						<div class="coupon-ad-wrapper" style="width: 100%; float: left; display:block; clear: both;">
							<?php
							foreach ($coupon_ads as $coupon_link_idx) {
								$coupon_link_data = $coupon_data['links']['link'][$coupon_link_idx];
								?>
								<div class="domain-check-coupon-ad">
									<p style="text-align: left;">
										<strong>
											<a href="<?php echo $coupon_link_data['clickUrl']; ?>" target="_blank">
									<?php
									echo $coupon_link_data['link-code-html'];
									?>
									</a>
									<?php
									if (isset($coupon_link_data['coupon-code'])
										&& ((is_array($coupon_link_data['coupon-code']) && count($coupon_link_data['coupon-code'])) || $coupon_link_data['coupon-code'])) {
										?>
										</strong>
									</p>
									<p style="text-align: center;">
										<div style="text-align: center;">
										Coupon Code:
										</div>
										<div style="text-align: center;">
										<a href="<?php echo $coupon_link_data['clickUrl']; ?>" target="_blank" style="background-color: #00AA00; color: #FFFFFF; font-size: 20px; margin: 10px; padding: 10px;">
											<strong>
												<?php echo $coupon_link_data['coupon-code']; ?>
											</strong>
										</a>
										</div>
									</p>
									<?php
									}
									?>
								</div>
								<?php
							}
							?>
						</div>
						<div class="txt-ad-wrapper" style="width: 100%; float: left; display:inline-block;">
						<?php
						foreach ($text_ads as $coupon_link_idx) {
							$coupon_link_data = $coupon_data['links']['link'][$coupon_link_idx];
							?>
							<div style="margin: 5px; padding: 5px; background-color: #ffffff; width: 40%; display: inline-block;" alt="<?php echo htmlentities($coupon_link_data['description']); ?>" title="<?php echo htmlentities($coupon_link_data['description']); ?>">
								<a href="<?php echo $coupon_link_data['clickUrl']; ?>" target="_blank">
								<?php
								echo $coupon_link_data['link-code-html'];
								?>
								</a>
							</div>
							<?php
						}
						?>
						</div>
						<!--div class="img-ad-wrapper" style="float: left; width: 100%; display:inline-block; vertical-align: top;">
						<?php
						foreach ($img_ads as $coupon_link_idx) {
							$coupon_link_data = $coupon_data['links']['link'][$coupon_link_idx];
							?>
							<div class="domain-check-img-ad">
								<?php
								echo $coupon_link_data['link-code-html'];
								?>
							</div>
							<?php
						}
						?>
						</div-->
						<?php
					}
					?>
					</div>
					<?php
				}
				?>
			</div>
			<?php

		}

		public function coupons_init() {
			if (isset($_GET['domain_check_coupons_update'])) {
				if (DomainCheckCouponData::update()) {
					DomainCheckAdmin::admin_notices_add('Coupons updated!', 'updated', null, '055-price-tags');
				} else {
					DomainCheckAdmin::admin_notices_add('Coupon update failure.', 'error', null, '055-price-tags');
				}
			}
			if (isset($_GET['domain_check_coupons_search']) && $_GET['domain_check_coupons_search']) {
				$coupons = DomainCheckCouponData::search($_GET['domain_check_coupons_search']);
				$found = 0;
				foreach ($coupons as $coupon_site => $coupon_data) {
					if (isset($coupon_data['links']['link'])) {
						$found += count($coupon_data['links']['link']);
					}
				}
				if ($found) {
					$message = 'Success! Found ' . $found . ' Coupons for "' . htmlentities($_GET['domain_check_coupons_search']) . '"!';
					DomainCheckAdmin::admin_notices_add(
						$message,
						'updated',
						null,
						'055-price-tags'
					);
				} else {
					$message = 'No Coupons found for "' . htmlentities($_GET['coupon_search']) . '"!';
					DomainCheckAdmin::admin_notices_add(
						$message,
						'error',
						null,
						'055-price-tags'
					);
				}
			}
		}

		public function coupons_search_box() {
			?>
			<form action="" method="GET">
				<input type="text" name="domain_check_coupons_search" id="domain_check_coupons_search">
				<input type="hidden" name="page" value="domain-check-coupons">
				<input type="submit" class="button" value="Search Coupons" />
			</form>
			<?php
		}

		public function dashboard() {
			global $wpdb;
			$this->admin_header();
			?>
			<style type="text/css">
				.domain-check-admin-dashboard-search-box {
					max-width: 450px;
					min-width: 350px;
					display: inline-block;
					float: left;
					background-color: #ffffff;
					padding:10px;
					margin:10px;
				}
				.domain-check-dasboard-table-tr {
					height: 28px;
				}
			</style>
			<div class="wrap">
				<h2>
					<img src="<?php echo plugins_url('/images/icons/circle-www2.svg', __FILE__); ?>" class="svg svg-icon-h1 svg-fill-gray">
					Domains
				</h2>
				<div class="domain-check-admin-dashboard-search-box">
				<h3>
					<a href="<?php echo admin_url( 'admin.php?page=domain-check-search' ); ?>">
					<img src="<?php echo plugins_url('/images/icons/magnifying-glass.svg', __FILE__); ?>" class="svg svg-icon-h3 svg-fill-gray">
					Domain Search
					</a>
				</h3>
				<?php
				$this->search_box();
				?>
					<h3>
						<a href="<?php echo admin_url( 'admin.php?page=domain-check-your-domains' ); ?>">
						<img src="<?php echo plugins_url('/images/icons/flag.svg', __FILE__); ?>" class="svg svg-icon-h3 svg-fill-owned">
						Your Domains
						</a>
					</h3>
					<table style="width: 100%;">
					<?php
					$sql = 'SELECT * FROM ' . DomainCheck::$db_prefix . '_domains WHERE status = 2 AND domain_expires > 0 ORDER BY domain_expires ASC LIMIT 0, 10';
					$result = $wpdb->get_results( $sql, 'ARRAY_A' );
					if (count($result)) {
						foreach ($result as $item) {
							if (isset($item['domain_expires']) && $item['domain_expires']) {
								$expire_days = number_format(($item['domain_expires'] - time())/60/60/24, 0) . ' Days';
								$days = number_format(($item['domain_expires'] - time())/60/60/24, 0);
								$days_flat = (int)floor(($item['domain_expires'] - time())/60/60/24);
								if ($days_flat < 60) {
									$fill = 'gray';
									if ($days_flat < 30) {
										$fill = 'update-nag';
									}
									if ($days_flat < 10) {
										$fill = 'error';
									}
									if ($days_flat < 3) {
										$fill = 'red';
									}
									if ($expire_days < 0) {
										$expire_days = 'Expired';
									}
									$expire_days = '<img src="' . plugins_url('/images/icons/clock.svg', __FILE__) . '" class="svg svg-icon-table svg-fill-' . $fill . '">' . $expire_days;
								}
							} else {
								$expire_days = 'n/a';
							}
						?>
						<tr class="domain-check-dasboard-table-tr">
							<td>
								<strong>
								<a href="?page=domain-check-profile&domain=<?php echo $item['domain_url']; ?>"><?php echo $item['domain_url']; ?></a>
								</strong>
							</td>
							<td>
								<?php
								echo $expire_days;
								?></td>
							<td><?php
								if (isset($item['domain_expires']) && $item['domain_expires']) {
										$days = number_format(($item['domain_expires'] - time())/60/60/24, 0);
										$days_flat = (int)floor(($item['domain_expires'] - time())/60/60/24);
										if ($days_flat < 60) {
											?>
											<a href="?page=domain-check&domain_check_search=<?php echo $item['domain_url']; ?>" class="button">
												<img src="<?php echo plugins_url('/images/icons/303-loop2.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-gray">
											</a>
											<a class="button" href="<?php echo DomainCheckLinks::homepage($item['domain_url']); ?>" target="_blank">
											Renew
											</a>
											<?php
										}
									}
									?></td>
						</tr>
						<?php
						}
					} else {

					}
					?>
					</table>
					<h3>
						<a href="<?php echo admin_url( 'admin.php?page=domain-check-watch' ); ?>">
						<img src="<?php echo plugins_url('/images/icons/207-eye.svg', __FILE__); ?>" class="svg svg-icon-h3 svg-fill-gray">
						Watched Domains
						</a>
					</h3>
					<table style="width: 100%;">
					<?php
					$sql = 'SELECT * FROM ' . DomainCheck::$db_prefix . '_domains WHERE domain_watch > 0 AND domain_expires > 0 ORDER BY domain_expires ASC LIMIT 0, 10';
					$result = $wpdb->get_results( $sql, 'ARRAY_A' );
					if (count($result)) {
						foreach ($result as $item) {
							if (isset($item['domain_expires']) && $item['domain_expires']) {
								$expire_days = number_format(($item['domain_expires'] - time())/60/60/24, 0) . ' Days';
								$days = number_format(($item['domain_expires'] - time())/60/60/24, 0);
								$days_flat = (int)floor(($item['domain_expires'] - time())/60/60/24);
								if ($days_flat < 60) {
									$fill = 'gray';
									if ($days_flat < 30) {
										$fill = 'update-nag';
									}
									if ($days_flat < 10) {
										$fill = 'error';
									}
									if ($days_flat < 3) {
										$fill = 'red';
									}
									if ($expire_days < 0) {
										$expire_days = 'Expired';
									}
									$expire_days = '<img src="' . plugins_url('/images/icons/clock.svg', __FILE__) . '" class="svg svg-icon-table svg-fill-' . $fill . '">' . $expire_days;
								}
							} else {
								$expire_days = 'n/a';
							}
						?>
						<tr class="domain-check-dasboard-table-tr">
							<td>
								<strong>
								<a href="?page=domain-check-profile&domain=<?php echo $item['domain_url']; ?>"><?php echo $item['domain_url']; ?></a>
								</strong>
							</td>
							<td><?php echo $expire_days; ?></td>
							<td><?php
							if (isset($item['domain_expires']) && $item['domain_expires']) {
									$days = number_format(($item['domain_expires'] - time())/60/60/24, 0);
									$days_flat = (int)floor(($item['domain_expires'] - time())/60/60/24);
									if ($days_flat < 60) {
										?>
										<a href="?page=domain-check&domain_check_search=<?php echo $item['domain_url']; ?>" class="button">
																												<img src="<?php echo plugins_url('/images/icons/303-loop2.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-gray">
																											</a>
										<a href="<?php echo DomainCheckLinks::homepage($item['domain_url']); ?>" class="button" target="_blank">
										Renew
										</a>
										<?php
									}
								}
								?></td>
						</tr>
						<?php
						}
					} else {

					}
					?>
					</table>
				</div>
				<div class="domain-check-admin-dashboard-search-box">
					<h3>
						<a href="<?php echo admin_url( 'admin.php?page=domain-check-ssl-check' ); ?>">
						<img src="<?php echo plugins_url('/images/icons/lock-locked.svg', __FILE__); ?>" class="svg svg-icon-h3 svg-fill-update-nag">
						SSL Check
						</a>
					</h3>
				<?php
				$this->ssl_search_box();
				?>
				<h3>
					<a href="<?php echo admin_url( 'admin.php?page=domain-check-ssl-watch' ); ?>">
					<img src="<?php echo plugins_url('/images/icons/bell.svg', __FILE__); ?>" class="svg svg-icon-h3 svg-fill-gray">
					SSL Expiration Alerts
					</a>
				</h3>
				<table style="width: 100%;">
				<?php
				$sql = 'SELECT * FROM ' . DomainCheck::$db_prefix . '_ssl WHERE domain_watch > 0 ORDER BY domain_expires ASC LIMIT 0, 10';
				$result = $wpdb->get_results( $sql, 'ARRAY_A' );
				if (count($result)) {
					foreach ($result as $item) {
						if (isset($item['domain_expires']) && $item['domain_expires']) {
							$expire_days = number_format(($item['domain_expires'] - time())/60/60/24, 0) . ' Days';
							$days = number_format(($item['domain_expires'] - time())/60/60/24, 0);
							$days_flat = (int)floor(($item['domain_expires'] - time())/60/60/24);
							if ($days_flat < 60) {
								$fill = 'gray';
								if ($days_flat < 30) {
									$fill = 'update-nag';
								}
								if ($days_flat < 10) {
									$fill = 'error';
								}
								if ($days_flat < 3) {
									$fill = 'red';
								}
								if ($expire_days < 0) {
									$expire_days = 'Expired';
								}
								$expire_days = '<img src="' . plugins_url('/images/icons/clock.svg', __FILE__) . '" class="svg svg-icon-table svg-fill-' . $fill . '">' . $expire_days;
							} else {
								$expire_days = '<img src="' . plugins_url('/images/icons/lock-locked.svg', __FILE__) . '" class="svg svg-icon-table svg-fill-updated">' . $expire_days;
							}
						} else {
							$expire_days = $expire_days = '<img src="' . plugins_url('/images/icons/lock-unlocked.svg', __FILE__) . '" class="svg svg-icon-table svg-fill-error">' . 'Not Secure';
						}
					?>
					<tr class="domain-check-dasboard-table-tr">
						<td>
							<strong>
								<a href="?page=domain-check-ssl-profile&domain=<?php echo $item['domain_url']; ?>">
							<?php echo $item['domain_url']; ?></a>
							</strong>
						</td>
						<td><?php echo $expire_days; ?></td>
						<td><?php
						if (isset($item['domain_expires']) && $item['domain_expires']) {
								$days = number_format(($item['domain_expires'] - time())/60/60/24, 0);
								$days_flat = (int)floor(($item['domain_expires'] - time())/60/60/24);
								if ($days_flat < 60) {
									?>
									<a href="?page=domain-check&domain_check_ssl_search=<?php echo $item['domain_url']; ?>" class="button">
										<img src="<?php echo plugins_url('/images/icons/303-loop2.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-gray">
									</a>
									<a href="<?php echo DomainCheckLinks::ssl($item['domain_url']); ?>" class="button" target="_blank">
									Renew
									</a>
									<?php
								}
							} else {
							?>
							<a href="?page=domain-check&domain_check_ssl_search=<?php echo $item['domain_url']; ?>" class="button">
								<img src="<?php echo plugins_url('/images/icons/303-loop2.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-gray">
							</a>
							<a href="<?php echo DomainCheckLinks::ssl($item['domain_url']); ?>" class="button" target="_blank">
							Fix
							</a>
							<?php
						}
							?></td>
					</tr>
					<?php
					}
				} else {

				}
				?>
				</table>
				<h3>
					<a href="<?php echo admin_url( 'admin.php?page=domain-check-ssl-watch' ); ?>">
					<img src="<?php echo plugins_url('/images/icons/lock-locked.svg', __FILE__); ?>" class="svg svg-icon-h3 svg-fill-green">
					Recent SSL Checks
					</a>
				</h3>
				<table style="width: 100%;">
				<?php
				$sql = 'SELECT * FROM ' . DomainCheck::$db_prefix . '_ssl ORDER BY search_date DESC LIMIT 0, 10';
				$result = $wpdb->get_results( $sql, 'ARRAY_A' );
				if (count($result)) {
					foreach ($result as $item) {
						if (isset($item['domain_expires']) && $item['domain_expires']) {
							$expire_days = number_format(($item['domain_expires'] - time())/60/60/24, 0) . ' Days';
							$days = number_format(($item['domain_expires'] - time())/60/60/24, 0);
							$days_flat = (int)floor(($item['domain_expires'] - time())/60/60/24);
							if ($days_flat < 60) {
								$fill = 'gray';
								if ($days_flat < 30) {
									$fill = 'update-nag';
								}
								if ($days_flat < 10) {
									$fill = 'error';
								}
								if ($days_flat < 3) {
									$fill = 'red';
								}
								if ($expire_days < 0) {
									$expire_days = 'Expired';
								}
								$expire_days = '<img src="' . plugins_url('/images/icons/clock.svg', __FILE__) . '" class="svg svg-icon-table svg-fill-' . $fill . '">' . $expire_days;
							} else {
								$expire_days = '<img src="' . plugins_url('/images/icons/lock-locked.svg', __FILE__) . '" class="svg svg-icon-table svg-fill-updated">' . $expire_days;
							}

						} else {
							$expire_days = '<img src="' . plugins_url('/images/icons/lock-unlocked.svg', __FILE__) . '" class="svg svg-icon-table svg-fill-error">' . 'Not Secure';
						}
					?>
					<tr class="domain-check-dasboard-table-tr">
						<td>
							<strong>
								<a href="?page=domain-check-ssl-profile&domain=<?php echo $item['domain_url']; ?>">
							<?php echo $item['domain_url']; ?></a>
							</strong>
						</td>
						<td><?php echo $expire_days; ?></td>
						<td><?php
						if (isset($item['domain_expires']) && $item['domain_expires']) {
								$days = number_format(($item['domain_expires'] - time())/60/60/24, 0);
								$days_flat = (int)floor(($item['domain_expires'] - time())/60/60/24);
								if ($days_flat < 60) {
									?>
									<a href="?page=domain-check&domain_check_ssl_search=<?php echo $item['domain_url']; ?>" class="button">
										<img src="<?php echo plugins_url('/images/icons/303-loop2.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-gray">
									</a>
									<a href="<?php echo DomainCheckLinks::ssl($item['domain_url']); ?>" class="button" target="_blank">
									Renew
									</a>
									<?php
								}
							} else {
							?>
							<a href="?page=domain-check&domain_check_ssl_search=<?php echo $item['domain_url']; ?>" class="button">
								<img src="<?php echo plugins_url('/images/icons/303-loop2.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-gray">
							</a>
							<a href="<?php echo DomainCheckLinks::ssl($item['domain_url']); ?>" class="button" target="_blank">
							Fix
							</a>
							<?php
						}
							?></td>
					</tr>
					<?php
					}
				} else {

				}
				?>
				</table>
				</div>
				<div class="domain-check-admin-dashboard-search-box">
					<h3>
						<a href="<?php echo admin_url( 'admin.php?page=domain-check-coupons' ); ?>">
						<img src="<?php echo plugins_url('/images/icons/055-price-tags.svg', __FILE__); ?>" class="svg svg-icon-h3 svg-fill-green">
						Coupons &amp; Deals
						</a>
					</h3>
				<?php
				$this->coupons_search_box();
				?>
				<?php
				$coupon_last_updated = DomainCheckCouponData::last_updated();
				if ($coupon_last_updated) {
				?>
				<h4>Updated: <?php echo date('m-d-Y', $coupon_last_updated); ?></h4>
				<a href="admin.php?page=domain-check&domain_check_coupons_update=1" class="button">
					<img src="<?php echo plugins_url('/images/icons/303-loop2.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-gray">
					Refresh Coupons
				</a>
				<?php } ?>
					<style type="text/css">
						.dashboard-coupon-table {

						}
					</style>
				<?php
				$coupons = DomainCheckCouponData::get_data();
				$coupon_site_counter = 0;
				foreach ($coupons as $coupon_site => $coupon_data) {
				?>
				<h3>
					<a href="admin.php?page=domain-check-coupons&domain_check_coupons_site=<?php echo $coupon_site; ?>">
					<?php echo ucfirst($coupon_site); ?>
					<div style="float:right; display: inline-block; font-size: 12px;">More [&raquo;]</div>
					</a>
				</h3>
				<table id="dashboard-coupon-table-<?php echo $coupon_site_counter; ?>" class="dashboard-coupon-table">
				<?php
				if (count($coupon_data['links']['link'])) {
					$coupon_ads = array();
					$text_ads = array();
					$img_ads = array();
					foreach ($coupon_data['links']['link'] as $coupon_link_idx => $coupon_link_data) {
						if (isset($coupon_link_data['link-type']) && $coupon_link_data['link-type']) {
							if ($coupon_link_data['link-type'] == 'Text Link') {
								if (isset($coupon_link_data['coupon-code'])
									&& ((is_array($coupon_link_data['coupon-code']) && count($coupon_link_data['coupon-code'])) || $coupon_link_data['coupon-code'])) {
									$coupon_ads[] = $coupon_link_idx;
								} else {
									$text_ads[] = $coupon_link_idx;
								}

							} else {
								$img_ads[] = $coupon_link_idx;
							}

						}
					}

				$limit = 3;
				$count = 0;
					foreach ($coupon_ads as $coupon_link_idx) {
						$coupon_link_data = $coupon_data['links']['link'][$coupon_link_idx];
						if ($count >= $limit) {
							break;
						}
						?>
						<tr style="background-color: #FFFFFF; color: #FFFFFF;">
							<td style="overflow: hidden;">
						<div class="domain-check-coupon-ad">
								<strong>
								<a href="<?php echo $coupon_link_data['clickUrl']; ?>" target="_blank">
							<?php
							echo $coupon_link_data['link-code-html'];
							if (isset($coupon_link_data['coupon-code'])
								&& ((is_array($coupon_link_data['coupon-code']) && count($coupon_link_data['coupon-code'])) || $coupon_link_data['coupon-code'])) {
								?>
								</a>
								</strong>
							</div>
							</td>
							<td style="border:1px #000000 dashed; width: 20%;">
								<div style="text-align: center;">
								<a href="<?php echo $coupon_link_data['clickUrl']; ?>" style="color:#000000;" target="_blank">
									<strong>
										<?php echo $coupon_link_data['coupon-code']; ?>
									</strong>
								</a>
								</div>
							</td>
							<?php
							}
							?>
							</div>
							</td>
						</tr>
						<?php
						$count++;
					}
					shuffle($text_ads);
					foreach ($text_ads as $coupon_link_idx) {
						if ($count >= $limit) {
							break;
						}
						$coupon_link_data = $coupon_data['links']['link'][$coupon_link_idx];
						?>
						<tr>
							<td colspan="1">
								<div alt="<?php echo htmlentities($coupon_link_data['description']); ?>" title="<?php echo htmlentities($coupon_link_data['description']); ?>">
									<a href="<?php echo $coupon_link_data['clickUrl']; ?>" target="_blank">
							<?php
							echo $coupon_link_data['link-code-html'];
							?>
										</a>
						</div>
							</td>
						</tr>
						<?php
						$count++;
					}
					?>
					</div>
					<?php
				} else {

				}
				?>
				</table>
				<?php
			}
			$coupon_site_counter++;
			?>
			</div>
		<?php
		}

		public function delete_init() {
			global $wpdb;

			$domain = strtolower($_GET['domain_check_delete']);

			if (!isset($_GET['domain_check_delete_confirm'])) {
				$message = 'Are you sure you want to delete <strong> ' . $domain . ' </strong>? It will no longer be watched and may expire! This cannot be undone.';
				$message_options = array(
					'Delete' => '?page=domain-check-search&domain_check_delete=' . $domain . '&domain_check_delete_confirm=' . $domain,
					'Cancel' => '?page=domain-check-search'
				);
				DomainCheckAdmin::admin_notices_add($message, 'error', $message_options, '174-bin2');
			} else {
				if ($_GET['domain_check_delete_confirm'] == $_GET['domain_check_delete']) {
					$wpdb->delete(
						DomainCheck::$db_prefix . '_domains',
						array(
							'domain_url' => $domain
						)
					);
					$message = 'Success! You deleted <strong>' . $domain . '</strong>!';
					DomainCheckAdmin::admin_notices_add($message, 'updated', null, '174-bin2');
				}
			}
		}

		public function domain_check() {
			$this->dashboard();
		}

		public function domain_search($domain, $use_cache = false, $force_owned = false, $force_watch = false, $ajax = false) {
			global $wpdb;

			$domain_data = DomainCheckWhois::validdomain($domain);
			if ($domain_data) {
				$domain_parse = $domain_data['domain'];
				$domain_root = $domain_data['domain'];
				$search = $domain_data['domain'];
				$domain_extension = $domain_data['domain_extension'];
				$fqdn = str_replace('.' . $domain_extension, '', $domain_parse);

				//favorites
				$fqdn = str_replace('.' . $domain_extension, '', $domain_parse);
				$domain_extension_favorites = DomainCheckConfig::$options[DomainCheckConfig::OPTIONS_PREFIX . 'settings']['domain_extension_favorites'];
				if (function_exists('get_option')) {
					$tmp_domain_extension_favorites = get_option(DomainCheckConfig::OPTIONS_PREFIX . 'domain_extension_favorites');
					if ($tmp_domain_extension_favorites && count($tmp_domain_extension_favorites)) {
						$domain_extension_favorites = $tmp_domain_extension_favorites;
					}
				}
				$message_options = array();
				foreach ($domain_extension_favorites as $domain_extension_favorite) {
					if ($domain_extension_favorite != $domain_extension) {
						$message_options['.' . $domain_extension_favorite] = 'admin.php?page=domain-check-search&domain_check_search='.$fqdn.'.'.$domain_extension_favorite;
					}
				}
				$message_options['Info'] = 'admin.php?page=domain-check-profile&domain='.$search;
				$message_options['Launch [&raquo]'] = $search;


				$sql = 'SELECT * FROM ' . DomainCheck::$db_prefix . '_domains WHERE domain_url ="' . strtolower($search) . '"';
				$result = $wpdb->get_results( $sql, 'ARRAY_A' );
				$use_cache = false;
				$in_db = false;
				if ( count ( $result ) ) {
					$in_db = true;
					$domain_result = array_pop($result);
					if ($domain_result['domain_expires'] > time() &&
						$domain_result['domain_last_check'] > (time() - (60 * 60 * 24 * 30))
					) {
						$use_cache = true;
					}
				}
				if (isset($_GET['cache'])) {
					$use_cache = (int)$_GET['cache'];
				}
				if ($use_cache) {
					//domain exists in DB...
					$new_data = array (
						'search_date' => time(),
					);
					if ($domain_result['status'] && $force_owned) {
						$new_data['status'] = 2;
						$domain_result['status'] = 2;
					}
					$wpdb->update(
						DomainCheck::$db_prefix . '_domains',
						$new_data,
						array (
							'domain_url' => strtolower($search)
						)

					);

					if (!$domain_result['status']) {
						$message = 'Yes! <strong><a href="">' . $search . '</a></strong> is available!';
						DomainCheckAdmin::admin_notices_add($message, 'updated', $message_options);
						$ajax_response = DomainCheckAdmin::ajax_success(array('message' => $message, 'status' => 0, 'domain' => $search));
					} else {
						if ($domain_result['status'] == 2) {
							$message = 'Success, you already own <strong><a href="">' . $search . '</a></strong>!';
							DomainCheckAdmin::admin_notices_add($message, 'owned', $message_options, 'flag');
							$ajax_response = array('message' => $message, 'status' => 2, 'domain' => $search, 'domain' => $search);
						} else {
							$status = 1;
							$message = 'Sorry, <strong><a href="">' . $search . '</a></strong> is not an available domain and is taken.';
							DomainCheckAdmin::admin_notices_add($message, 'error', $message_options, 'ban');
							$ajax_response = array('message' => $message, 'status' => 1, 'domain' => $search);
						}
					}

					if ($ajax) {
						DomainCheckAdmin::ajax_success($ajax_response);
					}
				} else {

					$dot = strpos($domain_root, '.');
					$sld = substr($domain_root, 0, $dot);
					$tld = substr($domain_root, $dot + 1);

					$whois = DomainCheckWhois::dolookup($domain_root);
					if (!isset($whois['error'])) {
						$ajax_response = null;
						$status = 0;
						$expires = 0;
						if ($whois['status'] == 0) {
							$message = 'Yes! <strong>' . $search . '</strong> is available!';
							DomainCheckAdmin::admin_notices_add(
								$message,
								'updated',
								null,
								'circle-check'
							);
							$ajax_response = array('message' => $message, 'status' => 0, 'domain' => $search);
						} else {
							if (isset($message_options['.' . $domain_parse[count($domain_parse)-1]])) {
								unset($message_options['.' . $domain_parse[count($domain_parse)-1]]);
							}
							if (($in_db && isset($domain_result) && $domain_result['status'] == 2) || $force_owned) {
								$status = 2;
								$domain_result['status'] = 2;
								$message = 'Success, you already own <a href=""><strong>' . $search . '</strong></a>!';
								DomainCheckAdmin::admin_notices_add($message, 'owned', $message_options, 'flag');
								$ajax_response = array('message' => $message, 'status' => 2, 'domain' => $search);
							} else {
								$status = 1;
								$message = 'Sorry, <strong>' . $search . '</strong> is not an available domain and is taken.';
								DomainCheckAdmin::admin_notices_add($message, 'error', $message_options, 'ban');
								$ajax_response = array('message' => $message, 'status' => 1, 'domain' => $search);
							}
							$expires = $whois['domain_expires'];
						}

						//$sql = 'INSERT INTO wp_domain_check_domains VALUES (null, "' . $search . '", 0, ' . $status . ', '.time().', 0, '.$expires.', null, null)';
						if (!$in_db) {
							$valarr = array(
								'domain_id' => null,
								'domain_url' => $search,
								'user_id' => 0,
								'status' => $status,
								'date_added' => time(),
								'search_date' => time(),
								'domain_created' => 0,
								'domain_last_check' => time(),
								'domain_next_check' => 0,
								'domain_expires' => $expires,
								'domain_settings' => null,
								'cache' => gzcompress(json_encode($whois)),
							);

							$wpdb->insert(
								'wp_domain_check_domains',
								$valarr
							);
						} else {
							if ($domain_result['status'] == 2) {
								$status = 2;
							}
							$valarr = array(
								'status' => $status,
								'search_date' => time(),
								'domain_created' => 0,
								'domain_last_check' => time(),
								'domain_expires' => $expires,
								'cache' => gzcompress(json_encode($whois)),
							);
							$wpdb->update(
								DomainCheck::$db_prefix . '_domains',
								$valarr,
								array (
									'domain_url' => $search
								)
							);
						}

						if ($ajax) {
							DomainCheckAdmin::ajax_success($ajax_response);
						}
					} else {
						DomainCheckAdmin::admin_notices_add($whois['error'], 'error', null, 'circle-x');
						if ($ajax) {
							DomainCheckAdmin::ajax_error(strip_tags($whois['error']));
						}
					}
				}
			} else {
				DomainCheckAdmin::admin_notices_add('<strong>' . htmlentities($domain) . '</strong> is not a valid domain.', 'error', null, 'circle-x');
				if ($ajax) {
					DomainCheckAdmin::ajax_error(strip_tags($domain) . ' is not a valid domain.');
				}
			}
		}

		public function domain_url_extension($url) {
			return DomainCheckWhois::dolookup($url);
		}

		public function domain_url_validate($url) {}

		/** Singleton instance */
		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function import_export() {
			global $wpdb;

			if (isset($_FILES['domain_check_your_domains_import'])) {
				$firstrow = null;
				$filedata = explode("\n", file_get_contents($_FILES['domain_check_your_domains_import']['tmp_name']));
				foreach ($filedata as $filedata_idx => $file_line) {
					if ($file_line == '' || $file_line == "\n") {
						break;
					}
					$file_line = explode(',', $file_line);
					$filedata[$filedata_idx] = $file_line;
					//DomainName,TLD,CreateDate,ExpirationDate,Status,Privacy,Locked
					$header_counter = 0;
					foreach ($file_line as $file_line_data) {
						if ($file_line_data == 'DomainName') {
							$firstrow = array_flip($file_line);
							break;
						}
						$search = strtolower(str_replace('"', '', $file_line[$firstrow['DomainName']]));
						//check if is in DB...
						if ( $search ) {
							$sql = 'SELECT * FROM ' . DomainCheck::$db_prefix . '_domains WHERE domain_url ="' . strtolower($search) . '"';
							$result = $wpdb->get_results( $sql, 'ARRAY_A' );
							if ( count ( $result ) ) {
								//domain exists in DB...
								$domain_result = array_pop($result);

								$sql = 'UPDATE ' . DomainCheck::$db_prefix . '_domains SET status = 2 WHERE domain_url = "' . $search . '"';

								$wpdb->update(
									DomainCheck::$db_prefix . '_domains',
									array(
										'status' => 2
									),
									array (
										'domain_url' => $search
									)
								);

								DomainCheckAdmin::admin_notices_add('Success! Data imported!', 'updated', null, 'data-transfer-upload');

							} else {
								$domain_root = $search;
								$dot = strpos($domain_root, '.');
								$sld = substr($domain_root, 0, $dot);
								$tld = substr($domain_root, $dot + 1);

								$whois = $this->getwhois( $sld, $tld );
								if (!isset($whois['error'])) {

									$status = 0;
									$expires = 0;
									if (strpos($whois['data'], 'No match for domain') !== false) {
										DomainCheckAdmin::admin_notices_add('<strong>' . $search . '</strong> is not yet registered.', 'updated', null, 'circle-check');
									} else {
										$whois = explode("\n", $whois['data']);
										foreach ($whois as $line) {
											if (strpos($line, 'Expiration Date') !== false) {
												$line = explode(' ', $line);
												$dateArr = explode('-', $line[5]);
												$expires = mktime(0, 0, 0, date('m', strtotime(ucfirst($dateArr[1]))), $dateArr[0], $dateArr[2]);

												//echo '<br>' . "\n";
											}
										}
									}

									$sql = 'INSERT INTO wp_domain_check_domains VALUES (null, "' . $search . '", 0, 2, '.time().', 0, '.$expires.', null, null)';

									$valarr = array(
										'domain_id' => null,
										'domain_url' => $search,
										'user_id' => 0,
										'status' => 2,
										'date_added' => time(),
										'domain_watch' => 1,
										'domain_created' => 0,
										'domain_last_check' => time(),
										'domain_next_check' => 0,
										'domain_expires' => $expires,
										'domain_settings' => null,
										'cache' => null,
									);

									$wpdb->insert(
										'wp_domain_check_domains',
										$valarr
									);

									//echo $sql;
								} else {
									DomainCheckAdmin::admin_notices_add($whois['error'], 'error', null, 'circle-x');
								}
							}
						}
						break;
					}
				}
			}
			$this->admin_header();
			?>
			<div class="wrap">
				<h2>
					<img src="<?php echo plugins_url('/images/icons/data-transfer-upload.svg', __FILE__); ?>" class="svg svg-icon-h1 svg-fill-gray">
					<img src="<?php echo plugins_url('/images/icons/data-transfer-download.svg', __FILE__); ?>" class="svg svg-icon-h1 svg-fill-gray">
					Import / Export
				</h2>
				<script type="text/javascript">
					function unique_domains(domain_array) {
						var unique_array = new Array();
						for (var i = 0; i < domain_array.length; i++ ) {
							var found_domain = false;
							for (var j = 0; j< unique_array.length;j++ ) {
								if (unique_array[j].toLowerCase() == domain_array[i].toLowerCase()) {
									found_domain = true;
									break;
								}
							}
							if (!found_domain) {
								unique_array.push(domain_array[i].toLowerCase());
							}
						}
						return unique_array;
					}

					function strip(arrayName) {
						var newArray = new Array();
						label:for (var i = 0; i < arrayName.length; i++ ) {
							//is there a list of reserved words that cannot be used for TLDs?
							if (arrayName[i].toLowerCase().match(/(pdf|php|html|htm|doc|txt|asp|aspx|cfm|sh|png|pdf|jpeg|jpg|gif|swf)$/)) {
								continue label;
							}
							newArray[newArray.length] = arrayName[i].toLowerCase();
						}
						return newArray;
					}

					function import_text_get_domains(import_data) {
						//a little sensitive but works...
						var regex_all_domains = eval("/([a-zA-Z0-9][-a-zA-Z0-9]*[a-zA-Z0-9]|[a-zA-Z0-9])\\.(([a-zA-Z]{2,4}|[a-zA-Z]{2,10}.[a-zA-Z0-9]{2,10}))(?![-0-9a-zA-Z])(?!\\.[a-zA-Z0-9])/gi");

						var import_data_domains = import_data.match(regex_all_domains);
						if (import_data_domains) {
							import_data_domains = unique_domains(strip(import_data_domains)).sort();
						} else {
							import_data_domains = null;
						}
						return import_data_domains;
					}

					var domains_to_search = null;
					var import_text_looping = false;
					var search_domain = null;
					function import_text_search() {
						var domain_arr = document.getElementById('found_domains').value.split("\n");
						if (domain_arr && domain_arr.length > 0 && !import_text_looping) {
							domains_to_search = domain_arr;
							domains_to_search.reverse();
							import_text_looping = true;
							import_text_loop({});
						}
					}

					function import_text_loop(data) {
						var plugins_url_icons = '<?php echo plugins_url('/images/icons/', __FILE__); ?>';
						if (!data.hasOwnProperty('error')) {
							if (data.hasOwnProperty('domain')) {
								if (!jQuery('#force_ssl').prop('checked')) {
									var status_image = '<img src="'  + plugins_url_icons + 'circle-check.svg" class="svg svg-icon-table svg-fill-updated">';
									var status_text = 'Available!';
									switch (data.status) {
										case 0:
											status_text = 'Available!';
											status_image = '<img src="'  + plugins_url_icons + 'circle-check.svg" class="svg svg-icon-table svg-fill-update-updated">';
											break;
										case 1:
											status_text = 'Taken';
											status_image = '<img src="'  + plugins_url_icons + 'ban.svg" class="svg svg-icon-table svg-fill-error">';
											break;
										case 2:
											status_text = 'Owned';
											status_image = '<img src="'  + plugins_url_icons + 'flag.svg" class="svg svg-icon-table svg-fill-owned">';
											break;
									}
									var table_row = '<tr>' +
										'<td>' +
										'<a href="admin.php?page=domain-check-search&domain_check_search=' + data.domain + '">' +
										data.domain +
										'</td>' +
										'<td>' + status_image + status_text + '</td>' +
										'</tr>';
									jQuery('#import-text-results-table').append(table_row);
								} else {
									var status_image = '<img src="'  + plugins_url_icons + 'lock-unlocked.svg" class="svg svg-icon-table svg-fill-updated">';
									var status_text = 'Not Secure';
									switch (data.status) {
										case 0:
											status_text = 'Not Secure';
											status_image = '<img src="'  + plugins_url_icons + 'lock-unlocked.svg" class="svg svg-icon-table svg-fill-error">';
											break;
										case 1:
											status_text = 'Secure';
											status_image = '<img src="'  + plugins_url_icons + 'lock-locked.svg" class="svg svg-icon-table svg-fill-updated">';
											break;
									}
									var table_row = '<tr>' +
										'<td>' +
										'<a href="admin.php?page=domain-check-ssl-search&domain_check_ssl_search=' + data.domain + '">' +
										data.domain +
										'</td>' +
										'<td>' + status_image + status_text + '</td>' +
										'</tr>';
									jQuery('#import-text-results-table').append(table_row);

								}
							}
						} else {
							//error w/ last domain...
							if (!jQuery('#force_ssl').prop('checked')) {
								status_text = 'Taken';
								status_image = '<img src="'  + plugins_url_icons + 'ban.svg" class="svg svg-icon-table svg-fill-error">';
								var table_row = '<tr>' +
									'<td>' +
									'<a href="admin.php?page=domain-check-search&domain_check_search=' + data.error.domain + '">' +
									data.error.domain +
									'</td>' +
									'<td>' + status_image + status_text + '</td>' +
									'</tr>';
								jQuery('#import-text-results-table').append(table_row);
							} else {
								status_text = 'Not Secure';
								status_image = '<img src="'  + plugins_url_icons + 'lock-unlocked.svg" class="svg svg-icon-table svg-fill-error">';
								var table_row = '<tr>' +
									'<td>' +
									'<a href="admin.php?page=domain-check-ssl-search&domain_check_ssl_search=' + data.error.domain + '">' +
									data.error.domain +
									'</td>' +
									'<td>' + status_image + status_text + '</td>' +
									'</tr>';
								jQuery('#import-text-results-table').append(table_row);
							}

						}
						if (domains_to_search && domains_to_search.length > 0) {
							search_domain = domains_to_search.pop();
							var data_obj = {
								action:'domain_search',
								domain: search_domain
							}
							if (jQuery('#force_owned').prop('checked')) {
								data_obj.force_owned = 1;
							}
							if (jQuery('#force_ssl').prop('checked')) {
								data_obj.force_owned = 0;
								data_obj.force_ssl = 1;
							}
							if (jQuery('#force_watch').prop('checked')) {
								data_obj.force_watch = 1;
							}
							domain_check_ajax_call(data_obj, import_text_loop);
							return;
						}
						import_text_looping = false;
					}


					//puts the image on a canvas tag
					function import_text_file_handler(e) {
						var imgIdx = 0;
						var i = 0, len = this.files.length, img, reader, file;
						for ( ; i < len; i++ ) {
							file = this.files[i];
							//if (!!file.type.match(/image.*/)) {
								if ( window.FileReader ) {
									reader = new FileReader();
									reader.onload = function(event){
										var found_domains = import_text_get_domains(event.target.result);
										if (found_domains) {
											document.getElementById("found_domains").value = found_domains.join("\n");
										}
									}
									reader.readAsText(e.target.files[0]);
								}
							//}
						}
					}
					//setup the image uploader
					function import_text_file_init() {
						//for checking the file input for user uploads
						document.getElementById('domain_check_your_domains_import').addEventListener('change', import_text_file_handler, false);
					}

					function import_text_raw_init() {
						var found_data = import_text_get_domains(document.getElementById('import_text').value);
						if (found_data) {
							document.getElementById('found_domains').value = found_data.join("\n");
						}
					}

				</script>
				<style type="text/css">
					.domain-check-import-left {
						max-width: 350px;
						min-width: 350px;
						min-height: 650px;
						display: inline-block;
						vertical-align: top;
						padding: 10px;
						margin: 10px;
						background-color: #ffffff;
					}
				</style>
				<p class="p">
					Use this tool to import your data and easily grab any domains from files, text lists, emails, or other documents.
				</p>
				<h2>
					<img src="<?php echo plugins_url('/images/icons/data-transfer-upload.svg', __FILE__); ?>" class="svg svg-icon-h2 svg-fill-gray">
					Import
				</h2>
				<div class="domain-check-import-left">
					<h2>Step 1</h2>
					<div>
						<h3>Copy & Paste Text!</h3>
						<p class="p">
							Copy and paste any text in to here to find any domain names! Extract domain names from any text, HTML, email, and anything you can copy & paste!
						</p>
						<textarea id="import_text" style="width: 100%; height: 200px;" onclick='if (this.value == "Copy and paste any text here and get the domains!") {this.value="";}'>Copy and paste any text here and get the domains!</textarea>
						<br>
						<input type="button" class="button" value="Find Domains" onclick="import_text_raw_init();"/>
					</div>
					<div style="min-height: 30px; text-align: center;"></div>
					<h3>File Import</h3>
					<p class="p">
						Upload any CSV or XML file! If you have an XLS file please save your file as a CSV (comma delimitted) file and upload!
					</p>
					<div>
						<form action="" method="POST" enctype="multipart/form-data">
							<input type="file" name="domain_check_your_domains_import" id="domain_check_your_domains_import">
						</form>
					</div>
				</div><?php
				//spacer
				?><div class="domain-check-import-left">
					<h2>Step 2</h2>
					<h3>Domains to Import</h3>
					<a href="#" class="button" value="Search" onclick="import_text_search();">
						Import
					</a>
					<div>
					<input type="checkbox" id="force_owned">&nbsp;-&nbsp;Import all as Owned<br>
					<input type="checkbox" id="force_ssl">&nbsp;-&nbsp;Import all as SSL<br>
					<input type="checkbox" id="force_watch">&nbsp;-&nbsp;Watch All Domains<br>
					</div>
					<textarea id="found_domains" style="width: 100%; height: 350px;"></textarea>
				</div><?php
				//spacer
				?><div class="domain-check-import-left">
					<h2>Step 3</h2>
					<h3>Domain Import Results</h3>
					<div id="import-text-results-wrapper" name="import-text-results-wrapper">
						<table id="import-text-results-table" name="import-text-results-table" style="width: 100%;"></table>
					</div>
				</div>
			</div>
			<script type="text/javascript">
				import_text_file_init();
			</script>
			<?php
		}

		public function profile() {
			global $wpdb;
			$this->admin_header();
			if (!isset($_GET['domain']) || !$_GET['domain']) {
				wp_redirect( admin_url( 'admin.php?page=domain-check-search' ) );
				return;
			}

			$domain_to_view = strtolower($_GET['domain']);
			$domain_result = null;
			$sql = 'SELECT * FROM ' . DomainCheck::$db_prefix . '_domains WHERE domain_url ="' . strtolower($domain_to_view) . '"';
			$result = $wpdb->get_results( $sql, 'ARRAY_A' );
			$use_cache = false;
			if ( count ( $result ) ) {
				$domain_result = array_pop($result);
			}
			?>
			<style type="text/css">
				.domain-check-profile-li {
					width: 100%;
				}
				.domain-check-profile-li:hover{
					background-color: #F4F4F4;
				}
				.domain-check-profile-li-div-left {
					width: 48%;
					text-align: left;
					display: inline-block;
					height: 100%;
				}
				.domain-check-profile-li-div-right {
					width: 45%;
					display: inline-block;
					padding-right: 10px;
					height: 100%;
				}
			</style>
			<div class="wrap">
				<h2>
					<?php
					$icon_src = DomainCheckAdmin::$admin_icon;
					$icon_fill = 'gray';
					if ($domain_result) {
						switch ($domain_result['status']) {
							case 0:
								$icon_src = plugins_url('/images/icons/circle-check.svg', __FILE__);
								$icon_fill = 'success';
								break;
							case 1:
								$icon_src = plugins_url('/images/icons/ban.svg', __FILE__);
								$icon_fill = 'taken';
								break;
							case 2:
								$icon_src = plugins_url('/images/icons/flag.svg', __FILE__);
								$icon_fill = 'owned';
								break;
							default:
								break;
						}
					}
					?>
					<img src="<?php echo $icon_src; ?>" class="svg svg-icon-h1 svg-fill-<?php echo $icon_fill; ?>">
					<?php echo $_GET['domain']; ?>
				</h2>
				<?php
				if ( $domain_result ) {
					?>
					<?php
					$domain_result['cache'] = ($domain_result['cache'] ? json_decode(gzuncompress($domain_result['cache']), true) : null);
					$domain_result['domain_settings'] = ($domain_result['domain_settings'] ? json_decode(gzuncompress($domain_result['domain_settings']), true) : null);
					?>
					<div style="max-width: 450px; min-width: 350px; display: inline-block; background: #ffffff; padding: 20px; float: left;">
							<a href="?page=domain-check-profile&domain=<?php echo $_GET['domain']; ?>&domain_check_search=<?php echo $_GET['domain']; ?>" class="button">
								<img src="<?php echo plugins_url('/images/icons/303-loop2.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-gray">
								Refresh
							</a>
							<a href="?page=domain-check-ssl-profile&domain=<?php echo $_GET['domain']; ?>&domain_check_ssl_search=<?php echo $_GET['domain']; ?>" class="button">
								<img src="<?php echo plugins_url('/images/icons/lock-locked.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-gray">
								SSL
							</a>
							<a href="?page=domain-check-profile&domain=<?php echo $_GET['domain']; ?>&domain_check_delete=<?php echo $_GET['domain']; ?>" class="button">
								<img src="<?php echo plugins_url('/images/icons/174-bin2.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-gray">
								Delete
							</a>
							<br>
						<ul style="display: inline-block; width: 100%; padding-right: 10px;">
							<!--li><strong>User ID:</strong> <?php echo $domain_result['user_id']; ?></li-->
							<li class="domain-check-profile-li">
								<div class="domain-check-profile-li-div-left">
									<strong>Status:</strong>
									<?php
								switch ($domain_result['status']) {
									case 0:
										?>
										Available
										<?php
										break;
									case 1:
										?>
										Taken
										<?php
										break;
									case 2:
										?>

										<?php
										break;
									default:
										?>
										Unknown (<?php echo $domain_result['status']; ?>)
										<?php
										break;
								}
								?></div>
								<div class="domain-check-profile-li-div-right">
								<?php
								switch ($domain_result['status']) {
									case 0:
										?>
										<a href="#">
											<img src="<?php echo plugins_url('/images/icons/circle-check.svg', __FILE__); ?>" class="svg svg-icon-table svg-fill-success">
											Available [&raquo;]
										</a>
										<?php
										break;
									case 1:
										?>
										<a href="admin.php?page=domain-check-profile&domain=<?php echo $_GET['domain']; ?>&domain_check_status_owned=<?php echo $_GET['domain']; ?>">
											<img src="<?php echo plugins_url('/images/icons/ban.svg', __FILE__); ?>" class="svg svg-icon-table svg-fill-taken">
											Taken
										</a>
										<?php
										break;
									case 2:
										?>
										<a href="admin.php?page=domain-check-profile&domain=<?php echo $_GET['domain']; ?>&domain_check_status_taken=<?php echo $_GET['domain']; ?>">
											<img src="<?php echo plugins_url('/images/icons/flag.svg', __FILE__); ?>" class="svg svg-icon-table svg-fill-owned">
											Owned
										</a>
										<?php
										break;
									default:
										?>
										Unknown (<?php echo $domain_result['status']; ?>)
										<?php
										break;
								}
								?>
								</div>
							</li>
							<li class="domain-check-profile-li">
								<div class="domain-check-profile-li-div-left">
								<strong>Watch:</strong>
								</div>
								<div class="domain-check-profile-li-div-right">
									<?php
									if (!$domain_result['domain_watch']) {
										?>
										<a href="?page=domain-check-profile&domain=<?php echo $_GET['domain']; ?>&domain_check_watch_start=<?php echo $_GET['domain']; ?>">
											<img src="<?php echo plugins_url('/images/icons/209-eye-minus.svg', __FILE__); ?>" class="svg svg-icon-table svg-fill-error">
											Not Watching
										</a>
										<?php
									} else {
										?>
										<a href="?page=domain-check-profile&domain=<?php echo $_GET['domain']; ?>&domain_check_watch_stop=<?php echo $_GET['domain']; ?>">
											<img src="<?php echo plugins_url('/images/icons/208-eye-plus.svg', __FILE__); ?>" class="svg svg-icon-table svg-fill-updated">
											Watching
										</a>
										<?php
									}
									?>
								</div>
							</li>
							<li class="domain-check-profile-li">
								<div class="domain-check-profile-li-div-left">
								<strong>Expires:</strong>
									<?php
									if ($domain_result['domain_expires']) {
										echo date('M-d-Y', $domain_result['domain_expires']);
									} else {
										echo 'n/a';
									}
									?>
								</div>
								<div class="domain-check-profile-li-div-right">
									<?php
									if ($domain_result['domain_expires']) {
										$days = number_format(($domain_result['domain_expires'] - time())/60/60/24, 0);
										$days_flat = (int)floor(($domain_result['domain_expires'] - time())/60/60/24);
										$out = '';
										if ($days_flat < 60) {
											$fill = 'gray';
											if ($days_flat < 30) {
												$fill = 'update-nag';
											}
											if ($days_flat < 10) {
												$fill = 'error';
											}
											if ($days_flat < 3) {
												$fill = 'red';
											}
											$out .= '<img src="' . plugins_url('/images/icons/clock.svg', __FILE__) . '" class="svg svg-icon-table svg-fill-' . $fill . '">';
										}
										if ($domain_result['domain_expires'] < time()) {
											$out .= ' Expired';
										} else {
											$out .= ' ' . number_format(($domain_result['domain_expires'] - time())/60/60/24, 0) . ' Days';
										}
										echo $out;
									}
									?>
								</div>
							</li>
							<!--li class="domain-check-profile-li">
								<strong>Created:</strong>
								<?php echo date('M-d-Y', $domain_result['domain_created']); ?>
							</li-->
							<li class="domain-check-profile-li"><strong>Last Check:</strong> <?php echo date('M-d-Y', $domain_result['domain_last_check']); ?></li>
							<!--li class="domain-check-profile-li"><strong>Next Check:</strong> <?php echo date('M-d-Y H:i:s', $domain_result['domain_next_check']); ?></li-->
							<!--li class="domain-check-profile-li"><strong>Search Date:</strong> <?php echo date('M-d-Y', $domain_result['search_date']); ?></li-->
							<li class="domain-check-profile-li"><strong>Date Added:</strong> <?php echo date('M-d-Y', $domain_result['date_added']); ?></li>
							<!--li><strong>Settings:</strong><?php
								echo str_replace("\n", '<br>' . "\n", print_r($domain_result['domain_settings'], true));
								?></li-->
						</ul>
</div>
						<div style="max-width: 450px; min-width: 350px; display: inline-block; background: #ffffff; padding: 20px; float:left;">
							<strong>Domain Expiration Watch Email Addresses</strong>
							<form action="admin.php?page=domain-check-profile&domain=<?php echo $domain_result['domain_url']; ?>" method="POST">
								<textarea name="watch_email_add" rows="10" cols="40"><?php
								if (isset($domain_result['domain_settings']['watch_emails']) && is_array($domain_result['domain_settings']['watch_emails']) && count($domain_result['domain_settings']['watch_emails'])) {
									echo implode("\n", $domain_result['domain_settings']['watch_emails']);
								}
									?></textarea>
							<br>
							<input type="submit" class="button" value="Update Emails">
						</form>
							</div>
							<div style="max-width: 450px; min-width: 350px; display: none; background: #ffffff; padding: 20px; float:left;">
							<strong>Notes</strong>
														<form action="admin.php?page=domain-check-profile&domain=<?php echo $domain_result['domain_url']; ?>" method="POST">
															<textarea name="notes_add" rows="10" cols="40"><?php
															if (isset($domain_result['domain_settings']['notes']) && is_array($domain_result['domain_settings']['notes']) && count($domain_result['domain_settings']['notes'])) {
																echo implode("\n", $domain_result['domain_settings']['notes']);
															}
																?></textarea>
														<br>
														<input type="submit" class="button" value="Update Notes">
													</form>
						</div>

<div style="clear: both;"></div>
					<h3>WHOIS Cache</h3>
								<strong>Last Updated:</strong> <?php echo date('m/d/Y', $domain_result['domain_last_check']); ?>
								<div style="float:right;">
									<a href="admin.php?page=domain-check-profile&domain=<?php echo $_GET['domain']; ?>&domain_check_search=<?php echo $_GET['domain']; ?>" class="button">
										<img src="<?php echo plugins_url('/images/icons/303-loop2.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-gray">
										Refresh
									</a>
								</div>
								<br>
								<div style="background-color: #FFFFFF;">
								<pre><?php
									if (is_array($domain_result['cache']['data'])) {
										echo implode('<br>', $domain_result['cache']['data']);
									} else {
										print_r($domain_result['cache']['data']);
									}
									?></pre>
								</div>
					<h3>SSL Cache</h3>
					<?php
					$ssl_sql = 'SELECT * FROM ' . DomainCheck::$db_prefix . '_ssl WHERE domain_url ="' . strtolower($domain_to_view) . '"';
					$ssl_result = $wpdb->get_results( $ssl_sql, 'ARRAY_A' );
					if ( count ( $ssl_result ) ) {
						$ssl_domain_result = array_pop($ssl_result);
						$ssl_domain_result['cache'] = ($ssl_domain_result['cache'] ? json_decode(gzuncompress($ssl_domain_result['cache']), true) : null);
						$ssl_domain_result['domain_settings'] = ($ssl_domain_result['domain_settings'] ? json_decode(gzuncompress($ssl_domain_result['domain_settings']), true) : null);
						?>

						<strong>Last Updated:</strong> <?php echo date('m/d/Y', $ssl_domain_result['domain_last_check']); ?>
						<div style="float:right;"> <a href="admin.php?page=domain-check-ssl-profile&domain=<?php echo $_GET['domain']; ?>&domain_check_ssl_search=<?php echo $_GET['domain']; ?>" class="button">
								<img src="<?php echo plugins_url('/images/icons/303-loop2.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-gray">
								<img src="<?php echo plugins_url('/images/icons/lock-locked.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-update-nag">
								Refresh SSL
							</a>
						</div>
						<br>
						<div style="background-color: #FFFFFF;">
						<pre><?php
							if (is_array($ssl_domain_result['cache'])) {
								echo print_r($ssl_domain_result['cache'], true);
								//echo implode('<br>', $ssl_domain_result['cache']);
							} else {

							}
							?></pre>
						</div>
						<?php
					} else {
						?>
						SSL not found in cache.<br><br>
						<a href="" class="button">
							<img src="<?php echo plugins_url('/images/icons/lock-locked.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-update-nag">
							Check SSL
						</a>
						<?php
					}

				} else {
					//domain not found... redirect to search...
					?>
					Domain not found in DB.
					<?php
				}
				?>
			</div>
			<?php
		}

		public function search() {
			global $wpdb;
			$this->admin_header();
			?>
			<div id="domain-check-wrapper" class="wrap">
				<h2>
					<img src="<?php echo plugins_url('/images/icons/magnifying-glass.svg', __FILE__); ?>" class="svg svg-icon-h1 svg-fill-gray">
					Domain Search
				</h2>
				<?php
				$this->search_box();
				?>
				<div id="domain-check-admin-notices"></div>
				<div id="poststuff">
					<div id="post-body" class="metabox-holder columns-2">
						<div id="post-body-content">
							<div class="meta-box-sortables ui-sortable">
								<form action="" method="post">
									<?php
									$this->domains_obj->prepare_items();
									$this->domains_obj->display();
									?>
								</form>
							</div>
						</div>
					</div>
					<br class="clear">
				</div>
			</div>
		<?php
		}

		public function help() {

			$test_domains = array(
				'domaincheck' . time(),
				'radio',
				'shopping',
			);

			$extensions = DomainCheckWhois::getextensions();

			$this->admin_header();
			?>
			<h2>
				<img src="<?php echo plugins_url('/images/icons/266-question.svg', __FILE__); ?>" class="svg svg-icon-h1 svg-fill-gray">
				Help
			</h2>
			<div class="setting-div">
				<h3>
					<a href="admin.php?page=domain-check">
						<img src="<?php echo plugins_url('/images/icons/circle-www2.svg', __FILE__); ?>" class="svg svg-icon-h2 svg-fill-gray">
						Domains / Dashboard
					</a>
				</h3>
				<p class="p">
					<?php echo DomainCheckHelp::get_help('page_dashboard'); ?>
				</p>
				<h3>
					<a href="admin.php?page=domain-check-your-domains">
						<img src="<?php echo plugins_url('/images/icons/flag.svg', __FILE__); ?>" class="svg svg-icon-h2 svg-fill-blue">
						Your Domains
					</a>
				</h3>
				<p class="p">
					<?php echo DomainCheckHelp::get_help('page_your_domains'); ?>
				</p>
				<h3>
					<a href="admin.php?page=domain-check-search">
						<img src="<?php echo plugins_url('/images/icons/magnifying-glass.svg', __FILE__); ?>" class="svg svg-icon-h2 svg-fill-gray">
						Domain Search
					</a>
				</h3>
				<p class="p">
					<?php echo DomainCheckHelp::get_help('page_domain_search'); ?>
				</p>
				<h3>
					<a href="admin.php?page=domain-check-watch">
						<img src="<?php echo plugins_url('/images/icons/207-eye.svg', __FILE__); ?>" class="svg svg-icon-h2 svg-fill-gray">
						Domain Watch
					</a>
				</h3>
				<p class="p">
					<?php echo DomainCheckHelp::get_help('page_domain_watch'); ?>
				</p>
			</div>
			<div class="setting-div">
				<h3>
					<a href="admin.php?page=domain-check-ssl-check">
						<img src="<?php echo plugins_url('/images/icons/lock-locked.svg', __FILE__); ?>" class="svg svg-icon-h2 svg-fill-update-nag">
						SSL Check
					</a>
				</h3>
				<p class="p">
					<?php echo DomainCheckHelp::get_help('page_ssl_check'); ?>
				</p>
				<h3>
					<a href="admin.php?page=domain-check-ssl-watch">
						<img src="<?php echo plugins_url('/images/icons/bell.svg', __FILE__); ?>" class="svg svg-icon-h2 svg-fill-gray">
						SSL Expiration Alerts
					</a>
				</h3>
				<p class="p">
					<?php echo DomainCheckHelp::get_help('page_ssl_watch'); ?>
				</p>
				<h3>
					<a href="admin.php?page=domain-check-import-export">
						<img src="<?php echo plugins_url('/images/icons/data-transfer-upload.svg', __FILE__); ?>" class="svg svg-icon-h2 svg-fill-gray">
						<img src="<?php echo plugins_url('/images/icons/data-transfer-download.svg', __FILE__); ?>" class="svg svg-icon-h2 svg-fill-gray">
						Import / Export
					</a>
				</h3>
				<p class="p">
					<?php echo DomainCheckHelp::get_help('page_import_export'); ?>
				</p>
				<h3>
					<a href="admin.php?page=domain-check-settings">
						<img src="<?php echo plugins_url('/images/icons/cog.svg', __FILE__); ?>" class="svg svg-icon-h2 svg-fill-gray">
						Settings
					</a>
				</h3>
				<p class="p">
					<?php echo DomainCheckHelp::get_help('page_settings'); ?>
				</p>
				<h3>
					<a href="admin.php?page=domain-check-coupons">
						<img src="<?php echo plugins_url('/images/icons/055-price-tags.svg', __FILE__); ?>" class="svg svg-icon-h2 svg-fill-updated">
						Coupons &amp; Deals
					</a>
				</h3>
				<p class="p">
					<?php echo DomainCheckHelp::get_help('page_coupons'); ?>
				</p>
			</div>
			<div class="setting-div">
				<h3>
					<img src="<?php echo plugins_url('/images/icons/266-question.svg', __FILE__); ?>" class="svg svg-icon-h2 svg-fill-gray">
					FAQ
				</h3>
				<p class="p">
					<?php echo DomainCheckHelp::get_help('page_faq'); ?>
				</p>
			</div>

			<div style="width: 90%; background-color: #ffffff; clear: both; padding: 10px; margin: 10px;">
			<h3>Domain Extension List</h3>
			<p class="p">
				Here is the full list of domain extensions recognized by this plugin. Not all of these extensions are working this is simply the list of current know extensions! Remember that domain extensions are also know as top level domains, or TLDs. This is a list of all the top level domains (TLDs) that are currently recognized and that have some known registrar and WHOIS service.
			</p>
			<strong><?php echo count($extensions); ?> Total Extensions</strong>
			<div style="clear: both;"></div>
			<br>
			<?php

			$whois_data = DomainCheckWhoisData::get_data();

			foreach ($extensions as $extension) {
				$extension = $extension->__toString();
				?>
				<div style="display: inline-block; width: 20%;">

					<?php
					//if (isset($_GET['show_support'])) {
					$available_color = '#CCCCCC';
					$expires_color = '#CCCCCC';
					if (isset($whois_data[$extension]) && isset($whois_data[$extension]['available']) && $whois_data[$extension]['available']) {
						//$available_color = '#ffba00';
						$available_color = '#00AA00';
					}
					if (isset($whois_data[$extension]) && isset($whois_data[$extension]['expires']) && $whois_data[$extension]['expires']) {
						//$expires_color = '#ffba00';
						$expires_color = '#00AA00';
					}
					if ($available_color != '#CCCCCC' && $expires_color != '#CCCCCC') {
						$available_color = '#00AA00';
						$expires_color = '#00AA00';
					}
					?>
					<div style="display: inline-block; margin-right: 5px; width: 14px; height: 14px; background-color: <?php echo $available_color; ?>;" alt="Availability Checks Supported" title="Availability Checks Supported"></div>
					<div style="display: inline-block; margin-right: 5px; width: 14px; height: 14px; background-color: <?php echo $expires_color; ?>;" alt="Expiration Dates Supported" title="Expirations Dates Supported"></div>
						<?php
			//}
			?>
					<strong>
						.<?php echo $extension; ?>
					</strong>
				</div>
				<?php
			}
			?>
			</div>
				<?php
		}

		public function search_box() {
			?>
			<form action="" method="GET">
				<input type="text" name="domain_check_search" id="domain_check_search">
				<input type="hidden" name="page" value="domain-check-search">
				<input type="submit" class="button" value="Search Domain"/>
			</form>
			<?php
		}

		public function search_init() {

		}

		/**
		 * Screen options
		 */
		public function search_screen_option() {
			$option = 'per_page';
			$args   = array(
				'label'   => 'Domain Search',
				'default' => 100,
				'option'  => 'domains_per_page'
			);

			add_screen_option( $option, $args );

			$this->domains_obj = new DomainCheck_Search_List();
		}

		/**
		 * Screen options
		 */
		public function screen_option() {
			$option = 'per_page';
			$args   = array(
				'label'   => 'Domains',
				'default' => 100,
				'option'  => 'domains_per_page'
			);

			add_screen_option( $option, $args );

			$this->domains_obj = new DomainCheck_Domains_List();
		}

		public static function set_screen( $status, $option, $value ) {
			return $value;
		}

		public function settings() {
			$this->admin_header();

			if (function_exists('get_option')) {
				$admin_email = get_option('admin_email');
				if (get_option(DomainCheckConfig::OPTIONS_PREFIX . 'email_primary_email')) {
					$admin_email = get_option(DomainCheckConfig::OPTIONS_PREFIX . 'email_primary_email');
				}
				if ($admin_email) {
					$emails[strtolower($admin_email)] = array(
						'owned' => array(),
						'taken' => array(),
						'ssl'	=> array()
					);
				}
				$blog_name = get_option('blogname');
				$site_url = get_option('site_url');
			}
			$domain_autosuggest_enabled = true;

			$email_additional_emails = get_option(DomainCheckConfig::OPTIONS_PREFIX . 'email_additional_emails');
			if (is_array($email_additional_emails) && count($email_additional_emails)) {
				$email_additional_emails = implode("\n", $email_additional_emails);
			} else {
				$email_additional_emails = '';
			}

			$domain_extension_favorites = get_option(DomainCheckConfig::OPTIONS_PREFIX . 'domain_extension_favorites');
			if (is_array($domain_extension_favorites) && count($domain_extension_favorites)) {
				foreach ($domain_extension_favorites as $fav_idx => $fav) {
					$domain_extension_favorites[$fav_idx] = '.' . $fav;
				}
				$domain_extension_favorites = implode("\n", $domain_extension_favorites);

			} else {
				$domain_extension_favorites = DomainCheckConfig::$options[DomainCheckConfig::OPTIONS_PREFIX . 'settings']['domain_extension_favorites'];
				foreach ($domain_extension_favorites as $fav_idx => $fav) {
					$domain_extension_favorites[$fav_idx] = '.' . $fav;
				}
				$domain_extension_favorites = implode("\n", $domain_extension_favorites);
				$domain_extension_favorites = '';
			}

			$coupons_primary_site = get_option(DomainCheckConfig::OPTIONS_PREFIX . 'coupons_primary_site');
			if (!$coupons_primary_site) {
				$coupons_primary_site = 'GoDaddy';
			}

			?>
			<script type="text/javascript">
				function update_setting(setting_id) {
					var data_obj = {};
					switch (setting_id) {
						case 'domain_extension_favorites':
							data_obj = {
								action:"settings",
								method:"domain_extension_favorites",
								domain_extension_favorites: document.getElementById('domain-extension-favorites').value
							};
							break;
						case 'email_additional_emails':
							data_obj = {
								action:"settings",
								method:"email_additional_emails",
								email_additional_emails: document.getElementById('email-additional-emails').value
							};
							break;
						case 'email_primary_email':
							data_obj = {
								action:"settings",
								method:"email_primary_email",
								email_primary_email: document.getElementById('email-primary-email').value
							};
							break;
						case 'coupons_primary_site':
							data_obj = {
								action:"settings",
								method:"coupons_primary_site",
								coupons_primary_site: jQuery("#coupons-primary-site" ).val()
							};
							break;
					}
					domain_check_ajax_call(
						data_obj,
						update_setting_callback
					);
				}

				function update_setting_callback(data) {}
			</script>
			<h2>
				<img src="<?php echo plugins_url('/images/icons/cog.svg', __FILE__); ?>" class="svg svg-icon-h1 svg-fill-gray">
				Settings
			</h2>
			<div class="setting-wrapper">
				<div class="setting-div">
					<h3>Additional Email Addresses</h3>
					<p class="p">
						These email addresses will automatically be added to any alerts for domains, SSL certificates, or hosting. These email addresses will receive alert emails and can also be individually removed for alerts. Use this to add multiple people, email inboxes, or ticket support systems to alerts. One email address per line.
					</p>
					<div class="setting-input">
						<textarea id="email-additional-emails" style="width: 100%; height: 200px;"><?php echo $email_additional_emails; ?></textarea>
					</div>
					<br>
					<a class="button" onclick="update_setting('email_additional_emails');">
						Update Additional Emails
					</a>
				</div>
				<div class="setting-div">
					<h3>Primary Email Address</h3>
					<p class="p">
						This is the default email used for all alerts. If you have a separate Technical Contact you may want to set them to this email address.
					</p>
					<input type="text" id="email-primary-email" value="<?php echo $admin_email; ?>">
					<br><br>
					<a class="button" onclick="update_setting('email_primary_email');">
						Update Primary Email Address
					</a>
				</div>
				<div class="setting-div">
					<div class="setting-label">
						<h3>Favorite Domain Extensions:</h3>
						<p class="p">
							These extensions will automatically be searched or listed with your domain search results. The default list contains some of the current most popular domain extensions. One extension per line.
						</p>
					</div>
					<div class="setting-input">
						<textarea id="domain-extension-favorites" style="width: 100%; height: 150px;"><?php echo $domain_extension_favorites; ?></textarea>
						<br><br>
						<a class="button" onclick="update_setting('domain_extension_favorites');">
							Update Favorite Extensions
						</a>
					</div>
				</div>
				<div class="setting-div" style="display: none;">
					<div class="setting-label">
						<h3>Domain Autosuggest:</h3>
						<p class="p">
							Autosuggest is when you search a domain using the search box and your favorite domain extensions are automatically searched on your domain search results page. If you turn Domain Autosuggest off you will still be able to easily click to search your domain name with your favorite domain extensions.
						</p>
					</div>
					<div class="setting-input">
						<input type="checkbox" id="domain-autosuggest-enabled"<?php echo ($domain_autosuggest_enabled) ? ' checked' : ''; ?>>&nbsp; Domain Autosuggest: <?php echo ($domain_autosuggest_enabled) ? 'Enabled' : 'Disabled'; ?>
					</div>
					<a class="button">
						Update Autosuggest
					</a>
				</div>
				<div class="setting-div">
					<div class="setting-input">
						<h4>Primary Coupon Site</h4>
						<p class="p">
							This should be set to the registrar you regularly use or the site you most. Your coupon site controls links in domain searches, domain expiration alerts, email links, and dashboard links for easy clicking.
						</p>
						<select id="coupons-primary-site">
						<?php
						$coupon_sites = array_keys(DomainCheckCouponData::get_data());
						foreach ($coupon_sites as $coupon_site) {
							$selected = '';
							$tmp_coupon_site = strtolower(trim($coupon_site));
							$tmp_coupons_primary_site = strtolower(trim($coupons_primary_site));
							if ($tmp_coupon_site == $tmp_coupons_primary_site) {
								$selected = ' selected';
							}
							?>
						<option value="<?php echo $coupon_site; ?>"<?php echo $selected; ?>><?php echo $coupon_site; ?></option>
						<?php } ?>
						</select>
						<a class="button"  onclick="update_setting('coupons_primary_site');">
							Update Primary Coupon Site
						</a>
					</div>
				</div>

				<div class="setting-div" style="display: none;">
					<div class="setting-label">
						<h3>Favorite Domain Registrars:</h3>
						<p class="p">
							Choose which domain registrar is your primary registrar and list your favorite registrars to get coupons for the sites you use. Your primary domain registrar controls links in domain searches, domain expiration alerts, email links, and dashboard links. The primary domain registrar is set to GoDaddy by default.
						</p>
					</div>
						<div class="setting-input">
						<h4>All Favorite Registrars</h4>
						<p class="p">
							Most people and organizations have domains at multiple registrars and use many different domain registration sites. Check all the domain registrars listed below that you use to see more coupons, deals, and links for your favorite domain registrars.
						</p>
						<textarea id="default-domain-extensions" cols="25" rows="15"><?php echo $domain_extension_favorites; ?></textarea>
						<a class="button">
							Update Favorite Registrars
						</a>
					</div>
				</div>
				<div class="setting-div" style="display: none;">
					<div class="setting-label">
						<h3>Coupon Updates:</h3>
						<p class="p"></p>
					</div>
					<div class="setting-input">
						<input type="checkbox" id="domain-autosuggest-enabled"<?php echo ($domain_autosuggest_enabled) ? ' checked' : ''; ?>>&nbsp; Domain Autosuggest: <?php echo ($domain_autosuggest_enabled) ? 'Enabled' : 'Disabled'; ?>
					</div>
					<a class="button">
						Update Autosuggest
					</a>
				</div>
			</div>
			<?php
		}

		public function ssl_check_init() {
			global $wpdb;

			return DomainCheckSearch::ssl_search($_GET['domain_check_ssl_search']);


			$exists_in_db = false;
			$use_cache = false;
			$force_return = false;
			$domain_result = array();
			$search = parse_url(strtolower($_GET['domain_check_ssl_search']));
			$search = $search['path'];
			$search = preg_replace("/[^a-z0-9.-]+/i", '', $search);

			$valarr = array(
				'ssl_domain_id' => null,
				'domain_id' => null,
				'domain_url' => $_GET['domain_check_ssl_search'],
				'user_id' => 0,
				'status' => 0,
				'date_added' => time(),
				'search_date' => time(),
				'domain_last_check' => time(),
				'domain_next_check' => 0,
				'domain_expires' => 0,
				'domain_settings' => null,
				'cache' => null,
			);

			if (strpos($_GET['domain_check_ssl_search'], 'http') === false) {
				$_GET['domain_check_ssl_search'] = 'http://' . $_GET['domain_check_ssl_search'];
			}



			$sql = 'SELECT * FROM ' . DomainCheck::$db_prefix . '_ssl WHERE domain_url ="' . strtolower($search) . '"';
			$result = $wpdb->get_results( $sql, 'ARRAY_A' );

			if ( count ( $result ) ) {
				$domain_result = array_pop($result);
				$exists_in_db = $domain_result['ssl_domain_id'];
				$valarr = array(
					'date_added' => $domain_result['date_added'],
					'search_date' => time(),
					'domain_last_check' => time(),
				);
			}

			$orignal_parse = parse_url($_GET['domain_check_ssl_search'], PHP_URL_HOST);
			$search = $orignal_parse;
			$get = stream_context_create(array("ssl" => array("capture_peer_cert" => TRUE)));
			try {
				//fear leads to anger, anger leads to hate, hate leads to suppression, supression leads to the Dark Side - Yoda
				$read = @stream_socket_client("ssl://".$orignal_parse.":443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $get);
			} catch (Exception $e) {
				DomainCheckAdmin::admin_notices_add(
					'No SSL certificate found for <strong>' . $search . '</strong>',
					'error',
					null,
					'145-unlocked'
				);
				$force_return = true;
				//return;
			}

			if (!$read && !$force_return) {
				DomainCheckAdmin::admin_notices_add(
					'Unable to read SSL certificate for <strong>' . $search . '</strong>',
					'error',
					null,
					'145-unlocked'
				);
				$force_return = true;
				//return;
			}

			if (!$force_return) {
				$cert = stream_context_get_params($read);
				$certinfo = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
			}

			if (!isset($certinfo['validFrom_time_t']) && !$force_return) {
				DomainCheckAdmin::admin_notices_add(
					'Certificate found but no expiration date given for <strong>' . $search . '</strong>',
					'error',
					null,
					'145-unlocked'
				);
				$force_return = true;
				//return;
			}

			if (!$force_return) {
				$valarr['cache'] = gzcompress(json_encode($certinfo));
				if ($certinfo['validFrom_time_t'] > time() || $certinfo['validTo_time_t'] < time() ) {
					$valarr['domain_expires'] = $certinfo['validTo_time_t'];
					$valarr['status'] = 1;
					DomainCheckAdmin::admin_notices_add(
						'SSL certificate for <strong>' . $search . '</strong> is not valid. Expires ' . date('m/d/Y H:i:s', $certinfo['validTo_time_t']),
						'error',
						null,
						'145-unlocked'
					);
				} else {
					$valarr['domain_expires'] = $certinfo['validTo_time_t'];
					$valarr['status'] = 1;
					DomainCheckAdmin::admin_notices_add(
						'Yes! SSL Certificate for <strong>' . $search . '</strong> is valid!',
						'updated',
						null,
						'144-lock'
					);
				}
			}

			if ($exists_in_db) {
				$wpdb->update(
					DomainCheck::$db_prefix . '_ssl',
					$valarr,
					array (
						'domain_url' => strtolower($search)
					)

				);
			} else {
				$wpdb->insert(
					'wp_domain_check_ssl',
					$valarr
				);
			}
		}

		public function ssl_check() {
			$this->admin_header();
			?>
			<div class="wrap">
				<h2>
					<img src="<?php echo plugins_url('/images/icons/lock-locked.svg', __FILE__); ?>" class="svg svg-icon-h1 svg-fill-update-nag">
					SSL Check
				</h2>
				<?php
				$this->ssl_search_box();
				?>
				<div id="domain-check-admin-notices"></div>
				<div id="poststuff">
					<div id="post-body" class="metabox-holder columns-2">
						<div id="post-body-content">
							<div class="meta-box-sortables ui-sortable">
								<form method="post">
									<?php
									$this->domains_obj->prepare_items();
									$this->domains_obj->display();
									?>
								</form>
							</div>
						</div>
					</div>
					<br class="clear">
				</div>
			</div>
			<?php
		}

		/**
		 * Screen options
		 */
		public function ssl_check_screen_option() {
			$option = 'per_page';
			$args   = array(
				'label'   => 'SSL Check',
				'default' => 100,
				'option'  => 'domains_per_page'
			);

			add_screen_option( $option, $args );

			$this->domains_obj = new DomainCheck_SSL_List();
		}

		public function ssl_delete_init() {
			global $wpdb;

			$domain = strtolower($_GET['domain_check_ssl_delete']);

			if (!isset($_GET['domain_check_ssl_delete_confirm'])) {
				$message = 'Are you sure you want to delete <strong> ' . $domain . ' </strong>? It will no longer be watched and may expire! This cannot be undone.';
				$message_options = array(
				'Delete' => '?page=domain-check-ssl-check&domain_check_ssl_delete=' . $domain . '&domain_check_ssl_delete_confirm=1',
				'Cancel' => '?page=domain-check-ssl-check'
			);
				DomainCheckAdmin::admin_notices_add($message, 'error', $message_options, '174-bin2');
			} else {
				$wpdb->delete(
					DomainCheck::$db_prefix . '_ssl',
					array(
						'domain_url' => $domain
					)
				);
				$message = 'Success! You deleted <strong>' . $domain . '</strong>!';
				DomainCheckAdmin::admin_notices_add($message, 'updated', null, '174-bin2');
			}
		}

		public function ssl_search_box() {
			?>
			<form action="" method="GET">
				<input type="text" name="domain_check_ssl_search" id="domain_check_ssl_search">
				<input type="hidden" name="page" value="domain-check-ssl-check">
				<input type="submit" class="button" value="Check SSL"/>
			</form>
			<?php
		}

		public function ssl_profile() {
			if (!isset($_GET['domain']) || !$_GET['domain']) {
				wp_redirect( admin_url( 'admin.php?page=domain-check-ssl-check' ) );
			}
			global $wpdb;
			$this->admin_header();
			$domain_to_view = strtolower($_GET['domain']);
			$sql = 'SELECT * FROM ' . DomainCheck::$db_prefix . '_ssl WHERE domain_url ="' . strtolower($domain_to_view) . '"';
			$result = $wpdb->get_results( $sql, 'ARRAY_A' );
			$use_cache = false;
			$domain_result = null;

			if ( count ( $result ) ) {
				$domain_result = array_pop($result);
			}
			if ($domain_result['status']) {
				$icon_fill = 'updated';
				$icon_url = plugins_url('/images/icons/lock-locked.svg', __FILE__);

			} else {
				$icon_fill = 'error';
				$icon_url = plugins_url('/images/icons/lock-unlocked.svg', __FILE__);
			}
			?>
			<div class="wrap">
				<h2>
					<img src="<?php echo $icon_url; ?>" class="svg svg-icon-h1 svg-fill-<?php echo $icon_fill; ?>">
					<?php echo $_GET['domain']; ?>
				</h2>

				<br>
				<?php
				if ( $domain_result ) {
					?>
					<?php
					$domain_result['cache'] = ($domain_result['cache'] ? json_decode(gzuncompress($domain_result['cache']), true) : null);
					$domain_result['domain_settings'] = ($domain_result['domain_settings'] ? json_decode(gzuncompress($domain_result['domain_settings']), true) : null);
					?>
					<div style="width: 100%; display: inline-block; background: #ffffff; padding: 20px;">
						<div style="display: inline-block; float:left;">
							<a href="?page=domain-check-ssl-profile&domain=<?php echo $_GET['domain']; ?>&domain_check_ssl_search=<?php echo $_GET['domain']; ?>" class="button">
								<img src="<?php echo plugins_url('/images/icons/303-loop2.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-gray">
								Refresh
							</a>
							<a href="?page=domain-check-profile&domain=<?php echo $_GET['domain']; ?>&domain_check_search=<?php echo $_GET['domain']; ?>" class="button">
								<img src="<?php echo plugins_url('/images/icons/circle-www2.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-gray">
								Domain
							</a>
							<a href="?page=domain-check-ssl-profile&domain=<?php echo $_GET['domain']; ?>&domain_check_ssl_delete=<?php echo $_GET['domain']; ?>" class="button">
								<img src="<?php echo plugins_url('/images/icons/174-bin2.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-gray">
								Delete
							</a>
							<br>
							<style type="text/css">
								.domain-check-profile-li {
									width: 100%;
								}
								.domain-check-profile-li:hover{
									background-color: #F4F4F4;
								}
								.domain-check-profile-li-div-left {
									width: 48%;
									text-align: left;
									display: inline-block;
									height: 100%;
								}
								.domain-check-profile-li-div-right {
									width: 45%;
									display: inline-block;
									padding-right: 10px;
									height: 100%;
								}
							</style>
							<ul style="display: inline-block; width: 100%; padding-right: 10px;">
								<!--li><strong>User ID:</strong> <?php echo $domain_result['user_id']; ?></li-->
								<li class="domain-check-profile-li">
									<div class="domain-check-profile-li-div-left">
										<strong>Status:</strong>
										<?php
									switch ($domain_result['status']) {
										case 0:
											?>
											Not Secure
											<?php
											break;
										case 1:
											?>
											Secure
											<?php
											break;
										default:
											?>
											Unknown (<?php echo $domain_result['status']; ?>)
											<?php
											break;
									}
									?></div>
									<div class="domain-check-profile-li-div-right">
									<?php
									switch ($domain_result['status']) {
										case 0:
											?>
											<a href="#">
												<img src="<?php echo plugins_url('/images/icons/lock-unlocked.svg', __FILE__); ?>" class="svg svg-icon-table svg-fill-error">
												Not Secure [&raquo;]
											</a>
											<?php
											break;
										case 1:
											?>
											<a href="#">
												<img src="<?php echo plugins_url('/images/icons/lock-locked.svg', __FILE__); ?>" class="svg svg-icon-table svg-fill-success">
												Secure
											</a>
											<?php
											break;
										default:
											?>
											Unknown (<?php echo $domain_result['status']; ?>)
											<?php
											break;
									}
									?>
									</div>
								</li>
								<li class="domain-check-profile-li">
									<div class="domain-check-profile-li-div-left">
									<strong>Watch:</strong>
									</div>
									<div class="domain-check-profile-li-div-right">
										<?php
										if (!$domain_result['domain_watch']) {
											?>
											<a href="?page=domain-check-ssl-profile&domain=<?php echo $_GET['domain']; ?>&domain_check_ssl_watch_start=<?php echo $_GET['domain']; ?>">
												<img src="<?php echo plugins_url('/images/icons/207-eye.svg', __FILE__); ?>" class="svg svg-icon-table svg-fill-disabled">
												Not Watching
											</a>
											<?php
										} else {
											?>
											<a href="?page=domain-check-ssl-profile&domain=<?php echo $_GET['domain']; ?>&domain_check_ssl_watch_stop=<?php echo $_GET['domain']; ?>">
												<img src="<?php echo plugins_url('/images/icons/207-eye.svg', __FILE__); ?>" class="svg svg-icon-table svg-fill-gray">
												Watching
											</a>
											<?php
										}
										?>
									</div>
								</li>
								<li class="domain-check-profile-li">
									<div class="domain-check-profile-li-div-left">
									<strong>Expires:</strong>
										<?php
										if ($domain_result['domain_expires']) {
											echo date('M-d-Y', $domain_result['domain_expires']);
										} else {
											echo 'n/a';
										}
										?>
									</div>
									<div class="domain-check-profile-li-div-right">
										<?php
										if ($domain_result['domain_expires']) {
											if ($domain_result['domain_expires'] < time()) {
												$out = 'Expired';
											} else {
												$days = number_format(($domain_result['domain_expires'] - time())/60/60/24, 0);
												$days_flat = (int)floor(($domain_result['domain_expires'] - time())/60/60/24);
												$out = '';
												if ($days_flat < 60) {
													$fill = 'gray';
													if ($days_flat < 30) {
														$fill = 'update-nag';
													}
													if ($days_flat < 10) {
														$fill = 'error';
													}
													if ($days_flat < 3) {
														$fill = 'red';
													}
													$out .= '<img src="' . plugins_url('/images/icons/clock.svg', __FILE__) . '" class="svg svg-icon-table svg-fill-' . $fill . '">';
												}
												$out .= ' ' . number_format(($domain_result['domain_expires'] - time())/60/60/24, 0) . ' Days';
											}
											echo $out;
										}
										?>
									</div>
								</li>
								<!--li class="domain-check-profile-li">
									<strong>Created:</strong>
									<?php echo date('M-d-Y', $domain_result['domain_created']); ?>
								</li-->
								<li class="domain-check-profile-li"><strong>Last Check:</strong> <?php echo date('M-d-Y', $domain_result['domain_last_check']); ?></li>
								<!--li class="domain-check-profile-li"><strong>Next Check:</strong> <?php echo date('M-d-Y', $domain_result['domain_next_check']); ?></li-->
								<!--li class="domain-check-profile-li"><strong>Search Date:</strong> <?php echo date('M-d-Y', $domain_result['search_date']); ?></li-->
								<li class="domain-check-profile-li"><strong>Date Added:</strong> <?php echo date('M-d-Y', $domain_result['date_added']); ?></li>
								<!--li><strong>Settings:</strong><?php
									echo str_replace("\n", '<br>' . "\n", print_r($domain_result['domain_settings'], true));
									?></li-->

							</ul>
						</div>

						<div style="max-width: 450px; min-width: 350px; display: inline-block; background: #ffffff; padding: 20px; float:left;">
							SSL Expiration Alert Email Addresses
							<form action="admin.php?page=domain-check-ssl-profile&domain=<?php echo $domain_result['domain_url']; ?>" method="POST">
								<textarea name="ssl_watch_email_add" rows="10" cols="40"><?php
								if (isset($domain_result['domain_settings']['watch_emails']) && is_array($domain_result['domain_settings']['watch_emails']) && count($domain_result['domain_settings']['watch_emails'])) {
									echo implode("\n", $domain_result['domain_settings']['watch_emails']);
								}
									?></textarea>
							<br>
							<input type="submit" class="button" value="Update Emails">
						</form>
						</div>
						<div style="max-width: 450px; min-width: 350px; display: none; background: #ffffff; padding: 20px; float:left;">
							<strong>Notes</strong>
														<form action="admin.php?page=domain-check-profile&domain=<?php echo $domain_result['domain_url']; ?>" method="POST">
															<textarea name="notes_add" rows="10" cols="40"><?php
															if (isset($domain_result['domain_settings']['notes']) && is_array($domain_result['domain_settings']['notes']) && count($domain_result['domain_settings']['notes'])) {
																echo implode("\n", $domain_result['domain_settings']['notes']);
															}
																?></textarea>
														<br>
														<input type="submit" class="button" value="Update Notes">
													</form>
						</div>
					</div>
					<h3>SSL Cache</h3>
					<strong>Last Updated:</strong> <?php echo date('m/d/Y', $domain_result['domain_last_check']); ?>
					<div style="float:right;">
						<a href="admin.php?page=domain-check-ssl-profile&domain=<?php echo $_GET['domain']; ?>&domain_check_ssl_search=<?php echo $_GET['domain']; ?>" class="button">
							<img src="<?php echo plugins_url('/images/icons/303-loop2.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-gray">
						<img src="<?php echo plugins_url('/images/icons/lock-locked.svg', __FILE__); ?>" class="svg svg-icon-table svg-icon-table-links svg-fill-update-nag">
						Refresh SSL
						</a>
					</div>
					<br>
					<div style="background-color: #FFFFFF;">
					<pre><?php
						if (is_array($domain_result['cache'])) {
							echo print_r($domain_result['cache'], true);
							//echo implode('<br>', $ssl_domain_result['cache']);
						} else {

						}
						?></pre>
					</div>
					<?php

				} else {
					//domain not found... redirect to search...
					?>
					SSL not found in cache.<br><br><a href="">Check SSL [&raquo;]</a>
					<?php
				}
				?>
			</div>
			<?php
		}

		function ssl_watch() {
			global $wpdb;
			$this->admin_header();
			?>
			<div class="wrap">
				<h2>
					<img src="<?php echo plugins_url('/images/icons/bell.svg', __FILE__); ?>" class="svg svg-icon-h1 svg-fill-update-gray">
					SSL Expiration Alerts
				</h2>
				<?php
				$this->ssl_search_box();
				?>
				<div id="poststuff">
					<div id="post-body" class="metabox-holder columns-2">
						<div id="post-body-content">
							<div class="meta-box-sortables ui-sortable">
								<form action="" method="post">
									<?php
									$this->domains_obj->prepare_items();
									$this->domains_obj->display();
									?>
								</form>
							</div>
						</div>
					</div>
					<br class="clear">
				</div>
			</div>
			<?php
		}

		public function ssl_watch_email_add($domain, $watch_email_content) {
			global $wpdb;

			$new_watch_email_content = $watch_email_content;
			$new_watch_email_content = strtolower($new_watch_email_content);
			$new_watch_email_content = str_replace(',', ' ', $new_watch_email_content);
			$new_watch_email_content = str_replace(' ', "\n", $new_watch_email_content);
			$new_watch_email_content = explode("\n", $new_watch_email_content);
			$new_watch_emails = array();
			foreach ($new_watch_email_content as $new_watch_email) {
				if ($new_watch_email != '' && $new_watch_email) {
					$new_watch_emails[] = $new_watch_email;
				}
			}
			sort($new_watch_emails);

			$sql = 'SELECT * FROM ' . DomainCheck::$db_prefix . '_ssl WHERE domain_url ="' . strtolower($domain) . '"';
			$result = $wpdb->get_results( $sql, 'ARRAY_A' );
			$use_cache = false;
			if ( count ( $result ) ) {
				$domain_result = array_pop($result);
				$domain_result['cache'] = ($domain_result['cache'] ? json_decode(gzuncompress($domain_result['cache']), true) : null);
				$domain_result['domain_settings'] = ($domain_result['domain_settings'] ? json_decode(gzuncompress($domain_result['domain_settings']), true) : null);
				if (!is_array($domain_result['domain_settings'])) {
					$domain_result['domain_settings'] = array();
				}
				$domain_result['domain_settings']['watch_emails'] = $new_watch_emails;
				$new_settings = array('domain_settings' => gzcompress(json_encode($domain_result['domain_settings'])));
				$wpdb->update(
					DomainCheck::$db_prefix . '_ssl',
					$new_settings,
					array (
						'domain_url' => strtolower($domain)
					)

				);
			}
		}

		/**
		 * Screen options
		 */
		public function ssl_watch_screen_option() {
			$option = 'per_page';
			$args   = array(
				'label'   => 'SSL Expiration Alerts',
				'default' => 100,
				'option'  => 'domains_per_page'
			);

			add_screen_option( $option, $args );

			$this->domains_obj = new DomainCheck_SSL_Watch_List();
		}

		public function ssl_watch_start($domain) {
			global $wpdb;

			$domain = strtolower($domain);

			$wpdb->update(
				DomainCheck::$db_prefix . '_ssl',
				array(
					'domain_watch' => 1
				),
				array (
					'domain_url' => $domain
				)
			);

			DomainCheckAdmin::admin_notices_add('Started watching SSL expiration for <strong>' . $domain . '</strong>!', 'updated', null, '208-eye-plus');
		}

		public function ssl_watch_stop($domain) {
			global $wpdb;

			$domain = strtolower($domain);

			$wpdb->update(
				DomainCheck::$db_prefix . '_ssl',
				array(
					'domain_watch' => 0
				),
				array (
					'domain_url' => $domain
				)
			);

			DomainCheckAdmin::admin_notices_add('Stopped watching SSL expiration for <strong>' . $domain . '</strong>!', 'error', null, '209-eye-minus');
		}

		public function ssl_watch_trigger($domain, $ajax = 0) {
			//this function sucks because it has to do a select first
			global $wpdb;

			if (isset($_POST['domain'])) {
				$ajax = 1;
				$domain = strtolower($_POST['domain']);
			}

			$domain = strtolower($domain);

			$sql = 'SELECT * FROM ' . DomainCheck::$db_prefix . '_ssl WHERE domain_url ="' . strtolower($domain) . '"';
			$result = $wpdb->get_results( $sql, 'ARRAY_A' );
			if ( count ( $result ) ) {
				$result = array_pop($result);
				$new_status = $result['domain_watch'] ? 0 : 1;
				$wpdb->update(
					DomainCheck::$db_prefix . '_ssl',
					array(
						'domain_watch' => $new_status
					),
					array(
						'domain_url' => $domain
					)
				);

				$message_start = 'Started watching <strong>' . $domain . '</strong>!';
				$message_stop = 'Stopped watching <strong>' . $domain . '</strong>!';

				if (!$ajax) {
					if ($new_status) {
						DomainCheckAdmin::admin_notices_add($message_start, 'updated', null, '208-eye-plus');
					} else {
						DomainCheckAdmin::admin_notices_add($message_stop, 'error', null, '209-eye-minus');
					}
				} else {
					DomainCheckAdmin::ajax_success(
						$data = array(
							'watch' => $new_status,
							'message' => ($new_status ? $message_start : $message_stop),
							'domain' => $domain
						)
					);
				}
			}
		}

		public function watch() {
			global $wpdb;
			$this->admin_header();
			?>
			<div class="wrap">
				<h2>
					<img src="<?php echo plugins_url('/images/icons/207-eye.svg', __FILE__); ?>" class="svg svg-icon-h1 svg-fill-gray">
					Domain Watch
				</h2>
				<?php
				$this->search_box();
				?>
				<div id="domain-check-admin-notices"></div>
				<div id="poststuff">
					<div id="post-body" class="metabox-holder columns-2">
						<div id="post-body-content">
							<div class="meta-box-sortables ui-sortable">
								<form action="" method="post">
									<?php
									$this->domains_obj->prepare_items();
									$this->domains_obj->display();
									?>
								</form>
							</div>
						</div>
					</div>
					<br class="clear">
				</div>
			</div>
		<?php
		}

		public function watch_email_add($domain, $watch_email_content) {
			global $wpdb;

			$new_watch_email_content = $watch_email_content;
			$new_watch_email_content = strtolower($new_watch_email_content);
			$new_watch_email_content = str_replace(',', ' ', $new_watch_email_content);
			$new_watch_email_content = str_replace(' ', "\n", $new_watch_email_content);
			$new_watch_email_content = explode("\n", $new_watch_email_content);
			$new_watch_emails = array();
			foreach ($new_watch_email_content as $new_watch_email) {
				if ($new_watch_email != '' && $new_watch_email) {
					$new_watch_emails[] = $new_watch_email;
				}
			}
			sort($new_watch_emails);
			//print_r($new_watch_emails);

			$sql = 'SELECT * FROM ' . DomainCheck::$db_prefix . '_domains WHERE domain_url ="' . strtolower($domain) . '"';
			$result = $wpdb->get_results( $sql, 'ARRAY_A' );
			$use_cache = false;
			if ( count ( $result ) ) {
				$domain_result = array_pop($result);
				$domain_result['cache'] = ($domain_result['cache'] ? json_decode(gzuncompress($domain_result['cache']), true) : null);
				$domain_result['domain_settings'] = ($domain_result['domain_settings'] ? json_decode(gzuncompress($domain_result['domain_settings']), true) : null);
				if (!is_array($domain_result['domain_settings'])) {
					$domain_result['domain_settings'] = array();
				}
				$domain_result['domain_settings']['watch_emails'] = $new_watch_emails;
				$new_settings = array('domain_settings' => gzcompress(json_encode($domain_result['domain_settings'])));
				$wpdb->update(
					DomainCheck::$db_prefix . '_domains',
					$new_settings,
					array (
						'domain_url' => strtolower($domain)
					)

				);
			}
		}

		public function watch_profile() {
			global $wpdb;

			$domain_to_view = strtolower($_GET['domain']);
			?>
			<div class="wrap">
				<h2><?php echo $_GET['domain']; ?></h2>
				<a href="?page=domain-check-ssl-profile&domain=<?php echo $_GET['domain']; ?>&domain_check_ssl_search=<?php echo $_GET['domain']; ?>">Refresh</a><br>
				<?php
				$sql = 'SELECT * FROM ' . DomainCheck::$db_prefix . '_domain WHERE domain_url ="' . strtolower($domain_to_view) . '"';
				$result = $wpdb->get_results( $sql, 'ARRAY_A' );
				$use_cache = false;
				if ( count ( $result ) ) {
					?>
					Domain found in DB!
					<br><br>
					<?php
					$domain_result = array_pop($result);
					$domain_result['cache'] = ($domain_result['cache'] ? json_decode(gzuncompress($domain_result['cache']), true) : null);
					$domain_result['domain_settings'] = ($domain_result['domain_settings'] ? json_decode(gzuncompress($domain_result['domain_settings']), true) : null);
					print_r($domain_result);

				} else {
					//domain not found... redirect to search...
					?>
					Domain not found in DB.
					<?php
				}
				?>
			</div>
			<?php

		}

		public function watch_start($domain) {
			global $wpdb;

			$domain = strtolower($domain);

			$wpdb->update(
				DomainCheck::$db_prefix . '_domains',
				array(
					'domain_watch' => 1
				),
				array (
					'domain_url' => $domain
				)
			);

			DomainCheckAdmin::admin_notices_add('Started watching <strong>' . $domain . '</strong>!', 'updated', null, '208-eye-plus');
		}

		public function watch_stop($domain) {
			global $wpdb;

			$domain = strtolower($domain);

			$wpdb->update(
				DomainCheck::$db_prefix . '_domains',
				array(
					'domain_watch' => 0
				),
				array (
					'domain_url' => $domain
				)
			);

			DomainCheckAdmin::admin_notices_add('Stopped watching <strong>' . $domain . '</strong>!', 'error', null, '209-eye-minus');
		}

		public function watch_trigger($domain, $ajax = 0) {
			//this function sucks because it has to do a select first
			global $wpdb;

			if (isset($_POST['domain'])) {
				$ajax = 1;
				$domain = strtolower($_POST['domain']);
			}

			$domain = strtolower($domain);

			$sql = 'SELECT * FROM ' . DomainCheck::$db_prefix . '_domains WHERE domain_url ="' . strtolower($domain) . '"';
			$result = $wpdb->get_results( $sql, 'ARRAY_A' );
			if ( count ( $result ) ) {
				$result = array_pop($result);
				$new_status = $result['domain_watch'] ? 0 : 1;
				$wpdb->update(
					DomainCheck::$db_prefix . '_domains',
					array(
						'domain_watch' => $new_status
					),
					array(
						'domain_url' => $domain
					)
				);

				$message_start = 'Started watching <strong>' . $domain . '</strong>!';
				$message_stop = 'Stopped watching <strong>' . $domain . '</strong>!';

				if (!$ajax) {
					if ($new_status) {
						DomainCheckAdmin::admin_notices_add($message_start, 'updated', null, '208-eye-plus');
					} else {
						DomainCheckAdmin::admin_notices_add($message_stop, 'error', null, '209-eye-minus');
					}
				} else {
					DomainCheckAdmin::ajax_success(
						$data = array(
							'watch' => $new_status,
							'message' => ($new_status ? $message_start : $message_stop),
							'domain' => $domain
						)
					);
				}
			}
		}

		public function status_owned($domain) {
			$this->status_update($domain, 'owned');
		}

		public function status_taken($domain) {
			$this->status_update($domain, 'taken');
		}

		public function status_trigger($domain) {
			global $wpdb;

			if (isset($_POST['domain'])) {
				$ajax = 1;
				$domain = strtolower($_POST['domain']);
			}

			$domain = strtolower($domain);

			$sql = 'SELECT * FROM ' . DomainCheck::$db_prefix . '_domains WHERE domain_url = "' . strtolower($domain) . '"';
			$result = $wpdb->get_results( $sql, 'ARRAY_A' );
			if ( count ( $result ) ) {
				$result = array_pop($result);
				$new_status = 0;
				if ($result['status'] == 1) {
					$new_status = 2;
				} else if ($result['status'] == 2) {
					$new_status = 1;
				}
				if ($new_status == 1 || $new_status == 2) {
					$wpdb->update(
						DomainCheck::$db_prefix . '_domains',
						array(
							'status' => $new_status
						),
						array(
							'domain_url' => $domain
						)
					);

					$message_start = '<strong>' . $domain . '</strong> marked as Owned!';
					$message_stop = '<strong>' . $domain . '</strong> marked as Taken!';

					if (!$ajax) {
						if ($new_status) {
							DomainCheckAdmin::admin_notices_add($message_start, 'owned', null, 'flag');
						} else {
							DomainCheckAdmin::admin_notices_add($message_stop, 'error', null, 'taken');
						}
					} else {
						DomainCheckAdmin::ajax_success(
							$data = array(
								'status' => $new_status,
								'message' => ($new_status == 2 ? $message_start : $message_stop),
								'domain' => $domain
							)
						);
					}
				}
			}
		}

		public function status_update($domain, $new_status) {
			global $wpdb;

			$status_name = ucfirst($new_status);
			$notice_type = 'owned';
			$icon = 'flag';

			switch ($new_status) {
				case 'owned':
				case 2:
					$new_status = 2;
					break;
				case 'registered':
				case 'taken':
				case 1:
					$new_status = 1;
					$notice_type = 'error';
					$icon = 'ban';
					break;
				default:
					DomainCheckAdmin::admin_notices_add('Status not allowed.', 'error', null, 'circle-x');
					return;
			}

			$domain = strtolower($domain);

			$wpdb->update(
				DomainCheck::$db_prefix . '_domains',
				array(
					'status' => $new_status
				),
				array (
					'domain_url' => $domain
				)
			);

			$message = 'Updated <strong>' . $domain . '</strong> status to <strong>' . $status_name . '</strong>!';
			DomainCheckAdmin::admin_notices_add(
				$message,
				$notice_type,
				null,
				$icon
			);
		}

		/**
		 * Screen options
		 */
		public function watch_screen_option() {
			$option = 'per_page';
			$args   = array(
				'label'   => 'Domain Watch',
				'default' => 100,
				'option'  => 'domains_per_page'
			);

			add_screen_option( $option, $args );

			$this->domains_obj = new DomainCheck_Watch_List();
		}

		public function your_domains() {
			global $wpdb;
			//add domain search box...
			//import domains CSV...
			//import domains XML...
			$this->admin_header();
			?>
			<div class="wrap">
				<h2>
					<img src="<?php echo plugins_url('/images/icons/flag.svg', __FILE__); ?>" class="svg svg-icon-h1 svg-fill-owned">
					Your Domains
				</h2>
				<?php
				$this->your_domains_search_box();
				?>
				<div id="poststuff">
					<div id="post-body" class="metabox-holder columns-2">
						<div id="post-body-content">
							<div class="meta-box-sortables ui-sortable">
								<form method="post">
									<?php
									$this->your_domains_obj->prepare_items();
									$this->your_domains_obj->display();
									?>
								</form>
							</div>
						</div>
					</div>
					<br class="clear">
				</div>
			</div>
			<?php
		}

		/**
		 * Screen options
		 */
		public function your_domains_screen_option() {
			$option = 'per_page';
			$args   = array(
				'label'   => 'Your Domains',
				'default' => 100,
				'option'  => 'domains_per_page'
			);

			add_screen_option( $option, $args );

			$this->your_domains_obj = new DomainCheck_Your_Domains_List();
		}

		public function your_domains_search_box() {
			?>
			<form action="" method="GET">
				<input type="text" name="domain_check_your_domains" id="domain_check_your_domains">
				<input type="hidden" name="page" value="domain-check-your-domains">
				<input type="submit" />
			</form>
			<?php
		}

	}

	//fire things up bruh...
	if (function_exists('add_action')) {
		add_action(
			'plugins_loaded',
			function () {
				DomainCheckAdmin::$instance = new DomainCheckAdmin();
			}
		);
	}
}