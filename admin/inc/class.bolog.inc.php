<?php
	/***************************************************************************\
	* phpGroupWare - log                                                        *
	* http://www.phpgroupware.org                                               *
	* Written by : Jerry Westrick [jerry@westrick.com]                          *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; either version 2 of the License, or (at your    *
	* option) any later version.                                                *
	\***************************************************************************/
	/* $Id$ */

	class bolog
	{

		var $public_functions = array
		(
			'read_log'		=> True
		);

		function bolog($session=False)
		{
			global $phpgw;
			$this->so = CreateObject('admin.solog');
		}

		function get_error_cols()
		{
			$fields = $this->so->get_error_cols();
			// boAccounts
			$fields['account_pwd']['include'] = false; 
			return $fields;
		}

		function get_error_cols_e()
		{
			$fields = $this->so->get_error_cols_e();
			$fields['log_date_e'] 		= array();
			$fields['log_msg_date_e'] 	= array();
			$fields['log_full_name'] 	= array(); 
			// boAccounts
			$fields['account_pwd']['include']	= false; 
			$fields['account_lastlogin_e'] 		= array(); 
			$fields['account_lastloginfrom_e'] 	= array(); 
			$fields['account_lastpwd_change_e'] = array(); 
			return $fields;
		}

		function get_error($values='')
		{
			$rows = $this->so->get_error($values);
			// should remove the accounts_pwd
			return $rows;
		}
		
		function get_error_e($values='')
		{
			$rows = $this->so->get_error_e($values);
			
			// Enhance the fields
			reset($rows);
			while(list($rno,$r)=each($rows))
			{
				unset($r['acount_pwd']);	// remove the accounts_pwd
				$r['log_date_e']               = $GLOBALS['phpgw']->common->show_date($GLOBALS['phpgw']->db->from_timestamp($r['log_date']));
				$r['log_msg_date_e']           = $GLOBALS['phpgw']->common->show_date($GLOBALS['phpgw']->db->from_timestamp($r['log_msg_date']));
				$r['log_full_name']            = $r['account_lastname'] . ', ' .$r['account_firstname'];
				$r['account_lastlogin_e']      = $GLOBALS['phpgw']->common->show_date($GLOBALS['phpgw']->db->from_timestamp($r['account_lastlogin']));
				$r['account_lastpwd_change_e'] = $GLOBALS['phpgw']->common->show_date($GLOBALS['phpgw']->db->from_timestamp($r['account_lastpwd_change']));
				$r['account_lastloginfrom_e']  = 'www.nowhere.com'; 

				$r['log_msg_text'] = lang($r['log_msg_msg'],explode('|',$r['log_msg_parms']));

				$rows[$rno]=$r;
			}
			return $rows;
		}
	}
?>
