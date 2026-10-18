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
	 * Seconds a connection can be unused, before connection() checks it is still alive
	 *
	 * The DB server closes idle connections after wait_timeout (300s on our Galera cluster), eg. while a long running
	 * job (uploading a big file) uses no DB. Unlike Api\Db::query() a PDO statement can not be repeated transparent
	 * on a new connection, so we have to check before a statement is prepared.
	 */
	const PING_AFTER = 30;

	/**
	 * @var float microtime of last connection() call
	 */
	static protected $last_used = 0.0;

	/**
	 * Get active PDO connection
	 *
	 * A connection not used for PING_AFTER seconds is checked and replaced by a new one, if the server closed it.
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
		elseif (microtime(true) - self::$last_used > self::PING_AFTER)
		{
			try {
				self::$pdo->query('SELECT 1')->closeCursor();
			}
			catch (\PDOException $e) {
				if (!self::connectionLost($e)) throw $e;
				self::reconnect();
			}
		}
		self::$last_used = microtime(true);

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
	 * Check if an exception was caused by the server closing our connection
	 *
	 * Eg. as it was idle longer than wait_timeout, while a long running job (eg. uploading a big file) was running,
	 * or because the server was restarted. Reconnecting and repeating is the right reaction, unlike for other errors.
	 *
	 * @param \Throwable $e
	 * @return bool true: "MySQL server has gone away" or "Lost connection to MySQL server during query"
	 */
	static public function connectionLost(\Throwable $e) : bool
	{
		$code = $e instanceof \PDOException && isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : (int)$e->getCode();

		return in_array($code, [2006, 2013], true) ||
			stripos($e->getMessage(), 'server has gone away') !== false ||
			stripos($e->getMessage(), 'Lost connection to') !== false;
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
			if ($e->getCode() == 2002)
			{
				unset($e);
				$dsn = preg_replace('/host=([^;]+);/', 'host='.$egw_db->get_host(true).';', $dsn, 1);
				try {
					self::$pdo = new \PDO($dsn,$egw_db->User,$egw_db->Password,array(
						\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,
					));
				}
				catch(\PDOException $e)
				{
					// handled below
				}
			}
			if (isset($e)) {
				unset($e);
				// Exception reveals password, so we ignore the exception and connect again without pw, to get the right exception without pw
				self::$pdo = new \PDO($dsn, $egw_db->User, '$egw_db->Password');
			}
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