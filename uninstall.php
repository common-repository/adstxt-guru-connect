<?php
/**
 * @package adstxt-guru-connect
 * @version 1.1.0
 */


if (!defined( 'WP_UNINSTALL_PLUGIN')) {

	exit;

}

if (get_option('atg-connect')) {

	delete_option('atg-connect');

}

?>