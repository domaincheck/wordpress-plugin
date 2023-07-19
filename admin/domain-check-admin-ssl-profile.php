<?php

class DomainCheckAdminSslProfile {

	public static function ssl_profile() {
		if (!isset($_GET['domain']) || !$_GET['domain']) {
			wp_redirect( admin_url( 'admin.php?page=domain-check-ssl-check' ) );
		}
		global $wpdb;
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
				<img src="<?php echo plugins_url('/images/icons/circle-www2.svg', __FILE__); ?>" class="svg svg-icon-h1 svg-fill-gray">
				<img src="<?php echo $icon_url; ?>" class="svg svg-icon-h1 svg-fill-<?php echo $icon_fill; ?>">
				Domain Check - SSL - <?php echo $_GET['domain']; ?>
			</h2>
			<?php DomainCheckAdminHeader::admin_header(); ?>
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
							<li>
								<strong>Settings</strong>
								<form action="admin.php?page=domain-check-ssl-profile&domain=<?php echo $domain_result['domain_url']; ?>" method="POST">
								<ul>
									<li>
										<div class="domain-check-profile-li-div-left">Owner:</div>
										<div class="domain-check-profile-li-div-right">
											<input type="text" name="profile_settings_owner" id="profile_settings_owner" value="<?php echo isset($domain_result['owner']) ? $domain_result['owner'] : ''; ?>">
										</div>
									</li>
									<li>
										<input type="submit" class="button" value="Update Settings">
									</li>
								</ul>
								<input type="hidden" name="ssl_profile_settings_update" value="<?php echo $domain_result['domain_url']; ?>" />
								</form>
							</li>
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
			<?php
			DomainCheckAdminHeader::admin_header_nav();
			DomainCheckAdminHeader::footer();
			?>
		</div>
		<?php
	}

	function ssl_profile_settings_update($domain) {
		global $wpdb;

		$sql = 'SELECT * FROM ' . DomainCheck::$db_prefix . '_ssl WHERE domain_url ="' . strtolower($domain) . '"';
		$result = $wpdb->get_results( $sql, 'ARRAY_A' );
		$use_cache = false;
		$domain_result = null;

		$new_settings = array();

		if ( count ( $result ) ) {
			$domain_result = array_pop($result);
		}
		if ( $domain_result ) {
			$domain_result['cache'] = ($domain_result['cache'] ? json_decode(gzuncompress($domain_result['cache']), true) : null);
			$domain_result['domain_settings'] = ($domain_result['domain_settings'] ? json_decode(gzuncompress($domain_result['domain_settings']), true) : null);
			if (array_key_exists('profile_settings_owner', $_POST)) {
				if (strlen($_POST['profile_settings_owner']) > 255) {
					$_POST['profile_settings_owner'] = substr($_POST['profile_settings_owner'], 0, 255);
				}
				$new_settings['owner'] = $_POST['profile_settings_owner'];
			}

			$new_settings['domain_settings'] = gzcompress(json_encode($domain_result['domain_settings']));
			$wpdb->update(
				DomainCheck::$db_prefix . '_ssl',
				$new_settings,
				array (
					'domain_url' => strtolower($domain)
				)

			);

			DomainCheckAdmin::admin_notices_add(
				'Success! SSL Certificate edited.',
				'updated',
				null,
				'circle-check'
			);
		}
	}
}