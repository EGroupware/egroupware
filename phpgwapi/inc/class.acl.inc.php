<?php
  /**************************************************************************\
  * phpGroupWare API - Access Control List                                   *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * Security scheme based on ACL design                                      *
  * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
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
  
  class acl
  {
    var $db;

    function acl()
    {
      global $phpgw;
      $this->db = $phpgw->db;
    }

    /* This is a new class. These are sample table entries
       insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) 
                         values('filemanager', 'create', 1, 'u', 4);
       insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) 
                         values('filemanager', 'create', 1, 'g', 2);
       insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) 
                         values('filemanager', 'create', 2, 'u', 1);
       insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) 
                          values('filemanager', 'create', 2, 'g', 2);
    */

    function get_rights($location,$appname = False, $user_id = ""){
      global $phpgw, $phpgw_info;
      if ($appname == False){
        $appname = $phpgw_info["flags"]["currentapp"];
      }
      if($user_id == "") {
        $user_id = $phpgw_info["user"]["account_id"];
      }
      // User piece
      $sql = "select acl_rights from phpgw_acl where acl_appname='$appname'";
      $sql .= " and (acl_location in ('$location','everywhere')) and ";
      $sql .= "((acl_account_type = 'u' and acl_account = ".$user_id.")";
    
      // Group piece
      $sql .= " or (acl_account_type='g' and acl_account in (0"; // group 0 covers all users
      $memberships = $phpgw->accounts->read_group_names($user_id);           
      if (is_array($memberships) && count($memberships) > 0){
        for ($idx = 0; $idx < count($memberships); ++$idx){
          $sql .= ",".$memberships[$idx][0];
        }
      }
      $sql .= ")))";
      $rights = 0;
      $this->db->query($sql ,__LINE__,__FILE__);
      if ($this->db->num_rows() == 0 && $phpgw_info["server"]["acl_default"] != "deny"){ return True; }
      while ($this->db->next_record()) {
        if ($this->db->f("acl_rights") == 0){ return False; }
        $rights |= $this->db->f("acl_rights");
      }
      return $rights;
    }

    function check($location, $required, $appname = False, $user_id = ""){
      global $phpgw, $phpgw_info;
      $rights = $this->get_rights($location,$appname, $user_id);
      
      return !!($rights & $required);
    }

    function get_specific_rights($location, $appname = False, $id = "", $id_type = "u"){
      global $phpgw, $phpgw_info;

      if ($appname == False){
        $appname = $phpgw_info["flags"]["currentapp"];
      }
      if($id == "") {
        $id = $phpgw_info["user"]["account_id"];
      }
      // User piece
      $sql = "select acl_rights from phpgw_acl where acl_appname='$appname'";
      $sql .= " and acl_location = '$location' and ";
      $sql .= "acl_account_type = '".$id_type."' and acl_account = ".$id;
      $this->db->query($sql ,__LINE__,__FILE__);
      $rights = 0;
      if ($this->db->num_rows() == 0 && $phpgw_info["server"]["acl_default"] != "deny"){ 
        return True; 
      }
      while ($this->db->next_record()) {
        if ($this->db->f("acl_rights") == 0){ return False; }
        $rights |= $this->db->f("acl_rights");
      }
      return $rights;
    }

    function check_specific($location, $required, $appname = False, $id = "", $id_type = "u"){
      global $phpgw, $phpgw_info;
      $rights = $this->get_specific_rights($location,$appname, $id, $id_type);
      
      return !!($rights & $required);
    }

    function add($app, $location, $id, $id_type, $rights){
      $sql = "insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights)";
      $sql .= " values('".$app."', '".$location."', ".$id.", '".$id_type."', ".$rights.")";
      $this->db->query($sql ,__LINE__,__FILE__);
      return True;
    }

    function delete($app, $location, $id, $id_type){
      $sql = "delete from phpgw_acl where acl_appname like '".$app."'"
           . " and acl_location like '".$location."' and "
           . " acl_account_type = '".$id_type."' and acl_account = ".$id;
      $this->db->query($sql ,__LINE__,__FILE__);
      return $this->db->num_rows();
    }

    function replace($app, $location, $id, $id_type, $rights){
      $this->delete($app, $location, $id, $id_type);
      $this->add($app, $location, $id, $id_type, $rights);
      return True;
    }

    function get_app_list_for_id($location, $required, $id = "", $id_type = "u"){
      global $phpgw, $phpgw_info;
      if ($id == ""){ $id = $phpgw_info["user"]["account_id"]; }
      $sql = "select acl_appname, acl_rights from phpgw_acl where acl_location = '$location' and ";
      $sql .= "acl_account_type = '".$id_type."' and acl_account = ".$id;
      $this->db->query($sql ,__LINE__,__FILE__);
      $rights = 0;
      if ($this->db->num_rows() == 0 ){ return False; }
      while ($this->db->next_record()) {
        if ($this->db->f("acl_rights") == 0){ return False; }
        $rights |= $this->db->f("acl_rights");
        if (!!($rights & $required) == True){
          $apps[] = $this->db->f("acl_appname");
        }
      }
      return $apps;
    }

    function get_location_list($app, $required){
      global $phpgw, $phpgw_info;
      // User piece
      $sql = "select acl_location, acl_rights from phpgw_acl where acl_appname = '$app' ";
      $sql .= " and ((acl_account_type = 'u' and acl_account = '".$phpgw_info["user"]["account_id"]."')";
    
      // Group piece
      $sql .= " or (acl_account_type='g' and acl_account in (0"; // group 0 covers all users
      $memberships = $phpgw->accounts->read_group_names($phpgw_info["user"]["account_id"]);           
      if (is_array($memberships) && count($memberships) > 0){
        for ($idx = 0; $idx < count($memberships); ++$idx){
          $sql .= ",".$memberships[$idx][0];
        }
      }
      $sql .= ")))";

      $this->db->query($sql ,__LINE__,__FILE__);
      $rights = 0;
      if ($this->db->num_rows() == 0 ){ return False; }
      while ($this->db->next_record()) {
        if ($this->db->f("acl_rights") == 0){ return False; }
        $rights |= $this->db->f("acl_rights");
        if (!!($rights & $required) == True){
          $locations[] = $this->db->f("acl_location");
        }else{
          return False;
        }
      }
      return $locations;
    }

    function get_location_list_for_id($app, $required, $id_type = "", $id = ""){
      global $phpgw, $phpgw_info;
      if ($id == ""){ $id = $phpgw_info["user"]["account_id"]; }
      if ($id_type == ""){ $id_type = "u"; }
      $sql = "select acl_location, acl_rights from phpgw_acl where acl_appname = '$app' and ";
      $sql .= "acl_account_type = '".$id_type."' and acl_account = ".$id;
      $this->db->query($sql ,__LINE__,__FILE__);
      $rights = 0;
      if ($this->db->num_rows() == 0 ){ return False; }
      while ($this->db->next_record()) {
        if ($this->db->f("acl_rights")) {
          $rights |= $this->db->f("acl_rights");
          if (!!($rights & $required) == True){
            $locations[] = $this->db->f("acl_location");
          }
        }
      }
      return $locations;
    }
   
    function get_ids_for_location($location, $required, $app = False, $id_type = "u"){
      global $phpgw, $phpgw_info;
      if ($app == False){
        $app = $phpgw_info["flags"]["currentapp"];
      }
      $sql = "select acl_account, acl_rights from phpgw_acl where acl_appname = '$app' and ";
      $sql .= "acl_account_type = '".$id_type."' and acl_location = '".$location."'";
      $this->db->query($sql ,__LINE__,__FILE__);
      $rights = 0;
      if ($this->db->num_rows() == 0 ){ return False; }
      while ($this->db->next_record()) {
        $rights |= $this->db->f("acl_rights");
        if (!!($rights & $required) == True){
          $accounts[] = $this->db->f("acl_account");
        }
      }
      return $accounts;
    }
  } //end of acl class
?>
