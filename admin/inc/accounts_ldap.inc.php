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

  if (! ldap_bind($ldap, $phpgw_info["server"]["ldap_root_dn"], $phpgw_info["server"]["ldap_root_pw"])) {
     echo "<p><b>Error binding to LDAP server.  Check your config</b>";
     exit;
  }

  
  function account_read($method,$start,$sort,$order)
  {
  
  }
  
  function account_add($account_info)
  {
     global $phpgw_info, $ldap;

     if ($phpgw_info["server"]["ldap_encryption_type"] == "DES") {
        $salt = randomstring(2);
        $userpassword = descryptpass($account_info["passwd"], $salt);
     }

     if ($phpgw_info["server"]["ldap_encryption_type"] == "MD5") {
        $salt = randomstring(9);
        $userpassword = md5cryptpass($account_info["passwd"], $salt);
     }

     // Create our entry
     $entry["uid"]              = $uid;
     $entry["uidNumber"]        = $uidnumber;
     $entry["gidNumber"]		= $gidnumber;
     $entry["userpassword"]	 = $userpassword;
     $entry["loginShell"]	   = $ushell;
     $entry["homeDirectory"]	= $homedir;
     $entry["cn"]			   = sprintf("%s %s", $givenname, $sn);
     $entry["sn"]			   = $sn;
     $entry["givenname"]		= $givenname;
     $entry["company"]		  = $company;
     $entry["title"] 		   = $title;
     $entry["mail"]			 = $mail;
     $entry["telephonenumber"]  = $telephonenumber;
     $entry["homephone"]		= $homephone;
     $entry["pagerphone"]	   = $pagerphone;
     $entry["cellphone"]		= $cellphone;
     $entry["streetaddress"]	= $streetaddress;
     $entry["locality"]		 = $locality;
     $entry["st"] 			  = $st;
     $entry["postalcode"]	   = $postalcode;
     $entry["countryname"] 	 = $countryname;
     $entry["homeurl"]		  = $homeurl;
     $entry["description"]	  = $description;
     $entry["objectclass"][0]   = "account";
     $entry["objectclass"][1]   = "posixAccount";
     $entry["objectclass"][2]   = "shadowAccount";
     $entry["objectclass"][3]   = "inetOrgperson";
     $entry["objectclass"][4]   = "person;
     $entry["objectclass"][5]   = "top";
     /* $dn=sprintf("cn=%s %s, %s", $givenname, $sn, $BASEDN);*/
     $dn=sprintf("uid=%s, %s", $uid, $BASEDN); 
      
     // add the entries
     if (ldap_add($ldap, $dn, $entry)) {
        $cd = 28;
     } else {
        $cd = 99;		// Come out with a code for this
     }
  
     @ldap_close($ldap);

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
