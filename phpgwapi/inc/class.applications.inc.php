<?php
  /**************************************************************************\
  * phpGroupWare API - Applications manager functions                        *
  * This file written by Mark Peters <skeeter@phpgroupware.org>              *
  * Copyright (C) 2001 Mark Peters                                           *
  * -------------------------------------------------------------------------*
  * This library is part of the phpGroupWare API                             *
  * http://www.phpgroupware.org/api                                          * 
  * ------------------------------------------------------------------------ *
  * This library is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published by *
  * the Free Software Foundation; either version 2.1 of the License,         *
  * or any later version.                                                    *
  * This library is distributed in the hope that it will be useful, but      *
  * WITHOUT ANY WARRANTY; without even the implied warranty of               *
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
  * See the GNU Lesser General Public License for more details.              *
  * You should have received a copy of the GNU Lesser General Public License *
  * along with this library; if not, write to the Free Software Foundation,  *
  * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
  \**************************************************************************/

  /* $Id$ */
	/*!
	@class applicatons
	@abstract functions for managing and installing apps
	@discussion Author: skeeter
	*/
	class applications
	{
		var $account_id;
		var $data = Array();
		var $db;

		/**************************************************************************\
		* Standard constructor for setting $this->account_id                       *
		\**************************************************************************/

		/*!
		@function applications
		@abstract standard constructor for setting $this->account_id
		@param $account_id account id
		*/
		function applications($account_id = '')
		{
			$this->db = $GLOBALS['phpgw']->db;
			$this->account_id = get_account_id($account_id);
		}

		/**************************************************************************\
		* These are the standard $this->account_id specific functions              *
		\**************************************************************************/

		/*!
		@function read_repository
		@abstract read from repository
		@discussion private should only be called from withing this class
		*/
		function read_repository()
		{
			global $phpgw, $phpgw_info;
			if (!isset($GLOBALS['phpgw_info']['apps']) ||
			    !is_array($GLOBALS['phpgw_info']['apps']))
			{
				$this->read_installed_apps();
			}
			$this->data = Array();
			if($this->account_id == False) { return False; }
			$apps = $GLOBALS['phpgw']->acl->get_user_applications($this->account_id);
			reset($GLOBALS['phpgw_info']['apps']);
			while ($app = each($GLOBALS['phpgw_info']['apps']))
			{
//				$check = $phpgw->acl->check('run',1,$app[0]);
				$check = (isset($apps[$app[0]])?$apps[$app[0]]:False);
				if ($check)
				{
					$this->data[$app[0]] = array(
						'title'   => $GLOBALS['phpgw_info']['apps'][$app[0]]['title'],
						'name'    => $app[0],
						'enabled' => True,
						'status'  => $GLOBALS['phpgw_info']['apps'][$app[0]]['status'],
						'id'      => $GLOBALS['phpgw_info']['apps'][$app[0]]['id']
					);
				} 
			}
			reset($this->data);
			return $this->data;
		}

		/*!
		@function read()
		@abstract read from the repository
		@discussion pubic function that is used to determine what apps a user has rights to
		*/
		function read()
		{
			if (count($this->data) == 0){ $this->read_repository(); }
			reset($this->data);
			return $this->data;
		}
		/*!
		@function add
		@abstract add an app to a user profile
		@discussion
		@param $apps array containing apps to add for a user
		*/	
		function add($apps)
		{
			if(is_array($apps))
			{
				while($app = each($apps))
				{
					$this->data[$app[1]] = array(
						'title'   => $GLOBALS['phpgw_info']['apps'][$app[1]]['title'],
						'name'    => $app[1],
						'enabled' => True,
						'status'  => $GLOBALS['phpgw_info']['apps'][$app[1]]['status'],
						'id'      => $GLOBALS['phpgw_info']['apps'][$app[1]]['id']
					);
				}
			}
			elseif(gettype($apps))
			{
				$this->data[$apps] = array(
					'title'   => $GLOBALS['phpgw_info']['apps'][$apps]['title'],
					'name'    => $apps,
					'enabled' => True,
					'status'  => $GLOBALS['phpgw_info']['apps'][$apps]['status'],
					'id'      => $GLOBALS['phpgw_info']['apps'][$app[1]]['id']
				);
			}
			reset($this->data);
			return $this->data;
		}
		/*!
		@function delete
		@abstract delete an app from a user profile
		@discussion
		@param $appname appname to remove
		*/
		function delete($appname)
		{
			if($this->data[$appname])
			{
				unset($this->data[$appname]);
			}
			reset($this->data);
			return $this->data;
		}
		/*!
		@function update_data
		@abstract update the array(?)
		@discussion
		@param $data update the repository array(?)
		*/
		function update_data($data)
		{
			reset($data);
			$this->data = Array();
			$this->data = $data;
			reset($this->data);
			return $this->data;
		}
		/*!
		@function save_repository()
		@abstract save the repository
		@discussion 
		*/
		function save_repository()
		{
			$num_rows = $GLOBALS['phpgw']->acl->delete_repository("%%", 'run', $this->account_id);
			reset($this->data);
			while($app = each($this->data))
			{
				if(!$this->is_system_enabled($app[0])) { continue; }
				$GLOBALS['phpgw']->acl->add_repository($app[0],'run',$this->account_id,1);
			}
			reset($this->data);
			return $this->data;
		}

		/**************************************************************************\
		* These are the non-standard $this->account_id specific functions          *
		\**************************************************************************/

		function app_perms()
		{
			if (count($this->data) == 0)
			{
				$this->read_repository();
			}
			@reset($this->data);
			while (list ($key) = each ($this->data))
			{
				$app[] = $this->data[$key]['name'];
			}
			return $app;
		}

		function read_account_specific()
		{
			if (!is_array($GLOBALS['phpgw_info']['apps']))
			{
				$this->read_installed_apps();
			}
			$app_list = $GLOBALS['phpgw']->acl->get_app_list_for_id('run',1,$this->account_id);
			if(!$app_list)
			{
				reset($this->data);
				return $this->data;
			}
			@reset($app_list);
			while ($app = each($app_list))
			{
				if ($this->is_system_enabled($app[1]))
				{
					$this->data[$app[1]] = array(
						'title'   => $GLOBALS['phpgw_info']['apps'][$app[1]]['title'],
						'name'    => $app[1],
						'enabled' => True,
						'status'  => $GLOBALS['phpgw_info']['apps'][$app[1]]['status'],
						'id'      => $GLOBALS['phpgw_info']['apps'][$app[1]]['id']
					);
				}
			}
			reset($this->data);
			return $this->data;
		}

		/**************************************************************************\
		* These are the generic functions. Not specific to $this->account_id       *
		\**************************************************************************/

		/*!
		@function read_installed_apps()
		@abstract populate array with a list of installed apps
		*/
		function read_installed_apps()
		{
			$this->db->query('select * from phpgw_applications where app_enabled != 0 order by app_order asc',__LINE__,__FILE__);
			if($this->db->num_rows())
			{
				while ($this->db->next_record())
				{
					$GLOBALS['phpgw_info']['apps'][$this->db->f('app_name')] = Array(
						'title'   => $this->db->f('app_title'),
						'name'    => $this->db->f('app_name'),
						'enabled' => True,
						'status'  => $this->db->f('app_enabled'),
						'id'      => intval($this->db->f('app_id')),
						'order'   => intval($this->db->f('app_order'))
					);
				}
			}
		}
		/*!
		@function is_system_enabled
		@abstract check if an app is enabled
		@param $appname name of the app to check for
		*/
		function is_system_enabled($appname)
		{
			if(!is_array($GLOBALS['phpgw_info']['apps']))
			{
				$this->read_installed_apps();
			}
			if ($GLOBALS['phpgw_info']['apps'][$appname]['enabled'])
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
			@reset($GLOBALS['phpgw_info']['apps']);
			while (list($appname,$app) = each($GLOBALS['phpgw_info']['apps']))
			{
				if(intval($app['id']) == intval($id))
				{
					@reset($GLOBALS['phpgw_info']['apps']);
					return $appname;
				}
			}
			@reset($GLOBALS['phpgw_info']['apps']);
			return '';
		}
		
		function name2id($appname)
		{
			if(is_array($GLOBALS['phpgw_info']['apps'][$appname]))
			{
				return $GLOBALS['phpgw_info']['apps'][$appname]['id'];
			}
			else
			{
				return 0;
			}
		}
	}
?>
