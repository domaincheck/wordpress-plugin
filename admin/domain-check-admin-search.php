<?php

class DomainCheckAdminSearch {

	public static $domains_obj;

	public static function search() {
		global $wpdb;
		?>
		<div id="domain-check-wrapper" class="wrap">
			<h2>
				<img src="<?php echo plugins_url('/images/icons/circle-www2.svg', __FILE__); ?>" class="svg svg-icon-h1 svg-fill-gray">
				<img src="<?php echo plugins_url('/images/icons/magnifying-glass.svg', __FILE__); ?>" class="svg svg-icon-h1 svg-fill-gray">
				Domain Check - Domain Search
			</h2>
			<?php echo DomainCheckAdminHeader::admin_header(); ?>
			<?php self::search_box(); ?>
			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-2">
					<div id="post-body-content">
						<div class="meta-box-sortables ui-sortable">
							<form action="" method="post">
								<?php
								self::$domains_obj->prepare_items();
								self::$domains_obj->display();
								?>
							</form>
						</div>
					</div>
				</div>
				<br class="clear">
			</div>
			<?php
			DomainCheckAdminHeader::admin_header_nav();
			DomainCheckAdminHeader::footer();
			?>
		</div>
	<?php
	}

	public static function search_box($dashboard = false) {
		$css_class = 'domain-check-admin-search-input';
		if ( $dashboard ) {
			$css_class .= '-dashboard';
		}
		$css_class_button = $css_class . '-btn';
		?>
		<script type="text/javascript">
			function domain_check_search_click(evt) {
				document.getElementById('domain-check-search-box-form').submit();
			}
		</script>
		<form id="domain-check-search-box-form" action="" method="GET">
			<input type="text" name="domain_check_search" id="domain_check_search" class="<?php echo $css_class; ?>" />
			<input type="hidden" name="page" value="domain-check-search" />
			<?php if ( !$dashboard ) { ?>
			<div type="button" class="button domain-check-admin-search-input-btn" onclick="domain_check_search_click();">
				<img src="<?php echo plugins_url('/images/icons/magnifying-glass.svg', __FILE__); ?>" class="svg svg-icon-h3 svg-fill-gray">
				<div style="display: inline-block;">Search Domain Name</div>
			</div>
			<?php } else { ?>
			<input type="submit" class="<?php echo $css_class_button; ?> button" value="Search Domain" />
			<?php } ?>
		</form>
		<?php
	}

	public static function search_init() {

	}

	/**
			 * Screen options
			 */
	public static function search_screen_option() {
		$option = 'per_page';
		$args   = array(
			'label'   => 'Domain Search',
			'default' => 100,
			'option'  => 'domains_per_page'
		);

		add_screen_option( $option, $args );

		self::$domains_obj = new DomainCheck_Search_List();
	}
}