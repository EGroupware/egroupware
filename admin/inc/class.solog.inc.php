<?php
	/**************************************************************************\
	* phpGroupWare - solog                                                     *
	* http://www.phpgroupware.org                                              *
	* This application written by Jerry Westrick <jerry@westrick.com>          *
	* --------------------------------------------                             *
	* Funding for this program was provided by http://www.checkwithmom.com     *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class solog
	{
		var $db;
		var $owner;
		var $error_cols = '';
		var $error_cols_e = '';
		var $public_functions = array(
			'get_error_cols'   => True,
			'get_error_cols_e' => True,
			'get_error'        => True,
			'get_error_e'      => True
		);

		function solog()
		{
			$this->db = $GLOBALS['phpgw']->db;
		}

		function get_error_cols()
		{
			if ($this->error_cols == '')
			{
				$this->error_cols = array();

				/* fields from phpgw_log table */
				$clist = $this->db->metadata('phpgw_log');
				for ($i=0; $i<count($clist); $i++)
				{
					$name =  $clist[$i]['name'];
					$this->error_cols[$name] = array();
				}

				/* fields from phpgw_log_msg table */
				$clist = $this->db->metadata('phpgw_log_msg');
				for ($i=0; $i<count($clist); $i++)
				{
					$name =  $clist[$i]['name'];
					$this->error_cols[$name] = array();
				}
			}
			return $this->error_cols;
		}

		function get_error_cols_e()
		{
			if ($this->task_cols_e == '')
			{
				/* Get Columns for Errors */
				$this->error_cols_e = $this->get_error_cols();

				/* Enhance with Columns for phpgw_accounts */
				$clist = $this->db->metadata('phpgw_accounts');
				for ($i=0; $i<count($clist); $i++)
				{
					$name =  $clist[$i]['name'];
					$this->error_cols_e[$name] = array();
				}
			}
			return $this->error_cols_e;
		}

		function get_error_e($parms)
		{
if ( false ) {
			/* Fixed From */
			if (!isset($parms['from']))
			{
				$parms['from'] = array('phpgw_accounts');
			}
			else
			{
				$parms['from'][] = 'phpgw_accounts';
			} 

}	
			/* Fix Where */
			if (!isset($parms['where']))
			{
				$parms['where'] = array('phpgw_log.log_user = phpgw_accounts.account_id');
			}
			else
			{
				$parms['where'][] = 'phpgw_log.log_user = phpgw_accounts.account_id';
			}
			/* Fix Default Fields */
			if (!isset($parms['fields']))
			{
				$parms['fields'] = $this->get_error_cols_e();
			}
			
			return $this->get_error($parms);
		}

		function get_no_errors()
		{
			/* Get max ErrorId */
			$this->db->query("select count(*) as max_id from phpgw_log, phpgw_log_msg WHERE phpgw_log.log_id = phpgw_log_msg.log_msg_log_id",__LINE__,__FILE__);
			$this->db->next_record();
			return $this->db->f('max_id');
		}

		function get_error($parms)
		{
			/* Get parameter values */
			$from    = $parms['from'];
			$where   = $parms['where'];
			$orderby = $parms['orderby'];
			$fields  = $parms['fields'];
			
			/* Build From_Clause */
			$from_clause = 'FROM phpgw_log, phpgw_log_msg ';
			if (isset($from))
			{
				$from[] = 'phpgw_log';
				$from[] = 'phpgw_log_msg';
				$from_clause = 'FROM '.implode(', ' , $from).' ';
			}

			/* Build Where_Clause */
			$where_clause = 'WHERE phpgw_log.log_id = phpgw_log_msg.log_msg_log_id ';
			if (isset($where))
			{
				$where[] = 'phpgw_log.log_id = phpgw_log_msg.log_msg_log_id';
				$where_clause = 'WHERE ' . implode(' AND ',$where) . ' ';
			}

			/* Build Order_By_Clause */
			$orderby_clause = 'ORDER BY phpgw_log.log_id, phpgw_log_msg.log_msg_seq_no ';
			if (isset($orderby))
			{
				$orderby_clause = 'ORDER BY ' . implode(', ',$orderby);
			}

			/* If no Fields specified default to * */
			if (!isset($fields))
			{
				$fields = $this->get_error_cols();
			}

			$rows = array();

			/* Do Select  */
			@reset($fields);
			while(list($key,$val) = @each($fields))
			{
				$fkeys .= $key . ',';
			}
			$fkeys = substr($fkeys,0,-1);

			$select = 'SELECT ' . $fkeys . ' ' . $from_clause . $where_clause . $orderby_clause;
			$this->db->query($select,__LINE__,__FILE__);
			while($this->db->next_record())
			{
				reset($fields);
				while(list($fname,$fopt) = each($fields))
				{
					$this_row[$fname]['value'] = $this->db->f($fname);
				}
				$rows[] = $this_row;
			}
			return $rows;
		}
	}
