<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\SnoopyBehavioralScoring;

use Piwik;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Db;
use \Piwik\API\Request;
use \RecursiveArrayIterator;
use \RecursiveIteratorIterator;

/**
 * API for plugin SnoopyBehavioralScoring let's you integrate scooring into other applications.
 *
 * @method static \Piwik\Plugins\SnoopyBehavioralScoring\API getInstance()
 */
class API extends \Piwik\Plugin\API {

	public function getVisitorsScores() {
		$table = new DataTable();
		$max_ids = Db::fetchAll("SELECT MAX(id) as id FROM " . Common::prefixTable(\Piwik\Plugins\SnoopyBehavioralScoring\SnoopyBehavioralScoring::getTableName()) . " GROUP BY idvisitor");
		$max_ids_array = array();
		foreach ($max_ids as $value) {
			$max_ids_array[] = $value['id'];
		}
		$ids = implode(",", $max_ids_array);
		$visitor_scores = Db::fetchAll("SELECT * FROM " . Common::prefixTable(\Piwik\Plugins\SnoopyBehavioralScoring\SnoopyBehavioralScoring::getTableName()) . "
                                        WHERE id IN ($ids)");

		//Create data to be used in report
		$i = 0;
		foreach ($visitor_scores as $visitor) {
			$i++;
			$email = Request::processRequest('SnoopyBehavioralScoring.getVisitorEmail', array('idvisitor' => $visitor['idvisitor'], 'format' => 'json'));
			$status = Request::processRequest('SnoopyBehavioralScoring.heatStatus', array('idvisitor' => $visitor['idvisitor']));
			$email = json_decode($email, true);
			if (isset($email[0]['email'])) {
				$email = $email[0]['email'];
			} else {
				$email = '/';
			}
			switch ($status) {
			case 'cooling':
				$icon = 'icon-arrow-bottom';
				break;
			case 'heating':
				$icon = 'icon-arrow-top';
				break;
			case 'idle':
				$icon = '';
				break;
			case 'new':
				$icon = 'icon-plus';
				break;
			}
			$table->addRowFromArray(array(Row::COLUMNS => array(
				'label' => $i,
				'idvisitor' => $visitor['idvisitor'],
				'email' => $email,
				'status' => $status,
				'icon' => $icon,
				'score' => $visitor['score'],
			)));
		}

		$table->queueFilter("Sort", array('score', 'desc'));
		return $table;
	}

	public function getVisitorIdsToScore() {
		//$table = new DataTable();

		$settings = new \Piwik\Plugins\SnoopyBehavioralScoring\Settings();
		$matching_site = $settings->matching_site->getValue();
		$matching_goals = $settings->matching_goals->getValue();

		$matching_visitor_ids = Db::fetchAll("SELECT DISTINCT HEX(idvisitor) as 'idvisitor' FROM " . Common::prefixTable("log_conversion") . " WHERE idsite = ? AND idgoal IN(" . implode(",", $matching_goals) . ")", array($matching_site));

		$result = array();

		foreach ($matching_visitor_ids as $id) {
			$result[] = $id['idvisitor'];
		}

		return $result;
	}

	/**
	 * Returns scoore for specific visitor
	 *
	 * @param string | $idvisitor
	 * @return DataTable
	 */

	public function getScore($idvisitor) {
		$table = new DataTable();
		$visitor = Db::fetchRow("SELECT * FROM " . Common::prefixTable(\Piwik\Plugins\SnoopyBehavioralScoring\SnoopyBehavioralScoring::getTableName()) . "
                                        WHERE idvisitor = ?
                                        ORDER BY id DESC", array($idvisitor));
		$table->addRowFromArray(array(Row::COLUMNS => array(
			'idvisitor' => $visitor['idvisitor'],
			'score' => $visitor['score'],
		)));

		return $table;
	}

	/**
	 * Stores custom data for visitor
	 *
	 * $data param is array of variables to store, snoopy support 5 custom variables (custom_1, custom_2,... custom_5)
	 * $data variables form custom 1-4 are varchar, custom_5 is text.
	 *
	 * @param string | $idvisitor
	 * @param array | $data
	 *
	 * @return Boolean
	 */
	public function storeVisitorData($idvisitor, $data) {
		\Piwik\Piwik::checkUserHasSuperUserAccess();
		Db::query("	INSERT INTO " . Common::prefixTable("snoopy_visitors") . " (idvisitor, custom_1, custom_2, custom_3, custom_4, custom_5, created_at)
					VALUES(?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE
						custom_1=VALUES(custom_1),
						custom_2=VALUES(custom_2),
						custom_3=VALUES(custom_3),
						custom_4=CONCAT_WS('\n',custom_4,VALUES(custom_4)),
						custom_5=CONCAT_WS('\n',custom_5,VALUES(custom_5))",
			array($idvisitor,
				htmlspecialchars_decode($data['custom_1']),
				htmlspecialchars_decode($data['custom_2']),
				htmlspecialchars_decode($data['custom_3']),
				htmlspecialchars_decode($data['custom_4']),
				htmlspecialchars_decode($data['custom_5'])));
	}

	public function getVisitorEmail($idvisitor) {
		\Piwik\Piwik::checkUserHasSuperUserAccess();
		$table = new DataTable();
		$json_data = Db::fetchRow("	SELECT custom_5 FROM " . Common::prefixTable("snoopy_visitors") . "
									WHERE idvisitor = ?",
			array($idvisitor));
		$json_data = explode(PHP_EOL, $json_data['custom_5']);
		$result = [];
		if (!empty($json_data) && !empty($json_data[0])) {
			foreach ($json_data as $data) {
				$iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator(json_decode($data)));
				foreach ($iterator as $key => $value) {
					if (filter_var($value, FILTER_VALIDATE_EMAIL) && !in_array($value, $result)) {
						$result[] = $value;
					}
				}
			}
		}

		foreach ($result as $email) {
			$table->addRowFromArray(array(Row::COLUMNS => array(
				'email' => $email,
				'idvisitor' => $idvisitor,
			)));
		}

		return $table;
	}

	public function getVisitorEmailFromJson($json_data) {
		\Piwik\Piwik::checkUserHasSuperUserAccess();
		$table = new DataTable();

		$iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator(json_decode($json_data)));
		foreach ($iterator as $key => $value) {
			if (filter_var($value, FILTER_VALIDATE_EMAIL) && !in_array($value, $result)) {
				$result[] = $value;
			}
		}

		foreach ($result as $email) {
			$table->addRowFromArray(array(Row::COLUMNS => array(
				'email' => $email,
				'idvisitor' => $idvisitor,
			)));
		}

		return $table;
	}

	public function matchVisitorsEmail() {
		$table = new DataTable();
		\Piwik\Piwik::checkUserHasSuperUserAccess();
		$visitors = Db::fetchAll("	SELECT DISTINCT idvisitor FROM " . Common::prefixTable("snoopy_visitors"));

		//$visitors_to_score = \Piwik\API\Request::processRequest('Snoopy.getVisitorEmail', array('idvisitor' => $visitor));

		foreach ($visitors as $visitor) {
			$email = \Piwik\API\Request::processRequest('SnoopyBehavioralScoring.getVisitorEmail', array('idvisitor' => $visitor['idvisitor']));
			//var_dump($email->getRows());
			$table->addRowsFromArray($email->getRows());
		}

		return $table;
	}

	public function storeVisitorsEmail() {
		$table = new DataTable();
		$visitors = Db::fetchAll("	SELECT DISTINCT idvisitor FROM " . Common::prefixTable("snoopy_visitors") . " WHERE custom_1 IS NULL OR custom_1=''");

		foreach ($visitors as $visitor) {
			$visitor = \Piwik\API\Request::processRequest('SnoopyBehavioralScoring.getVisitorEmail', array('idvisitor' => $visitor['idvisitor'], 'format' => 'json'));
			$visitor = json_decode($visitor, true);
			$visitor = $visitor[0];

			if ($visitor) {
				Db::query("UPDATE " . Common::prefixTable("snoopy_visitors") . " SET custom_1=? WHERE idvisitor=?", array($visitor['email'], $visitor['idvisitor']));
			}

		}
		return null;
	}

	public function findHeatStatus($idvisitor) {
		$visitor_log = Db::fetchAll("SELECT * FROM " . Common::prefixTable("SnoopyBehavioralScoring") . " WHERE idvisitor = ? AND created_at >= NOW() - INTERVAL 2 DAY ORDER BY id DESC", array($idvisitor));
		if (!empty($visitor_log)) {
			$tmp = $visitor_log[0]['score'];

			if ($tmp == 0) {
				return "idle";
			}
			foreach ($visitor_log as $row) {
				if ($row['score'] > $tmp) {
					return "cooling";
				} elseif ($row['score'] < $tmp) {
					return "heating";
				}
			}

			return "new";
		}
	}

	public function storeHeatStatuses() {
		$scored_visitors = Db::fetchAll("SELECT DISTINCT idvisitor FROM " . Common::prefixTable("snoopy"));

		foreach ($scored_visitors as $visitor) {
			//var_dump($visitor['idvisitor']);
			$status = Request::processRequest("SnoopyBehavioralScoring.findHeatStatus", array('idvisitor' => $visitor['idvisitor']));
			Db::query("	INSERT INTO " . Common::prefixTable("snoopy_visitors_statuses") . " (idvisitor, status)
					VALUES(?, ?) ON DUPLICATE KEY UPDATE
						status=VALUES(status)",
				array($visitor['idvisitor'], $status));
		}
	}

	public function heatStatus($idvisitor) {
		$status = Db::fetchRow("SELECT status FROM " . Common::prefixTable("snoopy_visitors_statuses") . " WHERE idvisitor = ? ", array($idvisitor));
		if (!empty($status)) {
			return $status['status'];
		}
	}
}