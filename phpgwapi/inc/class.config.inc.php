<?php
	/**************************************************************************\
	* eGroupWare API - Application configuration in a centralized location     *
	* This file written by Joseph Engo <jengo@phpgroupware.org>                *
	* Copyright (C) 2000, 2001 Joseph Engo                                     *
	* -------------------------------------------------------------------------*
	* This library is part of the eGroupWare API                               *
	* http://www.egroupware.org/api                                            * 
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

	class config
	{
		var $db;
		var $appname;
		var $config_data;	// actual config-data
		var $read_data;		// config-data as read from db

		function config($appname = '')
		{
			if (! $appname)
			{
				$appname = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}

			$this->db      = is_object($GLOBALS['phpgw']->db) ? $GLOBALS['phpgw']->db : $GLOBALS['phpgw_setup']->db;
			$this->db->set_app('phpgwapi');
			$this->table = 'phpgw_config';
			$this->appname = $appname;
		}

		/*!
		@function read_repository
		@abstract reads the whole repository for $this->appname, appname has to be set via the constructor
		@returns the whole config-array for that app
		*/
		function read_repository()
		{
			$this->config_data = array();

			$this->db->select($this->table,'*',array('config_app'=>$this->appname),__LINE__,__FILE__);
			while ($this->db->next_record())
			{
				$test = @unserialize($this->db->f('config_value'));
				if($test)
				{
					$this->config_data[$this->db->f('config_name')] = $test;
				}
				else
				{
					$this->config_data[$this->db->f('config_name')] = $this->db->f('config_value');
				}
			}
			return $this->read_data = $this->config_data;
		}

		/*!
		@function save_repository
		@abstract updates the whole repository for $this->appname, you have to call read_repository() before (!)
		*/
		function save_repository()
		{
			if (is_array($this->config_data))
			{
				$this->db->lock(array('phpgw_config','phpgw_app_sessions'));
				if($this->appname == 'phpgwapi')
				{
					$this->db->query("delete from phpgw_app_sessions where sessionid = '0' and loginid = '0' and app = '".$this->appname."' and location = 'config'",__LINE__,__FILE__);
				}
				foreach($this->config_data as $name => $value)
				{
					$this->save_value($name,$value);
				}
				foreach($this->read_data as $name => $value)
				{
					if (!isset($this->config_data[$name]))	// has been deleted
					{
						$this->db->delete($this->table,array('config_app'=>$this->appname,'config_name'=>$name),__LINE__,__FILE__);
					}
				}
				$this->db->unlock();
			}
			$this->read_data = $this->config_data;
		}

		/*!
		@function save_value
		@abstract updates or insert a single config-value
		@param $name string name of the config-value
		@param $value mixed content
		@param $app string app-name, defaults to $this->appname set via the constructor
		*/
		function save_value($name,$value,$app=False)
		{
			//echo "<p>config::save_value('$name','".print_r($value,True)."','$app')</p>\n";
			if (!$app || $app == $this->appname)
			{
				$app = $this->appname;
				$this->config_data[$name] = $value;
			}
			if ($app == $this->appname && $this->read_data[$name] == $value)
			{
				return True;	// no change ==> exit
			}
			//echo "<p>config::save_value('$name','".print_r($value,True)."','$app')</p>\n";

			if(is_array($value))
			{
				$value = serialize($value);
			}
			return $this->db->insert($this->table,array('config_value'=>$value),array('config_app'=>$app,'config_name'=>$name),__LINE__,__FILE__);
		}

		/*!
		@function delete_repository
		@abstract deletes the whole repository for $this->appname, appname has to be set via the constructor
		*/
		function delete_repository()
		{
			$this->db->delete("delete from phpgw_config where config_app='" . $this->appname . "'",__LINE__,__FILE__);
		}

		/*!
		@function delete_value
		@abstract deletes a single value from the repository, you need to call save_repository after
		@param $variable_name string name of the config
		*/
		function delete_value($variable_name)
		{
			unset($this->config_data[$variable_name]);
		}
		/*!
		@function value
		@abstract sets a single value in the repositry, you need to call save_repository after
		@param $variable_name string name of the config
		@param $variable_data mixed the content
		*/
		function value($variable_name,$variable_data)
		{
			$this->config_data[$variable_name] = $variable_data;
		}
	}
?>
