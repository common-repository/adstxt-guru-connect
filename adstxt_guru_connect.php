<?php
/**
 * @package adstxt-guru-connect
 * @version 1.1.0
 */
/*
Plugin Name: ads.txt Guru Connect
Plugin URI: http://wordpress.org/extend/plugins/adstxt-guru-connect/
Description: The ads.txt Guru Connect plugin connects your <a href="https://adstxt.guru/">ads.txt Guru</a> account and WordPress installation to enable automatic updates to your ads.txt file when changes are made via your ads.txt Guru account.  To get started: 1) Click the 'Activate' link to the left of this description, 2) Go to the 'ads.txt Guru' link shown in the menu on the left of the page.
Author: ads.txt Guru
Author URI: https://adstxt.guru/
License: GPLv2
Version: 1.1.0
*/
/*
Copyright 2018 ionix Limited (email: contact@adstxt.guru)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


if ((function_exists('add_action')) && (function_exists('add_filter'))) {

	if ((function_exists('is_admin')) && (is_admin())) {

		// Initiate admin functions

		add_action('init', 'atg_connect_init');

	} elseif ((isset($_POST['atg-connect-key'])) && (isset($_POST['atg-connect-secret']))) {

		// Initiate frontend function, only when request contains the key, secret and token parameters

		add_action('init', 'atg_connect_wp_loaded');

	}
}


function atg_connect_init() {

	// Initiate admin functions

	if ((function_exists('wp_get_current_user')) && (current_user_can('manage_options'))) {

		define('WP_ATG_VERSION', '1.1.0');

		add_action('admin_menu', 'atg_connect_admin_menu');

	}
}


function atg_connect_admin_menu() {

	// Initiate admin menu

	add_menu_page('ads.txt Guru Connect', 'ads.txt Guru', 'manage_options', 'adstxt-guru-connect', 'atg_connect_home', '', 82.14);

}


function atg_connect_home() {

	// Admin plugin output

	$atg_connect_plugin_url = admin_url('admin.php?page=adstxt-guru-connect');

	$atg_connect_notices = array();
	$atg_connect_notice = '';

	$atg_connect = false;

	if (isset($_POST['atg-connect-reset'])) {
		delete_option('atg-connect');
	}

	if (($atg_connect = atg_connect_get_option())===false) {

		// No configuration data, generate key and secret.

		$atg_connect = array(
			'key' => atg_connect_random_string(),
			'secret' => atg_connect_random_string(),
			'token_param' => atg_connect_random_string('8'),
			'last_update' => '0',
			'last_test' => '0',
			'adstxt_path' => get_home_path().'ads.txt',
			'adstxt_custom' => '',
			'url' => site_url('/')
		);

		if (atg_connect_add_option($atg_connect)!==true) {

			$atg_connect = false;

		}
	}

	if (!isset($atg_connect['adstxt_custom'])) {
		$atg_connect['adstxt_custom'] = '';
	}

	if ($atg_connect===false) {

		print<<<END

	<div class="wrap">

		<h1>ads.txt Guru Connect</h1>

		<div class="updated settings-error error is-dismissible">Unable to set-up initial configuration, please <a href="https://adstxt.guru/support/contact/" target="new">contact support</a>.</div>

	</div>
END;

	} else {

		if ($atg_connect['url']!=site_url('/')) {

			$atg_connect_notices[] = <<<END
The URL of your WordPress installation has changed and therefore any existing connection with ads.txt Guru may be disrupted.  If you are unable to synchronize your ads.txt file please select the 'Reset Connect Data' button and repeat the 'Connect WordPress' process.<br />
<form action="$atg_connect_plugin_url" method="POST">
	<input type="submit" name="atg-connect-reset" value="Reset Connect Data" style="margin-top:4px;" class="button button-primary" />
</form>
END;

		}

		if (isset($_POST['atg-connect-path'])) {

			// Change path to ads.txt file

			if (file_exists($_POST['atg-connect-path'])) {

				$atg_connect['adstxt_path'] = $_POST['atg-connect-path'];

				atg_connect_update_option($atg_connect);

				$atg_connect_notice = '<div class="updated settings-error success is-dismissible">Path to ads.txt file updated successfully!</div>';

			} else {

				$atg_connect_notices[] = 'Invalid path to ads.txt file, please ensure the ads.txt file exists (this can be an empty text file).';

			}
		}

		if (isset($_POST['atg-connect-custom'])) {

			$atg_connect['adstxt_custom'] = $_POST['atg-connect-custom'];

			atg_connect_update_option($atg_connect);

			if (file_exists($atg_connect['adstxt_path'])) {
				$adstxt_data = explode("\n# ATG-CUSTOM\n", file_get_contents($atg_connect['adstxt_path']));
				$adstxt_data = $adstxt_data[0]."\n# ATG-CUSTOM\n".$atg_connect['adstxt_custom'];
			} else {
				$adstxt_data = "\n# ATG-CUSTOM\n".$atg_connect['adstxt_custom'];
			}
			@file_put_contents($atg_connect['adstxt_path'], $adstxt_data);

			$atg_connect_notice = '<div class="updated settings-error success is-dismissible">Custom ads.txt records updated successfully!</div>';

		}



		if ($atg_connect['last_test']<time() - 3600) {

			// Test HTTP connections supported

			$atg_connect_test = array(
				'success' => false,
				'request' => false,
				'response' => '',
				'code' => '0'
			);

			$atg_connect_test['request'] = wp_remote_post('https://adstxt.guru/connect/1.0/', array(
				'body' => array(
					'test' => '1'
				)
			));

			$atg_connect_test['code'] = wp_remote_retrieve_response_code($atg_connect_test['request']);

			if ($atg_connect_test['code']=='200') {

				$atg_connect_test['response'] = json_decode(wp_remote_retrieve_body($atg_connect_test['request']), true);

				if ($atg_connect_test['response']['success']===true) {

					$atg_connect['last_test'] = time();

					if (atg_connect_update_option($atg_connect)) {

						$atg_connect_test['success'] = true;

					}
				}
			}

			if ($atg_connect_test['success']!==true) {

				$atg_connect_notices[] = 'Unable to connect to ads.txt Guru system, please <a href="https://adstxt.guru/support/contact/" target="new">contact support</a>.';

			}
		}


		// Test ads.txt file exists and is writable

		$atg_connect_adstxt_path_exists = false;
		$atg_connect_adstxt_path_writable = false;

		if (file_exists($atg_connect['adstxt_path'])) {

			$atg_connect_adstxt_path_exists = true;

			if (is_writable($atg_connect['adstxt_path'])) {

				$atg_connect_adstxt_path_writable = true;

			} else {

				$atg_connect_notices[] = 'ads.txt file is write protected, please adjust file permissions so file is writable, e.g. 666 or 777.<br />Path to ads.txt File: <code>'.$atg_connect['adstxt_path'].'</code>';

			}
		}


		// Generate copy and paste string to establish connection

		$atg_connect_copy_string = array(
			'key' => $atg_connect['key'],
			'secret' => $atg_connect['secret'],
			'param' => $atg_connect['token_param'],
			'url' => site_url('/')
		);

		$atg_connect_copy_string = base64_encode(json_encode($atg_connect_copy_string));


		// Output admin plugin

		$atg_connect_notices_count = count($atg_connect_notices);

		for ($i=0; $i<$atg_connect_notices_count; $i++) {

			$atg_connect_notices[$i] = '<div class="updated settings-error error"><b>Error</b><br />'.$atg_connect_notices[$i].'</div>';

		}

		$atg_connect_notices = implode('<br />', $atg_connect_notices);

		if ($atg_connect_notices=='') {

			if ($atg_connect['last_update']>time() - 31536000) {

				$atg_connect_notices = '<div class="updated settings-error success is-dismissible">ads.txt file last updated '.human_time_diff($atg_connect['last_update']).' ago.</div>';

			}
		}


		// Retrieve contents of current ads.txt file

		$atg_connect_adstxt = '';
		$atg_connect_adstxt_path = htmlentities($atg_connect['adstxt_path']);
		$atg_connect_adstxt_custom = htmlentities($atg_connect['adstxt_custom']);
		$atg_connect_adstxt_time = '';

		if (file_exists($atg_connect['adstxt_path'])) {

			$atg_connect_adstxt = htmlentities(file_get_contents($atg_connect['adstxt_path']));

			$atg_connect_adstxt_time = human_time_diff(filemtime($atg_connect['adstxt_path']));

		}



		print<<<END

	<div class="wrap">

		<h1>ads.txt Guru Connect</h1>

	$atg_connect_notice
	$atg_connect_notices

	<p>ads.txt Guru Connect enables you to connect your <a href="https://adstxt.guru/" target="new"><b>ads.txt Guru</b></a> account to your WordPress installation to automate your ads.txt file upload whenever you or a collaborator makes changes to your ads.txt file using the ads.txt Guru management system, this eliminates the need to manually upload your ads.txt file.</p>

	<h3>What is ads.txt Guru?</h2>

	<p><a href="https://adstxt.guru/" target="new"><b>ads.txt Guru</b></a> is a revolutionary tool to eliminate the burden of maintaining website ads.txt files!  The ads.txt Guru system allows you to manage your ads.txt files online, automatically validate your ads.txt files, and collaborate between publisher and ad network to automate ads.txt updates.  By allowing ad networks to manage their ads.txt records on your website without your intervention, the time-consuming process of manually updating your ads.txt files when ad networks need to make changes to their records can now be totally eliminated.</p>

	<h3>Connect WordPress</h3>

	<p>To connect WordPress simply <a href="https://adstxt.guru/my/" target="new"><b>log-in to your ads.txt Guru account</b></a> and proceed to the 'Settings' section for your website/domain, then select the 'Connect WordPress' tab and copy and paste the following code into the 'Connect Data' field.</p>

	<div class="card">
		&nbsp;<br /><p class="description">Connect Data</p>
		<textarea id="atg-connect-copy" cols="30" rows="4" style="width:100%;font-size:1.3em;">$atg_connect_copy_string</textarea><br />
		<a href="javascript:;" onclick="document.getElementById('atg-connect-copy').select();document.execCommand('Copy');" class="button button-primary">Copy Connect Data to Clipboard</a><br />&nbsp;
	</div><br />

	<h3>What is the 'Connect Data'?</h3>

	<p>The 'Connect Data' code contains a random key, secret and token, along with the URL of your website, this has been encoded to make it easier for you to copy and paste.  This data enables us to connect to your website to provoke this plugin to download and update your ads.txt file using a secure process.</p>

	<h3>Path to ads.txt file</h3>

	<p>Your ads.txt file must be placed at the root of your domain (e.g. http://yourdomain.com/ads.txt). If your WordPress installation is installed in a sub-directory you may need to adjust the path to your ads.txt file using the form below.</p>

	<form action="$atg_connect_plugin_url" method="POST">
		<div class="card">
			&nbsp;<br /><p class="description">Path to ads.txt file</p>
			<input type="text" id="atg-connect-path" name="atg-connect-path" size="30" value="$atg_connect_adstxt_path" style="width:100%;margin-bottom:4px;" /><br />
			<input type="submit" value="Update Path" class="button button-primary" />
			<br />&nbsp;
		</div>
	</form><br />

	<h3>Custom ads.txt Records</h3>

	<p>To append custom ads.txt records to the end of the ads.txt file generated by ads.txt Guru simply enter those records below.  Please ensure these records are validated using the <a href="https://adstxt.guru/validator/" target="new"><b>ads.txt Guru validator</b></a>.</p>

	<form action="$atg_connect_plugin_url" method="POST">
		<div class="card">
			&nbsp;<br /><p class="description">Custom ads.txt Records</p>
			<textarea id="atg-connect-custom" name="atg-connect-custom" cols="40" rows="6" style="width:100%;height:200px;margin-bottom:4px;">$atg_connect_adstxt_custom</textarea><br />
			<input type="submit" value="Save" class="button button-primary" />
			<br />&nbsp;
		</div>
	</form><br />

	<h3>Updating your ads.txt file</h3>

	<p>To update your ads.txt file simply <a href="https://adstxt.guru/my/" target="new"><b>log-in to your ads.txt Guru account</b></a> and select the relevant domain/website, then select the 'Synchronize' button.</p>

END;
/*
		if ($atg_connect_adstxt!='') {

			print<<<END
	<h3>Your ads.txt file</h3>

	<p>The contents of your current ads.txt file are shown below:</p>

	<div class="card">
		&nbsp;<br /><p class="description">$atg_connect_adstxt_path (updated $atg_connect_adstxt_time ago)</p>
		<textarea id="atg-connect-adstxt" cols="30" rows="12" style="width:100%;">$atg_connect_adstxt</textarea><br />&nbsp;
	</div>
END;

	}
*/
		print<<<END
