<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class soapplications
	{
		var $db;

		function soapplications()
		{
			$this->db = $GLOBALS['phpgw']->db;
		}

		function read($app_name)
		{
			$sql = "SELECT * FROM phpgw_applications WHERE app_name='$app_name'";

			$this->db->query($sql,__LINE__,__FILE__);
			$this->db->next_record();
			$app_info = array(
				$this->db->f('app_name'),
				$this->db->f('app_title'),
				$this->db->f('app_enabled'),
				$this->db->f('app_name'),
				$this->db->f('app_order')
			);
			return $app_info;
		}

		function get_list()
		{
			$this->db->query('SELECT * FROM phpgw_applications WHERE app_enabled<3',__LINE__,__FILE__);
			if($this->db->num_rows())
			{
				while ($this->db->next_record())
				{
					$apps[$this->db->f('app_name')] = array(
						'title'  => $this->db->f('app_title'),
						'name'   => $this->db->f('app_name'),
						'status' => $this->db->f('app_enabled')
					);
				}
			}
			@reset($apps);
			return $apps;
		}

		function add($data)
		{
			/* Yes, the sequence should work, but after a mass import in setup (new install)
			  it does not work on pg
			*/
			$sql = 'SELECT MAX(app_id) from phpgw_applications';
			$this->db->query($sql,__LINE__,__FILE__);
			$this->db->next_record();
			$app_id = $this->db->f(0) + 1;
			$sql = 'INSERT INTO phpgw_applications (app_id,app_name,app_title,app_enabled,app_order) VALUES('
				. $app_id . ",'" . addslashes($data['n_app_name']) . "','" . addslashes($data['n_app_title']) . "','"
				. $data['n_app_status'] . "','" . $data['app_order'] . "')";

			$this->db->query($sql,__LINE__,__FILE__);
			return True;
		}

		function save($data)
		{
			$sql = "UPDATE phpgw_applications SET app_name='" . addslashes($data['n_app_name']) . "',"
				. "app_title='" . addslashes($data['n_app_title']) . "', app_enabled='"
				. $data['n_app_status'] . "',app_order='" . $data['app_order'] . "' WHERE app_name='" . $data['old_app_name'] . "'";

			$this->db->query($sql,__LINE__,__FILE__);
			return True;
		}

		function exists($app_name)
		{
			$this->db->query("SELECT COUNT(app_name) FROM phpgw_applications WHERE app_name='" . addslashes($app_name) . "'",__LINE__,__FILE__);
			$this->db->next_record();

			if ($this->db->f(0) != 0)
			{
				return True;
			}
			return False;
		}

		function app_order()
		{
			$this->db->query('SELECT (MAX(app_order)+1) AS max FROM phpgw_applications',__LINE__,__FILE__);
			$this->db->next_record();
			return $this->db->f('max');
		}

		function delete($app_name)
		{
			$this->db->query("DELETE FROM phpgw_applications WHERE app_name='$app_name'",__LINE__,__FILE__);
		}

		function register_hook($app)
		{
			$this->db->query("INSERT INTO phpgw_hooks(hook_appname,hook_location,hook_filename) "
				. "VALUES ('".$app['app_name']."','".$app['hook']."','hook_".$app['hook'].".inc.php')",__LINE__,__FILE__
			);
		}
	}
