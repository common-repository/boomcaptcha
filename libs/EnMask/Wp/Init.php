<?php

require_once 'EnMask/Wp.php';
require_once 'EnMask/Wp/Model/Keywords.php';
require_once 'EnMask/Wp/Model/Options.php';
require_once 'EnMask/Captcha/Wrapper.php';
require_once 'EnMask/Wp/Page/Test.php';
require_once 'EnMask/Wp/Page/Settings.php';

class EnMask_Wp_Init
{
	public static function activation()
	{
		global $wpdb;

		if (false === get_option(EnMask_WP::TOTAL_OPTION_NAME)) {
			add_option(EnMask_WP::TOTAL_OPTION_NAME, 0);
			add_option(EnMask_WP::LICENSE_OPTION_NAME, '');
			add_option(EnMask_Wp::TOTAL_PERCENT_OPTION, 0);
		}

		EnMask_Wp_Model_Keywords::model()->createTbls();
	}

	public static function settingsLinks($links, $file = null)
	{
		if ($file == plugin_basename(ENMASK_PLUGIN_FILE)) {
			$links[] = '<a href="admin.php?page=enmask-settings">'.__('Settings').'</a>';
		}

		return $links;
	}

	public static function adminMenu()
	{
		add_submenu_page('plugins.php', __('Settings BoomCaptcha'), __('Settings BoomCaptcha'), 'manage_options', 'enmask-settings', array('EnMask_Wp_Page_Settings', 'get'));
	}

	public static function appendCaptchaToHead()
	{
		wp_enqueue_script('jquery');
		wp_enqueue_script('enmask_captcha_control', '/wp-content/plugins/boomcaptcha/js/control.js');
		wp_enqueue_script('enmask_captcha_validation', '/wp-content/plugins/boomcaptcha/js/validation.js');
		wp_enqueue_style('enmask_captcha_control', '/wp-content/plugins/boomcaptcha/css/captcha.css');
	}

	public static function appendCaptcha()
	{
		$wrapper = self::getCaptchaWrapper();

		$_SESSION[EnMask_Wp::CAPTCHA_SESS_KEY] = serialize($wrapper->getCaptcha());

		echo $wrapper->getRender()->getCssFonts();
		echo $wrapper->getRender()->getHtml();
		echo $wrapper->getRender()->getJs();

		$jsonConfig = json_encode(array(
			'loadingText' => __('Checking the captcha...'),
			'errorText' => __('Captcha code is incorrect'),
			'validationUrl' => get_site_url(null, 'wp-content/plugins/boomcaptcha/index.php?mode=validate')
		));

		echo <<<EOF
<script type="text/javascript">
	jQuery(document).ready(function() {
		var validation = new enmask.validation({$wrapper->getRender()->getJsGlobalVar()}, {$jsonConfig});
		validation.setup();
	});
</script>
<div id="enmask-captcha-checking"></div>
EOF;

	}

	public static function commentValidateCaptcha($commentId)
	{
		$code = (isset($_POST[EnMask_Wp::INPUT_NAME])) ? $_POST[EnMask_Wp::INPUT_NAME] : '';

		if (!self::validate($code)) {
			wp_set_comment_status($commentId, 'trash');
			wp_die(__('Captcha code is incorrect!', 'enmask'));
		}

		self::resetSess();
	}

	public static function resetSess()
	{
		$keys = array(EnMask_Wp::CAPTCHA_CHECKED_SESS_KEY, EnMask_Wp::CAPTCHA_SESS_KEY);
		foreach ($keys as $key) {
			if (isset($_SESSION[$key])) {
				unset($_SESSION[$key]);
			}
		}
	}

	public static function validate($code)
	{
		$isValid = false;

		if (!empty($_SESSION[EnMask_Wp::CAPTCHA_CHECKED_SESS_KEY])) {
			$isValid = true;
		} elseif (isset($_SESSION[EnMask_Wp::CAPTCHA_SESS_KEY])) {
			$captcha = unserialize($_SESSION[EnMask_Wp::CAPTCHA_SESS_KEY]);

			if ($captcha->validate($code)) {
				$isValid = true;
			}
		}

		return $isValid;
	}

	public static function startSess()
	{
		if (!session_id()) {
			session_start();
		}
	}

	public static function getCaptchaWrapper()
	{
		$total = get_option(EnMask_WP::TOTAL_PERCENT_OPTION, 0);

		EnMask_Wp_Model_Options::model()->increaseEnmaskTotalHits(EnMask_WP::TOTAL_OPTION_NAME);
		EnMask_Wp_Model_Options::model()->increaseEnmaskTotalHits(EnMask_WP::TOTAL_PERCENT_OPTION);

		list($version, $expire) = EnMask_Wp_Init::determineVersion();

		$config = array(
			'fontsDir' => ENMASK_FONTS_PATH,
			'webFontsDir' => get_site_url(null, 'wp-content/plugins/boomcaptcha/fonts/'),
			'resultInputName' => EnMask_Wp::INPUT_NAME,
			'refreshUrl' => get_site_url(null, 'wp-content/plugins/boomcaptcha/index.php?mode=refresh'),
			'copyright' => false
		);

		if ($version == EnMask_Wp::VERSION_FREE) {
			$config['fontListLimit'] = 10;
		}

		$keywords = EnMask_Wp_Model_Keywords::model()->getCaptchaKeywords($total, $version);
		if (!empty($keywords)) {
			$row = $keywords[array_rand($keywords, 1)];
			EnMask_Wp_Model_Keywords::model()->increaseHit($row['keyword_id']);

			$config['codePrefix'] = $row['keyword_name'] . ' ';
			$config['codeLength'] = array(1, 3);
		}

		$wrapper = new EnMask_Captcha_Wrapper($config);

		return $wrapper;
	}

	public static function determineVersion()
	{
		$key = get_option(EnMask_Wp::LICENSE_OPTION_NAME);

		if (!empty($key)) {
			$key = base64_decode($key);
			$parts = explode('.', $key);

			if (sizeof($parts) == 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
				if ($parts[0] > time()) {
					return array(EnMask_Wp::VERSION_PROFESSIONAL, $parts[0]);
				}
			}
		}

		return array(EnMask_Wp::VERSION_FREE, null);
	}
}