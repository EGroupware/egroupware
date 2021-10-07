<?php
/**
 * EGroupware API: PDO database connection
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage db
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\Db;

/**
 * PDO database connection
 */
class Pdo
{
	/**
	 * Reference to the PDO object we use
	 *
	 * @var \PDO
	 */
	static protected $pdo;

	/**
	 * PDO database type: mysql, pgsl
	 *
	 * @var string
	 */
	public static $pdo_type;

	/**
	 * Case sensitive comparison operator, for mysql we use ' COLLATE utf8_bin ='
	 *
	 * @var string
	 */
	public static $case_sensitive_equal = '=';

	/**
	 * Get active PDO connection
	 *
	 * @return \PDO
	 * @throws \PDOException when opening PDO connection fails
	 * @throws Exception when opening regular db-connection fails
	 */
	static public function connection()
	{
		if (!isset(self::$pdo))
		{
			self::reconnect();
		}
		return self::$pdo;
	}

	/**
	 * Reconnect to database
	 */
	static public function reconnect()
	{
		self::$pdo = self::_pdo();
	}

	/**
	 * Create pdo object / connection, as long as pdo is not generally used in eGW
	 *
	 * @return \PDO
	 */
	static protected function _pdo()
	{
		$egw_db = isset($GLOBALS['egw_setup']) ? $GLOBALS['egw_setup']->db : $GLOBALS['egw']->db;

		switch($egw_db->Type)
		{
			case 'mysqli':
			case 'mysqlt':
			case 'mysql':
				self::$case_sensitive_equal = '= BINARY ';
				self::$pdo_type = 'mysql';
				break;
			default:
				self::$pdo_type = $egw_db->Type;
				break;
		}
		// get host used be egw_db
		$egw_db->connect();
		$host = $egw_db->get_host();

		$dsn = self::$pdo_type.':dbname='.$egw_db->Database.($host ? ';host='.$host.($egw_db->Port ? ';port='.$egw_db->Port : '') : '');
		// check once if pdo extension and DB specific driver is loaded or can be loaded
		static $pdo_available=null;
		if (is_null($pdo_available))
		{
			foreach(array('pdo','pdo_'.self::$pdo_type) as $ext)
			{
				check_load_extension($ext,true);	// true = throw Exception
			}
			$pdo_available = true;
		}
		// set client charset of the connection
		switch(self::$pdo_type)
		{
			case 'mysql':
				$dsn .= ';charset=utf8';
				// switch off MySQL 5.7+ ONLY_FULL_GROUP_BY sql_mode
				if ((float)$egw_db->ServerInfo['version'] >= 5.7 && (float)$egw_db->ServerInfo['version'] < 10.0)
				{
					$query = "SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))";
				}
				break;
			case 'pgsql':
				$query = "SET NAMES 'utf-8'";
				break;
		}
		try {
			self::$pdo = new \PDO($dsn,$egw_db->User,$egw_db->Password,array(
				\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,
			));
		}
		catch(\PDOException $e)
		{
			unset($e);
			// Exception reveals password, so we ignore the exception and connect again without pw, to get the right exception without pw
			self::$pdo = new \PDO($dsn,$egw_db->User,'$egw_db->Password');
		}
		if (!empty($query))
		{
			self::$pdo->exec($query);
		}
		return self::$pdo;
	}

	/**
	 * Just a little abstration 'til I know how to organise stuff like that with PDO
	 *
	 * @param mixed $time
	 * @return string Y-m-d H:i:s
	 */
	static public function _pdo_timestamp($time)
	{
		if (is_numeric($time))
		{
			$time = date('Y-m-d H:i:s',$time);
		}
		return $time;
	}

	/**
	 * Just a little abstration 'til I know how to organise stuff like that with PDO
	 *
	 * @param boolean $val
	 * @return string '1' or '0' for mysql, 'true' or 'false' for everyone else
	 */
	static public function _pdo_boolean($val)
	{
		if (self::$pdo_type == 'mysql')
		{
			return $val ? '1' : '0';
		}
		return $val ? 'true' : 'false';
	}
}
