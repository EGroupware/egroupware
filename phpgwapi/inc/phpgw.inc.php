<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */
  
  $d1 = strtolower(substr($phpgw_info["server"]["api_inc"],0,3));
  $d2 = strtolower(substr($phpgw_info["server"]["server_root"],0,3));
  $d3 = strtolower(substr($phpgw_info["server"]["app_inc"],0,3));
  if($d1 == "htt" || $d1 == "ftp" || $d2 == "htt" || $d2 == "ftp" || $d3 == "htt" || $d3 == "ftp") {
    echo "Failed attempt to break in via an old Security Hole!<br>\n";
    exit;
  } unset($d1);unset($d2);unset($d3);

  /****************************************************************************\
  * Direct functions, which are not part of the API class                      *
  * for whatever reason.                                                       *
  \****************************************************************************/
  function CreateObject($classname, $constructor_param = ""){
    global $phpgw, $phpgw_info, $phpgw_domain;
    $classpart = explode (".", $classname);
    if (!$phpgw_info["flags"]["included_classes"][$classpart[1]]){
      $phpgw_info["flags"]["included_classes"][$classpart[1]] = True;   
      include($phpgw_info["server"]["include_root"]."/".$classpart[0]."/inc/class.".$classpart[1].".inc.php");
    }
    $obj = new $classpart[1]($constructor_param);
    return $obj;
  }

  function lang($key, $m1="", $m2="", $m3="", $m4="", $m5="", $m6="", $m7="", $m8="", $m9="", $m10=""  ) 
  {
    global $phpgw;
    // # TODO: check if $m1 is of type array.
    // If so, use it instead of $m2-$mN (Stephan)
    $vars = array( $m1, $m2, $m3, $m4, $m5, $m6, $m7, $m8, $m9, $m10 );
    $value = $phpgw->translation->translate("$key", $vars );
    return $value;
  }


  // Just a temp wrapper.
  function check_code($code)
  {
    global $phpgw;
    return $phpgw->common->check_code($code);
  }

  /****************************************************************************\
  * Optional classes, which can be disabled for performance increases          *
  *  - they are loaded after pulling in the config from the DB                 *
  \****************************************************************************/
  function load_optional()
  {
    global $phpgw,$phpgw_info;
 
    if ($phpgw_info["flags"]["enable_categories_class"]) {
      $phpgw->categories = CreateObject("phpgwapi.categories");
    }
 
    if ($phpgw_info["flags"]["enable_network_class"]) {
      $phpgw->network = CreateObject("phpgwapi.network");
    }
   
    if ($phpgw_info["flags"]["enable_send_class"]) {
     $phpgw->send = CreateObject("phpgwapi.send");
    }

    if ($phpgw_info["flags"]["enable_nextmatchs_class"]) {
     $phpgw->nextmatchs = CreateObject("phpgwapi.nextmatchs");
    }
   
    if ($phpgw_info["flags"]["enable_utilities_class"]) {
     $phpgw->utilities = CreateObject("phpgwapi.utilities");
    }

    if ($phpgw_info["flags"]["enable_vfs_class"]) {
     $phpgw->vfs = CreateObject("phpgwapi.vfs");
    }
  } 

  /****************************************************************************\
  * Quick verification of updated header.inc.php                               *
  \****************************************************************************/
  error_reporting(7);
  if ($phpgw_info["server"]["versions"]["header"] != $phpgw_info["server"]["versions"]["current_header"]){
    echo "You need to port your settings to the new header.inc.php version.";
  }

  /****************************************************************************\
  * Load up all the base values                                                 *
  \****************************************************************************/
  magic_quotes_runtime(false);

  /* Make sure the developer is following the rules. */
  if (!isset($phpgw_info["flags"]["currentapp"])) {
	  echo "!!! YOU DO NOT HAVE YOUR \$phpgw_info[\"flags\"][\"currentapp\"] SET !!!";
	  echo "!!! PLEASE CORRECT THIS SITUATION !!!";
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
    $phpgw_info["server"]["template_dir"] = $phpgw->common->get_tpl_dir("phpgwapi");
    $phpgw_info["server"]["images_dir"]   = $phpgw->common->get_image_path("phpgwapi");
    $phpgw_info["server"]["images_filedir"]   = $phpgw->common->get_image_dir("phpgwapi");
    $phpgw_info["server"]["app_root"]   = $phpgw->common->get_app_dir();
    $phpgw_info["server"]["app_inc"]    = $phpgw->common->get_inc_dir();
    $phpgw_info["server"]["app_tpl"]    = $phpgw->common->get_tpl_dir();
    $phpgw_info["server"]["app_images"] = $phpgw->common->get_image_path();
    $phpgw_info["server"]["app_images_dir"] = $phpgw->common->get_image_dir();
  
    /* ********This sets the user variables******** */
    $phpgw_info["user"]["private_dir"] = $phpgw_info["server"]["files_dir"] . "/users/"
                   					     . $phpgw_info["user"]["userid"];
  
    // This shouldn't happen, but if it does get ride of the warnings it will spit out    
    if (gettype($phpgw_info["user"]["preferences"]) != "array") {
       $phpgw_info["user"]["preferences"] = array();
    }
  }

  /****************************************************************************\
  * These lines load up the API, fill up the $phpgw_info array, etc            *
  \****************************************************************************/
  $phpgw = CreateObject("phpgwapi.phpgw");
  $phpgw->phpgw_();
  if ($phpgw_info["flags"]["currentapp"] != "login" &&
      $phpgw_info["flags"]["currentapp"] != "logout") {
      //if (! $phpgw->session->verify()) {
      //   Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/login.php", "cd=10"));
      //   exit;
      //}
     load_optional();

     phpgw_fillarray();
     $phpgw->common->common_();

     if ($phpgw_info["flags"]["enable_utilities_class"]){
        $phpgw->utilities->utilities_();
     }

    if (!isset($phpgw_info["flags"]["nocommon_preferences"]) ||
  	!$phpgw_info["flags"]["nocommon_preferences"]) {
      if (!isset($phpgw_info["user"]["preferences"]["common"]["maxmatchs"]) ||
	    !$phpgw_info["user"]["preferences"]["common"]["maxmatchs"]) {
        $phpgw->preferences->change("common","maxmatchs",15);
        $preferences_update = True;
      }
      if (!isset($phpgw_info["user"]["preferences"]["common"]["theme"]) ||
	    !$phpgw_info["user"]["preferences"]["common"]["theme"]) {
        $phpgw->preferences->change("common","theme","default");
        $preferences_update = True;
      }
      if (!isset($phpgw_info["user"]["preferences"]["common"]["dateformat"]) ||
	    !$phpgw_info["user"]["preferences"]["common"]["dateformat"]) {
        $phpgw->preferences->change("common","dateformat","m/d/Y");
        $preferences_update = True;
      }
      if (!isset($phpgw_info["user"]["preferences"]["common"]["timeformat"]) ||
	    !$phpgw_info["user"]["preferences"]["common"]["timeformat"]) {
        $phpgw->preferences->change("common","timeformat",12);
        $preferences_update = True;
      }
      if (!isset($phpgw_info["user"]["preferences"]["common"]["lang"]) ||
	    !$phpgw_info["user"]["preferences"]["common"]["lang"]) {
	      $phpgw->preferences->change("common","lang",$phpgw->common->getPreferredLanguage());
        $preferences_update = True;
      }
      if ($preferences_update) {
        echo "Committing new preferences<br>\n";
        $phpgw->preferences->commit(__LINE__,__FILE__);
      }
      unset($preferences_update);
    }
     /*************************************************************************\
     * These lines load up the themes                                          *
     \*************************************************************************/
     include($phpgw_info["server"]["server_root"] . "/phpgwapi/themes/" .
	     $phpgw_info["user"]["preferences"]["common"]["theme"] . ".theme");

     if ($phpgw_info["theme"]["bg_color"] == "") {
        /* Looks like there was a problem finding that theme. Try the default */
        echo "Warning: error locating selected theme";
        include ($phpgw_info["server"]["server_root"] . "/phpgwapi/themes/default.theme");
        if ($phpgw_info["theme"]["bg_color"] == "") {
           // Hope we don't get to this point.  Better then the user seeing a 
           // complety back screen and not know whats going on
           echo "<body bgcolor=FFFFFF>Fatal error: no themes found";
           exit;
        }
     }
     /*************************************************************************\
     * If they are using frames, we need to set some variables                 *
     \*************************************************************************/
     if (($phpgw_info["user"]["preferences"]["common"]["useframes"] && $phpgw_info["server"]["useframes"] == "allowed")
        || ($phpgw_info["server"]["useframes"] == "always")) {
        $phpgw_info["flags"]["navbar_target"] = "phpgw_body";
     }

     /*************************************************************************\
     * Verify that the users session is still active otherwise kick them out   *
     \*************************************************************************/
     if ($phpgw_info["flags"]["currentapp"] != "home" &&
	    $phpgw_info["flags"]["currentapp"] != "logout" &&
	    $phpgw_info["flags"]["currentapp"] != "preferences" &&
	    $phpgw_info["flags"]["currentapp"] != "about") {

      if (! $phpgw_info["user"]["apps"][$phpgw_info["flags"]["currentapp"]]) {
        $phpgw->common->phpgw_header();
        echo "<p><center><b>".lang("Access not permitted")."</b></center>";
        exit;
      }
    }

     /*************************************************************************\
     * Load the header unless the developer turns it off                       *
     \*************************************************************************/
     if (! $phpgw_info["flags"]["noheader"]) {
        $phpgw->common->phpgw_header();
     }

     /*************************************************************************\
     * Load the app include files if the exists                                *
     \*************************************************************************/
     /* Then the include file */
     if (file_exists ($phpgw_info["server"]["app_inc"]."/functions.inc.php")){
        include($phpgw_info["server"]["app_inc"]."/functions.inc.php");
     }

     if (!$phpgw_info["flags"]["noheader"] &&
	    !$phpgw_info["flags"]["noappheader"] &&
  	    file_exists ($phpgw_info["server"]["app_inc"]."/header.inc.php")) {
        include($phpgw_info["server"]["app_inc"]."/header.inc.php");
      }
  }
  error_reporting(7);
