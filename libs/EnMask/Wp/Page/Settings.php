<?php

require_once 'EnMask/Wp/Page/Abstract.php';
require_once 'EnMask/Wp/Model/Keywords.php';

class EnMask_Wp_Page_Settings extends EnMask_Wp_Page_Abstract {

	protected $_keywords = array();

	protected $_errors = array();

	protected $_version;

	protected $_expire;

	public static function get()
	{
		$obj = parent::model(__CLASS__);
		$obj->start();
	}

	public function start()
	{
		wp_enqueue_script('jquery');
		wp_enqueue_script('enmask_admin', '/wp-content/plugins/boomcaptcha/js/admin.js');
		wp_enqueue_style('enmask_admin', '/wp-content/plugins/boomcaptcha/css/admin.css');

		list($this->_version, $this->_expire) = EnMask_Wp_Init::determineVersion();

		$model = new EnMask_Wp_Model_Keywords();
		$action = get_site_url(null, 'wp-admin/plugins.php?page=enmask-settings');
		$messages = array();
		$this->_keywords = $model->getList($this->_version);
		$quickStat = $this->_keywords;

		if ($this->isPost()) {
			if ($this->validatePostKeywords()) {
				$this->deleteNotInList();

				//reset count for percent
				update_option(EnMask_Wp::TOTAL_PERCENT_OPTION, 0);

				foreach ($this->_keywords as $row) {
					$data = array(
						'keyword_name' => $row['keyword_name'],
						'keyword_percent' => $row['keyword_percent'],
						'keyword_real_hit' => 0
					);

					if (!empty($row['keyword_id'])) {
						$model->update($row['keyword_id'], $data);
					} else {
						$model->insert($data);
					}
				}

				$messages[] = __('Keywords was successfully saved.', 'enmask');
				$this->_keywords = $model->getList($this->_version);
				$quickStat = $this->_keywords;
			}
		}

		echo $this->render('settings', array(
			'action' => $action,
			'percents' => $this->getPercents(),
			'keywords' => $this->_keywords,
			'errors' => $this->_errors,
			'messages' => $messages,
			'totalHits' => get_option(EnMask_WP::TOTAL_OPTION_NAME, 0),
			'quickStat' => $quickStat,
			'csvUrl' => get_site_url(null, 'wp-content/plugins/boomcaptcha/index.php?mode=csv'),
			'version' => $this->_version,
			'expire' => $this->_expire,
			'licenseAction' => get_site_url(null, 'wp-content/plugins/boomcaptcha/index.php?mode=license'),
			'licenseKey' => get_option(EnMask_Wp::LICENSE_OPTION_NAME)
		));
	}

	public function validatePostKeywords()
	{
		$this->_keywords = array();
		$this->_errors = array();

		$summaryPercent = 0;
		$percents = $this->getPercents();
		foreach ($_POST['keyword'] as $key => $keyword) {
			if (!isset($_POST['percent'][$key], $_POST['id'][$key]) || !in_array($_POST['percent'][$key], $percents)) {
				continue;
			}

			$keyword = trim($keyword);
			if (!empty($keyword)) {
				$this->_keywords[] = array(
					'keyword_id' => $_POST['id'][$key],
					'keyword_name' => $keyword,
					'keyword_percent' => $_POST['percent'][$key]
				);

				$summaryPercent += intval($_POST['percent'][$key]);

				$index = sizeof($this->_keywords) - 1;

				if (!preg_match('/^[a-z0-9 ]+$/i', $keyword)) {
					$this->_errors[$index] = __('Keyword can contain only latin characters, numbers and spaces.');
				} elseif (strlen($keyword) > 20) {
					$this->_errors[$index] = __('Keyword max length is 20');
				} elseif ($summaryPercent > 100) {
					$this->_errors[$index] = __('Summary % cannot be greater than 100.');
				}
			}
		}

		if (empty($this->_errors)) {
			return true;
		} else {
			return false;
		}
	}

	public function getPercents()
	{
		$out = array();
		for ($i = 10; $i <= 100; $i = $i + 10) {
//			if ($i > 10 && $this->_version == EnMask_Wp::VERSION_FREE) {
//				break;
//			}

			$out[$i] = $i;
		}

		return $out;
	}

	protected function deleteNotInList()
	{
		$id = array();

		foreach ($this->_keywords as $row) {
			if (!empty($row['keyword_id'])) {
				$id[] = intval($row['keyword_id']);
			}
		}

		if (!empty($id)) {
			EnMask_Wp_Model_Keywords::model()->deleteNotInList($id);
		}
	}
}