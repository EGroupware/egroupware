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

  $phpgw_info = array();

  if ($confirm || ! $account_id) {
     $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);
  }

  $phpgw_info["flags"]["currentapp"] = "admin";
  include("../header.inc.php");
  $phpgw->template->set_file(array("body" => "delete_common.tpl"));

  // I didn't active this code until all tables are up to date using the owner field
  // The calendar isn't update to date.  (jengo)
  // NOTE: This is so I don't forget, add a double explode() to the app_tables field
  //       to say what the name of the owner field is.
  function delete_users_records($account_id, $permissions)
  {
     global $phpgw;
     
     $db2 = $phpgw->db;

     while ($permission = each($permissions)) {
       $db2->query("select app_tables from applications where app_name='$permission[0]'");
       $db2->next_record();

       if ($db2->f("app_tables")) {
          $tables = explode(",",$db2->f("app_tables"));
          while (list($null,$table) = each($tables)) {
            $db2->query("delete from $table where owner='$account_id'");
          }
       }
     }        // end while
  }           // end function


  
  // Make sure they are not attempting to delete there own account.
  // If they are, they should not reach this point anyway.
  if ($phpgw_info["user"]["account_id"] == $account_id) {
     Header('Location: ' . $phpgw->link('/admin/accounts.php'));
     $phpgw->common->phpgw_exit();
  }

  if (($account_id) && (! $confirm)) {
     // the account can have special chars/white spaces, if it is a ldap dn
     $account_id = rawurlencode($account_id);
     $phpgw->template->set_var("messages",lang("Are you sure you want to delete this account ?") . "<br>"
                             . "<font color=\"red\"><blink>" . lang("All records and account information will be lost!") . "</blink></font>");
     $phpgw->template->set_var("yes",'<a href="' . $phpgw->link("/admin/deleteaccount.php","account_id=$account_id&confirm=true")
                                   . '">' . lang("Yes") . '</a>');
     $phpgw->template->set_var("no",'<a href="' . $phpgw->link("/admin/accounts.php")
                                  . '">' . lang("No") . '</a>');
     $phpgw->template->pparse("out","body");

     $phpgw->common->phpgw_footer();
  }

	if ($confirm) {
		$accountid = get_account_id($account_id);
		$lid = $phpgw->accounts->id2name($accountid);
		$table_locks = array('phpgw_preferences','todo','phpgw_addressbook','phpgw_accounts');

		$cal = CreateObject('calendar.calendar');
		$cal_stream = $cal->open('INBOX',$accountid,'');

		$cal->delete_calendar($cal_stream,$accountid);

		$phpgw->db->lock($table_locks);
// This really needs to fall back on the app authors job to write the delete routines for their apps.
// I need to get with Milosch and have him write a small hook for deleting ALL records for an owner.
		$phpgw->db->query('delete from todo where todo_owner='.$accountid);
		$phpgw->db->query('delete from phpgw_addressbook where owner='.$accountid);
		$phpgw->db->query('delete from phpgw_preferences where preference_owner='.$accountid);

		$phpgw->accounts->delete($accountid);
		$phpgw->db->unlock();

		$sep = $phpgw->common->filesystem_separator();

		$basedir = $phpgw_info['server']['files_dir'] . $sep . 'users' . $sep;

		if (! @rmdir($basedir . $lid))
		{
			$cd = 34;
		}
		else
		{
			$cd = 29;
		}

		Header("Location: " . $phpgw->link("/admin/accounts.php","cd=$cd"));
	}
?>
