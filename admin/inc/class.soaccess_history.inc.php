<?php

	class soaccess_history
	{
		var $db;

		function soaccess_history()
		{
			global $phpgw;

			$this->db       = $phpgw->db;
		}

		function test_account_id($account_id)
		{
			if ($account_id)
			{
				return " where account_id='$account_id'";
			}
		}

		function list_history($account_id,$start,$order,$sort)
		{
			$where = $this->test_account_id($account_id);

			$this->db->limit_query("select loginid,ip,li,lo,account_id from phpgw_access_log $where order by li desc",$start,__LINE__,__FILE__);
			while ($this->db->next_record())
			{
				$records[] = array(
					'loginid'    => $this->db->f('loginid'),
					'ip'         => $this->db->f('ip'),
					'li'         => $this->db->f('li'),
					'lo'         => $this->db->f('lo'),
					'account_id' => $this->db->f('account_id')
				);
			}
			return $records;
		}

		function total($account_id)
		{
			$where = $this->test_account_id($account_id);

			$this->db->query("select count(*) from phpgw_access_log $where");
			$this->db->next_record();

			return $this->db->f(0);
		}

		function return_logged_out($account_id)
		{
			if ($account_id)
			{
				$where = "and account_id='$account_id'";
			}

			$this->db->query("select count(*) from phpgw_access_log where lo!='' $where");
			$this->db->next_record();

			return $this->db->f(0);
		}
	}