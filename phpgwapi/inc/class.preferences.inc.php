<?php
  /**************************************************************************\
  * phpGroupWare API - Preferences                                           *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * and Mark Peters <skeeter@phpgroupware.org>                               *
  * Manages user preferences                                                 *
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
  
  class preferences
  {
    var $account_id;
    var $account_type;
    var $data = Array();
    var $db;

    /**************************************************************************\
    * Standard constructor for setting $this->account_id                       *
    \**************************************************************************/

    function preferences($account_id = False)
    {
      global $phpgw, $phpgw_info;
      $this->db = $phpgw->db;
      if ($account_id == False){ 
        $this->account_id = $phpgw_info["user"]["account_id"]; 
      } elseif (is_long($account_id)) {
        $this->account_id = $account_id;
      } elseif(is_string($account_id)) {
        $this->account_id = $phpgw->accounts->name2id($account_id);
      }
    }

    /**************************************************************************\
    * These are the standard $this->account_id specific functions              *
    \**************************************************************************/

    function read_repository()
    {
      $this->db->lock("preferences");
      $this->db->query("SELECT preference_value FROM preferences WHERE preference_owner=".$this->account_id,__LINE__,__FILE__);
      $this->db->next_record();
      $pref_info = $this->db->f("preference_value");
      $this->data = Array();
      $this->data = unserialize($pref_info);
      $this->db->unlock();
      reset ($this->data);
      return $this->data;
    }

    function read()
    {
      if (count($this->data) == 0){ $this->read_repository(); }
      reset ($this->data);
      return $this->data;
    }

    function add($app_name,$var,$value = "")
    {
      if (! $value) {
        global $$var;
        $value = $$var;
      }
 
      $this->data[$app_name][$var] = $value;
      reset($this->data);
      return $this->data;
    }
    
    function delete($app_name, $var = "")
    {
      if ($var == "") {
        $this->data[$app_name] = array();
      } else {
        unset($this->data[$app_name][$var]);
      }
      reset ($this->data);
      return $this->data;
    }

    function save_repository()
    {
      global $phpgw, $phpgw_info;
      $this->db->lock("preferences");
      $this->db->query('delete from preferences where preference_owner=' . $this->account_id,__LINE__,__FILE__);

      if ($PHP_VERSION < "4.0.0") {
        $pref_info = addslashes(serialize($this->data));
      } else {
        $pref_info = serialize($this->data);
      }

      $this->db->query('insert into preferences (preference_owner,preference_value) values ('
                . $this->account_id . ",'" . $pref_info . "')",__LINE__,__FILE__);

      $this->db->unlock();
      return $this->data;
    }

    function update_data($data) {
      reset($data);
      $this->data = Array();
      $this->data = $data;
      reset($this->data);
      return $this->data;
    }

  } //end of preferences class
?>
