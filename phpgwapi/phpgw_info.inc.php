<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * Written by Dan Kuykendall <seek3r@phpgroupware.org>                      *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $d1 = strtolower(substr($phpgw_info["server"]["api_dir"],0,3));
  $d2 = strtolower(substr($phpgw_info["server"]["server_root"],0,3));
  if($d1 == "htt" || $d1 == "ftp" || $d2 == "htt" || $d2 == "ftp") {
    echo "Failed attempt to break in via an old Security Hole!<br>\n";
    exit;
  } unset($d1);unset($d2);

  magic_quotes_runtime(false);

  /* Make sure the developer is following the rules. */
  if (!isset($phpgw_info["flags"]["currentapp"])) {
	  echo "!!! YOU DO NOT HAVE YOUR \$phpgw_info[\"flags\"][\"currentapp\"] SET !!!";
	  echo "!!! PLEASE CORRECT THIS SITUATION !!!";
  }

  if (empty($phpgw_info["server"]["default_tplset"])){
    $phpgw_info["server"]["default_tplset"] = "default";
  }

  if (empty($phpgw_info["server"]["template_dir"])){
    $phpgw_info["server"]["template_dir"] = $phpgw_info["server"]["api_dir"]."/templates/".$phpgw_info["server"]["default_tplset"];
  }

  if (!isset($phpgw_domain)) { // make them fix their header
    echo "The administration is required to upgrade the header.inc.php file before you can continue.";
    exit;
  }

  reset($phpgw_domain);
  $default_domain = each($phpgw_domain);
  $phpgw_info["server"]["default_domain"] = $default_domain[0];
  unset ($default_domain); // we kill this for security reasons

  // This code will handle virtdomains so that is a user logins with user@domain.com, it will switch into virtualization mode.
  if (isset($domain)){
    $phpgw_info["user"]["domain"] = $domain;
  }elseif (isset($login) && isset($logindomain)){
    if (!ereg ("\@", $login)){
      $login = $login."@".$logindomain;
    }
    $phpgw_info["user"]["domain"] = $logindomain;
    unset ($logindomain);
  }elseif (isset($login) && !isset($logindomain)){
    if (ereg ("\@", $login)){
      $login_array = explode("@", $login);
      $phpgw_info["user"]["domain"] = $login_array[1];
    }else{
      $phpgw_info["user"]["domain"] = $phpgw_info["server"]["default_domain"];
      $login = $login."@".$phpgw_info["user"]["domain"];
    }
  }

  if (isset($phpgw_domain[$phpgw_info["user"]["domain"]])){
    $phpgw_info["server"]["db_host"] = $phpgw_domain[$phpgw_info["user"]["domain"]]["db_host"];
    $phpgw_info["server"]["db_name"] = $phpgw_domain[$phpgw_info["user"]["domain"]]["db_name"];
    $phpgw_info["server"]["db_user"] = $phpgw_domain[$phpgw_info["user"]["domain"]]["db_user"];
    $phpgw_info["server"]["db_pass"] = $phpgw_domain[$phpgw_info["user"]["domain"]]["db_pass"];
    $phpgw_info["server"]["db_type"] = $phpgw_domain[$phpgw_info["user"]["domain"]]["db_type"];
  }else{
    $phpgw_info["server"]["db_host"] = $phpgw_domain[$phpgw_info["server"]["default_domain"]]["db_host"];
    $phpgw_info["server"]["db_name"] = $phpgw_domain[$phpgw_info["server"]["default_domain"]]["db_name"];
    $phpgw_info["server"]["db_user"] = $phpgw_domain[$phpgw_info["server"]["default_domain"]]["db_user"];
    $phpgw_info["server"]["db_pass"] = $phpgw_domain[$phpgw_info["server"]["default_domain"]]["db_pass"];
    $phpgw_info["server"]["db_type"] = $phpgw_domain[$phpgw_info["server"]["default_domain"]]["db_type"];
  }

  if ($phpgw_info["flags"]["currentapp"] != "login" && ! $phpgw_info["server"]["show_domain_selectbox"]) {
     unset ($phpgw_domain); // we kill this for security reasons  
  }
  unset ($domain); // we kill this to save memory

  // some constants which can be used in setting user acl rights.
  define("PHPGW_ACL_READ",1);
  define("PHPGW_ACL_ADD",2);
  define("PHPGW_ACL_EDIT",4);
  define("PHPGW_ACL_DELETE",8);

  // This function needs to be optimized, its reading duplicate information.
  function phpgw_fillarray()
  {
    global $phpgw, $phpgw_info, $cd, $colspan;
    $phpgw_info["server"]["images_dir"]   = $phpgw_info["server"]["webserver_url"] . "/images";
    $phpgw_info["server"]["template_dir"] = $phpgw_info["server"]["api_dir"] . "/templates/"
                                          . $phpgw_info["server"]["default_tplset"];
  
    $phpgw_info["server"]["app_root"]   = $phpgw_info["server"]["server_root"]."/".$phpgw_info["flags"]["currentapp"];
    $phpgw_info["server"]["app_inc"]    = $phpgw_info["server"]["app_root"]."/inc";
    $phpgw_info["server"]["app_images"] = $phpgw_info["server"]["webserver_url"]."/".$phpgw_info["flags"]["currentapp"]."/images";
    $phpgw_info["server"]["app_tpl"]    = $phpgw_info["server"]["app_root"]."/templates/".$phpgw_info["server"]["default_tplset"];
  
    /* ********This sets the user variables******** */
    $phpgw_info["user"]["private_dir"] = $phpgw_info["server"]["files_dir"] . "/users/"
                   					     . $phpgw_info["user"]["userid"];
  
    $phpgw_info["server"]["my_include_dir"] = $phpgw_info["server"]["app_inc"];

    // This shouldn't happen, but if it does get ride of the warnings it will spit out    
    if (gettype($phpgw_info["user"]["preferences"]) != "array") {
       $phpgw_info["user"]["preferences"] = array();
    }
  }
?>
