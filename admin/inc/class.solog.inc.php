<?php
	/**************************************************************************\
	* eGroupWare - solog                                                       *
	* http://www.egroupware.org                                                *
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
		var $accounts_table = 'egw_accounts';
		var $log_table      = 'egw_log';
		var $msg_table      = 'egw_log_msg';
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
			$this->db = clone($GLOBALS['egw']->db);
		}

		function get_error_cols()
		{
			if ($this->error_cols == '')
			{
				$this->error_cols = array();

				/* fields from log table */
				$clist = $this->db->metadata($this->log_table);
				for ($i=0; $i<count($clist); $i++)
				{
					$name =  $clist[$i]['name'];
					$this->error_cols[$name] = array();
				}

				/* fields from msg table */
				$clist = $this->db->metadata($this->msg_table);
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

				/* Enhance with Columns from accounts-table */
				$clist = $this->db->metadata($this->accounts_table);
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

			/* Fixed From */
			if (!isset($parms['from']))
			{
				$parms['from'] = array($this->accounts_table);
			}
			else
			{
				$parms['from'][] = $this->accounts_table;
			} 

			/* Fix Where */
			if (!isset($parms['where']))
			{
				$parms['where'] = array("$this->log_table.log_user = $this->accounts_table.account_id");
			}
			else
			{
				$parms['where'][] = "$this->log_table.log_user = $this->accounts_table.account_id";
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
			$this->db->query("select count(*) as max_id from $this->log_table, $this->msg_table WHERE $this->log_table.log_id = $this->msg_table.log_msg_log_id",__LINE__,__FILE__);

			return $this->db->next_record() ? $this->db->f('max_id') : 0;
		}

		function get_error($parms)
		{
			/* Get parameter values */
			$from    = $parms['from'];
			$where   = $parms['where'];
			$orderby = $parms['orderby'];
			$fields  = $parms['fields'];
			
			/* Build From_Clause */
			$from_clause = "FROM $this->log_table ,  $this->msg_table ";
			if (isset($from))
			{
				$from[] = $this->log_table;
				$from[] = $this->msg_table;
				$from_clause = 'FROM '.implode(', ' , $from).' ';
			}

			/* Build Where_Clause */
			$where_clause = "WHERE $this->log_table.log_id = $this->msg_table.log_msg_log_id ";
			if (isset($where))
			{
				$where[] = "$this->log_table.log_id = $this->msg_table.log_msg_log_id";
				$where_clause = 'WHERE ' . implode(' AND ',$where) . ' ';
			}

			/* Build Order_By_Clause */
			$orderby_clause = "ORDER BY $this->log_table.log_id, $this->msg_table.log_msg_seq_no ";
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

			$select = 'SELECT ' . implode(',',array_keys($fields)) . ' ' . $from_clause . $where_clause . $orderby_clause;
			$this->db->query($select,__LINE__,__FILE__);
			while($this->db->next_record())
			{
				foreach($fields as $fname => $fopt) 
				{
					$this_row[$fname]['value'] = $this->db->f($fname);
				}
				$rows[] = $this_row;
			}
			return $rows;
		}
	}
