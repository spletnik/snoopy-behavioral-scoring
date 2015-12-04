<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SnoopyBehavioralScoring;

use Piwik\Settings\SystemSetting;
use Piwik\Settings\UserSetting;

/**
 * Defines Settings for SnoopyBehavioralScoring.
 *
 * Usage like this:
 * $settings = new Settings('SnoopyBehavioralScoring');
 * $settings->autoRefresh->getValue();
 * $settings->metric->getValue();
 */
class Settings extends \Piwik\Plugin\Settings {
	/** @var SystemSetting */
	public $matching_goals;

	/** @var SystemSetting */
	public $matching_site;

	protected function init() {
		$this->setIntroduction('Setup Snoopy behavioral scoring to match your needs.');

		$this->createMatchingSiteSetting();
		$this->createStartTrackingSetting();
		$this->createCoolingFactorSetting();
		$this->createEnableFullConsoleDebugSetting();
		$this->createCampaignEntrySetting();
		$this->createSpecialUrlsSetting();
	}
	/**
	 * Example functions
	 *
	 *
	private function createAutoRefreshSetting()
	{
	$this->autoRefresh        = new UserSetting('autoRefresh', 'Auto refresh');
	$this->autoRefresh->type  = static::TYPE_BOOL;
	$this->autoRefresh->uiControlType = static::CONTROL_CHECKBOX;
	$this->autoRefresh->description   = 'If enabled, the value will be automatically refreshed depending on the specified interval';
	$this->autoRefresh->defaultValue  = false;

	$this->addSetting($this->autoRefresh);
	}

	private function createRefreshIntervalSetting()
	{
	$this->refreshInterval        = new UserSetting('refreshInterval', 'Refresh Interval');
	$this->refreshInterval->type  = static::TYPE_INT;
	$this->refreshInterval->uiControlType = static::CONTROL_TEXT;
	$this->refreshInterval->uiControlAttributes = array('size' => 3);
	$this->refreshInterval->description     = 'Defines how often the value should be updated';
	$this->refreshInterval->inlineHelp      = 'Enter a number which is >= 15';
	$this->refreshInterval->defaultValue    = '30';
	$this->refreshInterval->validate = function ($value, $setting) {
	if ($value < 15) {
	throw new \Exception('Value is invalid');
	}
	};

	$this->addSetting($this->refreshInterval);
	}

	private function createColorSetting()
	{
	$this->color        = new UserSetting('color', 'Color');
	$this->color->uiControlType = static::CONTROL_RADIO;
	$this->color->description   = 'Pick your favourite color';
	$this->color->availableValues  = array('red' => 'Red', 'blue' => 'Blue', 'green' => 'Green');

	$this->addSetting($this->color);
	}

	private function createMetricSetting()
	{
	$this->metric        = new SystemSetting('metric', 'Metric to display');
	$this->metric->type  = static::TYPE_STRING;
	$this->metric->uiControlType = static::CONTROL_SINGLE_SELECT;
	$this->metric->availableValues  = array('nb_visits' => 'Visits', 'nb_actions' => 'Actions', 'visitors' => 'Visitors');
	$this->metric->introduction  = 'Only Super Users can change the following settings:';
	$this->metric->description   = 'Choose the metric that should be displayed in the browser tab';
	$this->metric->defaultValue  = 'nb_visits';
	$this->metric->readableByCurrentUser = true;

	$this->addSetting($this->metric);
	}

	private function createBrowsersSetting()
	{
	$this->browsers        = new SystemSetting('browsers', 'Supported Browsers');
	$this->browsers->type  = static::TYPE_ARRAY;
	$this->browsers->uiControlType = static::CONTROL_MULTI_SELECT;
	$this->browsers->availableValues  = array('firefox' => 'Firefox', 'chromium' => 'Chromium', 'safari' => 'safari');
	$this->browsers->description   = 'The value will be only displayed in the following browsers';
	$this->browsers->defaultValue  = array('firefox', 'chromium', 'safari');
	$this->browsers->readableByCurrentUser = true;

	$this->addSetting($this->browsers);
	}

	private function createDescriptionSetting()
	{
	$this->description = new SystemSetting('description', 'Description for value');
	$this->description->readableByCurrentUser = true;
	$this->description->uiControlType = static::CONTROL_TEXTAREA;
	$this->description->description   = 'This description will be displayed next to the value';
	$this->description->defaultValue  = "This is the value: \nAnother line";

	$this->addSetting($this->description);
	}

	private function createPasswordSetting()
	{
	$this->password = new SystemSetting('password', 'API password');
	$this->password->readableByCurrentUser = true;
	$this->password->uiControlType = static::CONTROL_PASSWORD;
	$this->password->description   = 'Password for the 3rd API where we fetch the value';
	$this->password->transform     = function ($value) {
	return sha1($value . 'salt');
	};

	$this->addSetting($this->password);
	}
	 */

