<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Snoopy;

use Piwik\Common;
use Piwik\Db;
use \Exception;

class Snoopy extends \Piwik\Plugin {
	private static $table_name = "snoopy";

	public function install() {
		try {
			$sql = "CREATE TABLE IF NOT EXISTS " . Common::prefixTable("snoopy") . " (
                        id int(11) NOT NULL AUTO_INCREMENT,
                        idvisitor varchar(45) DEFAULT NULL,
                        score int(11) DEFAULT NULL,
                        updated_at timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        created_at datetime DEFAULT NULL,
                        PRIMARY KEY (id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 ";
			Db::exec($sql);

			$sql = "CREATE TABLE IF NOT EXISTS " . Common::prefixTable("snoopy_visitors") . " (
                        id int(11) NOT NULL AUTO_INCREMENT,
                        idvisitor varchar(45) DEFAULT NULL,
                        custom_1 varchar(255) DEFAULT NULL,
                        custom_2 varchar(255) DEFAULT NULL,
                        custom_3 varchar(255) DEFAULT NULL,
                        custom_4 varchar(255) DEFAULT NULL,
                        custom_5 TEXT DEFAULT NULL,
                        updated_at timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        created_at datetime DEFAULT NULL,
                        PRIMARY KEY (id),
                        UNIQUE UNIQUE (idvisitor)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 ";
			Db::exec($sql);

		} catch (Exception $e) {
			// ignore error if table already exists (1050 code is for 'table already exists')
			if (!Db::get()->isErrNo($e, '1050')) {
				throw $e;
			}
		}
	}

	public function uninstall() {
		Db::dropTables(Common::prefixTable("snoopy"));
		Db::dropTables(Common::prefixTable("snoopy_visitors"));
	}

	public function activate() {
		$this->install();
	}

	static function getTableName() {
		return self::$table_name;
	}

	public function getStylesheetFiles(&$files) {
		$files[] = "plugins/Snoopy/stylesheets/style.less";
		$files[] = "plugins/Snoopy/stylesheets/style.css";
	}

	public function registerEvents() {
		return array(
			'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
		);
	}
}
