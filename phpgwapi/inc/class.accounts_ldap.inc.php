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
      $phpgw_info["user"]["account_lid"] = $allValues[0]["uid"][0];
      $phpgw_info["user"]["firstname"]   = $allValues[0]["givenname"][0];
      $phpgw_info["user"]["lastname"]    = $allValues[0]["sn"][0];
      $phpgw_info["user"]["fullname"]    = $allValues[0]["cn"][0];
      
/*
      // Please don't remove this code. Lars Kneschke
      // remove the "count" value
      for ($i=0; $i < $allValues[0]["phpgw_groups"]["count"]; $i++)
      {
      	$phpgw_info["user"]["groups"][] = $allValues[0]["phpgw_groups"][$i];
      }
      
      // remove the "count" value
      for ($i=0; $i < $allValues[0]["phpgw_account_perms"]["count"]; $i++)
      {
      	$phpgw_info["user"]["app_perms"][] = $allValues[0]["phpgw_account_perms"][$i];
      }

      $phpgw_info["user"]["lastlogin"]         = $allValues[0]["phpgw_lastlogin"][0];
      $phpgw_info["user"]["lastloginfrom"]     = $allValues[0]["phpgw_lastfrom"][0];
      $phpgw_info["user"]["lastpasswd_change"] = $allValues[0]["phpgw_lastpasswd_change"][0];
      $phpgw_info["user"]["status"]            = $allValues[0]["phpgw_status"][0];
*/
      $db = $phpgw->db;
      $db->query("select * from accounts where account_lid='" . $phpgw_info["user"]["userid"] . "'",__LINE__,__FILE__);
      $db->next_record();
      
      $phpgw_info["user"]["groups"]            = explode (",",$db->f("account_groups"));
      $apps = CreateObject('phpgwapi.applications',intval($phpgw_info["user"]["account_id"]));
      $phpgw_info["user"]["app_perms"]         = $apps->app_perms;
      $phpgw_info["user"]["lastlogin"]         = $db->f("account_lastlogin");
      $phpgw_info["user"]["lastloginfrom"]     = $db->f("account_lastloginfrom");
      $phpgw_info["user"]["lastpasswd_change"] = $db->f("account_lastpwd_change");
      $phpgw_info["user"]["status"]            = $db->f("account_status");                                                                   
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
      $apps = CreateObject('phpgwapi.applications',intval($userData["account_lid"]));
      $userData["app_perms"]         = $apps->app_perms;
      $userData["lastlogin"]         = $db->f("account_lastlogin");
      $userData["lastloginfrom"]     = $db->f("account_lastloginfrom");
      $userData["lastpasswd_change"] = $db->f("account_lastpwd_change");
      $userData["status"]            = $db->f("account_status");                                                                   

      return $userData;
    }

    function read_groups($lid) {
       global $phpgw_info, $phpgw;
       
       $db2 = $phpgw->db;
       
       if (gettype($lid) == "integer") {
         if ($phpgw_info["user"]["account_id"] != $lid || !$phpgw_info["user"]["groups"]) {
           $db2->query("select account_groups from accounts where account_id=$lid",__LINE__,__FILE__);
           $db2->next_record();
           $gl = explode(",",$db2->f("account_groups"));
         } else {
          $gl = $phpgw_info["user"]["groups"];
         }
       } else {
         if ($phpgw_info["user"]["userid"] != $lid || !$phpgw_info["user"]["groups"]) {
           $db2->query("select account_groups from accounts where account_lid='$lid'",__LINE__,__FILE__);
           $db2->next_record();
           $gl = explode(",",$db2->f("account_groups"));
         } else {
          $gl = $phpgw_info["user"]["groups"];
         }
       }

       for ($i=1; $i<(count($gl)-1); $i++) {
          $ga = explode(":",$gl[$i]);
          $groups[$ga[0]] = $ga[1];
       }
       return $groups;
    }

    function read_group_names($lid = "")
    {
       global $phpgw, $phpgw_info;
       
       if (! $lid) {
          $lid = $phpgw_info["user"]["userid"];
       }
       $groups = $this->read_groups($lid);

       $db = $phpgw->db;
       $i = 0;
       while ($groups && $group = each($groups)) {
           $db->query("select group_name from groups where group_id=".$group[0],__LINE__,__FILE__);
           $db->next_record();
           $group_names[$i][0] = $group[0];
           $group_names[$i][1] = $db->f("group_name");
           $group_names[$i++][2] = $group[1];
       }
       if (! $lid)
          $this->group_names = $group_names;

       return $group_names;
    }

