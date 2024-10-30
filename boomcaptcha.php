<?php
/*
Plugin Name: BoomCaptcha
Plugin URI: http://boomcaptcha.com
Description: captcha with encrypted fonts.
Version: 0.1
*/

$libsPath = realpath(dirname(__FILE__) . '/libs');
set_include_path(get_include_path() . PATH_SEPARATOR . $libsPath);

define('ENMASK_PLUGIN_FILE', __FILE__);
define('ENMASK_TPLS_PATH', realpath(dirname(__FILE__) . '/tpls'));
define('ENMASK_FONTS_PATH', realpath(dirname(__FILE__) . '/fonts'));

require_once 'EnMask/Wp/Init.php';

register_activation_hook(__FILE__, array('EnMask_Wp_Init', 'activation'));

if (is_admin()) {
	add_filter('plugin_action_links', array('EnMask_Wp_Init', 'settingsLinks'), 10, 2);
	add_action('admin_menu', array('EnMask_Wp_Init', 'adminMenu'));
} elseif (empty($userdata->ID)) {
	add_action('init', array('EnMask_Wp_Init', 'startSess'));
	add_action('init', array('EnMask_Wp_Init', 'appendCaptchaToHead'));
	add_action('comment_form_after_fields', array('EnMask_Wp_Init', 'appendCaptcha'));
	add_action('comment_post', array('EnMask_Wp_Init', 'commentValidateCaptcha'));
}