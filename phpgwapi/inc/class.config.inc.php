<?php
	/**************************************************************************\
	* phpGroupWare API - Application configuration in a centralized location   *
	* This file written by Joseph Engo <jengo@phpgroupware.org>                *
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

	class config
	{
		var $db;
		var $appname;
		var $config_data;

		function config($appname = '')
		{
			if (! $appname)
			{
				$appname = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}

			$this->db      = $GLOBALS['phpgw']->db;
			$this->appname = $appname;
		}

		function read_repository()
		{
			$this->db->query("select * from phpgw_config where config_app='" . $this->appname . "'",__LINE__,__FILE__);
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
		}

		function save_repository()
		{
			$config_data = $this->config_data;

			if ($config_data)
			{
				$this->db->lock(array('phpgw_config','phpgw_app_sessions'));
				$this->db->query("delete from phpgw_config where config_app='" . $this->appname . "'",__LINE__,__FILE__);
				if($this->appname == 'phpgwapi')
				{
					$this->db->query("delete from phpgw_app_sessions where sessionid = '0' and loginid = '0' and app = '".$this->appname."' and location = 'config'",__LINE__,__FILE__);
				}
				while (list($name,$value) = each($config_data))
				{
					if(is_array($value))
					{
						$value = serialize($value);
					}
					$name  = addslashes($name);
					$value = addslashes($value);
					$this->db->query("delete from phpgw_config where config_name='" . $name . "'",__LINE__,__FILE__);
					$query = "insert into phpgw_config (config_app,config_name,config_value) "
						. "values ('" . $this->appname . "','" . $name . "','" . $value . "')";
					$this->db->query($query,__LINE__,__FILE__);
				}
				$this->db->unlock();
			}
		}

		function delete_repository()
		{
			$this->db->query("delete from phpgw_config where config_app='" . $this->appname . "'",__LINE__,__FILE__);
		}

		function value($variable_name,$variable_data)
		{
			$this->config_data[$variable_name] = $variable_data;
		}
	}
?>