/*    // This works a little odd, but it is required for apps to be listed in the correct order.
    // We first take an array of apps in the correct order and give it a value of 1.  Which local means false.
    // After the app is verified, it is giving the value of 2, meaning true.
    function read_apps($lid)
    {
       global $phpgw, $phpgw_info;
       
       $db = $phpgw->db;
       // fing enabled apps in this system
       $db->query("select app_name from applications where app_enabled != 0 order by app_order",__LINE__,__FILE__);
       while ($phpgw->db->next_record()) {
         $enabled_apps[$db->f("app_name")] = 1;
       }

      // get a ldap connection handle
      $ds = $phpgw->common->ldapConnect();
	
      // search the dn for the given uid
      $sri = ldap_search($ds, $phpgw_info["server"]["ldap_context"], "uid=$lid");
      $allValues = ldap_get_entries($ds, $sri);

      for ($i=0; $i < $allValues[0]["phpgw_account_perms"]["count"]; $i++)
      {
      	$pl = $allValues[0]["phpgw_account_perms"][$i];
      	if ($enabled_apps[$pl])
      	{
      		$enabled_apps[$pl] = 2;
      	}
      }

       // This is to prevent things from being loaded twice
       if ($phpgw_info["user"]["userid"] == $lid) {
          $group_list = $this->groups;
       } else {
          $group_list = $this->read_groups($lid);
       }

       while ($group_list && $group = each($group_list)) {
          $db->query("select group_apps from groups where group_id=".$group[0]);
          $db->next_record();

          $gp = explode(":",$db->f("group_apps"));
          for ($i=1,$j=0;$i<count($gp)-1;$i++,$j++) {
             $enabled_apps[$gp[$i]] = 2;
          }
       }
       
       while ($sa = each($enabled_apps)) {
          if ($sa[1] == 2) {
             $return_apps[$sa[0]] = True;
          }
       }
     
       return $return_apps;  
    }
*/
   // This works a little odd, but it is required for apps to be listed in the correct order.
    // We first take an array of apps in the correct order and give it a value of 1.  Which local means false.
    // After the app is verified, it is giving the value of 2, meaning true.
    function read_apps($lid)
    {
       global $phpgw, $phpgw_info;
       
       $db2 = $phpgw->db;

       $db2->query("select * from applications where app_enabled != '0'",__LINE__,__FILE__);
       while ($db2->next_record()) {
          $name   = $db2->f("app_name");
          $title  = $db2->f("app_title");
          $status = $db2->f("app_enabled");
          $phpgw_info["apps"][$name] = array("title" => $title, "enabled" => True, "status" => $status);
 
          $enabled_apps[$db2->f("app_name")] = 1;
          $app_status[$db2->f("app_name")]   = $db2->f("app_status");
       } 

       if (gettype($lid) == "integer") {
          $db2->query("select account_permissions from accounts where account_id=$lid",__LINE__,__FILE__);
       } else {
          $db2->query("select account_permissions from accounts where account_lid='$lid'",__LINE__,__FILE__);
       }
       $db2->next_record();

       $pl = explode(":",$db2->f("account_permissions"));

       for ($i=0; $i<count($pl); $i++) {
          if ($enabled_apps[$pl[$i]]) {
             $enabled_apps[$pl[$i]] = 2;
          }
       }

       $group_list = $this->read_groups($lid);

       while ($group_list && $group = each($group_list)) {
          $db2->query("select group_apps from groups where group_id=".$group[0],__LINE__,__FILE__);
          $db2->next_record();

          $gp = explode(":",$db2->f("group_apps"));
          for ($i=1,$j=0;$i<count($gp)-1;$i++,$j++) {
             $enabled_apps[$gp[$i]] = 2;
          }
       }
       
       while ($sa = each($enabled_apps)) {
          if ($sa[1] == 2) {
             $return_apps[$sa[0]] = True;
          }
       }
     
       return $return_apps;  
    }
    
    // This will return the group permissions in an array
    function read_group_apps($group_id)
    {
       global $phpgw;

       $db = $phpgw->db;
       $db->query("select group_apps from groups where group_id=".$group_id,__LINE__,__FILE__);
       $db->next_record();

       $gp = explode(":",$db->f("group_apps"));
       for ($i=1,$j=0;$i<count($gp)-1;$i++,$j++) {
          $apps_array[$j] = $gp[$i];
       }
       return $apps_array;
    }       
    
    // Note: This needs to work off LDAP (jengo)
    function listusers($groups="")
    {
       global $phpgw;

       $db = $phpgw->db;

       if ($groups) {
          $db->query("select account_lid,account_firstname,account_lastname from accounts where account_groups"
    				      . "like '%,$groups,%'",__LINE__,__FILE__);
       } else {
          $db->query("select account_lid,account_firstname,account_lastname from accounts",__LINE__,__FILE__);
       }
       $i = 0;
       while ($db->next_record()) {
          $accounts["account_lid"][$i]       = $db->f("account_lid");
          $accounts["account_firstname"][$i] = $db->f("account_firstname");
          $accounts["account_lastname"][$i]  = $db->f("account_lastname");
    	  $i++;
       }
       return $accounts;
    }


  function username2userid($user_name)
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

    function userid2username($user_id)
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
  }
