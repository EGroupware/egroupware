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
    var $enabled = Array();
    var $status = Array();
    var $user_apps = Array();
    var $group_apps = Array(Array());

    function applications($lid=0)
    {
      global $phpgw;
      global $phpgw_info;

      $db2 = $phpgw->db;
      $db2->query("select * from applications where app_enabled != '0'",__LINE__,__FILE__);
      $apps_enabled = False;
      while ($db2->next_record()) {
        if($apps_enabled) $apps_enabled = True;
        $name   = $db2->f("app_name");
        $title  = $db2->f("app_title");
        $status = $db2->f("app_enabled");
        $phpgw_info["apps"][$name] = array("title" => $title, "enabled" => True, "status" => $status);
 
        $this->set_var("enabled",1,$name);
        $this->set_var("status",$db2->f("app_status"),$name);
      }
      if($apps_enabled && $lid) {
        $owner_found = False;
        if($this->is_type($lid,"integer") {
          $owner_id = $lid;
          $owner_found = True;
        } else {
          $db2->query("SELECT account_id FROM accounts WHERE account_lid='".$lid."'",__LINE__,__FILE__);
          if($db2->num_rows()) {
            $db2->next_record();
            $owner_id = $db2->f("account_id");
            $owner_found = True;
          }
        }
        if($owner_found) {
          $this->set_var("account_id",$lid);
          $this->read_user_apps($this->get_var("account_id"));
          $this->read_group_apps($this->get_var("account_id"));
        }
      }
    }

    function set_var($var,$value="",$index="")
    {
      if($index == "") {
        $this->$var = $value;
      } else {
        $this->$var[$index] = $value;
      }
    }

    function get_var($var,$index="")
    {
      if($index == "") {
        if($this->$var) {
          return $this->$var;
        }
      } else {
        if($this->$var[$index]) {
          return $this->$var[$index];
        }
      }
      return False;
    }

    function is_type($lid,$type)
    {
      return (strtoupper(gettype($lid)) == strtoupper($type));
    }

    function read_user_apps($lid)
    {
      global $phpgw;

      $db2 = $phpgw->db;
     
      if ($this->is_type($lid,"integer")) {
        $db2->query("select account_permissions from accounts where account_id=$lid",__LINE__,__FILE__);
      } else {
        $db2->query("select account_permissions from accounts where account_lid='$lid'",__LINE__,__FILE__);
      }
      $db2->next_record();

      $apps = explode(":",$db2->f("account_permissions"));
      for ($i=0; $i<count($apps); $i++) {
        if ($this->get_var("enabled",$apps[$i]) == 1) {
          $this->set_var("user_apps[]",$apps[$i]);
          $this->set_var("enabled",2,$apps[$i]);
        }
      }
    }

    function read_group_apps($lid)
    {
      global $phpgw;

      $db2 = $phpgw->db;
     
      if ($this->is_type($lid,"integer")) {
        $db2->query("select account_groups from accounts where account_id=$lid",__LINE__,__FILE__);
      } else {
        $db2->query("select account_groups from accounts where account_lid='$lid'",__LINE__,__FILE__);
      }

      $db2->next_record();
      $groups = explode(",",$db2->f("account_groups"));

      for ($i=1; $i<(count($groups)-1); $i++) {
        $ga = explode(":",$groups[$i]);
        $group_array[$ga[0]] = $ga[1];
      }

      while ($group = each($group_array)) {
        $db2->query("select group_apps from groups where group_id=".$group[0],__LINE__,__FILE__);
        $db2->next_record();

        $apps = explode(":",$db2->f("group_apps"));
        for ($i=0;$i<count($apps);$i++) {
          if ($this->get_var("enabled",$apps[$i]) == 1) {
            $this->set_var("group_apps[".$group[0]."][]",$apps[$i]);
            $this->set_var("enabled",2,$apps[$i]);
          }
        }
      }
    }

    function is_system_enabled($appname)
    {
      return $this->get_var("enabled",$appname) >= 1;
    }

    function is_user_enabled($appname)
    {
      return $this->get_var("enabled",$appname) == 2;
    }
    
    function group_app_string($group_id)
    {
      return ":".implode(":",$this->get_var("group_apps",$group_id)).":";
    }

    function user_app_string()
    {
      return ":".implode(":",$this->get_var("user_apps")).":";
    }

    function is_group($group_id,$appname)
    {
      return $this->get_var("group_apps[".$group_id."]",$appname) == $appname;
    }

    function is_user($appname)
    {
      return $this->get_var("user_apps",$appname) == $appname;
    }

    function add_group($group_id,$appname)
    {
      if ($this->get_var("enabled",$appname) && !$this->is_group($group_id,$appname)) {
        $this->set_var("group_apps[".$group_id."][]",$appname);
        $this->set_var("enabled",2,$appname);
      }
      return $this->group_app_string($group_id);
    }

    function add_user($appname)
    {
      if ($this->get_var("enabled",$appname) && !$this->is_user($appname)) {
        $this->set_var("user_apps[]",$appname);
        $this->set_var("enabled",2,$appname);
      }
      return $this->user_app_string();
    }

    function delete_group($group_id,$appname)
    {
      if($this->is_group($group_id,$appname)) {
        unset($this->group_apps[$group_id][$appname]);
      }
    }

    function delete_user($appname)
    {
      if($this->is_user($appname)) {
        unset($this->user_apps[$appname]);
      }
    }
    
    function save_group($group_id)
    {
      global $phpgw;

      if($group_id) {
        $db2 = $phpgw->db;
        $db2->query("UPDATE groups SET group_apps='".$this->group_app_string($group_id)."' WHERE group_id=".$group_id,__LINE__,__FILE__);
    }

    function save_user()
    {
      global $phpgw;

      if($this->get_var("account_id")) {
        $db2 = $phpgw->db;
        $db2->query("UPDATE account SET account_permissions = '".$this->user_app_string()."' WHERE account_id=".$this->get_var("account_id"),__LINE__,__FILE__);
      }
    }
  }
?>
