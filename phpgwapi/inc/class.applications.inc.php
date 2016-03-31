<?php
/**
 * eGroupWare API - Applications
 *
 * @link http://www.egroupware.org
 * @author Mark Peters <skeeter@phpgroupware.org>
 * Copyright (C) 2001 Mark Peters
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @version $Id$
 */

/**
 * functions for managing and installing apps
 *
 * Author: skeeter
 */
class applications
{
	var $account_id;
	var $data = Array();
	/**
	 * Reference to the global db class
	 *
	 * @var egw_db
	 */
	var $db;
	var $table_name = 'egw_applications';

	/**************************************************************************\
	* Standard constructor for setting $this->account_id                       *
	\**************************************************************************/

	/**
	 * standard constructor for setting $this->account_id
	 *
	 * @param $account_id account id
	 */
	function __construct($account_id = '')
	{
		if (is_object($GLOBALS['egw_setup']) && is_object($GLOBALS['egw_setup']->db))
		{
			$this->db = $GLOBALS['egw_setup']->db;
		}
		else
		{
			$this->db = $GLOBALS['egw']->db;
		}

		$this->account_id = get_account_id($account_id);
	}

	/**************************************************************************\
	* These are the standard $this->account_id specific functions              *
	\**************************************************************************/

	/**
	 * read from repository
	 *
	 * private should only be called from withing this class
	 */
	function read_repository()
	{
		if (!isset($GLOBALS['egw_info']['apps']) ||	!is_array($GLOBALS['egw_info']['apps']))
		{
			$this->read_installed_apps();
		}
		$this->data = Array();
		if(!$this->account_id)
		{
			return False;
		}
		$apps = $GLOBALS['egw']->acl->get_user_applications($this->account_id);
		foreach($GLOBALS['egw_info']['apps'] as $app => $data)
		{
			if (isset($apps[$app]) && $apps[$app])
			{
				$this->data[$app] =& $GLOBALS['egw_info']['apps'][$app];
			}
		}
		return $this->data;
	}

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
		foreach($this->data as $app => $data)
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
		foreach ($this->data as $app => $data)
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

	/**************************************************************************\
	* These are the generic functions. Not specific to $this->account_id       *
	\**************************************************************************/

	/**
	 * populate array with a list of installed apps
	 *
	 */
	function read_installed_apps()
	{
		foreach($this->db->select($this->table_name,'*',false,__LINE__,__FILE__,false,'ORDER BY app_order ASC') as $row)
		{
			$title = $app_name = $row['app_name'];

			if (@is_array($GLOBALS['egw_info']['user']['preferences']) && ($t = lang($app_name)) != $app_name.'*')
			{
				$title = $t;
			}
			$GLOBALS['egw_info']['apps'][$app_name] = Array(
				'title'   => $title,
				'name'    => $app_name,
				'enabled' => True,
				'status'  => $row['app_enabled'],
				'id'      => (int)$row['app_id'],
				'order'   => (int)$row['app_order'],
				'version' => $row['app_version'],
				'index'   => $row['app_index'],
				'icon'    => $row['app_icon'],
				'icon_app'=> $row['app_icon_app'],
			);
		}
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
