<?php
/**
 * eGroupWare API: VFS - Homedirectories
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

require_once(EGW_API_INC.'/class.vfs.inc.php');

/**
 * eGroupWare API: VFS - Homedirectories
 *
 * This class implements some necessary stuff for user- and group-directories ontop the vfs class.
 * 
 * Some of it is already implemented in the filemanager app, but it's also needed for the WebDAV access:
 * - show only directories in /home, to which the user has at least read-access
 * 
 * Stuff dealing with creation, renaming or deletion of users via some hooks from admin:
 * - create the homedir if a new user gets created
 * - rename the homedir if the user-name changes
 * - delete the homedir or copy its content to an other users homedir, if a user gets deleted
 * --> these hooks are registered via phpgwapi/setup/setup.inc.php and called by the admin app
 */
class vfs_home extends vfs
{
	/**
	 * List a directory, reimplemented to hide dirs the user has no rights to read
	 *
	 * @param array $data
	 * @param string  $data['string'] path
	 * @param array   $data['relatives'] Relativity array (default: RELATIVE_CURRENT)
	 * @param boolean $data['checksubdirs'] If true return information for all subdirectories recursively
	 * @param string  $data['mime'] Only return information for locations with MIME type specified (eg. 'Directory')
	 * @param boolean $data['nofiles'] If set and $data['string'] is a directory, return information about the directory, not the files in it.
	 * @return array of arrays of file information.
	 */
	function ls($data)
	{
		//error_log("vfs_home(".print_r($data,true).")");
		$fileinfos = parent::ls($data);
		
		if (!$this->override_acl && !$data['nofiles'] && ($data['string'] == $this->fakebase || $data['string'].$this->fakebase.'/'))
		{
			//error_log("vfs_home() grants=".print_r($this->grants,true));

			// remove directories the user has no rights to see, no grant from the owner
			foreach($fileinfos as $key => $info)
			{
				//error_log("vfs_home() ".(!$this->grants[$info['owner_id']] ? 'hidding' : 'showing')." $info[directory]/$info[name] (owner=$info[owner_id])");

				if (!$this->grants[$info['owner_id']])
				{
					unset($fileinfos[$key]);
				}
			}
		}
		return $fileinfos;
	}

	/**
	 * Hook called after new accounts have been added
	 *
	 * @param array $data
	 * @param int $data['account_id'] numerical id
	 * @param string $data['account_lid'] account-name
	 */
	function addAccount($data)
	{
		// create a user-dir
		$save_id = $this->working_id;
		$this->working_id  = $data['account_id'];
		$save_currentapp = $GLOBALS['egw_info']['flags']['currentapp'];
		$GLOBALS['egw_info']['flags']['currentapp'] = 'filemanager';

		$this->override_acl = true;
		$this->mkdir(array(
			'string'    => $this->fakebase.'/'.$data['account_lid'],
			'relatives' => array(RELATIVE_ROOT),
		));
		$this->override_acl = false;

		$this->working_id  = $save_id;
		$GLOBALS['egw_info']['flags']['currentapp'] = $currentapp;
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
		$this->override_acl = true;
		$this->mv(array(
			'from'      => $this->fakebase.'/'.$data['old_loginid'],
			'to'        => $this->fakebase.'/'.$data['account_lid'],
			'relatives' => array(RELATIVE_ROOT,RELATIVE_ROOT),
		));
		$this->override_acl = false;
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
		$this->override_acl = true;
		$this->delete(array(
			'string'    => $this->fakebase.'/'.$data['account_lid'],
			'relatives' => array(RELATIVE_ROOT),
		));
		$this->override_acl = false;
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
		$save_id = $this->working_id;
		$this->working_id  = $data['account_id'];
		$save_currentapp = $GLOBALS['egw_info']['flags']['currentapp'];
		$GLOBALS['egw_info']['flags']['currentapp'] = 'filemanager';

		$this->override_acl = true;
		$this->mkdir(array(
			'string'    => $this->fakebase.'/'.$data['account_name'],
			'relatives' => array(RELATIVE_ROOT),
		));
		$this->override_acl = false;

		$this->working_id  = $save_id;
		$GLOBALS['egw_info']['flags']['currentapp'] = $currentapp;
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
		$this->override_acl = true;
		$this->mv(array(
			'from'      => $this->fakebase.'/'.$data['old_name'],
			'to'        => $this->fakebase.'/'.$data['account_name'],
			'relatives' => array(RELATIVE_ROOT,RELATIVE_ROOT),
		));
		$this->override_acl = false;
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
		$this->override_acl = true;
		$this->delete(array(
			'string'    => $this->fakebase.'/'.$data['account_name'],
			'relatives' => array(RELATIVE_ROOT),
		));
		$this->override_acl = false;
	}
	
}