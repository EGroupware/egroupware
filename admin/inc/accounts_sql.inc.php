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