</div>
END;

	}
}


function atg_connect_wp_loaded() {

	// Authenticate and process ads.txt update

	if (($atg_connect = atg_connect_get_option())!==false) {

		if (isset($atg_connect['token_param'])) {

			$atg_connect_token_param = $atg_connect['token_param'];

			if ((isset($_POST['atg-connect-token-'.$atg_connect_token_param])) && (isset($atg_connect['key'])) && (isset($atg_connect['secret'])) && ($_POST['atg-connect-key']==$atg_connect['key']) && ($_POST['atg-connect-secret']==$atg_connect['secret']) && (strlen($_POST['atg-connect-token-'.$atg_connect_token_param])=='32') && (!preg_match('/[^a-zA-Z0-9]/', $_POST['atg-connect-token-'.$atg_connect_token_param]))) {

				// Request authenticated, process ads.txt file retrieval from ads.txt Guru and update website's ads.txt file

				if ((file_exists($atg_connect['adstxt_path'])) && (!is_writable($atg_connect['adstxt_path']))) {

					// ads.txt file write protected

					atg_connect_json(array(
						'success' => false,
						'reason' => 'adstxt-protected'
					));

				} else {

					$atg_connect_adstxt = array(
						'request' => false,
						'response' => '',
						'code' => '0'
					);

					if (isset($_POST['atg-connect-test'])) {

						$atg_connect_adstxt['request'] = wp_remote_post('https://adstxt.guru/connect/1.0/', array(
							'body' => array(
								'test' => '1'
							)
						));

					} else {

						$atg_connect_adstxt['request'] = wp_remote_post('https://adstxt.guru/connect/1.0/', array(
							'body' => array(
								'token' => $_POST['atg-connect-token-'.$atg_connect_token_param]
							)
						));

					}

					$atg_connect_adstxt['code'] = wp_remote_retrieve_response_code($atg_connect_adstxt['request']);

					if ($atg_connect_adstxt['code']=='200') {

						$atg_connect_adstxt['response'] = wp_remote_retrieve_body($atg_connect_adstxt['request']);

						if ($atg_connect_adstxt['response']!='') {

							$atg_connect_adstxt['response'] = @json_decode($atg_connect_adstxt['response'], true);

							if ((isset($_POST['atg-connect-test'])) && (isset($atg_connect_adstxt['response']['success'])) && ($atg_connect_adstxt['response']['success']===true)){

								atg_connect_json(array(
									'success' => true
								));

							} elseif ((isset($atg_connect_adstxt['response']['success'])) && (isset($atg_connect_adstxt['response']['data'])) && ($atg_connect_adstxt['response']['success']===true)) {

								if (substr(trim($atg_connect_adstxt['response']['data']), 0, 6)=='# ATG-') {

									if ((isset($atg_connect['adstxt_custom'])) && ($atg_connect['adstxt_custom']!='')) {

										$atg_connect_adstxt['response']['data'] .= "\n# ATG-CUSTOM\n".$atg_connect['adstxt_custom'];

										if (file_put_contents($atg_connect['adstxt_path'], $atg_connect_adstxt['response']['data'])!==false) {

											if (file_get_contents($atg_connect['adstxt_path'])==$atg_connect_adstxt['response']['data']) {

												// Success, ads.txt file updated

												$atg_connect['last_update'] = time();
												$atg_connect['last_test'] = time();

												atg_connect_update_option($atg_connect);

												atg_connect_json(array(
													'success' => true
												));

											} else {

												// ads.txt file not overwritten correctly

												atg_connect_json(array(
													'success' => false,
													'reason' => 'adstxt-check'
												));

											}
										} else {

											// ads.txt file could not be written

											atg_connect_json(array(
												'success' => false,
												'reason' => 'adstxt-write'
											));

										}


									} elseif (file_put_contents($atg_connect['adstxt_path'], $atg_connect_adstxt['response']['data'])!==false) {

										if (file_get_contents($atg_connect['adstxt_path'])==$atg_connect_adstxt['response']['data']) {

											// Success, ads.txt file updated

											$atg_connect['last_update'] = time();
											$atg_connect['last_test'] = time();

											atg_connect_update_option($atg_connect);

											atg_connect_json(array(
												'success' => true
											));

										} else {

											// ads.txt file not overwritten correctly

											atg_connect_json(array(
												'success' => false,
												'reason' => 'adstxt-check'
											));

										}
									} else {

										// ads.txt file could not be written

										atg_connect_json(array(
											'success' => false,
											'reason' => 'adstxt-write'
										));

									}
								} else {

									// ads.txt file invalid

									atg_connect_json(array(
										'success' => false,
										'reason' => 'adstxt-invalid'
									));

								}
							} else {

								if ((isset($atg_connect_adstxt['response']['reason'])) && ($atg_connect_adstxt['response']['reason']=='token')) {

									// Token expired

									atg_connect_json(array(
										'success' => false,
										'reason' => 'connect-token'
									));

								} elseif ((isset($atg_connect_adstxt['response']['reason'])) && ($atg_connect_adstxt['response']['reason']=='maintenance')) {

									// ads.txt Guru Connect API down for maintenance

									atg_connect_json(array(
										'success' => false,
										'reason' => 'connect-maintenance'
									));

								} elseif ((isset($atg_connect_adstxt['response']['reason'])) && ($atg_connect_adstxt['response']['reason']=='invalid')) {

									// ads.txt Guru Connect API received invalid request

									atg_connect_json(array(
										'success' => false,
										'reason' => 'connect-invalid'
									));

								} else {

									// Unknown process error, return body for debugging

									atg_connect_json(array(
										'success' => false,
										'reason' => 'connect-process',
										'body' => $atg_connect_adstxt['response']
									));

								}
							}
						} else {

							// Invalid response

							atg_connect_json(array(
								'success' => false,
								'reason' => 'connect-retrieve'
							));

						}
					} else {

						// Connection failed

						atg_connect_json(array(
							'success' => false,
							'reason' => 'connect-error'
						));

					}
				}
			}
		}
	}

	// Authentication failed, ignore

}


function atg_connect_json($output = false) {

	// Output JSON and exit

	if ($output===false) {

		$output = array(
			'success' => false,
			'reason' => 'error'
		);

	}

	print json_encode($output, JSON_PRETTY_PRINT);
	exit;

}


function atg_connect_add_option($option = array()) {

	// Save plugin configuration

	$option = base64_encode(serialize($option));

	return add_option('atg-connect', $option, '', 'no');

}


function atg_connect_update_option($option = array()) {

	// Update plugin configuration

	$option = base64_encode(serialize($option));

	return update_option('atg-connect', $option);

}


function atg_connect_get_option() {

	// Retrieve plugin configuration

	if ($option = get_option('atg-connect')) {

		$option = unserialize(base64_decode($option));

		if (is_array($option)) {

			return $option;

		}
	}

	return false;

}


function atg_connect_random_string($length = '32', $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789') {

	// Generate random string

	$string = '';

	for ($i=0; $i<$length; $i++) {

		$string .= $characters[rand(0,strlen($characters)-1)];

	}

	return $string;

}


?>