<?php
  /**************************************************************************\
  * phpGroupWare API - Accounts manager for SQL                              *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * and Dan Kuykendall <seek3r@phpgroupware.org>                             *
  * View and manipulate account records using SQL                            *
  * Copyright (C) 2000, 2001 Joseph Engo                                     *
  * -------------------------------------------------------------------------*
  * This library is part of the phpGroupWare API                             *
  * http://www.phpgroupware.org/api                                          * 
  * ------------------------------------------------------------------------ *
  * This library is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published by *
  * the Free Software Foundation; either version 2.1 of the License,         *
  * or any later version.                                                    *
  * This library is distributed in the hope that it will be useful, but      *
  * WITHOUT ANY WARRANTY; without even the implied warranty of               *
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
  * See the GNU Lesser General Public License for more details.              *
  * You should have received a copy of the GNU Lesser General Public License *
  * along with this library; if not, write to the Free Software Foundation,  *
  * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
  \**************************************************************************/

  /* $Id$ */

  class accounts_
  {
    var $groups;
    var $group_names;
    var $apps;
    var $db;
    
    function accounts_()
    {
       global $phpgw;
       $this->db = $phpgw->db;
    }

    function fill_user_array()
    {
      global $phpgw_info, $phpgw;

      $this->db->query("select * from accounts where account_lid='" . $phpgw_info["user"]["userid"] . "'",__LINE__,__FILE__);
      $this->db->next_record();
    
      /* Now dump it into the array */
      $phpgw_info["user"]["account_id"]        = $this->db->f("account_id");
      $phpgw_info["user"]["firstname"]         = $this->db->f("account_firstname");
      $phpgw_info["user"]["lastname"]          = $this->db->f("account_lastname");
      $phpgw_info["user"]["fullname"]          = $this->db->f("account_firstname") . " "
                                               . $this->db->f("account_lastname");
      $phpgw_info["user"]["groups"]            = explode (",", $this->db->f("account_groups"));

//      $apps = CreateObject('phpgwapi.applications',intval($phpgw_info["user"]["account_id"]));
//      $prefs = CreateObject('phpgwapi.preferences',intval($phpgw_info["user"]["account_id"]));
//      $phpgw_info["user"]["preferences"] = $prefs->get_saved_preferences();
//      $phpgw_info["user"]["apps"] = $apps->enabled_apps();

      $phpgw_info["user"]["lastlogin"]         = $this->db->f("account_lastlogin");
      $phpgw_info["user"]["lastloginfrom"]     = $this->db->f("account_lastloginfrom");
      $phpgw_info["user"]["lastpasswd_change"] = $this->db->f("account_lastpwd_change");
      $phpgw_info["user"]["status"]            = $this->db->f("account_status");
    }

    function read_userData($id)
    {
      global $phpgw_info, $phpgw;
      
      $this->db->query("select * from accounts where account_id='$id'",__LINE__,__FILE__);
      $this->db->next_record();
    
      /* Now dump it into the array */
      $userData["account_id"]        = $this->db->f("account_id");
      $userData["account_lid"]       = $this->db->f("account_lid");
      $userData["firstname"]         = $this->db->f("account_firstname");
      $userData["lastname"]          = $this->db->f("account_lastname");
      $userData["fullname"]          = $this->db->f("account_firstname") . " "
                                               . $this->db->f("account_lastname");
      $userData["groups"]            = explode(",", $this->db->f("account_groups"));
//      $apps = CreateObject('phpgwapi.applications',intval($phpgw_info["user"]["account_id"]));
//      $prefs = CreateObject('phpgwapi.preferences',intval($phpgw_info["user"]["account_id"]));
//      $userData["preferences"] = $prefs->get_saved_preferences();
//      $userData["apps"] = $apps->enabled_apps();

      $userData["lastlogin"]         = $this->db->f("account_lastlogin");
      $userData["lastloginfrom"]     = $this->db->f("account_lastloginfrom");
      $userData["lastpasswd_change"] = $this->db->f("account_lastpwd_change");
      $userData["status"]            = $this->db->f("account_status");
      
      return $userData;
    }

    function read_groups($id)
    {
      global $phpgw_info, $phpgw;

      if (gettype($id) == "string") { $id = $this->name2id($id); }
      $groups = Array();
      $group_memberships = $phpgw->acl->get_location_list_for_id("phpgw_group", 1, intval($id));
      if (!$group_memberships) { return False; }
      for ($idx=0; $idx<count($group_memberships); $idx++){
        $groups[$group_memberships[$idx]] = 1;
      }
      return $groups;
    }

    function read_group_names($lid = ""){
      return $this->security_equals($lid);
    }

    function security_equals($lid = "")
    {
       global $phpgw, $phpgw_info;

       if (! $lid) {
          $lid = $phpgw_info["user"]["userid"];
       }
       $groups = $this->read_groups($lid);

       $i = 0;
       while ($groups && $group = each($groups)) {
          $this->db->query("select group_name from groups where group_id=".$group[0],__LINE__,__FILE__);
          $this->db->next_record();
          $group_names[$i][0]   = $group[0];
          $group_names[$i][1]   = $this->db->f("group_name");
          $group_names[$i++][2] = $group[1];
       }

       if (! $lid) {
          $this->group_names = $group_names;
       }

       return $group_names;
    }

    function listusers($group="")
    {
       global $phpgw;

       if ($group) {
          $users = $phpgw->acl->get_ids_for_location($group, 1, "phpgw_group", "u");
          reset ($users);
          $sql = "select account_lid,account_firstname,account_lastname from accounts where account_id in (";
          for ($idx=0; $idx<count($num); ++$idx){
            if ($idx == 1){
              $sql .= $users[$idx];
            }else{
              $sql .= ",".$users[$idx];
            }
          }
          $sql .= ")";
          $this->db->query($sql,__LINE__,__FILE__);
       } else {
          $this->db->query("select account_lid,account_firstname,account_lastname from accounts",__LINE__,__FILE__);
       }
       $i = 0;
       while ($this->db->next_record()) {
          $accounts["account_lid"][$i]       = $this->db->f("account_lid");
          $accounts["account_firstname"][$i] = $this->db->f("account_firstname");
          $accounts["account_lastname"][$i]  = $this->db->f("account_lastname");
    	  $i++;
       }
       return $accounts;
    }

    function name2id($account_name)
    {
      global $phpgw, $phpgw_info;

      $this->db->query("SELECT account_id FROM accounts WHERE account_lid='".$account_name."'",__LINE__,__FILE__);
      if($this->db->num_rows()) {
        $this->db->next_record();

        return $this->db->f("account_id");
      }else{
        return False;
      }
    }

    function id2name($account_id)
    {
      global $phpgw, $phpgw_info;

      $this->db->query("SELECT account_lid FROM accounts WHERE account_id='".$account_id."'",__LINE__,__FILE__);
      if($this->db->num_rows()) {
        $this->db->next_record();
        return $this->db->f("account_lid");
      }else{
        return False;
      }
    }

    function get_type($account_id)
    {
      global $phpgw, $phpgw_info;

/*
      $this->db->query("SELECT account_type FROM accounts WHERE account_id='".$account_id."'",__LINE__,__FILE__);
      if($this->db->num_rows()) {
        $this->db->next_record();
        return $this->db->f("account_type");
      }else{
        return False;
      }
*/
return "u";      
    }

    function exists($accountname){
      $this->db->query("SELECT account_id FROM accounts WHERE account_lid='".$accountname."'",__LINE__,__FILE__);
      if($this->db->num_rows()) {
        return True;
      }else{
        return False;
      }
    }

    function auto_generate($accountname, $passwd, $defaultprefs =""){
      global $phpgw, $phpgw_info;
      $accountid = mt_rand (100, 600000);
      if ($defaultprefs ==""){ $defaultprefs = 'a:5:{s:6:"common";a:1:{s:0:"";s:2:"en";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}i:8;a:1:{s:0:"";s:13:"workdaystarts";}i:15;a:1:{s:0:"";s:11:"workdayends";}s:6:"Monday";a:1:{s:0:"";s:13:"weekdaystarts";}}';  }
      $sql = "insert into accounts";
      $sql .= "(account_id, account_lid, account_pwd, account_firstname, account_lastname, account_lastpwd_change, account_status)";
      $sql .= "values (".$accountid.", '".$accountname."', '".md5($passwd)."', '".$accountname."', 'AutoCreated', ".time().", 'A')";
      $this->db->query($sql);
      $this->db->query("insert into preferences (preference_owner, preference_value) values ('".$accountid."', '$defaultprefs')");
      $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights)values('preferences', 'changepassword', ".$accountid.", 'u', 0)");
      $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('phpgw_group', '1', ".$accountid.", 'u', 1)");
      $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('addressbook', 'run', ".$accountid.", 'u', 1)");
      $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('filemanager', 'run', ".$accountid.", 'u', 1)");
      $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('calendar', 'run', ".$accountid.", 'u', 1)");
      $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('email', 'run', ".$accountid.", 'u', 1)");
      $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('notes', 'run', ".$accountid.", 'u', 1)");
      $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('todo', 'run', ".$accountid.", 'u', 1)");
      return $accountid;
    }
  }//end of class
?>
