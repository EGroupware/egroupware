<?php
	/**************************************************************************\
	* phpGroupWare API - Preferences                                           *
	* This file written by Joseph Engo <jengo@phpgroupware.org>                *
	* and Mark Peters <skeeter@phpgroupware.org>                               *
	* Manages user preferences                                                 *
	* Copyright (C) 2000, 2001 Joseph Engo                                     *
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
	@class preferences 
	@abstract preferences class used for setting application preferences
	@discussion Author: none yet
	*/
	class preferences
	{
		/*! @var account_id */
		var $account_id;
		/*! @var account_type */
		var $account_type;
		/*! @var data */
		var $data = array();
		/*! @var db */
		var $db;

		/**************************************************************************\
		* Standard constructor for setting $this->account_id                       *
		\**************************************************************************/
		/*! 
		@function preferences
		@abstract Standard constructor for setting $this->account_id
		@discussion Author:
		*/
		function preferences($account_id = '')
		{
			$this->db         = $GLOBALS['phpgw']->db;
			$this->account_id = get_account_id($account_id);
		}

		/**************************************************************************\
		* These are the standard $this->account_id specific functions              *
		\**************************************************************************/

		/*! 
		@function read_repository
		@abstract private - read preferences from the repository
		@discussion private function should only be called from within this class
		*/
		function read_repository()
		{
			$this->db->query("SELECT * FROM phpgw_preferences WHERE "
				. "preference_owner='" . $this->account_id . "' or "
				. "preference_owner='-1' order by preference_owner desc",__LINE__,__FILE__);
			$this->db->next_record();

			$pref_info  = $this->db->f('preference_value');
			$this->data = unserialize($pref_info);

			if ($this->db->next_record())
			{
				$global_defaults = unserialize($this->db->f('preference_value'));

				while (is_array($global_defaults) && list($appname,$values) = each($global_defaults))
				{
					while (is_array($values) && list($var,$value) = each($values))
					{
						$this->data[$appname][$var] = $value;
					}
				}
			}

			/* This is to supress warnings during login */
			if (is_array($this->data))
			{
				 reset ($this->data);
			}

			// This is to supress warnings durring login
			if (is_array($this->data))
			{
				reset($this->data);
			}
			return $this->data;
		}

		/*!
		@function read
		@abstract public - read preferences from repository and stores in an array
		@discussion Syntax array read(); <>
		Example1: preferences->read();
		@result $data array containing user preferences
		*/
		function read()
		{
			if (count($this->data) == 0)
			{
				$this->read_repository();
			}
			reset ($this->data);
			return $this->data;
		}

		/*!
		@function add
		@abstract add preference to $app_name a particular app
		@discussion
		@param $app_name name of the app
		@param $var name of preference to be stored
		@param $value value of the preference
		*/
		function add($app_name,$var,$value = '')
		{
			if (! $value)
			{
				global $$var;
				$value = $$var;
			}
 
			$this->data[$app_name][$var] = $value;
			reset($this->data);
			return $this->data;
		}

		/*! 
		@function delete
		@abstract delete preference from $app_name
		@discussion
		@param $app_name name of app
		@param $var variable to be deleted
		*/
		function delete($app_name, $var = '')
		{
			if ($var == '')
			{
				$this->data[$app_name] = array();
			}
			else
			{
				unset($this->data[$app_name][$var]);
			}
			reset ($this->data);
			return $this->data;
		}

		/*!
		@function save_repository
		@abstract save the the preferences to the repository
		@discussion
		*/
		function save_repository($update_session_info = False)
		{
			$temp_data = $this->data;
			if (! $GLOBALS['phpgw']->acl->check('session_only_preferences',1,'preferences'))
			{
				$this->db->transaction_begin();
				$this->db->query("delete from phpgw_preferences where preference_owner='" . $this->account_id
						. "'",__LINE__,__FILE__);

				if (floor(phpversion()) < 4)
				{
					$pref_info = addslashes(serialize($this->data));
				}
				else
				{
					$pref_info = serialize($this->data);
				}
				$this->db->query("insert into phpgw_preferences (preference_owner,preference_value) values ('"
						. $this->account_id . "','" . $pref_info . "')",__LINE__,__FILE__);

				$this->db->transaction_commit();
			}
			else
			{
				$GLOBALS['phpgw_info']['user']['preferences'] = $this->data;
				$GLOBALS['phpgw']->session->save_repositories();
			}

			if ($GLOBALS['phpgw_info']['server']['cache_phpgw_info'] && $this->account_id == $GLOBALS['phpgw_info']['user']['account_id'])
			{
				$GLOBALS['phpgw']->session->read_repositories(False);
			}
			
			return $temp_data;
		}

		function create_defaults($account_id)
		{
			$this->db->query("select * from phpgw_preferences where preference_owner='-2'",__LINE__,__FILE__);
			$this->db->next_record();

			$this->db->query("insert into phpgw_preferences values ('$account_id','"
				. $this->db->f('preference_value') . "')",__LINE__,__FILE__);
		}

		/*!
		@function update_data
		@abstract update the preferences array
		@discussion 
		@param $data array of preferences
		*/
		function update_data($data)
		{
			reset($data);
			$this->data = Array();
			$this->data = $data;
			reset($this->data);
			return $this->data;
		}

		/* legacy support */
		function change($app_name,$var,$value = "")
		{
			return $this->add($app_name,$var,$value);
		}
		function commit($update_session_info = True)
		{
			return $this->save_repository($update_session_info);
		}

		/**************************************************************************\
		* These are the non-standard $this->account_id specific functions          *
		\**************************************************************************/

		/*!
		@function verify_basic_settings
		@abstract verify basic settings
		@discussion
		*/
		function verify_basic_settings()
		{
			if (gettype($GLOBALS['phpgw_info']['user']['preferences']) != 'array')
			{
				 $GLOBALS['phpgw_info']['user']['preferences'] = array();
			}
			/* This takes care of new users who dont have proper default prefs setup */
			if (!isset($GLOBALS['phpgw_info']['flags']['nocommon_preferences']) || 
				!$GLOBALS['phpgw_info']['flags']['nocommon_preferences'])
			{
				$preferences_update = False;
				if (!isset($GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs']) || 
					!$GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'])
				{
					$this->add('common','maxmatchs',15);
					$preferences_update = True;
				}
				if (!isset($GLOBALS['phpgw_info']['user']['preferences']['common']['theme']) || 
					!$GLOBALS['phpgw_info']['user']['preferences']['common']['theme'])
				{
					$this->add('common','theme','default');
					$preferences_update = True;
				}
				if (!isset($GLOBALS['phpgw_info']['user']['preferences']['common']['template_set']) || 
					!$GLOBALS['phpgw_info']['user']['preferences']['common']['template_set'])
				{
					$this->add('common','template_set','default');
					$preferences_update = True;
				}
				if (!isset($GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']) || 
					!$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat'])
				{
					$this->add('common','dateformat','m/d/Y');
					$preferences_update = True;
				}
				if (!isset($GLOBALS['phpgw_info']['user']['preferences']['common']['timeformat']) || 
					!$GLOBALS['phpgw_info']['user']['preferences']['common']['timeformat'])
				{
					$this->add('common','timeformat',12);
					$preferences_update = True;
				}
				if (!isset($GLOBALS['phpgw_info']['user']['preferences']['common']['lang']) || 
					!$GLOBALS['phpgw_info']['user']['preferences']['common']['lang'])
				{
					$this->add('common','lang',$GLOBALS['phpgw']->common->getPreferredLanguage());
					$preferences_update = True;
				}
				if ($preferences_update)
				{
					$this->save_repository();
				}
				unset($preferences_update);
			}
		}
	} /* end of preferences class */
?>
