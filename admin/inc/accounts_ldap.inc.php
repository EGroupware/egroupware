<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  
  /* $Id$ */
  
  // Sections of code where taking from slapda http://www.jeremias.net/projects/sldapa  by
  // Jason Jeremias <jason@jeremias.net>
  
  
  $ldap = ldap_connect($phpgw_info["server"]["ldap_host"]);

  if (! @ldap_bind($ldap, $phpgw_info["server"]["ldap_root_dn"], $phpgw_info["server"]["ldap_root_pw"])) {
     echo "<p><b>Error binding to LDAP server.  Check your config</b>";
     exit;
  }

  function getSearchLine($searchstring)
  {
     if (($searchstring=="*") || ($searchstring=="")) {
        $searchline = "cn=*";
     } else {
        $searchline = sprintf("cn=*%s*",$searchstring);
     }
     return $searchline;
  }

  function descryptpass($userpass, $random)
  {
    $lcrypt = "{crypt}";
    $password = crypt($userpass);
    $ldappassword = sprintf("%s%s", $lcrypt, $password);
 
    return $ldappassword;
  }

  function md5cryptpass($userpass, $random)
  {
    $bsalt = "$1$";
    $lcrypt = "{crypt}";
    $modsalt = sprintf("%s%s", $bsalt, $random);
    $password = crypt($userpass, $modsalt);
    $ldappassword = sprintf("%s%s", $lcrypt, $password);
  
    return $ldappassword;
  }
  
  // Not the best method, but it works for now.
  function account_total()
  {
    global $phpgw_info, $ldap;

    $filter = "(|(uid=*))";
    $sr = ldap_search($ldap,$phpgw_info["server"]["ldap_context"],$filter,array("uid"));
    $info = ldap_get_entries($ldap, $sr);

    return count($info);
  }
  
  function account_view($loginid)
  {
    global $phpgw_info, $ldap;

    $filter = "(|(uid=$loginid))";
    $sr = ldap_search($ldap,$phpgw_info["server"]["ldap_context"],$filter,array("sn","givenname","uid","uidnumber"));
    $aci = ldap_get_entries($ldap, $sr);
    
    $account_info["account_id"]        = $aci[0]["uid"][0];
    $account_info["account_lid"]       = $aci[0]["uidnumber"][0];
    $account_info["account_lastname"]  = $aci[0]["sn"][0];
    $account_info["account_firstname"] = $aci[0]["givenname"][0];

    return $account_info;
  }

  function account_read($method,$start,$sort,$order)
  {
    global $phpgw_info, $ldap;
  
    $filter = "(|(uid=*))";
    $sr = ldap_search($ldap,$phpgw_info["server"]["ldap_context"],$filter,array("sn","givenname","uid","uidnumber"));
    $info = ldap_get_entries($ldap, $sr);
  
    for ($i=0; $i<count($info); $i++) {
       if (! $phpgw_info["server"]["global_denied_users"][$info[$i]["uid"][0]]) {
          $account_info[$i]["account_id"]        = $info[$i]["uidnumber"][0];
          $account_info[$i]["account_lid"]       = $info[$i]["uid"][0];
          $account_info[$i]["account_firstname"] = $info[$i]["givenname"][0];
          $account_info[$i]["account_lastname"]  = $info[$i]["sn"][0];
       }
    }

    return $account_info;
  }
  
  function account_add($account_info)
  {
     global $phpgw_info, $phpgw, $ldap;

     if ($phpgw_info["server"]["ldap_encryption_type"] == "DES") {
        $salt = $phpgw->common->randomstring(2);
        $account_info["passwd"] = descryptpass($account_info["passwd"], $salt);
     }

     if ($phpgw_info["server"]["ldap_encryption_type"] == "MD5") {
        $salt = $phpgw->common->randomstring(9);
        $account_info["passwd"] = md5cryptpass($account_info["passwd"], $salt);
     }

     // This method is only temp.  We need to figure out the best way to assign uidnumbers and
     // guidnumbers.
     
     $phpgw->db->query("select (max(account_id)+1) from accounts");
     $phpgw->db->next_record();
     
     $account_info["account_id"] = $phpgw->db->f(0);

     // Much of this is going to be guess work for now, until we get things planned out.
     $entry["uid"]              = $account_info["loginid"];
     $entry["uidNumber"]        = $account_info["account_id"];
     $entry["gidNumber"]		= $account_info["account_id"];
     $entry["userpassword"]	 = $account_info["passwd"];
     $entry["loginShell"]	   = "/bin/bash";
     $entry["homeDirectory"]	= "/home/" . $account_info["loginid"];
     $entry["cn"]			   = sprintf("%s %s", $account_info["firstname"], $account_info["lastname"]);
     $entry["sn"]			   = $account_info["lastname"];
     $entry["givenname"]		= $account_info["firstname"];
     //$entry["company"]		  = $company;
     //$entry["title"] 		   = $title;
     $entry["mail"]			 = $account_info["loginid"] . "@" . $phpgw_info["server"]["mail_suffix"];
     //$entry["telephonenumber"]  = $telephonenumber;
     //$entry["homephone"]		= $homephone;
     //$entry["pagerphone"]	   = $pagerphone;
     //$entry["cellphone"]		= $cellphone;
     //$entry["streetaddress"]	= $streetaddress;
     //$entry["locality"]		 = $locality;
     //$entry["st"] 			  = $st;
     //$entry["postalcode"]	   = $postalcode;
     //$entry["countryname"] 	 = $countryname;
     //$entry["homeurl"]		  = $homeurl;
     //$entry["description"]	  = $description;
     $entry["objectclass"][0]   = "account";
     $entry["objectclass"][1]   = "posixAccount";
     $entry["objectclass"][2]   = "shadowAccount";
     $entry["objectclass"][3]   = "inetOrgperson";
     $entry["objectclass"][4]   = "person";
     $entry["objectclass"][5]   = "top";
     /* $dn=sprintf("cn=%s %s, %s", $givenname, $sn, $BASEDN);*/
     $dn=sprintf("uid=%s, %s", $account_info["loginid"], $phpgw_info["server"]["ldap_context"]);
      
     // add the entries
     if (ldap_add($ldap, $dn, $entry)) {
        $cd = 28;
     } else {
        $cd = 99;		// Come out with a code for this
     }
  
     @ldap_close($ldap);
     
     $phpgw->db->lock(array("accounts","preferences"));

     $phpgw->common->preferences_add($account_info["loginid"],"maxmatchs","common","15");
     $phpgw->common->preferences_add($account_info["loginid"],"theme","common","default");
     $phpgw->common->preferences_add($account_info["loginid"],"tz_offset","common","0");
     $phpgw->common->preferences_add($account_info["loginid"],"dateformat","common","m/d/Y");
     $phpgw->common->preferences_add($account_info["loginid"],"timeformat","common","12");
     $phpgw->common->preferences_add($account_info["loginid"],"lang","common","en");
     $phpgw->common->preferences_add($account_info["loginid"],"company","addressbook","True");
     $phpgw->common->preferences_add($account_info["loginid"],"lastname","addressbook","True");
     $phpgw->common->preferences_add($account_info["loginid"],"firstname","addressbook","True");

     // Even if they don't have access to the calendar, we will add these.
     // Its better then the calendar being all messed up, they will be deleted
     // the next time the update there preferences.
     $phpgw->common->preferences_add($account_info["loginid"],"weekstarts","calendar","Monday");
     $phpgw->common->preferences_add($account_info["loginid"],"workdaystarts","calendar","9");
     $phpgw->common->preferences_add($account_info["loginid"],"workdayends","calendar","17");

     while ($permission = each($account_info["permissions"])) {
       if ($phpgw_info["apps"][$permission[0]]["enabled"]) {
          $phpgw->accounts->add_app($permission[0]);
       }
     }

     $sql = "insert into accounts (account_id,account_lid,account_pwd,account_firstname,"
          . "account_lastname,account_permissions,account_groups,account_status,"
          . "account_lastpwd_change) values ('" . $account_info["account_id"] . "','"
          . $account_info["loginid"] . "','x','". addslashes($account_info["firstname"]) . "','"
          . addslashes($account_info["lastname"]) . "','" . $phpgw->accounts->add_app("",True)
          . "','" . $account_info["groups"] . "','A',0)";

     $phpgw->db->query($sql);
     $phpgw->db->unlock();

     $sep = $phpgw->common->filesystem_separator();

     $basedir = $phpgw_info["server"]["files_dir"] . $sep . "users" . $sep;

     if (! @mkdir($basedir . $n_loginid, 0707)) {
        $cd = 36;
     } else {
        $cd = 28;
     }

     return $cd;
  }
  
  function account_edit($account_info)
  {
  
  }
  
  function account_delete($account_id)
  {
     global $ldap;

     $searchline = getSearchLine($searchstring);
     $result     = ldap_search($ldap, $BASEDN, $searchline);
     $entry      = ldap_get_entries($ldap, $result);
     $numentries = $entry["count"];
 
    @ldap_delete($ldap, $button); 
  }

  function account_exsists($loginid)
  {

  }
  
  function account_close()
  {
     @ldap_close($ldap);  
  }
