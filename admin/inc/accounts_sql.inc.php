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
        $ordermethod = "order by account_lastname,account_firstname,account_lid asc";
     }

     if (! $sort) {
        $sort = "desc";
     }

     if ($query) {
        $querymethod = " where account_firstname like '%$query%' OR account_lastname like "
        			 . "'%$query%' OR account_lid like '%$query%' ";
     }
  
     $phpgw->db->query("select account_id,account_firstname,account_lastname,account_lid "
     				. "from accounts $querymethod $ordermethod limit "
     				. $phpgw->nextmatchs->sql_limit($start));

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
  
  function account_add($account_info)
  {
     global $phpgw, $phpgw_info;
  
     $phpgw->db->lock(array("accounts","preferences"));

     $phpgw->common->preferences_add($account_info["loginid"],"maxmatchs","common","15");
     $phpgw->common->preferences_add($account_info["loginid"],"theme","common","default");
     $phpgw->common->preferences_add($account_info["loginid"],"tz_offset","common","0");
     $phpgw->common->preferences_add($account_info["loginid"],"dateformat","common","m/d/Y");
     $phpgw->common->preferences_add($account_info["loginid"],"timeformat","common","12");
     $phpgw->common->preferences_add($account_info["loginid"],"lang","common","en");
     $phpgw->common->preferences_add($account_info["loginid"],"company","addressbook","True");
     $phpgw->common->preferences_add($account_info["loginid"],"lastname","addressbook","True");
     $phpgw->common->preferences_add($account_info["loginid"],"firstname","addressbook","True");

     // Even if they don't have access to the calendar, we will add these.
     // Its better then the calendar being all messed up, they will be deleted
     // the next time the update there preferences.
     $phpgw->common->preferences_add($account_info["loginid"],"weekstarts","calendar","Monday");
     $phpgw->common->preferences_add($account_info["loginid"],"workdaystarts","calendar","9");
     $phpgw->common->preferences_add($account_info["loginid"],"workdayends","calendar","17");

     while ($permission = each($account_info["permissions"])) {
       if ($phpgw_info["apps"][$permission[0]]["enabled"]) {
          $phpgw->accounts->add_app($permission[0]);
       }
     }

     $sql = "insert into accounts (account_lid,account_pwd,account_firstname,account_lastname,"
          . "account_permissions,account_groups,account_status,account_lastpwd_change) values ('"
          . $account_info["loginid"] . "','" . md5($account_info["passwd"]) . "','"
          . addslashes($account_info["firstname"]) . "','". addslashes($account_info["lastname"])
          . "','" . $phpgw->accounts->add_app("",True) . "','" . $account_info["groups"] . "','A',0)";

     $phpgw->db->query($sql);
     $phpgw->db->unlock();

     $sep = $phpgw->common->filesystem_separator();

     $basedir = $phpgw_info["server"]["files_dir"] . $sep . "users" . $sep;

     if (! @mkdir($basedir . $n_loginid, 0707)) {
        $cd = 36;
     } else {
        $cd = 28;
     }
     return $cd;
  }
  
  function account_edit($account_info)
  {
     global $phpgw_info, $phpgw;
  
     $phpgw->db->lock(array('accounts','preferences','sessions'));
     
     if ($account_info["c_loginid"]) {
        $phpgw->db->query("update accounts set account_lid='" . $account_info["c_loginid"]
                        . "' where account_lid='" . $account_info["loginid"] . "'");

        $account_info["loginid"] = $account_info["c_loginid"];
     }

     if ($account_info["passwd"]) {
        $phpgw->db->query("update accounts set account_pwd='" . md5($account_info["passwd"]) . "', "
		               . "account_lastpwd_change='" . time() . "' where account_lid='"
		               . $account_info["loginid"] . "'");
        $phpgw->db->query("update sessions set session_pwd='" . addslashes($account_info["passwd"])
                        . "' where session_lid='" . $account_info["loginid"] . "'");
      }

      while ($permission = each($account_info["permissions"])) {
        if ($phpgw_info["apps"][$permission[0]]["enabled"]) {
           $phpgw->accounts->add_app($permission[0]);
        }
      }

      if (! $account_info["account_status"]) {
         $account_info["account_status"] = "L";
      }
      $cd = 27;

      // If they changed there loginid, we need to change the owner in ALL
      // tables to reflect on the new one
      if ($lid != $account_info["loginid"]) {
         change_owner("","preferences","preference_owner",$account_info["loginid"],$lid);
         change_owner("addressbook","addressbook","ab_owner",$account_info["loginid"],$lid);
         change_owner("todo","todo","todo_owner",$account_info["loginid"],$lid);
         change_owner("","accounts","account_lid",$account_info["loginid"],$lid);
         change_owner("","sessions","session_lid",$account_info["loginid"],$lid);
         change_owner("calendar","webcal_entry","cal_create_by",$account_info["loginid"],$lid);
         change_owner("calendar","webcal_entry_user","cal_login",$account_info["loginid"],$lid);

         if ($lid != $n_loginid) {
            $sep = $phpgw->common->filesystem_separator();
	
            $basedir = $phpgw_info["server"]["files_dir"] . $sep . "users" . $sep;

  	      if (! @rename($basedir . $lid, $basedir . $account_info["loginid"])) {
	           $cd = 35;
            }
         }
      }

      $phpgw->db->query("update accounts set account_firstname='"
      			 . addslashes($account_info["firstname"]) . "', account_lastname='"
      			 . addslashes($account_info["lastname"]) . "', account_permissions='"
	  	         . $phpgw->accounts->add_app("",True) . "', account_status='"
			       . $account_info["account_status"] . "', account_groups='"
    		       . $account_info["groups"] . "' where account_lid='" . $account_info["loginid"]
    		       . "'");

      $phpgw->db->unlock();
  
      return $cd;
  }
  
  function account_delete($account_id)
  {
     global $phpgw;
     
     $phpgw->db->query("select account_lid from accounts where account_id=$account_id");
     $phpgw->db->next_record();
     $lid = $phpgw->db->f(0);

     $i = 0;
     $phpgw->db->query("select cal_id from webcal_entry where cal_create_by='$lid'");
     while ($phpgw->db->next_record()) {
       $cal_id[$i] = $phpgw->db->f("cal_id");
       echo "<br>" . $phpgw->db->f("cal_id");
       $i++;
     }

     $table_locks = array('preferences','todo','addressbook','accounts',
                          'webcal_entry','webcal_entry_user','webcal_entry_repeats',
                          'webcal_entry_groups');
     $phpgw->db->lock($table_locks);

     for ($i=0; $i<count($cal_id); $i++) {
        $phpgw->db->query("delete from webcal_entry_repeats where cal_id='$cal_id[$i]'");
        $phpgw->db->query("delete from webcal_entry_groups where cal_id='$cal_id[$i]'");
     }

     $phpgw->db->query("delete from webcal_entry where cal_create_by='$lid'");
     $phpgw->db->query("delete from webcal_entry_user where cal_login='$lid'");

     $phpgw->db->query("delete from todo where todo_owner='$lid'");
     $phpgw->db->query("delete from addressbook where ab_owner='$lid'");
     $phpgw->db->query("delete from accounts where account_lid='$lid'");
     
     $phpgw->common->preferences_delete("all",$lid);

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
  
     $phpgw->db->query("select count(*) from accounts where account_lid='$loginid'");
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
     
     $phpgw->db->query("select count(*) from accounts $querymethod");
     $phpgw->db->next_record();

     return $phpgw->db->f(0);
  }
