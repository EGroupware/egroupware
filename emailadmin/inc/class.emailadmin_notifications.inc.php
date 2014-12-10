<?php
/**
 * EGroupware EMailAdmin: Mail account folders to notify user about arriving mail
 *
 * @link http://www.stylite.de
 * @package emailadmin
 * @author Ralf Becker <rb@stylite.de>
 * @author Stylite AG <info@stylite.de>
 * @copyright (c) 2014 by Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Mail account folders to notify user about arriving mail
 */
class emailadmin_notifications
{
	const APP = 'emailadmin';
	const TABLE = 'egw_ea_notifications';

	/**
	 * Reference to global db object
	 *
	 * @var egw_db
	 */
	static protected $db;

	/**
	 * Cache for credentials to minimize database access
	 *
	 * @var array
	 */
	protected static $cache = array();

	/**
	 * Read credentials for a given mail account
	 *
	 * @param int $acc_id
	 * @param int|array $account_id=null default use current user or all (in that order)
	 * @param boolean $return_empty_marker=false should we return null
	 * @return array with values for "notify_folders", "notify_use_default"
	 */
	public static function read($acc_id, $account_id=null, $return_empty_marker=false)
	{
		if (is_null($account_id))
		{
			$account_id = array(0, $GLOBALS['egw_info']['user']['account_id']);
		}

		// check cache, if nothing found, query database
		// check assumes always same accounts (eg. 0=all plus own account_id) are asked
		if (!isset(self::$cache[$acc_id]) ||
			!($rows = array_intersect_key(self::$cache[$acc_id], array_flip((array)$account_id))))
		{
			$rows = self::$db->select(self::TABLE, '*', array(
				'acc_id' => $acc_id,
				'account_id' => $account_id,
			), __LINE__, __FILE__, false, '', self::APP);
			//error_log(__METHOD__."($acc_id, ".array2string($account_id).") nothing in cache");
		}
		$account_specific = 0;
		foreach($rows as $row)
		{
			if ($row['account_id'])
			{
				$account_specific = $row['account_id'];
			}
			// update cache (only if we have database-iterator)
			if (!is_array($rows))
			{
				self::$cache[$acc_id][$row['account_id']][] = $row['notif_folder'];
			}
		}
		$folders = (array)self::$cache[$acc_id][$account_specific];
		if (!$return_empty_marker && $folders == array(null)) $folders = array();
		$result = array(
			'notify_folders' => $folders,
			'notify_account_id' => $account_specific,
		);
		//error_log(__METHOD__."($acc_id, ".array2string($account_id).") returning ".array2string($result));
		return $result;
	}

	/**
	 * Write notification folders
	 *
	 * @param int $acc_id id of account
	 * @param int $account_id if of user-account for whom folders are or 0 for default
	 * @param array $folders folders to store
	 * @return int number of changed rows
	 */
	public static function write($acc_id, $account_id, array $folders)
	{
		if (!is_numeric($account_id) || !($account_id >= 0))
		{
			return 0;	// nothing to save, happens eg. in setup
		}

		if ($account_id && !$folders && ($default = self::read($acc_id, 0)) && $default['notify_folders'])
		{
			$folders[] = null;	// we need to write a marker, that user wants no notifications!
		}
		$old = self::read($acc_id, $account_id, true);	// true = return empty marker
		if ($account_id && !$old['notify_account_id']) $old['notify_folders'] = array();	// ignore returned default

		$changed = 0;
		// insert newly added ones
		foreach(array_diff($folders, $old['notify_folders']) as $folder)
		{
			self::$db->insert(self::TABLE, array(
				'acc_id' => $acc_id,
				'account_id' => $account_id,
				'notif_folder' => $folder,
			), false, __LINE__, __FILE__, self::APP);

			$changed += self::$db->affected_rows();
		}
		// delete removed ones
		if (($to_delete = array_diff($old['notify_folders'], $folders)))
		{
			self::$db->delete(self::TABLE, array(
				'acc_id' => $acc_id,
				'account_id' => $account_id,
				'notif_folder' => $to_delete,
			), __LINE__, __FILE__, self::APP);

			$changed += self::$db->affected_rows();
		}
		// update cache
		self::$cache[$acc_id][(int)$account_id] = $folders;

		//error_log(__METHOD__."(acc_id=$acc_id, account_id=".array2string($account_id).", folders=".array2string($folders).") returning $changed");
		return $changed;
	}

	/**
	 * Delete credentials from database
	 *
	 * @param int $acc_id
	 * @param int|array $account_id=null
	 * @return int number of rows deleted
	 */
	public static function delete($acc_id, $account_id=null)
	{
		if (!($acc_id > 0) && !isset($account_id))
		{
			throw new egw_exception_wrong_parameter(__METHOD__."() no acc_id AND no account_id parameter!");
		}
		$where = array();
		if ($acc_id > 0) $where['acc_id'] = $acc_id;
		if (isset($account_id)) $where['account_id'] = $account_id;

		self::$db->delete(self::TABLE, $where, __LINE__, __FILE__, self::APP);

		// invalidate cache: we allways unset everything about an account to simplify cache handling
		foreach($acc_id > 0 ? (array)$acc_id : array_keys(self::$cache) as $acc_id)
		{
			unset(self::$cache[$acc_id]);
		}
		$ret = self::$db->affected_rows();
		//error_log(__METHOD__."($acc_id, ".array2string($account_id).", $type) affected $ret rows");
		return $ret;
	}

	/**
	 * Init our static properties
	 */
	static public function init_static()
	{
		self::$db = isset($GLOBALS['egw_setup']) ? $GLOBALS['egw_setup']->db : $GLOBALS['egw']->db;
	}
}
emailadmin_notifications::init_static();