	private function createMatchingSiteSetting() {
		$sites = \Piwik\API\Request::processRequest('SitesManager.getAllSites');

		$options = array();

		foreach ($sites as $site) {
			$options[$site['idsite']] = $site['name'];
		}

		$this->matching_site = new SystemSetting('matching_site', 'Sites');
		$this->matching_site->type = static::TYPE_STRING;
		$this->matching_site->uiControlType = static::CONTROL_SINGLE_SELECT;
		$this->matching_site->availableValues = $options;
		$this->matching_site->description = 'Choose for which site you want scoring to work. (Currently only one site is supported)';
		$this->matching_site->defaultValue = 'nb_visits';
		$this->matching_site->readableByCurrentUser = true;

		$this->addSetting($this->matching_site);
	}

	private function createStartTrackingSetting() {
		$goals = \Piwik\API\Request::processRequest('Goals.getGoals', array(
			'idSite' => $this->matching_site->getValue(),
		));

		$options = array();

		foreach ($goals as $goal) {
			$options[$goal['idgoal']] = $goal['name'];
		}

		$this->matching_goals = new SystemSetting('matching_goals', 'Goals to start scoring');
		$this->matching_goals->readableByCurrentUser = true;
		$this->matching_goals->type = static::TYPE_ARRAY;
		$this->matching_goals->uiControlType = static::CONTROL_MULTI_SELECT;
		$this->matching_goals->description = 'Goals that match our visitor.';
		$this->matching_goals->availableValues = array_merge(array('0' => 'None'), $options);

		$this->addSetting($this->matching_goals);
	}

	private function createEnableFullConsoleDebugSetting() {
		$this->full_console_debug = new SystemSetting('full_console_debug', 'Enable console full debug');
		$this->full_console_debug->type = static::TYPE_BOOL;
		$this->full_console_debug->uiControlType = static::CONTROL_CHECKBOX;
		$this->full_console_debug->description = 'If enabled, when calculating score in console (cronjob) additional info will be displayed.';
		$this->full_console_debug->defaultValue = false;

		$this->addSetting($this->full_console_debug);
	}

	private function createCoolingFactorSetting() {
		$this->cooling_factor = new SystemSetting('cooling_factor', 'Cooling factor');
		$this->cooling_factor->type = static::TYPE_FLOAT;
		$this->cooling_factor->uiControlType = static::CONTROL_TEXT;
		$this->cooling_factor->description = 'Set the cooling factor for cooling visitors which are not active.';
		$this->cooling_factor->defaultValue = 1.1;

		$this->addSetting($this->cooling_factor);
	}

	private function createCampaignEntrySetting() {
		$this->campaign_entry = new SystemSetting('campaign_entry', 'Campaign entry score bonus');
		$this->campaign_entry->type = static::TYPE_FLOAT;
		$this->campaign_entry->uiControlType = static::CONTROL_TEXT;
		$this->campaign_entry->description = 'How much is entry from campaign worth (e.g. newsletter)';
		$this->campaign_entry->defaultValue = 5;

		$this->addSetting($this->campaign_entry);
	}

	private function createSpecialUrlsSetting() {
		$this->special_urls = new SystemSetting('special_urls', 'Special urls');
		$this->special_urls->readableByCurrentUser = true;
		$this->special_urls->uiControlType = static::CONTROL_TEXTAREA;
		$this->special_urls->description = 'Special urls are listed one in each line. Score is provided at the end separated by semicolon (e.g. http://example.com;3)';
		$this->special_urls->defaultValue = "http://example.com;3";

		$this->addSetting($this->special_urls);
	}

}
