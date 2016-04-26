<?php
/**
 * EGroupware API - Applications
 *
 * @link http://www.egroupware.org
 * @author Mark Peters <skeeter@phpgroupware.org>
 * Copyright (C) 2001 Mark Peters
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @version $Id$
 */

use EGroupware\Api;

/**
 * functions for managing and installing apps
 *
 * Author: skeeter
 *
 * @deprecated use just methods from Api\Egw\Applications
 */
class applications extends Api\Egw\Applications
{
	/**
	 * read from the repository
	 *
	 * pubic function that is used to determine what apps a user has rights to
	 */
	function read()
	{
		if (!count($this->data))
		{
			$this->read_repository();
		}
		return $this->data;
	}
	/**
	 * add an app to a user profile
	 *
	 * @discussion
	 * @param $apps array containing apps to add for a user
	 */
	function add($apps)
	{
		if(is_array($apps))
		{
			foreach($apps as $app)
			{
				$this->data[$app] =& $GLOBALS['egw_info']['apps'][$app];
			}
		}
		elseif(gettype($apps))
		{
			$this->data[$apps] =& $GLOBALS['egw_info']['apps'][$apps];
		}
		return $this->data;
	}
	/**
	 * delete an app from a user profile
	 *
	 * @discussion
	 * @param $appname appname to remove
	 */
	function delete($appname)
	{
		if($this->data[$appname])
		{
			unset($this->data[$appname]);
		}
		return $this->data;
	}
	/**
	 * update the array(?)
	 *
	 * @discussion
	 * @param $data update the repository array(?)
	 */
	function update_data($data)
	{
		$this->data = $data;
		return $this->data;
	}
	/**
	 * save the repository
	 *
	 * @discussion
	 */
	function save_repository()
	{
		$GLOBALS['egw']->acl->delete_repository("%%", 'run', $this->account_id);
		foreach(array_keys($this->data) as $app)
		{
			if(!$this->is_system_enabled($app))
			{
				continue;
			}
			$GLOBALS['egw']->acl->add_repository($app,'run',$this->account_id,1);
		}
		return $this->data;
	}

	/**************************************************************************\
	* These are the non-standard $this->account_id specific functions          *
	\**************************************************************************/

	function app_perms()
	{
		if (!count($this->data))
		{
			$this->read_repository();
		}
		foreach (array_keys($this->data) as $app)
		{
			$apps[] = $this->data[$app]['name'];
		}
		return $apps;
	}

	function read_account_specific()
	{
		if (!is_array($GLOBALS['egw_info']['apps']))
		{
			$this->read_installed_apps();
		}
		if (($app_list = $GLOBALS['egw']->acl->get_app_list_for_id('run',1,$this->account_id)))
		{
			foreach($app_list as $app)
			{
				if ($this->is_system_enabled($app))
				{
					$this->data[$app] =& $GLOBALS['egw_info']['apps'][$app];
				}
			}
		}
		return $this->data;
	}

	/**
	 * check if an app is enabled
	 *
	 * @param $appname name of the app to check for
	 */
	function is_system_enabled($appname)
	{
		if(!is_array($GLOBALS['egw_info']['apps']))
		{
			$this->read_installed_apps();
		}
		if ($GLOBALS['egw_info']['apps'][$appname]['enabled'])
		{
			return True;
		}
		else
		{
			return False;
		}
	}

	function id2name($id)
	{
		foreach($GLOBALS['egw_info']['apps'] as $appname => $app)
		{
			if((int)$app['id'] == (int)$id)
			{
				return $appname;
			}
		}
		return '';
	}

	function name2id($appname)
	{
		if(is_array($GLOBALS['egw_info']['apps'][$appname]))
		{
			return $GLOBALS['egw_info']['apps'][$appname]['id'];
		}
		return 0;
	}
}
