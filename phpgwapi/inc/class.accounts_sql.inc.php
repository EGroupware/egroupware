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

  class accounts
  {
    var $db;
    var $account_id;
    var $data;

    function accounts($account_id = "")
    {
       global $phpgw_info, $phpgw;

       if (! $account_id) {
          $this->account_id = $phpgw_info["user"]["account_id"];
       }
       $this->db = $phpgw->db;
       //$this->read();
    }

    function read()
    {
       $this->db->query("select * from phpgw_accounts where account_id='" . $this->account_id . "'",__LINE__,__FILE__);
       $this->db->next_record();

       $this->data["userid"]            = $this->db->f("account_id");
       $this->data["account_id"]        = $this->db->f("account_id");
       $this->data["account_lid"]       = $this->db->f("account_lid");
       $this->data["firstname"]         = $this->db->f("account_firstname");
       $this->data["lastname"]          = $this->db->f("account_lastname");
       $this->data["fullname"]          = $this->db->f("account_firstname") . " "
                                        . $this->db->f("account_lastname");
 
 //      $apps = CreateObject('phpgwapi.applications',intval($phpgw_info["user"]["account_id"]));
 //      $prefs = CreateObject('phpgwapi.preferences',intval($phpgw_info["user"]["account_id"]));
 //      $phpgw_info["user"]["preferences"] = $prefs->get_saved_preferences();
 //      $phpgw_info["user"]["apps"] = $apps->enabled_apps();
 
       $this->data["lastlogin"]         = $this->db->f("account_lastlogin");
       $this->data["lastloginfrom"]     = $this->db->f("account_lastloginfrom");
       $this->data["lastpasswd_change"] = $this->db->f("account_lastpwd_change");
       $this->data["status"]            = $this->db->f("account_status");
    }

    function read_repository()
    {
       return $this->data;
    }

    function save_repository()
    {
       global $phpgw_info, $phpgw;
       $db = $phpgw->db;

       /* ********This sets the server variables from the database******** */
       $db->query("select * from config",__LINE__,__FILE__);
       while ($db->next_record()) {
          $phpgw_info["server"][$db->f("config_name")] = $db->f("config_value");
       }

       $phpgw_info_temp["user"]        = $phpgw_info["user"];
       $phpgw_info_temp["apps"]        = $phpgw_info["apps"];
       $phpgw_info_temp["server"]      = $phpgw_info["server"];
       $phpgw_info_temp["hooks"]       = $phpgw->hooks->read();
       $phpgw_info_temp["user"]["preferences"] = $phpgw_info["user"]["preferences"];
       $phpgw_info_temp["user"]["kp3"] = "";                     // We don't want it anywhere in the
                                                                 // database for security.
       if ($PHP_VERSION < "4.0.0") {
          $info_string = addslashes($phpgw->crypto->encrypt($phpgw_info_temp));
       } else {
          $info_string = $phpgw->crypto->encrypt($phpgw_info_temp);       
       }
       $db->query("update phpgw_sessions set session_info='$info_string' where session_id='"
                . $phpgw_info["user"]["sessionid"] . "'",__LINE__,__FILE__);
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


    // This is used to convert a raw group string (,5,6,7,) into a string of
    // there names.
    // Example: accounting, billing, developers
    function convert_string_to_names($gs)
    {
      global $phpgw;

      $groups = explode(",",$gs);

      $s = "";
      for ($i=1;$i<count($groups)-1; $i++) {
	      $group_number = explode(",",$groups[$i]);
        //$phpgw->db->query("select group_name from groups where group_id=".$groups[$i]);
        $phpgw->db->query("select group_name from groups where group_id=".$group_number[0],__LINE__,__FILE__);
        $phpgw->db->next_record();
        $s .= $phpgw->db->f("group_name");
        if (count($groups) != 0 && $i != count($groups)-2)
           $s .= ",";
        }
      return $s;
    }    


    function listusers($group="")
    {
       global $phpgw;

       if ($group) {
          $users = $phpgw->acl->get_ids_for_location($group, 1, "phpgw_group", "u");
          reset ($users);
          $sql = "select account_lid,account_firstname,account_lastname from phpgw_accounts where account_id in (";
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
          $this->db->query("select account_lid,account_firstname,account_lastname from phpgw_accounts",__LINE__,__FILE__);
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

      $this->db->query("SELECT account_id FROM phpgw_accounts WHERE account_lid='".$account_name."'",__LINE__,__FILE__);
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

      $this->db->query("SELECT account_lid FROM phpgw_accounts WHERE account_id='".$account_id."'",__LINE__,__FILE__);
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
 
       $this->db->query("SELECT account_type FROM phpgw_accounts WHERE account_id='".$account_id."'",__LINE__,__FILE__);
       if ($this->db->num_rows()) {
          $this->db->next_record();
          return $this->db->f("account_type");
       } else {
          return False;
       }
    }

    function exists($accountname)
    {
       $this->db->query("SELECT account_id FROM phpgw_accounts WHERE account_lid='".$accountname."'",__LINE__,__FILE__);
       if ($this->db->num_rows()) {
          return True;
       } else {
          return False;
       }
    }

    function auto_generate($accountname, $passwd, $defaultprefs ="")
    {
       global $phpgw, $phpgw_info;
       $accountid = mt_rand (100, 600000);
       if ($defaultprefs =="") {
          $defaultprefs = 'a:5:{s:6:"common";a:1:{s:0:"";s:2:"en";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}i:8;a:1:{s:0:"";s:13:"workdaystarts";}i:15;a:1:{s:0:"";s:11:"workdayends";}s:6:"Monday";a:1:{s:0:"";s:13:"weekdaystarts";}}';
       }
       $sql = "insert into phpgw_accounts";
       $sql .= "(account_id, account_lid, account_pwd, account_firstname, account_lastname, account_lastpwd_change, account_status, account_type)";
       $sql .= "values (".$accountid.", '".$accountname."', '".md5($passwd)."', '".$accountname."', 'AutoCreated', ".time().", 'A','u')";
       $this->db->query($sql);
       $this->db->query("insert into preferences (preference_owner, preference_value) values ('".$accountid."', '$defaultprefs')");
       $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights)values('preferences', 'changepassword', ".$accountid.", 'u', 0)",__LINE__,__FILE__);
       $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('phpgw_group', '1', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
       $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('addressbook', 'run', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
       $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('filemanager', 'run', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
       $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('calendar', 'run', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
       $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('email', 'run', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
       $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('notes', 'run', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
       $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('todo', 'run', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
       return $accountid;
    }
  }        //end of class
?>
