<?php
/**
 * EGroup2are API: VFS - Hooks to add/rename/delete user and group home-directories
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\Vfs;

use EGroupware\Api;

/**
 * VFS - Hooks to add/rename/delete user and group home-directories
 *
 * This class implements the creation, renaming or deletion of home-dirs via some hooks from admin:
 * - create the homedir if a new user gets created
 * - rename the homedir if the user-name changes
 * - delete the homedir or copy its content to an other users homedir, if a user gets deleted
 * --> these hooks are registered via phpgwapi/setup/setup.inc.php and called by the admin app
 */
class Hooks
{
	/**
	 * Should we log our calls to the error_log
	 * 0 - no logging
	 * 1 - log method calls
	 */
	const LOG_LEVEL = 0;

	/**
	 * Hook called after new accounts have been added
	 *
	 * @param array $data
	 * @param int $data['account_id'] numerical id
	 * @param string $data['account_lid'] account-name
	 */
	static function addAccount($data)
	{
		if (self::LOG_LEVEL > 0) error_log(__METHOD__.'('.array2string($data).')');
		// create a user-dir
		Api\Vfs::$is_root = true;
		if (Api\Vfs::file_exists($dir='/home/'.$data['account_lid']) || Api\Vfs::mkdir($dir, 0700, 0))
		{
			Api\Vfs::chown($dir,(int)$data['account_id']);
			Api\Vfs::chgrp($dir,0);
			Api\Vfs::chmod($dir,0700);	// only user has access
		}
		Api\Vfs::$is_root = false;
	}

	/**
	 * Hook called after accounts has been modified
	 *
	 * @param array $data
	 * @param int $data['account_id'] numerical id
	 * @param string $data['account_lid'] new account-name
	 * @param string $data['old_loginid'] old account-name
	 */
	static function editAccount($data)
	{
		if (self::LOG_LEVEL > 0) error_log(__METHOD__.'('.array2string($data).')');
		if (empty($data['account_lid']) || empty($data['old_loginid']) || $data['account_lid'] == $data['old_loginid'])
		{
			return;	// nothing to do here
		}
		// rename the user-dir
		Api\Vfs::$is_root = true;
		Api\Vfs::rename('/home/'.$data['old_loginid'],'/home/'.$data['account_lid']);
		Api\Vfs::$is_root = false;
	}

	/**
	 * Hook called before an account get deleted
	 *
	 * @param array $data
	 * @param int $data['account_id'] numerical id
	 * @param string $data['account_lid'] account-name
	 * @param int $data['new_owner'] account-id of new owner, or false if data should get deleted
	 */
	static function deleteAccount($data)
	{
		if (self::LOG_LEVEL > 0) error_log(__METHOD__.'('.array2string($data).')');
		Api\Vfs::$is_root = true;

		// Home directory
		if ($data['new_owner'] && ($new_lid = $GLOBALS['egw']->accounts->id2name($data['new_owner'])))
		{
			// copy content of user-dir to new owner's user-dir as old-home-$name
			for ($i=''; file_exists(Api\Vfs::PREFIX.($new_dir = '/home/'.$new_lid.'/old-home-'.$data['account_lid'].$i)); $i++)
			{

			}
			Api\Vfs::rename('/home/'.$data['account_lid'],$new_dir);
		}
		elseif(!empty($data['account_lid']) && $data['account_lid'] != '/')
		{
			// delete the user-directory
			Api\Vfs::remove('/home/'.$data['account_lid']);
		}
		else
		{
			throw new Api\Exception\AssertionFailed(__METHOD__.'('.array2string($data).') account_lid NOT set!');
		}

		// Change owner of all files
		Api\Vfs\Sqlfs\StreamWrapper::chownAll($data['account_id'], $data['new_owner'] ? $data['new_owner'] : 0);

		Api\Vfs::$is_root = false;
	}

	/**
	 * Hook called after new groups have been added
	 *
	 * @param array $data
	 * @param int $data['account_id'] numerical id
	 * @param string $data['account_lid'] group-name
	 */
	static function addGroup($data)
	{
		if (self::LOG_LEVEL > 0) error_log(__METHOD__.'('.array2string($data).')');
		if (empty($data['account_lid'])) throw new Api\Exception\WrongParameter('account_lid must not be empty!');

		// create a group-dir
		Api\Vfs::$is_root = true;
		if (Api\Vfs::file_exists($dir='/home/'.$data['account_lid']) || Api\Vfs::mkdir($dir, 0070, 0))
		{
			Api\Vfs::chown($dir,0);
			Api\Vfs::chgrp($dir,abs($data['account_id']));	// gid in Vfs is positiv!
			Api\Vfs::chmod($dir,0070);	// only group has access
		}
		Api\Vfs::$is_root = false;
	}

	/**
	 * Hook called after group has been modified
	 *
	 * Checks if group has been renamed and renames the group directory too,
	 * or if the group directory exists and creates it if not.
	 *
	 * @param array $data
	 * @param int $data['account_id'] numerical id
	 * @param string $data['account_lid'] new group-name
	 * @param string $data['old_name'] old account-name
	 */
	static function editGroup($data)
	{
		if (self::LOG_LEVEL > 0) error_log(__METHOD__.'('.array2string($data).')');
		if (empty($data['account_lid']) || empty($data['old_name'])) throw new Api\Exception\WrongParameter('account_lid and old_name must not be empty!');

		if ($data['account_lid'] == $data['old_name'])
		{
			// check if group directory exists and create it if not (by calling addGroup hook)
			if (!Api\Vfs::stat('/home/'.$data['account_lid']))
			{
				self::addGroup($data);
			}
		}
		else
		{
			// rename the group-dir
			Api\Vfs::$is_root = true;
			Api\Vfs::rename('/home/'.$data['old_name'],'/home/'.$data['account_lid']);
			Api\Vfs::$is_root = false;
		}
	}

	/**
	 * Hook called before a group get deleted
	 *
	 * @param array $data
	 * @param int $data['account_id'] numerical id
	 * @param string $data['account_lid'] account-name
	 */
	static function deleteGroup($data)
	{
		if (self::LOG_LEVEL > 0) error_log(__METHOD__.'('.array2string($data).')');

		if(empty($data['account_lid']) || $data['account_lid'] == '/')
		{
			throw new Api\Exception\AssertionFailed(__METHOD__.'('.array2string($data).') account_lid NOT set!');
		}
		// delete the group-directory
		Api\Vfs::$is_root = true;
		Api\Vfs::remove('/home/'.$data['account_lid']);
		Api\Vfs::$is_root = false;
	}
}