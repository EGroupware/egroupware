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
       $phpgw->preferences->read_preferences();
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
       $phpgw_info_temp["user"]["kp3"] = "";                     // We don't want it anywhere in the
                                                                 // database for security.

       $db->query("update phpgw_sessions set session_info='" . serialize($phpgw_info_temp)
                . "' where session_id='" . $phpgw_info["user"]["sessionid"] . "'",__LINE__,__FILE__);

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

    function read_preferences()
    {
      global $phpgw, $phpgw_info;

      $phpgw->db->query("select preference_value from preferences where preference_owner='"
                      . $phpgw_info["user"]["account_id"] . "'",__LINE__,__FILE__);
      $phpgw->db->next_record();
      $phpgw_info["user"]["preferences"] = unserialize($phpgw->db->f("preference_value"));
    }

    // This should be called when you are doing changing the $phpgw_info["user"]["preferences"]
    // array
    function commit($line = "",$file = "")
    {
       //echo "<br>commit called<br>Line: $line<br>File: $file";
    
       global $phpgw_info, $phpgw;
       $db = $phpgw->db;

       $db->query("delete from preferences where preference_owner='" . $phpgw_info["user"]["account_id"]
                . "'",__LINE__,__FILE__);

       $db->query("insert into preferences (preference_owner,preference_value) values ('"
                . $phpgw_info["user"]["account_id"] . "','" . serialize($phpgw_info["user"]["preferences"])
                . "')",__LINE__,__FILE__);
       $phpgw->accounts->sync(__LINE__,__FILE__);
    }

    // Add a new preference.
    function change($app_name,$var,$value = "")
    {
       global $phpgw_info;
     
       if (! $value) {
          global $$var;
          $value = $$var;
       }
 
       $phpgw_info["user"]["preferences"][$app_name][$var] = $value;
    }
    
    function delete($app_name,$var)
    {
      global $phpgw_info;
      unset($phpgw_info["user"]["preferences"][$app_name][$var]);    
    }

    // This will kill all preferences within a certain app
    function reset($app_name)
    {
      global $phpgw_info;
      $phpgw_info["user"]["preferences"][$app_name] = array();
    }

    // This will commit preferences for a new user
    function commit_newuser($n_loginid) {
       global $phpgw;
     
       $db = $phpgw->db;
       $db->lock(array("accounts"));
       $db->query("SELECT account_id FROM accounts WHERE account_lid='".$n_loginid."'");
       $db->next_record();
       $id = $db->f("account_id");
       $db->unlock();
       $this->commit_user($id);
    }

    // This will commit preferences for a new user
    function commit_user($id) {
       global $phpgw_newuser, $phpgw;
     
       $db = $phpgw->db;
       $db->lock(array("preferences"));
       $db->query("SELECT * FROM preferences WHERE preference_owner=".$id);
       if($db->num_rows()) {
	 $db->query("UPDATE preferences SET preference_value = '"
		. serialize($phpgw_newuser["user"]["preferences"])
		. "' WHERE preference_owner=".$id,__LINE__,__FILE__);
       } else {
	 $db->query("insert into preferences (preference_owner,preference_value) values ("
		. $id.",'".serialize($phpgw_newuser["user"]["preferences"])."')",__LINE__,__FILE__);
       }
       $db->unlock();
       unset($phpgw_newuser);
    }

    // This will add all preferences within a certain app for a new user
    function add_newuser($app_name,$var,$value="") {
       global $phpgw_newuser;
     
       if (! $value) {
          global $$var;
          $value = $$var;
       }
 
       $phpgw_newuser["user"]["preferences"][$app_name][$var] = $value;
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
    }

    function edit($app, $location, $id, $id_type, $rights){
    }

    function replace($app, $location, $id, $id_type, $rights){
    }

    function delete($app, $location, $id, $id_type){
    }

    function view($app, $location, $id, $id_type){
    }

  } //end of acl class
?>
