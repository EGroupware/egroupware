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
  
    function accounts_const($line,$file)
    {
       global $phpgw, $phpgw_info;
       
       //echo "accounts_const called<br>line: $line<br>$file";

       $phpgw->accounts->phpgw_fillarray();
       if(!$phpgw->preferences->account_id) {
         $phpgw->preferences = new preferences($phpgw_info["user"]["account_id"]);
       }
       $phpgw_info["user"]["preferences"] = $phpgw->preferences->get_preferences();
       $this->groups = $this->read_groups($phpgw_info["user"]["userid"]);
       $this->apps   = $this->read_apps($phpgw_info["user"]["userid"]);
       
       $phpgw_info["user"]["apps"] = $this->apps;
    }
  
    // use this if you make any changes to phpgw_info, including preferences, config table changes, etc
    function sync($line="",$file="")
    {
       global $phpgw_info, $phpgw;
       $db = $phpgw->db;
       
       //echo "<br>sync called<br>Line: $line<br>File:$file";

       /* ********This sets the server variables from the database******** */
       $db->query("select * from config",__LINE__,__FILE__);
       while($db->next_record()) {
         $phpgw_info["server"][$db->f("config_name")] = $db->f("config_value");
       }
       $phpgw->accounts->accounts_const(__LINE__,__FILE__);

       $phpgw_info_temp["user"]        = $phpgw_info["user"];
       $phpgw_info_temp["apps"]        = $phpgw_info["apps"];
       $phpgw_info_temp["server"]      = $phpgw_info["server"];
       $phpgw_info_temp["hooks"]       = $phpgw->hooks->read();
       $phpgw_info_temp["user"]["preferences"] = $phpgw_info["user"]["preferences"];
       $phpgw_info_temp["user"]["kp3"] = "";                     // We don't want it anywhere in the
                                                                 // database for security.
       if ($PHP_VERSION < "4.0.0") {
          $info_string = addslashes($phpgw->crypto->encrypt($phpgw_info_temp));
       } else {
          $info_string = $phpgw->crypto->encrypt($phpgw_info_temp);       
       }
       $db->query("update phpgw_sessions set session_info='$info_string' where session_id='"
                . $phpgw_info["user"]["sessionid"] . "'",__LINE__,__FILE__);
    }

    function add_app($appname,$rebuild = False)
    {
       if (! $rebuild) {
          if (gettype($appname) == "array") {
             $t .= ":";
             $t .= implode(":",$appname);
             $this->temp_app_list .= $t;
          } else {
	        $this->temp_app_list .= ":" . $appname;
	     }
       } else {
          $t = $this->temp_app_list . ":";
          unset($this->temp_app_list);
          return $t;
       }
    }
    
    function sql_search($table,$owner=0)
    {
      global $phpgw_info;
      global $phpgw;
      $s = "";
// Changed By: Skeeter  29 Nov 00
// This is to allow the user to search for other individuals group info....
      if(!$owner) {
	$owner = $phpgw_info["user"]["account_id"];
      }
      $db = $phpgw->db;
      $db->query("SELECT account_lid FROM accounts WHERE account_id=".$owner,__LINE__,__FILE__);
      $db->next_record();
      $groups = $this->read_groups($db->f("account_lid"));
      if (gettype($groups) == "array") {
//         echo "\n\n\n\n\ntest: " . count($groups) . "\n\n\n\n\n\n";
         while ($group = each($groups)) {
           $s .= " or $table like '%," . $group[0] . ",%'";
	 }
      }
      return $s;
    }

    // This is used to split the arrays in the access column into an array    
    function string_to_array($s)
    {
       $raw_array = explode(",",$s);

       for ($i=1,$j=0;$i<count($raw_array)-1; $i++,$j++) {
          $return_array[$j] = $raw_array[$i];
       }

       return $return_array;
    }


    // This is used to convert a raw group string (,5,6,7,) into a string of
    // there names.
    // Example: accounting, billing, developers
    function convert_string_to_names($gs)
    {
      global $phpgw;

      $groups = explode(",",$gs);

      $s = "";
      for ($i=1;$i<count($groups)-1; $i++) {
	      $group_number = explode(",",$groups[$i]);
        //$phpgw->db->query("select group_name from groups where group_id=".$groups[$i]);
        $phpgw->db->query("select group_name from groups where group_id=".$group_number[0],__LINE__,__FILE__);
        $phpgw->db->next_record();
        $s .= $phpgw->db->f("group_name");
        if (count($groups) != 0 && $i != count($groups)-2)
           $s .= ",";
        }
      return $s;
    }    

    // This one is used for the access column
    // This is used to convert a raw group string (,5,6,7,) into a string of
    // there names.
    // Example: accounting, billing, developers
    function convert_string_to_names_access($gs)
    {
      global $phpgw;

      $groups = explode(",",$gs);
      
      $db2 = $phpgw->db;

      $s = ""; $i = 0;
      for ($j=1;$j<count($groups)-1; $j++) {      
         $db2->query("select group_name from groups where group_id=".$groups[$j],__LINE__,__FILE__);
         $db2->next_record();
         $group_names[$i] = $db2->f("group_name");
         $i++;
      }
      return implode(",",$group_names);
    }    

    // Convert an array into the format needed for the groups column in the accounts table.
    // This function is only temp, until we create the wrapper class's for different forms
    // of auth.
    function groups_array_to_string($groups)
    {
      $s = "";
      if (count($groups)) {
         while (list($t,$group,$level) = each($groups)) {
		    $s .= "," . $group . ":0";
         }
         $s .= ",";
      }
      return $s;
    }
    
    // Convert an array into the format needed for the access column.
    function array_to_string($access,$array)
    {
      $s = "";
      if ($access == "group" || $access == "public" || $access == "none") {
         if (count($array)) {
            while ($t = each($array)) {
   			$s .= "," . $t[1];
            }
            $s .= ",";
         }
         if (! count($array) && $access == "none") {
            $s = "";
         }
      }
      return $s;
    }
  }
?>
