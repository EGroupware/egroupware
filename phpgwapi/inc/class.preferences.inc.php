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
    var $preference;

    function preferences($account_id = 0)
    {
      global $phpgw;
//      echo "Account ID (Initializing) = ".$account_id."<br>\n";

  
      $db2 = $phpgw->db;
      $load_pref = True;
      if (is_long($account_id) && $account_id) {
        $this->account_id = $account_id;
      } elseif(is_string($account_id)) {
        $db2->query("SELECT account_id FROM accounts WHERE account_lid='".$account_id."'",__LINE__,__FILE__);
        if($db2->num_rows()) {
          $db2->next_record();
          $this->account_id = $db2->f("account_id");
        } else {
          $load_pref = False;
        }
      } else {
        $load_pref = False;
      }

//echo "Load Pref = $load_pref<br>\n";
//echo "Account ID (After Initializing) = ".$this->account_id."<br>\n";

      if ($load_pref) {
        $db2->lock("preferences");
        $db2->query("SELECT preference_value FROM preferences WHERE preference_owner=".$this->account_id,__LINE__,__FILE__);
        $db2->next_record();
        $pref_info = $db2->f("preference_value");
        $this->preference = unserialize($pref_info);
//	echo "Preferences = ".$this->get_preferences()."<br>\n";
        $db2->unlock();
      }
    }

    // This should be called when you are done makeing changes to the preferences
    function commit($line = "",$file = "")
    {
      global $phpgw, $phpgw_info;

      //echo "<br>commit called<br>Line: $line<br>File: $file".$phpgw_info["user"]["account_id"]."<br>";
      if ($this->account_id) {
        $db = $phpgw->db;

        $db->lock("preferences");

        $db->query("delete from preferences where preference_owner=" . $this->account_id,__LINE__,__FILE__);

        if ($PHP_VERSION < "4.0.0") {
          $pref_info = addslashes(serialize($this->preference));
        } else {
          $pref_info = serialize($this->preference);
        }

        $db->query("insert into preferences (preference_owner,preference_value) values ("
                  . $this->account_id . ",'" . $pref_info . "')",__LINE__,__FILE__);

        $db->unlock();


        if ($phpgw_info["user"]["account_id"] == $this->account_id) {
          $phpgw->preferences->preference = $this->get_preferences();
          $phpgw->accounts->sync(__LINE__,__FILE__);
        }
      }
    }

    // Add a new preference.
    function change($app_name,$var,$value = "")
    {
       global $phpgw_info;
     
       if (! $value) {
          global $$var;
          $value = $$var;
       }
 
       $this->preference["$app_name"]["$var"] = $value;
    }
    
    function delete($app_name,$var)
    {
       if (! $var) {
	  $this->reset($app_name);
       } else {
	  unset($this->preference["$app_name"]["$var"]);
       }
    }

    // This will kill all preferences within a certain app
    function reset($app_name)
    {
       $this->preference["$app_name"] = array();
    }

    function get_preferences()
    {
       return $this->preference;
    }
  } //end of preferences class
?>
