<?php
  /**************************************************************************\
  * phpGroupWare API - Access Control List                                   *
  * http://www.phpgroupware.org/api                                          *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * Security scheme based on ACL design                                      *
  * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
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
  
  class acl
  {
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
          
    function check($location, $required, $appname = False){
      global $phpgw, $phpgw_info;
      if ($appname == False){
        $appname = $phpgw_info["flags"]["currentapp"];
      }
      // User piece
      $sql = "select acl_rights from phpgw_acl where acl_appname='$appname'";
      $sql .= " and (acl_location in ('$location','everywhere')) and ";
      $sql .= "((acl_account_type = 'u' and acl_account = ".$phpgw_info["user"]["account_id"].")";
    
      // Group piece
      $sql .= " or (acl_account_type='g' and acl_account in (0"; // group 0 covers all users
      $memberships = $phpgw->accounts->read_group_names();           
      if (is_array($memberships) && count($memberships) > 0){
        for ($idx = 0; $idx < count($memberships); ++$idx){
          $sql .= ",".$memberships[$idx][0];
        }
      }
      $sql .= ")))";
      $rights = 0;
      $phpgw->db->query($sql ,__LINE__,__FILE__);
      if ($phpgw->db->num_rows() == 0 && $phpgw_info["server"]["acl_default"] != "deny"){ return True; }
      while ($phpgw->db->next_record()) {
        if ($phpgw->db->f("acl_rights") == 0){ return False; }
        $rights |= $phpgw->db->f("acl_rights");
      }
      return !!($rights & $required);
    }

    function add($app, $location, $id, $id_type, $rights){
      $sql = "insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights)";
      $sql .= " values('".$app."', '".$location."', ".$id.", '".$id_type."', ".$rights.")";
      $phpgw->db->query($sql ,__LINE__,__FILE__);
      return True;
    }

    function delete($app, $location, $id, $id_type){
      $sql = "delete from phpgw_acl where acl_appname='".$app."'";
      $sql .= " and acl_location ='".$location."' and ";
      $sql .= " acl_account_type = '".$id_type."' and acl_account = ".$id.")";
      $phpgw->db->query($sql ,__LINE__,__FILE__);
      return True;
    }

    function view($app, $location, $id, $id_type){
    }

  } //end of acl class
?>