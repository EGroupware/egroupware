<?php
  /**************************************************************************\
  * phpGroupWare API - Accounts manager for LDAP                             *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * and Lars Kneschke <kneschke@phpgroupware.org>                            *
  * View and manipulate account records using LDAP                           *
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

      // get a ldap connection handle
      $ds = $phpgw->common->ldapConnect();
      // search the dn for the given uid
      $sri = ldap_search($ds, $phpgw_info["server"]["ldap_context"], "uid=".$phpgw_info["user"]["userid"]);
      $allValues = ldap_get_entries($ds, $sri);

      /* Now dump it into the array; take first entry found */
      $phpgw_info["user"]["account_id"]  = $allValues[0]["uidnumber"][0];
      $phpgw_info["user"]["account_dn"]  = $allValues[0]["dn"];
#      $phpgw_info["user"]["account_lid"] = $allValues[0]["uid"][0];
      $phpgw_info["user"]["firstname"]   = $allValues[0]["givenname"][0];
      $phpgw_info["user"]["lastname"]    = $allValues[0]["sn"][0];
      $phpgw_info["user"]["fullname"]    = $allValues[0]["cn"][0];
      
      $this->db->query("select * from accounts where account_lid='" . $phpgw_info["user"]["userid"] . "'",__LINE__,__FILE__);
      $this->db->next_record();
      
      $phpgw_info["user"]["groups"]            = explode (",",$this->db->f("account_groups"));
#      $apps = CreateObject('phpgwapi.applications',array(intval($phpgw_info["user"]["account_id"]),'u'));
#      $phpgw_info["user"]["app_perms"]         = $apps->app_perms;
      $phpgw_info["user"]["lastlogin"]         = $this->db->f("account_lastlogin");
      $phpgw_info["user"]["lastloginfrom"]     = $this->db->f("account_lastloginfrom");
      $phpgw_info["user"]["lastpasswd_change"] = $this->db->f("account_lastpwd_change");
      $phpgw_info["user"]["status"]            = $this->db->f("account_status");                                                                   
    }

    function read_userData($dn)
    {
      global $phpgw_info, $phpgw;

      // get a ldap connection handle
      $ds = $phpgw->common->ldapConnect();
	
      // search the dn for the given uid
      $sri = ldap_read($ds,rawurldecode("$dn"),"objectclass=*");
      $allValues = ldap_get_entries($ds, $sri);

      /* Now dump it into the array; take first entry found */
      $userData["account_id"]  = $allValues[0]["uidnumber"][0];
      $userData["account_dn"]  = $allValues[0]["dn"];
      $userData["account_lid"] = $allValues[0]["uid"][0];
      $userData["firstname"]   = $allValues[0]["givenname"][0];
      $userData["lastname"]    = $allValues[0]["sn"][0];
      $userData["fullname"]    = $allValues[0]["cn"][0];
      
/*    // Please don't remove this code. Lars Kneschke
      // remove the "count" value
      for ($i=0; $i < $allValues[0]["phpgw_groups"]["count"]; $i++)
      {
      	$userData["groups"][] = $allValues[0]["phpgw_groups"][$i];
      }
      
      // remove the "count" value
      for ($i=0; $i < $allValues[0]["phpgw_app_perms"]["count"]; $i++)
      {
      	$userData["app_perms"][] = $allValues[0]["phpgw_account_perms"][$i];
      }

      $userData["lastlogin"]         = $allValues[0]["phpgw_lastlogin"][0];
      $userData["lastloginfrom"]     = $allValues[0]["phpgw_lastfrom"][0];
      $userData["lastpasswd_change"] = $allValues[0]["phpgw_lastpasswd_change"][0];
      $userData["status"]            = $allValues[0]["phpgw_status"][0];
*/

      $db = $phpgw->db;
      $db->query("select * from accounts where account_lid='" . $userData["account_lid"] . "'",__LINE__,__FILE__);
      $db->next_record();
      
      $userData["groups"]            = explode (",",$db->f("account_groups"));
      $apps = CreateObject('phpgwapi.applications',array(intval($userData["account_id"]),'u'));
      $userData["app_perms"]         = $apps->app_perms;
      $userData["lastlogin"]         = $db->f("account_lastlogin");
      $userData["lastloginfrom"]     = $db->f("account_lastloginfrom");
      $userData["lastpasswd_change"] = $db->f("account_lastpwd_change");
      $userData["status"]            = $db->f("account_status");                                                                   

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
        $groups[intval($group_memberships[$idx])] = 1;
      }
      return $groups;
    }

    function read_group_names($lid = "")
    {
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

       $db2 = $phpgw->db;

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
          $db2->query($sql,__LINE__,__FILE__);
       } else {
          $db2->query("select account_lid,account_firstname,account_lastname from accounts",__LINE__,__FILE__);
       }
       $i = 0;
       while ($db2->next_record()) {
          $accounts["account_lid"][$i]       = $db2->f("account_lid");
          $accounts["account_firstname"][$i] = $db2->f("account_firstname");
          $accounts["account_lastname"][$i]  = $db2->f("account_lastname");
    	  $i++;
       }
       return $accounts;
    }


    function name2id($user_name)
    {
      global $phpgw, $phpgw_info;
      $db2 = $phpgw->db;
      $db2->query("SELECT account_id FROM accounts WHERE account_lid='".$user_name."'",__LINE__,__FILE__);
      if($db2->num_rows()) {
        $db2->next_record();
        return $db2->f("account_id");
      }else{
        return False;
      }
    }

    function id2name($user_id)
    {
      global $phpgw, $phpgw_info;
      $db2 = $phpgw->db;
      $db2->query("SELECT account_lid FROM accounts WHERE account_id='".$user_id."'",__LINE__,__FILE__);
      if($db2->num_rows()) {
        $db2->next_record();
        return $db2->f("account_lid");
      }else{
        return False;
      }
    }

    function groupname2groupid($group_name)
    {
      global $phpgw, $phpgw_info;
      $db2 = $phpgw->db;
      $db2->query("SELECT group_id FROM groups WHERE group_name='".$group_name."'",__LINE__,__FILE__);
      if($db2->num_rows()) {
        $db2->next_record();
        return $db2->f("group_id");
      }else{
        return False;
      }
    }

    function groupid2groupname($group_id)
    {
      global $phpgw, $phpgw_info;
      $db2 = $phpgw->db;
      $db2->query("SELECT group_name FROM groups WHERE group_id='".$group_id."'",__LINE__,__FILE__);
      if($db2->num_rows()) {
        $db2->next_record();
        return $db2->f("group_name");
      }else{
        return False;
      }
    }

    function get_type($account_id)
    {
      global $phpgw, $phpgw_info;
      
      return "u";
    }

    function exists($accountname)
    {
    	return True;
    }
  }
