<?php
  /**************************************************************************\
  * phpGroupWare API - Accounts manager shared functions                     *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * shared functions for other account repository managers                   *
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
  
  class accounts extends accounts_
  {
    /**************************************************************************\
    * Standard constructor for setting $this->account_id                       *
    * This constructor sets the account id, if string is sent, converts to id  *
    * I might move this to the accounts_shared if it stays around              *
    \**************************************************************************/
    function accounts($account_id = False)
    {
      global $phpgw, $phpgw_info;
      $this->db = $phpgw->db;
      if ($account_id == False){ $this->account_id = $account_id; }
    }

    function read()
    {
      if (count($this->data) == 0){ $this->read_repository(); }
      reset($this->data);
      return $this->data;
    }

    function update_data($data) {
      reset($data);
      $this->data = Array();
      $this->data = $data;
      reset($this->data);
      return $this->data;
    }

    function memberships($account_id = False)
    {
      global $phpgw_info, $phpgw;
      if ($account_id == ""){ $account_id = $phpgw_info["user"]["account_id"]; }
      elseif (gettype($account_id) == "string") { $account_id = $this->name2id($account_id); }

      $security_equals = Array();
      $security_equals = $phpgw->acl->get_location_list_for_id("phpgw_group", 1, intval($account_id));
      if (!$security_equals) { return False; }
      for ($idx=0; $idx<count($security_equals); $idx++){
        $name = $this->id2name(intval($security_equals[$idx]));
        $this->memberships[] = Array("account_id" => $security_equals[$idx], "account_name" => "$name");
      }
      return $this->memberships;
    }

    function members ($account_id = False)
    {
      global $phpgw_info, $phpgw;
      if ($account_id == ""){ $account_id = $phpgw_info["user"]["account_id"]; }
      elseif (gettype($account_id) == "string") { $account_id = $this->name2id($account_id); }

      $security_equals = Array();
      $security_equals = $phpgw->acl->get_ids_for_location(intval($account_id), 1, "phpgw_group");
      if (!$security_equals) { return False; }
      for ($idx=0; $idx<count($security_equals); $idx++){
        $name = $this->name2id($security_equals[$idx]);
        $this->members[] = Array("account_id" => $security_equals[$idx], "account_name" => "$name");
      }
      return $this->members;
    }
  }
?>
