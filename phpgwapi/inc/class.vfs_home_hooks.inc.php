<?php
/**
 * eGroupWare API: VFS - Hooks to add/rename/delete user and group home-directories
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * eGroupWare API: VFS - Hooks to add/rename/delete user and group home-directories
 *
 * This class implements the creation, renaming or deletion of home-dirs via some hooks from admin:
 * - create the homedir if a new user gets created
 * - rename the homedir if the user-name changes
 * - delete the homedir or copy its content to an other users homedir, if a user gets deleted
 * --> these hooks are registered via phpgwapi/setup/setup.inc.php and called by the admin app
 */
class vfs_home_hooks
{
	/**
	 * Hook called after new accounts have been added
	 *
	 * @param array $data
	 * @param int $data['account_id'] numerical id
	 * @param string $data['account_lid'] account-name
	 */
	static function addAccount($data)
	{
		// create a user-dir
		egw_vfs::$is_root = true;
		egw_vfs::mkdir($dir='/home/'.$data['account_lid']);
		egw_vfs::chown($dir,$data['account_id']);
		egw_vfs::chgrp($dir,0);
		egw_vfs::chmod($dir,0700);	// only user has access
		egw_vfs::$is_root = false;
	}
	
	/**
	 * Hook called after accounts has been modified
	 *
	 * @param array $data
	 * @param int $data['account_id'] numerical id
	 * @param string $data['account_lid'] new account-name
	 * @param string $data['old_loginid'] old account-name
	 */
	function editAccount($data)
	{
		if ($data['account_lid'] == $data['old_loginid']) return;	// nothing to do here
		
		// rename the user-dir
		egw_vfs::$is_root = true;
		egw_vfs::rename('/home/'.$data['old_loginid'],'/home/'.$data['account_lid']);
		egw_vfs::$is_root = false;
	}
	
	/**
	 * Hook called before an account get deleted
	 *
	 * @param array $data
	 * @param int $data['account_id'] numerical id
	 * @param string $data['account_lid'] account-name
	 * @param int $data['new_owner'] account-id of new owner, or false if data should get deleted
	 */
	function deleteAccount($data)
	{
		if ($data['new_owner'])
		{
			// ToDo: copy content of user-dir to new owner's user-dir
			
		}
		// delete the user-directory
		egw_vfs::$is_root = true;
		egw_vfs::remove('/home/'.$data['account_lid']);
		egw_vfs::$is_root = false;
	}

	/**
	 * Hook called after new groups have been added
	 *
	 * @param array $data
	 * @param int $data['account_id'] numerical id
	 * @param string $data['account_name'] group-name
	 */
	function addGroup($data)
	{
		// create a group-dir
		egw_vfs::$is_root = true;
		egw_vfs::mkdir($dir='/home/'.$data['account_name']);
		egw_vfs::chown($dir,0);
		egw_vfs::chgrp($dir,$data['account_id']);
		egw_vfs::chmod($dir,0070);	// only group has access
		egw_vfs::$is_root = false;
	}
	
	/**
	 * Hook called after group has been modified
	 *
	 * @param array $data
	 * @param int $data['account_id'] numerical id
	 * @param string $data['account_name'] new group-name
	 * @param string $data['old_name'] old account-name
	 */
	function editGroup($data)
	{
		if ($data['account_name'] == $data['old_name']) return;	// nothing to do here
		
		// rename the group-dir
		egw_vfs::$is_root = true;
		egw_vfs::rename('/home/'.$data['old_name'],'/home/'.$data['account_name']);
		egw_vfs::$is_root = false;
	}
	
	/**
	 * Hook called before a group get deleted
	 *
	 * @param array $data
	 * @param int $data['account_id'] numerical id
	 * @param string $data['account_name'] account-name
	 */
	function deleteGroup($data)
	{
		// delete the group-directory
		egw_vfs::$is_root = true;
		egw_vfs::remove('/home/'.$data['account_name']);
		egw_vfs::$is_root = false;
	}
}