<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SnoopyBehavioralScoring\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;
use Piwik\View;

/**
 * This class defines a new report.
 *
 * See {@link http://developer.piwik.org/api-reference/Piwik/Plugin/Report} for more information.
 */
class GetVisitorsScores extends Base {
	protected function init() {
		parent::init();

		$this->name = Piwik::translate('SnoopyBehavioralScoring_VisitorsScores');
		$this->dimension = '';
		$this->documentation = Piwik::translate('');

		// This defines in which order your report appears in the mobile app, in the menu and in the list of widgets
		$this->order = 1;

		// By default standard metrics are defined but you can customize them by defining an array of metric names
		//$this->metrics       = array('timestamp','actionId','date','url','visitorid');

		$this->metrics = array('score');
		// Uncomment the next line if your report does not contain any processed metrics, otherwise default
		// processed metrics will be assigned
		$this->processedMetrics = array();

		// Uncomment the next line if your report defines goal metrics
		// $this->hasGoalMetrics = true;

		// Uncomment the next line if your report should be able to load subtables. You can define any action here
		// $this->actionToLoadSubTables = $this->action;

		// Uncomment the next line if your report always returns a constant count of rows, for instance always
		// 24 rows for 1-24hours
		// $this->constantRowsCount = true;

		// If a menu title is specified, the report will be displayed in the menu
		// $this->menuTitle    = 'Snoopy_Usersscores';
		$this->menuTitle = 'Scores';

		// If a widget title is specified, the report will be displayed in the list of widgets and the report can be
		// exported as a widget
		// $this->widgetTitle  = 'Snoopy_Usersscores';
	}

	/**
	 * Here you can configure how your report should be displayed. For instance whether your report supports a search
	 * etc. You can also change the default request config. For instance change how many rows are displayed by default.
	 *
	 * @param ViewDataTable $view
	 */
	public function configureView(ViewDataTable $view) {
		if (!empty($this->dimension)) {
			$view->config->addTranslations(array('label' => $this->dimension->getName()));
		}
		// $view->config->show_search = false;
		$view->requestConfig->filter_sort_column = 'score';
		//$view->config->show_limit_control = false;
		//$view->config->show_search = false;
		$view->config->show_footer = false;
		$view->config->self_url = true;
		$view->requestConfig->filter_limit = 500;

		$view->config->columns_to_display = array_merge(array('label'), $this->metrics);
	}

	/**
	 * Here you can define related reports that will be shown below the reports. Just return an array of related
	 * report instances if there are any.
	 *
	 * @return \Piwik\Plugin\Report[]
	 */
	public function getRelatedReports() {
		return array(); // eg return array(new XyzReport());
	}

	/**
	 * A report is usually completely automatically rendered for you but you can render the report completely
	 * customized if you wish. Just overwrite the method and make sure to return a string containing the content of the
	 * report. Don't forget to create the defined twig template within the templates folder of your plugin in order to
	 * make it work. Usually you should NOT have to overwrite this render method.
	 *
	 * @return string */
	public function render() {
		$view = new View('@SnoopyBehavioralScoring/getVisitorsScores');
		/*var_dump($view);
		die;*/
		$visitors = \Piwik\API\Request::processRequest('SnoopyBehavioralScoring.getVisitorsScores', array('format' => 'json'));
		$view->myData = json_decode($visitors); //array( 'label' => "label");

		$settings = new \Piwik\Plugins\SnoopyBehavioralScoring\Settings();
		$idSite = $settings->matching_site->getValue();
		$view->idSite = $idSite;

		return $view->render();
	}

	/**
	 * By default your report is available to all users having at least view access. If you do not want this, you can
	 * limit the audience by overwriting this method.
	 *
	 * @return bool
	 */
	public function isEnabled() {
		return Piwik::hasUserSuperUserAccess();
	}

}
