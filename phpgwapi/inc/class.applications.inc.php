<?php
  /**************************************************************************\
  * phpGroupWare API - Applications manager functions                        *
  * This file written by Mark Peters <skeeter@phpgroupware.org>              *
  * Copyright (C) 2001 Mark Peters                                           *
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
  
  class applications
  {
    var $account_id;
    var $user_apps = Array();
    var $group_apps = Array();

    function applications($var = ""){
       global $phpgw, $phpgw_info;
      if ($var != ""){
        $this->users_enabled_apps();
      }

    }

    function users_enabled_apps()
    {
       global $phpgw, $phpgw_info;

       if (gettype($phpgw_info["apps"]) != "array") {
          $this->read_installed_apps();
       }
       reset ($phpgw_info["apps"]);
       while (list($app) = each($phpgw_info["apps"])) {
          if ($phpgw->acl->check("run",1,$app)) {
             $phpgw_info["user"]["apps"][$app] = array("title" => $phpgw_info["apps"][$app]["title"], "name" => $app, "enabled" => True, "status" => $phpgw_info["apps"][$app]["status"]);
          } 
       }
    }

    function read_installed_apps(){
      global $phpgw, $phpgw_info;
      $phpgw->db->query("select * from applications where app_enabled != '0' order by app_order",__LINE__,__FILE__);
      if($phpgw->db->num_rows()) {
        while ($phpgw->db->next_record()) {
//          echo "<br>TEST: " . $phpgw->db->f("app_order") . " - " . $phpgw->db->f("app_name");
          $name = $phpgw->db->f("app_name");
          $title  = $phpgw->db->f("app_title");
          $status = $phpgw->db->f("app_enabled");
          $phpgw_info["apps"][$name] = array("title" => $title, "enabled" => True, "status" => $status);
        }
      }
    }

    function read_user_apps($lid ="") {
      global $phpgw, $phpgw_info;
      if ($lid == ""){$lid = $phpgw_info["user"]["account_id"];}
      $owner_found = False;
      if(gettype($lid) == "string" && $lid == $phpgw_info["user"]["user_id"]) {
        $owner_id = $phpgw_info["user"]["account_id"];
        $owner_found = True;
      }
      if($owner_found == False && gettype($lid) == "integer") {
        $owner_id = $lid;
        $owner_found = True;
      } elseif($owner_found == False && gettype($lid) == "string") {
        $phpgw->db->query("SELECT account_id FROM accounts WHERE account_lid='".$lid."'",__LINE__,__FILE__);
        if($phpgw->db->num_rows()) {
          $phpgw->db->next_record();
          $owner_id = $phpgw->db->f("account_id");
          $owner_found = True;
        }
      }
      if($owner_found) {
        $acl_apps = $phpgw->acl->get_app_list_for_id('run', 1, 'u', $lid);
        if ($acl_apps != False){
          reset ($acl_apps);
          while (list(,$value) = each($acl_apps)){
            $apps[] = $value;
          }
        }
        if(gettype($phpgw_info["apps"]) != "array") {
          $this->read_installed_apps();
        }
        if(count($apps)) {
          for ($i=0; $i<count($apps); $i++) {
            if ($phpgw_info["apps"][$apps[$i]]["enabled"] == True) {
              $this->user_apps[$owner_id][] = $apps[$i];
            }
          }
        }
        return $this->user_apps[$owner_id];
      }
      return False;
    }

    function read_group_apps($group_id) {
      global $phpgw, $phpgw_info;
      if(gettype($group_id) == "integer") {
        $group_found = True;
      } elseif(gettype($group_id) == "string") {
        $phpgw->db->query("SELECT group_id FROM groups WHERE group_name='".$group_id."'",__LINE__,__FILE__);
        if($phpgw->db->num_rows()) {
          $phpgw->db->next_record();
          $group_id = $phpgw->db->f("group_id");
          $group_found = True;
        }           
      }

      if($group_found) {
        $acl_apps = $phpgw->acl->get_app_list_for_id('run', 1, 'g', $group_id);
        if ($acl_apps != False){
          reset ($acl_apps);
          while (list(,$value) = each($acl_apps)){
            $apps[] = $value;
          }
        }
        if(gettype($phpgw_info["apps"]) != "array") {
          $this->read_installed_apps();
        }
        if(count($apps)) {
          for ($i=0;$i<count($apps);$i++) {
            if ($phpgw_info["apps"][$apps[$i]]["enabled"] == True) {
              $this->group_apps[$group_id][] = $apps[$i];
            }
          }
        }
        return $this->group_apps[$group_id];
      }
      return False;
    }

    function is_system_enabled($appname){
      if(gettype($phpgw_info["apps"]) != "array") {
        $this->read_installed_apps();
      }
      if ($phpgw_info["apps"][$appname]["enabled"] == True) {
        return True;
      }else{
        return False;
      }
    }

    function add_group_app($apps, $group_id) {
      if(gettype($appname) == "array") {
        while($app = each($appname)) {
          $this->group_apps[$group_id][] = $app[0];
        }
      } elseif(gettype($appname) == "string") {
          $this->group_apps[$group_id][] = $appname;
      }
    }

    function add_user_app($appname, $user_id = "") {
      global $phpgw, $phpgw_info;
      if ($user_id == ""){$user_id = $phpgw_info["user"]["account_id"];}
      if(gettype($appname) == "array") {
        while($app = each($appname)) {
          $this->user_apps[$user_id][] = $app[0];
        }
      } elseif(gettype($appname) == "string") {
          $this->user_apps[$user_id][] = $appname;
      }
    }

    function delete_group_app($appname, $group_id) {
      unset($this->group_apps[$group_id][$appname]);
    }

    function delete_user_app($appname, $user_id = ""){
      global $phpgw, $phpgw_info;
      if ($user_id == ""){$user_id = $phpgw_info["user"]["account_id"];}
      unset($this->group_apps[$user_id][$appname]);
    }
    
    function save_group_apps($group_id){
      global $phpgw, $phpgw_info;

      if($group_id) {
        $phpgw->acl->delete("%", "run", "g", $group_id);
        reset($this->group_apps[$group_id]);
        while($app = each($this->group_apps[$group_id])) {
          $phpgw->acl->add($app[1],'run',$group_id,'g',1);
        }
      }
    }

    function save_user_apps($user_id = ""){
      global $phpgw, $phpgw_info;
      if ($user_id == ""){$user_id = $phpgw_info["user"]["account_id"];}
      if($user_id) {
        $phpgw->acl->delete("%", "run", "u", $user_id);
        reset($this->user_apps);
        while($app = each($this->user_apps[$user_id])) {
          $phpgw->acl->add($app[1],'run',$user_id,'u',1);
        }
      }
    }
  }
?>