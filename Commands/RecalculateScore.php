<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SnoopyBehavioralScoring\Commands;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\SnoopyBehavioralScoring\SnoopyBehavioralScoring;
use Piwik\Plugin\ConsoleCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This class lets you define a new command. To read more about commands have a look at our Piwik Console guide on
 * http://developer.piwik.org/guides/piwik-on-the-command-line
 *
 * As Piwik Console is based on the Symfony Console you might also want to have a look at
 * http://symfony.com/doc/current/components/console/index.html
 */
class RecalculateScore extends ConsoleCommand {
	/**
	 * This methods allows you to configure your command. Here you can define the name and description of your command
	 * as well as all options and arguments you expect when executing it.
	 */
	protected function configure() {
		$this->setName('snoopybehavioralscoring:recalculate-score');
		$this->setDescription('RecalculateScore');
		//$this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Your name:');
	}

	/**
	 * The actual task is defined in this method. Here you can access any option or argument that was defined on the
	 * command line via $input and write anything to the console via $output argument.
	 * In case anything went wrong during the execution you should throw an exception to make sure the user will get a
	 * useful error message and to make sure the command does not exit with the status code 0.
	 *
	 * Ideally, the actual command is quite short as it acts like a controller. It should only receive the input values,
	 * execute the task by calling a method of another class and output any useful information.
	 *
	 * Execute the command like: ./console snoopy:recalculate-score --name="The Piwik Team"
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		/**
		 * Settings for scooring
		 */
		$settings = new \Piwik\Plugins\SnoopyBehavioralScoring\Settings();

		/**
		 * Which site we scoore
		 */
		$matching_site = $settings->matching_site->getValue();

		/**
		 * Which goals mark our visitor to stat with tracking
		 */
		$matching_goals = $settings->matching_goals->getValue();

		/**
		 * Enable full debug (Additional infor are displayed like cooling and adding)
		 */
		$full_debug = $settings->full_console_debug->getValue();

		/**
		 * Cooling factor that tells how fast the visitor will coole down when no action made
		 */
		$cooling_factor = $settings->cooling_factor->getValue();

		/**
		 * How much specific type of referer is worth
		 */
		$campaign_entry = $settings->campaign_entry->getValue();

		/**
		 * How much specific url is worth
		 */
		$special_urls = array();
		$special_urls_raw = $settings->special_urls->getValue();
		$special_urls_raw = explode("\n", $special_urls_raw);
		foreach ($special_urls_raw as $url_info) {
			$special_url = explode(';', $url_info);
			if (!array_key_exists($special_url[0], $special_urls) && sizeof($special_url) == 2) {
				$special_urls[$special_url[0]] = (float) trim($special_url[1]);
			}
		}

		$output->writeln("<info>************************************************************************</info>");
		$output->writeln("<info>Starting visitor scoring...</info>");
		$output->writeln("<comment>Getting visitors to score...</comment>");
		$visitors_to_score = \Piwik\API\Request::processRequest('SnoopyBehavioralScoring.getVisitorIdsToScore', array());

