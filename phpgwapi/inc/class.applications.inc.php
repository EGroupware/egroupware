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
    var $app_perms = Array(Array());
    var $apps_loaded = False;

    function applications($lid=0)
    {
      global $phpgw;
      global $phpgw_info;

      $db2 = $phpgw->db;
//      $db3 = $phpgw->db;
      if(($this->is_type($lid,"integer") && $lid == $phpgw_info["user"]["account_id"]) ||
         ($this->is_type($lid,"string") && $lid == $phpgw_info["user"]["user_id"])) {
         $load_info = True;
      }
      if(!$this->apps_loaded) {
        $this->apps_loaded = True;
        $db2->query("select * from applications where app_enabled != '0'",__LINE__,__FILE__);
        $apps_enabled = False;
        while ($db2->next_record()) {
          if(!$apps_enabled) $apps_enabled = True;
          $name   = $db2->f("app_name");
          if($load_info) {
            $title  = $db2->f("app_title");
            $status = $db2->f("app_enabled");
            $phpgw_info["apps"][$name] = array("title" => $title, "enabled" => True, "status" => $status);
          }
          $this->enabled[$name] = 1;
          $this->status[$name] = $db2->f("app_status");
        }
      }
      if($apps_enabled && $lid) {
        $owner_found = False;
        if($this->is_type($lid,"integer")) {
          $owner_id = $lid;
          $owner_found = True;
        } elseif($this->is_type($lid,"string")) {
          $db2->query("SELECT account_id FROM accounts WHERE account_lid='".$lid."'",__LINE__,__FILE__);
          if($db2->num_rows()) {
            $db2->next_record();
            $owner_id = $db2->f("account_id");
            $owner_found = True;
          }
        }
        if($owner_found) {
          $this->account_id = $owner_id;
          $this->read_user_group_apps($this->account_id);
          $this->read_user_apps($this->account_id);
          if($load_info) {
            $phpgw_info["user"]["apps"] = $this->apps_enabled();
          }
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
      } elseif($this->$var[$index]) {
        return $this->$var[$index];
      }
      return False;
    }

    function apps_enabled()
    {
       while ($sa = each($this->enabled)) {
          if ($sa[1] == 2) {
             $return_apps[$sa[0]] = True;
          }
       }
       return $return_apps;  
    }

    function is_type($variable,$type)
    {
      return (strtoupper(gettype($variable)) == strtoupper($type));
    }

    function read_user_apps($lid)
    {
      global $phpgw, $phpgw_info;

      $db2 = $phpgw->db;

      if ($this->is_type($lid,"string")) {
        $db2->query("select account_id from accounts where account_lid='$lid'",__LINE__,__FILE__);
        if($db2->num_rows()) {
          $db2->next_record();
          $account_id = $db2->f("account_id");
        } else {
          return False;
        }
      } elseif ($this->is_type($lid,"integer")) {
        $account_id = $lid;
      } else {
        return False;
      }

      $acl_apps = $phpgw->acl->view_app_list('run', 1, 'u');
      if ($acl_apps != False){
        reset ($acl_apps);
        while (list(,$value) = each($acl_apps)){
          $apps[] = $value;
        }
      } else {
        $db2->query("select account_permissions from accounts where account_id=$account_id",__LINE__,__FILE__);
        $db2->next_record();
        $apps_perms = explode(":",$db2->f("account_permissions"));
        for($i=1;$i<count($apps_perms)-1;$i++) {
          $apps[] = $apps_perms[$i];
        }
      }
      if(count($apps)) {
//if($lid <> $phpgw_info["user"]["account_id"]) echo "<!-- applications: Account Permissions - ".$db2->f("aaccount_permissions")." -->\n";
        for ($i=0; $i<count($apps); $i++) {
//if($lid <> $phpgw_info["user"]["account_id"]) echo "<!-- applications: Reading user app - ".$apps[$i]." -->\n";
          if ($this->enabled[$apps[$i]] == 1) {
            $this->user_apps[$apps[$i]] = $apps[$i];
            $this->enabled[$apps[$i]] = 2;
            $this->app_perms[] = $apps[$i];
          }
        }
      }
    }

    function read_user_group_apps($lid)
    {
      global $phpgw;

      $db2 = $phpgw->db;

      if ($this->is_type($lid,"string")) {
        $db2->query("select account_id from accounts where account_lid='$lid'",__LINE__,__FILE__);
        if($db2->num_rows()) {
          $db2->next_record();
          $account_id = $db2->f("account_id");
        } else {
          return False;
        }
      } elseif ($this->is_type($lid,"integer")) {
        $account_id = $lid;
      } else {
        return False;
      }

      $groups = $phpgw->accounts->read_groups($account_id);

      if($groups) {
        while ($group = each($groups)) {
          $this->read_group_apps($group[0]);
        }
      }
    }

    function read_group_apps($group_id)
    {
      global $phpgw;

      $db2 = $phpgw->db;

      $acl_apps = $phpgw->acl->view_app_list('run', 1, 'g', $group_id);
      if ($acl_apps != False){
        reset ($acl_apps);
        while (list(,$value) = each($acl_apps)){
          $apps[] = $value;
        }
      } else {
        $db2->query("select group_apps from groups where group_id=".$group_id,__LINE__,__FILE__);
        $db2->next_record();
        $apps_perms = explode(":",$db2->f("group_apps"));
        for($i=1;$i<count($apps_perms)-1;$i++) {
          $apps[] = $apps_perms[$i];
        }
      }
      if(count($apps)) {
        for ($i=0;$i<count($apps);$i++) {
          if ($this->enabled[$apps[$i]] == 1) {
            $this->group_apps[$group_id][$apps[$i]] = $apps[$i];
            $this->enabled[$apps[$i]] = 2;
            $this->app_perms[] = $apps[$i];
          }
        }
      }
    }

    function is_system_enabled($appname)
    {
      return $this->enabled[$appname] >= 1;
    }

    function is_user_enabled($appname)
    {
      return $this->enabled[$appname] == 2;
    }

    function get_group_array($group_id)
    {
      return $this->group_apps[$group_id];
    }
    
    function group_app_string($group_id)
    {
      reset($this->group_apps[$group_id]);
      while($app = each($this->group_apps[$group_id])) {
        $group_apps[] = $app[1];
      }
      return ":".implode(":",$group_apps).":";
    }

    function user_app_string()
    {
      reset($this->user_apps);
      while($app = each($this->user_apps)) {
        $user_apps[] = $app[1];
      }
      return ":".implode(":",$user_apps).":";
    }

    function is_group($group_id,$appname)
    {
      return $this->group_apps[$group_id][$appname];
    }

    function is_user($appname)
    {
      return $this->user_apps[$appname];
    }

    function add_group($group_id,$appname)
    {
      if($this->is_type($appname,"array")) {
        while($app = each($appname)) {
          $this->add_group_app($group_id,$app[0]);
        }
        return $this->group_app_string($group_id);
      } elseif($this->is_type($appname,"string")) {
        $this->add_group_app($group_id,$appname);
        return $this->group_app_string($group_id);
      }
    }

    function add_group_app($group_id,$appname)
    {
      if ($this->enabled[$appname] && !$this->is_group($group_id,$appname)) {
        $this->group_apps[$group_id][] = $appname;
        $this->enabled[$appname] = 2;
      }
    }

    function add_user($appname)
    {
      if($this->is_type($appname,"array")) {
        while($app = each($appname)) {
          $this->add_user_app($app[0]);
        }
        return $this->user_app_string($group_id);
      } elseif($this->is_type($appname,"string")) {
        $this->add_user_app($group_id,$appname);
        return $this->user_app_string($group_id);
      }
    }

    function add_user_app($appname)
    {
      if ($this->enabled[$appname] && !$this->is_user($appname)) {
        $this->user_apps[] =$appname;
        $this->enabled[$appname] = 2;
      }
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
        $phpgw->acl->remove_locations("run", "g", $group_id);
        reset($this->group_apps[$group_id]);
        while($app = each($this->group_apps[$group_id])) {
          $phpgw->acl->add($app[1],'run',$group_id,'g',1);
        }
      }
    }

    function save_user()
    {
      global $phpgw;

      if($this->account_id) {
        $db2 = $phpgw->db;
        $db2->query("UPDATE account SET account_permissions = '".$this->user_app_string()."' WHERE account_id=".$this->account_id,__LINE__,__FILE__);
        $phpgw->acl->remove_locations("run");
        reset($this->user_apps);
        while($app = each($this->user_apps)) {
          $phpgw->acl->add($app[1],'run',$this->account_id,'u',1);
        }
      }
    }
  }
?>
