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
		var $public_functions = array
		        ('get_error_cols'	=> True
		        ,'get_error_cols_e'=> True
		        ,'get_error'     	=> True
		        ,'get_error_e'		=> True
				);

		function solog()
		{
			global $phpgw;
			$this->db = $phpgw->db;
		}

		function get_error_cols()
		{
			if ($this->error_cols == '')
			{
				$this->error_cols = array();

				// fields from phpgw_log table
				$clist = $this->db->metadata('phpgw_log');
				for ($i=0; $i<count($clist); $i++)
				{
					$name =  $clist[$i]['name'];
					$this->error_cols[$name] = array();
				}

				// fields from phpgw_log_msg table
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
				// Get Columns for Errors
				$this->error_cols_e = $this->get_error_cols();

				// Enhance with Columns for phpgw_accounts
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
			// Fixed From
			if ($parms['from'] == '')
			{
				$parms['from'] = array('phpgw_accounts');
			}
			else
			{
				$parms['from'][] = 'phpgw_accounts';
			} 

			// Fix Where
			if ($parms['where'] == '')
			{
				$parms['where'] = array('phpgw_log.log_user = phpgw_accounts.account_id');
			}
			else
			{
				$parms['where'][] = 'phpgw_log.log_id = phpgw_accounts.account_id';
			}
			
			// Fix Default Fields
			if ($parms['fields'] == '')
			{
				$parms['fields'] = $this->get_error_cols_e();
			}
			
			return $this->get_error($parms);
		}

		function get_error($parms)
		{	// Get paramenter values
			$from    = $parms['from'];
			$where   = $parms['where'];
			$orderby = $parms['orderby'];
			$fields  = $parms['fields'];
			
			// Build From_Clause
			$from_clause = 'FROM phpgw_log, phpgw_log_msg ';
			if ($from != '')
			{
				$from[] = 'phpgw_log';
				$from[] = 'phpgw_log_msg';
				$from_clause = 'FROM '.implode(', ' , $from).' ';
			}
		    
			// Build Where_Clause
			$where_clause = 'WHERE phpgw_log.log_id = phpgw_log_msg.log_msg_log_id ';
			if ($where != '')
			{
				$where[] = 'phpgw_log.log_id = phpgw_log_msg.log_msg_log_id';
				$where_clause = 'WHERE ' . implode(' AND ',$where) . ' ';
			}
		
			// Build Order_By_Clause
			$orderby_clause = 'Order By phpgw_log.log_id, phpgw_log_msg.log_msg_seq_no ';
			if ($orderby != '')
			{
				$orderby_clause = 'Order By ' . implode(', ',$orderby);
			}
			
			// If no Fields specified default to *
			if ($fields == '')
			{
				$fields = $this->error_cols;
			}
			
			$rows = array();
			$rowno = 0;
			
			// Do Select 
			$this->db->query("select ". implode(', ',array_keys($fields)).' '.$from_clause.$where_clause.$orderby_clause,__LINE__,__FILE__);
			while($this->db->next_record())
			{
				reset($fields);
				while(list($fname,$fopt) = each($fields))
				{
					$this_row[$fname] = $this->db->f($fname);
				};
				$rows[$rowno] = $this_row;
				$rowno++;
			};
			return $rows;
		}
	}
