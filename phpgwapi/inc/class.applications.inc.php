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
    var $account_type;
    var $account_apps = Array(Array());
    var $db;

    function applications($params = "")
    {
      global $phpgw, $phpgw_info;
      $this->db = $phpgw->db;
      if (is_array($params)) {
        if (isset($params[1])){ 
          $this->account_type = $params[1];
        }else{
          $this->account_type = "u";
        }
        if ($params[0] == ""){ 
          $this->account_id = $phpgw_info["user"]["account_id"]; 
        } elseif (is_long($params[0])) {
          $this->account_id = $params[0];
        } elseif(is_string($params[0])) {
          if ($this->account_type = "u"){
            $this->account_id = $phpgw->accounts->username2userid($params[0]);
          }else{
            $this->account_id = $phpgw->accounts->groupname2groupid($params[0]);
          }
        }
      }else{
        $this->account_id = $phpgw_info["user"]["account_id"]; 
        $this->account_type = "u";
      }
    }

    function enabled_apps()
    {
      global $phpgw, $phpgw_info;
      if (gettype($phpgw_info["apps"]) != "array") {
        $this->read_installed_apps();
      }
      @reset($phpgw_info["apps"]);
      while ($app = each($phpgw_info["apps"])) {
        if ($this->account_type == "g") {
          $check = $phpgw->acl->check_specific("run",1,$app[0], $this->account_id, "g");
        }else{
          $check = $phpgw->acl->check("run",1,$app[0], $this->account_id);
        }
        if ($check) {
          $this->account_apps[$app[0]] = array("title" => $phpgw_info["apps"][$app[0]]["title"], "name" => $app[0], "enabled" => True, "status" => $phpgw_info["apps"][$app[0]]["status"]);
        } 
      }
      return $this->account_apps;
    }

    function app_perms()
    {
      global $phpgw, $phpgw_info;
      if (count($this->account_apps) == 0) {
        $this->enabled_apps();
      }
      @reset($this->account_apps);
      while (list ($key) = each ($this->account_apps)) {
          $app[] = $this->account_apps[$key]["name"];
      }
      return $app;
    }

    function read_account_specific() {
      global $phpgw, $phpgw_info;
      if (gettype($phpgw_info["apps"]) != "array") {
        $this->read_installed_apps();
      }
      @reset($phpgw_info["apps"]);
      while ($app = each($phpgw_info["apps"])) {
        if ($phpgw->acl->check_specific("run",1,$app[0], $this->account_id, $this->account_type) && $this->is_system_enabled($app[0])) {
          $this->account_apps[$app[0]] = array("title" => $phpgw_info["apps"][$app[0]]["title"], "name" => $app[0], "enabled" => True, "status" => $phpgw_info["apps"][$app[0]]["status"]);
        } 
      }
      return $this->account_apps;
    }

    function add_app($apps) {
      if(gettype($apps) == "array") {
        while($app = each($apps)) {
          $this->account_apps[$app[1]] = array("title" => $phpgw_info["apps"][$app[1]]["title"], "name" => $app[1], "enabled" => True, "status" => $phpgw_info["apps"][$app[1]]["status"]);
        }
      } elseif(gettype($apps) == "string") {
          $this->account_apps[$apps] = array("title" => $phpgw_info["apps"][$apps]["title"], "name" => $apps, "enabled" => True, "status" => $phpgw_info["apps"][$apps]["status"]);
      }
      reset($this->account_apps);
      return $this->account_apps;
    }

    function delete_app($appname) {
      unset($this->account_apps[$appname]);
      reset($this->account_apps);
      return $this->account_apps;
    }
    
    function save_apps(){
      global $phpgw, $phpgw_info;
      $num_rows = $phpgw->acl->delete("%%", "run", $this->account_id, $this->account_type);
      reset($this->account_apps);
      while($app = each($this->account_apps)) {
        if(!$phpgw_info["apps"][$app[0]]["enabled"]) { continue; }
        $phpgw->acl->add($app[0],'run',$this->account_id,$this->account_type,1);
      }
      reset($this->account_apps);
      return $this->account_apps;
    }

    function read_installed_apps(){
      global $phpgw, $phpgw_info;
      $this->db->query("select * from applications where app_enabled != '0' order by app_order asc",__LINE__,__FILE__);
      if($this->db->num_rows()) {
        while ($this->db->next_record()) {
          $name = $this->db->f("app_name");
          $title  = $this->db->f("app_title");
          $status = $this->db->f("app_enabled");
          $phpgw_info["apps"]["$name"] = array("title" => $title, "name" => $name, "enabled" => True, "status" => $status);
        }
      }
    }

    function is_system_enabled($appname){
      global $phpgw_info;
      if(gettype($phpgw_info["apps"]) != "array") {
        $this->read_installed_apps();
      }
      if ($phpgw_info["apps"][$appname]["enabled"]) {
        return True;
      }else{
        return False;
      }
    }
  }
?>
