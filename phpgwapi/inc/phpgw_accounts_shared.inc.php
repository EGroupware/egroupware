<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */
  
  class accounts extends accounts_
  {
  
    function accounts_const($line,$file)
    {
       global $phpgw, $phpgw_info;
       
       //echo "accounts_const called<br>line: $line<br>$file";

       $phpgw->accounts->phpgw_fillarray();
       if(! $phpgw->preferences->account_id) {
         $phpgw->preferences->preferences($phpgw_info["user"]["account_id"]);
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



  class preferences
  {
    var $account_id = 0;
    var $preference;

    function preferences($account_id)
    {
      global $phpgw;

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

      if ($load_pref) {
	$db2->query("SELECT preference_value FROM preferences WHERE preference_owner=".$this->account_id,__LINE__,__FILE__);
	$db2->next_record();
	$pref_info = $db2->f("preference_value");
	$this->preference = unserialize($pref_info);
      }
    }

    // This should be called when you are done makeing changes to the preferences
    function commit($line = "",$file = "")
    {
      global $phpgw, $phpgw_info;

      //echo "<br>commit called<br>Line: $line<br>File: $file".$phpgw_info["user"]["account_id"]."<br>";
    
      if ($this->account_id) {
        $db = $phpgw->db;

        $db->query("delete from preferences where preference_owner='" . $this->account_id . "'",__LINE__,__FILE__);

        if ($PHP_VERSION < "4.0.0") {
	  $pref_info = addslashes(serialize($this->preference));
	} else {
	  $pref_info = serialize($this->preference);
	}

        $db->query("insert into preferences (preference_owner,preference_value) values ("
                  . $this->account_id . ",'" . $pref_info . "')",__LINE__,__FILE__);

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
