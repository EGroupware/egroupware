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
  
  function account_list($start,$sort,$order)
  {
  
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
  
  function account_edit($account_id,$account_info)
  {
  
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
  
  