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
  
  function account_read($method,$start,$sort,$order)
  {
     global $phpgw;
     
     if (! $start) {
        $start = 0;
     }

     if ($order) {
        $ordermethod = "order by $order $sort";
     } else {
        $ordermethod = "order by account_lid,account_lastname,account_firstname asc";
     }

     if (! $sort) {
        $sort = "desc";
     }

     if ($query) {
        $querymethod = " where account_firstname like '%$query%' OR account_lastname like "
        			 . "'%$query%' OR account_lid like '%$query%' ";
     }
  
     $phpgw->db->query("select account_id,account_firstname,account_lastname,account_lid "
     				. "from phpgw_accounts $querymethod $ordermethod " . $phpgw->db->limit($start));

     $i = 0;
     while ($phpgw->db->next_record()) {
        $account_info[$i]["account_id"]        = $phpgw->db->f("account_id");
        $account_info[$i]["account_lid"]       = $phpgw->db->f("account_lid");
        $account_info[$i]["account_lastname"]  = $phpgw->db->f("account_lastname");
        $account_info[$i]["account_firstname"] = $phpgw->db->f("account_firstname");
        $i++;
     }

     return $account_info;
  }
  
  function account_view($loginid)
  {
    global $phpgw_info, $phpgw;

    $phpgw->db->query("select account_id,account_firstname,account_lastname from phpgw_accounts where "
    				. "account_lid='$loginid'");
    $phpgw->db->next_record();
        
    $account_info["account_id"]        = $phpgw->db->f("account_id");
    $account_info["account_lid"]       = $loginid;
    $account_info["account_lastname"]  = $phpgw->db->f("account_lastname");
    $account_info["account_firstname"] = $phpgw->db->f("account_firstname");

    return $account_info;
  }
  
  function account_add($account_info)
  {
     global $phpgw, $phpgw_info;
  
     $sql = "insert into accounts (account_lid,account_pwd,account_firstname,account_lastname,"
          . "account_status,account_lastpwd_change) values ('"
          . $account_info["loginid"] . "','" . md5($account_info["passwd"]) . "','"
          . addslashes($account_info["firstname"]) . "','". addslashes($account_info["lastname"])
          . "','A',0)";

     $phpgw->db->query($sql,__LINE__,__FILE__);

     $sep = $phpgw->common->filesystem_separator();

     $basedir = $phpgw_info["server"]["files_dir"] . $sep . "users" . $sep;
     //echo "TEST: " . $basedir . $account_info["loginid"];
     if (! @mkdir($basedir . $account_info["loginid"], 0707)) {
        $cd = 36;
     } else {
        $cd = 28;
     }
     return $cd;
  }
  
  function account_edit($account_info)
  {
     global $phpgw_info, $phpgw;
  
//     $lid = $account_info["loginid"];

     if ($account_info["old_loginid"] != $account_info["loginid"]) {
        $phpgw->db->query("update accounts set account_lid='" . $account_info["loginid"]
                        . "' where account_lid='" . $account_info["old_loginid"] . "'");
        $phpgw->db->query("update phpgw_sessions set session_lid='" . $account_info["loginid"]
                        . "' where session_lid='" . $account_info["old_loginid"] . "'");

//        $account_info["loginid"] = $account_info["n_loginid"];
     }

     if ($account_info["passwd"]) {
        $phpgw->db->query("update accounts set account_pwd='" . md5($account_info["passwd"]) . "', "
		               . "account_lastpwd_change='" . time() . "' where account_lid='"
		               . $account_info["loginid"] . "'");
		if ($account_info["account_id"] == $phpgw_info["user"]["account_id"]) {
 		  $phpgw_info["user"]["passwd"] = $phpgw->common->encrypt($account_info["passwd"]);
		}
//        $phpgw->db->query("update phpgw_sessions set session_pwd='" . addslashes($account_info["passwd"])
//                        . "' where session_lid='" . $account_info["loginid"] . "'");
      }

      if (! $account_info["account_status"]) {
         $account_info["account_status"] = "L";
      }
      $cd = 27;

      if ($account_info["old_loginid"] != $account_info["loginid"]) {
         $sep = $phpgw->common->filesystem_separator();
	
         $basedir = $phpgw_info["server"]["files_dir"] . $sep . "users" . $sep;

         if (! @rename($basedir . $account_info["old_loginid"], $basedir . $account_info["loginid"])) {
            $cd = 35;
         }
      }

      $phpgw->db->query("update accounts set account_firstname='"
      			 . addslashes($account_info["firstname"]) . "', account_lastname='"
      			 . addslashes($account_info["lastname"]) . "', account_status='"
			       . $account_info["account_status"] . "' where account_lid='" . $account_info["loginid"]
    		       . "'");

      return $cd;
  }
  
  function account_delete($account_id)
  {
     global $phpgw;
     global $phpgw_info;

     
     $phpgw->db->query("select account_lid from accounts where account_id=$account_id");
     $phpgw->db->next_record();
     $lid = $phpgw->db->f(0);

     $table_locks = array('preferences','todo','addressbook','accounts');

     include($phpgw_info["server"]["server_root"]."/calendar/inc/functions.inc.php");
     $phpgw->calendar->delete($lid);

     $phpgw->db->lock($table_locks);

     $phpgw->db->query("delete from todo where todo_owner='".$account_id."'");
     $phpgw->db->query("delete from addressbook where ab_owner='".$account_id."'");
     $phpgw->db->query("delete from accounts where account_id='".$account_id."'");
     $phpgw->db->query("delete from preferences where preference_owner='".$account_id."'");

     $phpgw->db->unlock();

     $sep = $phpgw->common->filesystem_separator();

     $basedir = $phpgw_info["server"]["files_dir"] . $sep . "users" . $sep;

     if (! @rmdir($basedir . $lid)) {
        $cd = 34;
     } else {
        $cd = 29;
     }
     return $cd;
  }

  function account_exsists($loginid)
  {
     global $phpgw;
  
     $phpgw->db->query("select count(*) from phpgw_accounts where account_lid='$loginid'");
     $phpgw->db->next_record();
     if ($phpgw->db->f(0) != 0) {
        return True;
     } else {
        return False;
     }  
  }
  
  function account_total()
  {
     global $phpgw, $query;

     if ($query) {
        $querymethod = " where account_firstname like '%$query%' OR account_lastname like "
        			 . "'%$query%' OR account_lid like '%$query%' ";
     }
     
     $phpgw->db->query("select count(*) from phpgw_accounts $querymethod");
     $phpgw->db->next_record();

     return $phpgw->db->f(0);
  }

  // This is need for LDAP, so this is a dummy function.  
  function account_close()
  {
     return True;
  }
