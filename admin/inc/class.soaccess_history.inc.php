<?php
	/**************************************************************************\
	* eGroupWare - Administration                                              *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class soaccess_history
	{
		var $db;
		var $table = 'egw_access_log';

		function soaccess_history()
		{
			$this->db = clone($GLOBALS['egw']->db);
			$this->db->set_app('phpgwapi');
		}

		function test_account_id($account_id)
		{
			if ($account_id)
			{
				return array('account_id' => $account_id);
			}
			return false;
		}

		function &list_history($account_id,$start,$order,$sort)
		{
			$where = $this->test_account_id($account_id);

			$this->db->select($this->table,'loginid,ip,li,lo,account_id,sessionid',$where,__LINE__,__FILE__,(int) $start,'ORDER BY li DESC');
			while (($row = $this->db->row(true)))
			{
				$records[] = $row;
			}
			return $records;
		}

		function total($account_id)
		{
			$where = $this->test_account_id($account_id);

			$this->db->select($this->table,'COUNT(*)',$where,__LINE__,__FILE__);

			return $this->db->next_record() ? $this->db->f(0) : 0;
		}

		function return_logged_out($account_id)
		{
			$where = array('lo != 0');
			if ($account_id)
			{
				$where['account_id'] = $account_id;
			}
			$this->db->select($this->table,'COUNT(*)',$where,__LINE__,__FILE__);
			
			return $this->db->next_record() ? $this->db->f(0) : 0;
		}
	}
