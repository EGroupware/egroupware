<?php
  /**************************************************************************\
  * phpGroupWare API - Accounts manager for SQL                              *
  * http://www.phpgroupware.org/api                                          *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * and Dan Kuykendall <seek3r@phpgroupware.org>                             *
  * View and manipulate account records using SQL                            *
  * Copyright (C) 2000, 2001 Joseph Engo                                     *
  * -------------------------------------------------------------------------*
  * This library is part of phpGroupWare (http://www.phpgroupware.org)       * 
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

    function phpgw_fillarray()
    {
      global $phpgw_info, $phpgw;
      
      $db2 = $phpgw->db;

      $db2->query("select * from accounts where account_lid='" . $phpgw_info["user"]["userid"] . "'",__LINE__,__FILE__);
      $db2->next_record();
    
      /* Now dump it into the array */
      $phpgw_info["user"]["account_id"]        = $db2->f("account_id");
      $phpgw_info["user"]["firstname"]         = $db2->f("account_firstname");
      $phpgw_info["user"]["lastname"]          = $db2->f("account_lastname");
      $phpgw_info["user"]["fullname"]          = $db2->f("account_firstname") . " "
                                               . $db2->f("account_lastname");
      $phpgw_info["user"]["groups"]            = explode (",", $db2->f("account_groups"));
      $phpgw_info["user"]["app_perms"]         = explode (":", $db2->f("account_permissions"));
      $phpgw_info["user"]["lastlogin"]         = $db2->f("account_lastlogin");
      $phpgw_info["user"]["lastloginfrom"]     = $db2->f("account_lastloginfrom");
      $phpgw_info["user"]["lastpasswd_change"] = $db2->f("account_lastpwd_change");
      $phpgw_info["user"]["status"]            = $db2->f("account_status");
    }

    function read_userData($id)
    {
      global $phpgw_info, $phpgw;
      
      $db2 = $phpgw->db;
      
      $db2->query("select * from accounts where account_id='$id'",__LINE__,__FILE__);
      $db2->next_record();
    
      /* Now dump it into the array */
      $userData["account_id"]        = $db2->f("account_id");
      $userData["account_lid"]       = $db2->f("account_lid");
      $userData["firstname"]         = $db2->f("account_firstname");
      $userData["lastname"]          = $db2->f("account_lastname");
      $userData["fullname"]          = $db2->f("account_firstname") . " "
                                               . $db2->f("account_lastname");
      $userData["groups"]            = explode(",", $db2->f("account_groups"));
      $userData["app_perms"]         = explode(":", $db2->f("account_permissions"));
      $userData["lastlogin"]         = $db2->f("account_lastlogin");
      $userData["lastloginfrom"]     = $db2->f("account_lastloginfrom");
      $userData["lastpasswd_change"] = $db2->f("account_lastpwd_change");
      $userData["status"]            = $db2->f("account_status");
      
      return $userData;
    }

    function read_groups($lid)
    {
       global $phpgw_info, $phpgw;
       
       $db2 = $phpgw->db;
       
       if ($phpgw_info["user"]["userid"] != $lid) {
          $db2->query("select account_groups from accounts where account_lid='$lid'",__LINE__,__FILE__);
          $db2->next_record();
          $gl = explode(",",$db2->f("account_groups"));
       } else {
          $gl = $phpgw_info["user"]["groups"];
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

       $db2 = $phpgw->db;

       if (! $lid) {
          $lid = $phpgw_info["user"]["userid"];
       }
       $groups = $this->read_groups($lid);

       $i = 0;
       while ($groups && $group = each($groups)) {
          $db2->query("select group_name from groups where group_id=".$group[0],__LINE__,__FILE__);
          $db2->next_record();
          $group_names[$i][0]   = $group[0];
          $group_names[$i][1]   = $db2->f("group_name");
          $group_names[$i++][2] = $group[1];
       }

       if (! $lid) {
          $this->group_names = $group_names;
       }

       return $group_names;
    }

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

       $db2 = $phpgw->db;

       $db2->query("select group_apps from groups where group_id=".$group_id,__LINE__,__FILE__);
       $db2->next_record();

       $gp = explode(":",$db2->f("group_apps"));
       for ($i=1,$j=0;$i<count($gp)-1;$i++,$j++) {
          $apps_array[$j] = $gp[$i];
       }
       return $apps_array;
    }       
    

    function listusers($groups="")
    {
       global $phpgw;

       $db2 = $phpgw->db;

       if ($groups) {
          $db2->query("select account_lid,account_firstname,account_lastname from accounts where account_groups"
				        . "like '%,$groups,%'",__LINE__,__FILE__);
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

  }