		$previously_scored = Db::fetchAll(" SELECT DISTINCT idvisitor
                                            FROM " . Common::prefixTable(SnoopyBehavioralScoring::getTableName()) . "
                                            WHERE idvisitor IN('" . implode("','", $visitors_to_score) . "')
                                            ORDER BY id DESC");

		/**
		SCORE ALREADY SCORED VISITORS
		 **/
		//First score already visitors
		$output->writeln(sprintf("<comment>Scoring already scored visitors (%s)...</comment>", count($previously_scored)));
		foreach ($previously_scored as $scored_visitor) {
			if (($key = array_search($scored_visitor['idvisitor'], $visitors_to_score)) !== false) {
				unset($visitors_to_score[$key]);
			}
			$idvisitor = $scored_visitor['idvisitor'];
			$scored_visitor = Db::fetchRow("SELECT *
                                            FROM " . Common::prefixTable(SnoopyBehavioralScoring::getTableName()) . "
                                            WHERE idvisitor = ?
                                            ORDER BY id DESC
                                            LIMIT 1", array($scored_visitor['idvisitor']));

			$output->writeln(sprintf("<info>Scoring visitor: %s</info>", $idvisitor));
			if ($full_debug) {
				$output->writeln(sprintf("<comment>Curent score: %s</comment>", $scored_visitor['score']));
			}
			$visits = Db::fetchAll("SELECT idvisit, visit_first_action_time, visit_last_action_time, referer_type
                                    FROM " . Common::prefixTable("log_visit") . "
                                    WHERE HEX(idvisitor) = ?
                                    AND idsite = ?
                                    AND visit_last_action_time > ?", array($idvisitor, $matching_site, $scored_visitor['created_at']));

			//$output->writeln(print_r($visits, true));
			$output->writeln(sprintf("<comment>Number of visits: %s</comment>", count($visits)));
			$visitor_score = $scored_visitor['score'];
			$last_date = null;
			$campaigns = array();
			$total_goals = 0;
			foreach ($visits as $visit) {
				$tmp_score = 0;
				$goals = Db::fetchRow("SELECT  COUNT(*) AS count
								FROM " . Common::prefixTable("log_conversion") . "
								WHERE idsite = ?
								AND idgoal IN(" . implode(",", $matching_goals) . ")
								AND HEX(idvisitor) = ?
								AND server_time >= ? AND server_time <= ?", array($matching_site, $idvisitor, $visit['visit_first_action_time'], $visit['visit_last_action_time']));

				$total_goals += $goals['count'];
				$tmp_score += $goals['count'] * 5;
				if ($full_debug) {
					$output->writeln(sprintf("<comment>\tGoals: %s</comment>", $goals['count']));
					$output->writeln(sprintf("<comment>\t\tFirst action: %s</comment>", $visit['visit_first_action_time']));
					$output->writeln(sprintf("<comment>\t\tLast action: %s</comment>", $visit['visit_last_action_time']));
				}

				$visit_score = array();

				if ($full_debug) {
					$output->writeln(sprintf("<comment>\tScoring visitid: %s</comment>", $visit['idvisit']));
				}

				/**
				 * If visitor came from email campaign we add adittional campaign entry
				 */
				if ($visit['referer_type'] == 6) {
					if ($full_debug) {
						$output->writeln("<comment>\t\tAdding campaign entry bonus</comment>");
					}
					if (!array_key_exists($visit['referer_name'], $campaigns)) {
						$tmp_score += $campaign_entry;
						$campaigns[$visit['referer_name']] = $visit['referer_name'];
					}
				}

				$visit_actions = Db::fetchAll(" SELECT idvisit, idaction_url, name, type, url_prefix, server_time
                                                FROM " . Common::prefixTable("log_link_visit_action") . " AS lva
                                                LEFT JOIN " . Common::prefixTable("log_action") . " AS la
                                                ON lva.idaction_url = la.idaction
                                                WHERE HEX(idvisitor) = ?
                                                AND idsite = ?
                                                AND idvisit = ?", array($idvisitor, $matching_site, $visit['idvisit']));

				foreach ($visit_actions as $action) {
					$full_url = $action['name'];

					switch ($action['url_prefix']) {
					case '0':
						$full_url = 'http://' . $action['name'];
						break;
					case '1':
						$full_url = 'http://www.' . $action['name'];
						break;
					case '2':
						$full_url = 'https://' . $action['name'];
						break;
					case '3':
						$full_url = 'https://www.' . $action['name'];
						break;
					}

					if (!array_key_exists($action['idaction_url'], $visit_score) && $action['server_time'] > $scored_visitor['created_at']) {
						//TODO Duplicate worth less, some links may be worth more
						$visit_score[$action['idaction_url']] = 1;
						if (array_key_exists($full_url, $special_urls)) {
							$tmp_score += $special_urls[$full_url];
						} else {
							$tmp_score++;
						}
					}
				}

				if ($last_date === null) {
					$visitor_score += $tmp_score;
				} else {
					$timeDiff = $last_date->diff(new \DateTime($visit['visit_first_action_time']));
					if ($full_debug) {
						$output->writeln(sprintf("<comment>\t\tDate difference: %s</comment>", abs($timeDiff->format("%a"))));
						$output->writeln(sprintf("<comment>\t\tScore before: %s</comment>", $visitor_score));
					}
					$visitor_score = $this->calculateCooling($visitor_score, 1, $cooling_factor, abs($timeDiff->format("%a")));
					if ($full_debug) {
						$output->writeln(sprintf("<comment>\t\tScore after calculating cooling: %s</comment>", $visitor_score));
						$output->writeln(sprintf("<comment>\t\tScore add: %s</comment>", $tmp_score));
					}
					$visitor_score += (float) $tmp_score;
					if ($full_debug) {
						$output->writeln(sprintf("<comment>\t\tScore after: %s</comment>", $visitor_score));
					}
				}
				$last_date = new \DateTime($visit['visit_last_action_time']);
			}

			//Calculate cooling from last visit to now
			if (count($visits) > 0) {
				$sinceLastVisit = $last_date->diff(new \DateTime("now"));
				if ($full_debug) {
					$output->writeln(sprintf("<comment>\t\tDays since last visit difference: %s</comment>", abs($sinceLastVisit->format("%a"))));
				}
				$visitor_score = $this->calculateCooling($visitor_score, 1, $cooling_factor, abs($sinceLastVisit->format("%a")));
			} elseif ($scored_visitor['score'] > 0) {
				$last_visit = Db::fetchRow("SELECT idvisit, visit_first_action_time, visit_last_action_time
                                            FROM " . Common::prefixTable("log_visit") . "
                                            WHERE HEX(idvisitor) = ?
                                            AND idsite = ?
                                            ORDER BY idvisit DESC
                                            LIMIT 1", array($idvisitor, $matching_site));
				$last_date = new \DateTime($last_visit['visit_last_action_time']);
				$last_calculated = new \DateTime($scored_visitor['created_at']);

				$diff_since_last_visit = $last_date->diff($last_calculated);
				$diff_since_last_visit2 = $last_date->diff(new \DateTime("now"));

				$time_to_calculate = abs($diff_since_last_visit->format("%a")) != abs($diff_since_last_visit2->format("%a")); // != abs($last_date->diff(new \DateTime("now")->format("%a")));

				if ($time_to_calculate) {
					$visitor_score -= $this->calculateCoolingNoScore(0, 1, $cooling_factor, abs($diff_since_last_visit2->format("%a"))) - $this->calculateCoolingNoScore(0, 1, $cooling_factor, abs($diff_since_last_visit->format("%a")));
				}
			}

			if ($visitor_score < 0) {
				$visitor_score = 0;
			}

			Db::query(" INSERT INTO " . Common::prefixTable(SnoopyBehavioralScoring::getTableName()) . " (idvisitor, score, created_at) VALUES(?,?,?)", array($idvisitor, $visitor_score, date("Y-m-d H:i:s")));

			$output->writeln(sprintf('<error>Total goals: %s</error>', $total_goals));
			$output->writeln(sprintf('<error>Visitor score: %s</error>', $visitor_score));
		}

		/**
		SCORE NEW VISITORS
		 **/
		$output->writeln(sprintf("<comment>Scoring new visitors (%s)...</comment>", count($visitors_to_score)));
		foreach ($visitors_to_score as $idvisitor) {
			$output->writeln(sprintf("<info>Scoring visitor: %s</info>", $idvisitor));

			$visits = Db::fetchAll("SELECT idvisit, visit_first_action_time, visit_last_action_time, referer_type,referer_name
                                    FROM " . Common::prefixTable("log_visit") . "
                                    WHERE HEX(idvisitor) = ?
                                    AND idsite = ?", array($idvisitor, $matching_site));

			//$output->writeln(print_r($visits, true));
			$output->writeln(sprintf("<comment>Number of visits: %s</comment>", count($visits)));
			$visitor_score = 0;
			$last_date = null;
			$campaigns = array();
			$total_goals = 0;
			foreach ($visits as $visit) {
				$visit_score = array();
				$tmp_score = 0;

				$goals = Db::fetchRow("SELECT  COUNT(*) AS count
								FROM " . Common::prefixTable("log_conversion") . "
								WHERE idsite = ?
								AND idgoal IN(" . implode(",", $matching_goals) . ")
								AND HEX(idvisitor) = ?
								AND server_time >= ? AND server_time <= ?", array($matching_site, $idvisitor, $visit['visit_first_action_time'], $visit['visit_last_action_time']));
				$total_goals += $goals['count'];

				$tmp_score += $goals['count'] * 5;
				if ($full_debug) {
					$output->writeln(sprintf("<comment>\tGoals: %s</comment>", $goals['count']));
					$output->writeln(sprintf("<comment>\t\tFirst action: %s</comment>", $visit['visit_first_action_time']));
					$output->writeln(sprintf("<comment>\t\tLast action: %s</comment>", $visit['visit_last_action_time']));
				}

				if ($visit['referer_type'] == 6) {
					if ($full_debug) {
						$output->writeln("<comment>\tAdding campaign entry bonus</comment>");
					}
					if (!array_key_exists($visit['referer_name'], $campaigns)) {
						$tmp_score += $campaign_entry;
						$campaigns[$visit['referer_name']] = $visit['referer_name'];
					}
				}

				if ($full_debug) {
					$output->writeln(sprintf("<comment>\tScoring visitid: %s</comment>", $visit['idvisit']));
				}
				$visit_actions = Db::fetchAll(" SELECT idvisit, idaction_url, name, type, url_prefix
                                                FROM " . Common::prefixTable("log_link_visit_action") . " AS lva
                                                LEFT JOIN " . Common::prefixTable("log_action") . " AS la
                                                ON lva.idaction_url = la.idaction
                                                WHERE HEX(idvisitor) = ?
                                                AND idsite = ?
                                                AND idvisit = ?", array($idvisitor, $matching_site, $visit['idvisit']));

				foreach ($visit_actions as $action) {
					$full_url = $action['name'];

					switch ($action['url_prefix']) {
					case '0':
						$full_url = 'http://' . $action['name'];
						break;
					case '1':
						$full_url = 'http://www.' . $action['name'];
						break;
					case '2':
						$full_url = 'https://' . $action['name'];
						break;
					case '3':
						$full_url = 'https://www.' . $action['name'];
						break;
					}

					if (!array_key_exists($action['idaction_url'], $visit_score)) {
						$visit_score[$action['idaction_url']] = 1;
						if (array_key_exists($full_url, $special_urls)) {
							$tmp_score += $special_urls[$full_url];
						} else {
							$tmp_score++;
						}
					}
				}

				if ($last_date === null) {
					$visitor_score += $tmp_score;
				} else {
					$timeDiff = $last_date->diff(new \DateTime($visit['visit_first_action_time']));
					if ($full_debug) {
						$output->writeln(sprintf("<comment>\t\tDate difference: %s</comment>", abs($timeDiff->format("%a"))));
						$output->writeln(sprintf("<comment>\t\tScore before: %s</comment>", $visitor_score));
					}
					$visitor_score = $this->calculateCooling($visitor_score, 1, $cooling_factor, abs($timeDiff->format("%a")));
					if ($full_debug) {
						$output->writeln(sprintf("<comment>\t\tScore after calculating cooling: %s</comment>", $visitor_score));
						$output->writeln(sprintf("<comment>\t\tScore add: %s</comment>", $tmp_score));
					}
					$visitor_score += (float) $tmp_score;
					if ($full_debug) {
						$output->writeln(sprintf("<comment>\t\tScore after: %s</comment>", $visitor_score));
					}
				}
				$last_date = new \DateTime($visit['visit_last_action_time']);
			}

			//Calculate cooling from last visit to now
			$sinceLastVisit = $last_date->diff(new \DateTime("now"));
			if ($full_debug) {
				$output->writeln(sprintf("<comment>Days since last visit: %s</comment>", abs($sinceLastVisit->format("%a"))));
			}
			$visitor_score = $this->calculateCooling($visitor_score, 1, $cooling_factor, abs($sinceLastVisit->format("%a")));

			if ($visitor_score < 0) {
				$visitor_score = 0;
			}

			Db::query("INSERT INTO " . Common::prefixTable(SnoopyBehavioralScoring::getTableName()) . " (idvisitor, score, created_at) VALUES(?,?,?)", array($idvisitor, $visitor_score, date("Y-m-d H:i:s")));
			$output->writeln(sprintf('<error>Total goals: %s</error>', $total_goals));
			$output->writeln(sprintf('<error>Visitor score: %s</error>', $visitor_score));
		}
		\Piwik\API\Request::processRequest('SnoopyBehavioralScoring.storeVisitorsEmail', array());
		\Piwik\API\Request::processRequest('SnoopyBehavioralScoring.storeHeatStatuses', array());
	}

	private function calculateCooling($currentScore, $cool, $coolingFactor, $numberOfDays) {
		if ($currentScore <= 0) {
			return 0;
		}
		if ($numberOfDays == 0) {
			return $currentScore;
		}
		$currentScore -= $cool;
		$cool *= $coolingFactor;
		$numberOfDays -= 1;
		return $this->calculateCooling($currentScore, $cool, $coolingFactor, $numberOfDays);
	}

	private function calculateCoolingNoScore($coolingMinus, $cool, $coolingFactor, $numberOfDays) {
		if ($numberOfDays == 0) {
			return $coolingMinus;
		}

		$coolingMinus += $cool;
		$cool *= $coolingFactor;
		$numberOfDays -= 1;
		return $this->calculateCoolingNoScore($coolingMinus, $cool, $coolingFactor, $numberOfDays);
	}
}
