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

  error_reporting(7);

  /**************************************************************************\
  * Quick verification of updated header.inc.php                             *
  \**************************************************************************/
  if ($phpgw_info["server"]["versions"]["header"] != $phpgw_info["server"]["versions"]["current_header"]){
    echo "You need to port your settings to the new header.inc.php version.";
  }

  /**************************************************************************\
  * Load up all the base files                                               *
  \**************************************************************************/
  include($phpgw_info["server"]["api_inc"] . "/phpgw_info.inc.php");

  /**************************************************************************\
  * Required classes                                                         *
  \**************************************************************************/
  /* Load selected database class */
  if (empty($phpgw_info["server"]["db_type"])){$phpgw_info["server"]["db_type"] = "mysql";}
  include($phpgw_info["server"]["api_inc"] . "/phpgw_db_".$phpgw_info["server"]["db_type"].".inc.php");

  include($phpgw_info["server"]["api_inc"] . "/phpgw_session.inc.php");

  /* Load selected translation class */
  if (empty($phpgw_info["server"]["translation_system"])){$phpgw_info["server"]["translation_system"] = "sql";}
  include($phpgw_info["server"]["api_inc"] . "/phpgw_lang_".$phpgw_info["server"]["translation_system"].".inc.php");

  include($phpgw_info["server"]["api_inc"] . "/phpgw_crypto.inc.php");
  include($phpgw_info["server"]["api_inc"] . "/phpgw_template.inc.php");
  include($phpgw_info["server"]["api_inc"] . "/phpgw_common.inc.php");

  /**************************************************************************\
  * Optional classes, which can be disabled for performance increases        *
  *  - they are loaded after pulling in the config from the DB               *
  \**************************************************************************/
  function load_optional()
  {
    global $phpgw,$phpgw_info;
 
    if ($phpgw_info["flags"]["enable_categories_class"]) {
       include($phpgw_info["server"]["api_inc"] . "/phpgw_categories.inc.php");
       $phpgw->categories = new categories;
    }
 
    if ($phpgw_info["flags"]["enable_network_class"]) {
       include($phpgw_info["server"]["api_inc"] . "/phpgw_network.inc.php");
       $phpgw->network = new network;
    }
   
    if ($phpgw_info["flags"]["enable_send_class"]) {
  	 include($phpgw_info["server"]["api_inc"] . "/phpgw_send.inc.php");
     $phpgw->send = new send;
    }

    if ($phpgw_info["flags"]["enable_nextmatchs_class"]) {
       include($phpgw_info["server"]["api_inc"] . "/phpgw_nextmatchs.inc.php");
       $phpgw->nextmatchs = new nextmatchs;
    }
   
    if ($phpgw_info["flags"]["enable_utilities_class"]) {
       include($phpgw_info["server"]["api_inc"] . "/phpgw_utilities.inc.php");
       $phpgw->utilities	= new utilities;
    }

    if ($phpgw_info["flags"]["enable_vfs_class"]) {
       include($phpgw_info["server"]["api_inc"] . "/phpgw_vfs.inc.php");
       $phpgw->vfs  = new vfs;
    }
  } 
    
  /**************************************************************************\
  * Our API class starts here                                                *
  \**************************************************************************/
  class phpgw
  {
    var $accounts;
    var $acl;
    var $auth;
    var $db;
    var $debug = 0;		// This will turn on debugging information. (Not fully working)
    var $crypto;
    var $categories;
    var $common;
    var $hooks;
    var $network;
    var $nextmatchs;
    var $preferences;
    var $session;
    var $send;
    var $template;
    var $translation;
    var $utilities;
    var $vfs;

    var $calendar;
    var $msg;

    var $addressbook;
    var $todo;

    // This is here so you can decied what the best way to handle bad sessions
    // You could redirect them to login.php with code 2 or use the default
    // I recommend using the default until all of the bugs are worked out.

    function phpgw_()
    {
      global $phpgw_info, $sessionid, $login;
      /**************************************************************************\
      * Required classes                                                         *
      \**************************************************************************/
      $this->db           = new db;
      $this->db->Host     = $phpgw_info["server"]["db_host"];
      $this->db->Type     = $phpgw_info["server"]["db_type"];
      $this->db->Database = $phpgw_info["server"]["db_name"];
      $this->db->User     = $phpgw_info["server"]["db_user"];
      $this->db->Password = $phpgw_info["server"]["db_pass"];

      if ($this->debug) {
         $this->db->Debug = 1;
      }

      if ($phpgw_info["flags"]["currentapp"] == "login") {
         $this->db->query("select * from config",__LINE__,__FILE__);
         while($this->db->next_record()) {
           $phpgw_info["server"][$this->db->f("config_name")] = $this->db->f("config_value");
         }
      } else {
	 $config_var = array("encryptkey","auth_type","account_repository");
	 $c= "";
	 for ($i=0;$i<count($config_var);$i++) {
	   if($i) $c .= " OR ";
	   $c .= "config_name='".$config_var[$i]."'";
	 }
         $this->db->query("select * from config where $c",__LINE__,__FILE__);
         while($this->db->next_record()) {
           $phpgw_info["server"][$this->db->f("config_name")] = $this->db->f("config_value");
         }
      }

      /**************************************************************************\
      * Continue adding the classes                                              *
      \**************************************************************************/
      $this->common        = new common;
      $this->hooks         = new hooks;

      /* Load selected authentication class */
      if (empty($phpgw_info["server"]["auth_type"])){$phpgw_info["server"]["auth_type"] = "sql";}
      include($phpgw_info["server"]["api_inc"] . "/phpgw_auth_".$phpgw_info["server"]["auth_type"].".inc.php");

      $this->auth          = new auth;

      /* Load selected accounts class */
      if (empty($phpgw_info["server"]["account_repository"])){$phpgw_info["server"]["account_repository"] = $phpgw_info["server"]["auth_type"];}
      include($phpgw_info["server"]["api_inc"] . "/phpgw_accounts_".$phpgw_info["server"]["account_repository"].".inc.php");
      include($phpgw_info["server"]["api_inc"] . "/phpgw_accounts_shared.inc.php");

      $this->accounts      = new accounts;
      $this->preferences   = new preferences(0);

      $this->session       = new sessions;

      if ($phpgw_info["flags"]["currentapp"] == "login") {
	$log = explode("@",$login);
	$this->preferences   = new preferences($log[0]);
      }else{
        if (! $this->session->verify()) {
          $this->db->query("select config_value from config where config_name='webserver_url'",__LINE__,__FILE__);
          $this->db->next_record();
          Header("Location: " . $this->redirect($this->link($this->db->f("config_value")."/login.php","cd=10")));
          exit;
        }
        $this->preferences->preference = $phpgw_info["user"]["preferences"];
        $this->preferences->account_id = $phpgw_info["user"]["account_id"];

     }
      $this->translation   = new translation;
      $this->acl           = new acl;

      $sep = filesystem_separator();
      $template_root = $this->common->get_tpl_dir();

      if (is_dir($template_root)) {          
         $this->template = new Template($template_root);
      }
    } 
    /**************************************************************************\
    * Core functions                                                           *
    \**************************************************************************/

    /* A function to handle session support via url session id, or cookies */
    function link($url = "", $extravars = "")
    {
      global $phpgw, $phpgw_info, $usercookie, $kp3, $PHP_SELF;

      if (! $kp3)
         $kp3 = $phpgw_info["user"]["kp3"];

      if (! $url) {                         // PHP won't allow you to set a var to a var
         $url = $PHP_SELF;                  // or function for default values
      }

      if (isset($phpgw_info["server"]["usecookies"]) && $phpgw_info["server"]["usecookies"]) {
         if ($extravars) {
            $url .= "?$extravars";
         }
      } else {
        $url .= "?sessionid=" . $phpgw_info["user"]["sessionid"];
        $url .= "&kp3=" . $kp3;
        $url .= "&domain=" . $phpgw_info["user"]["domain"];
        // This doesn't belong in the API.  Its up to the app to pass this value. (jengo)
        if ($phpgw_info["flags"]["newsmode"]) {
          $url .= "&newsmode=on";
        }

        if ($extravars) {
          $url .= "&$extravars";
        }
      }

      // Note: The following code is slighty redundant, you should ALWAYS pass the full path (jengo)

      // next line adds index.php when one is assumed since
      // iis will not interpret urls like http://.../addressbook/?xyz=5
/*      $url = str_replace("/?", "/index.php?", $url);
      $html_check = strtolower(substr($url ,0,4));
      $slash_check = strtolower(substr($url ,0,1));
      if($url_check != "http") {
        if($slash_check != "/") {
          $url = $phpgw_info["server"]["hostname"].$phpgw_info["server"]["webserver_url"]."/".$url; 
        } else{
          $url = $phpgw_info["server"]["hostname"].$url; 
        } 
      } */
      return $url;
    }  

    function strip_html($s)
    {
       return htmlspecialchars(stripslashes($s));
    }

    function redirect($url = "")
    {
      // This function handles redirects under iis and apache
      // it assumes that $phpgw->link() has already been called

      global $HTTP_ENV_VARS;

      $iis = strpos($HTTP_ENV_VARS["SERVER_SOFTWARE"], "IIS", 0);
      
      if ( !$url ) {
        $url = $PHP_SELF;
      }
      if ( $iis ) {
        echo "\n<HTML>\n<HEAD>\n<TITLE>Redirecting to $url</TITLE>";
        echo "\n<META HTTP-EQUIV=REFRESH CONTENT=\"0; URL=$url\">";
        echo "\n</HEAD><BODY>";
        echo "<H3>Please continue to <a href=\"$url\">this page</a></H3>";
        echo "\n</BODY></HTML>";
        exit;
      } else {
        Header("Location: $url");
        print("\n\n");
        exit;
      }
    }

    function lang($key, $m1 = "", $m2 = "", $m3 = "", $m4 = "") 
    {
      global $phpgw;
      
      return $phpgw->translation->translate($key);
    }

    // Some people might prefear to use this one
    function _L($key, $m1 = "", $m2 = "", $m3 = "", $m4 = "") 
    {
      global $phpgw;
      
      return $phpgw->translation->translate($key);
    }

  }
  /**************************************************************************\
  * Our API class ends here                                                  *
  \**************************************************************************/
  /**************************************************************************\
  * Direct functions, which are not part of the API class                    *
  * for whatever reason.                                                     *
  \**************************************************************************/

  function lang($key, $m1="", $m2="", $m3="", $m4="", $m5="", $m6="", $m7="", $m8="", $m9="", $m10=""  ) 
  {
    global $phpgw;
# TODO: check if $m1 is of type array. If so, use it instead of $m2-$mN (Stephan)
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

  /**************************************************************************\
  * These lines load up the API, fill up the $phpgw_info array, etc          *
  \**************************************************************************/
  $phpgw = new phpgw;
  $phpgw->phpgw_();
  if ($phpgw_info["flags"]["currentapp"] != "login" && $phpgw_info["flags"]["currentapp"] != "logout") {
     if (! $phpgw->session->verify()) {
        Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/login.php", "cd=10"));
        exit;
     }
     load_optional();

     phpgw_fillarray();
     $phpgw->common->common_();

     if ($phpgw_info["flags"]["enable_utilities_class"]){
        $phpgw->utilities->utilities_();
     }

    if (!isset($phpgw_info["flags"]["nocommon_preferences"]) || !$phpgw_info["flags"]["nocommon_preferences"]) {
      if (!isset($phpgw_info["user"]["preferences"]["common"]["maxmatchs"]) || !$phpgw_info["user"]["preferences"]["common"]["maxmatchs"]) {
         $phpgw->preferences->change("common","maxmatchs",15);
         $preferences_update = True;
      }
      if (!isset($phpgw_info["user"]["preferences"]["common"]["theme"]) || !$phpgw_info["user"]["preferences"]["common"]["theme"]) {
         $phpgw->preferences->change("common","theme","default");
         $preferences_update = True;
      }
      if (!isset($phpgw_info["user"]["preferences"]["common"]["dateformat"]) || !$phpgw_info["user"]["preferences"]["common"]["dateformat"]) {
         $phpgw->preferences->change("common","dateformat","m/d/Y");
         $preferences_update = True;
      }
      if (!isset($phpgw_info["user"]["preferences"]["common"]["timeformat"]) || !$phpgw_info["user"]["preferences"]["common"]["timeformat"]) {
         $phpgw->preferences->change("common","timeformat",12);
         $preferences_update = True;
      }
      if (!isset($phpgw_info["user"]["preferences"]["common"]["lang"]) || !$phpgw_info["user"]["preferences"]["common"]["lang"]) {
         $phpgw->preferences->change("common","lang","en");
         $preferences_update = True;
      }
      if ($preferences_update) {
         $phpgw->preferences->commit(__LINE__,__FILE__);
      }
      unset($preferences_update);
    }

     /**************************************************************************\
     * These lines load up the themes                                           *
     \**************************************************************************/
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

     /**************************************************************************\
     * If they are using frames, we need to set some variables                  *
     \**************************************************************************/
     if (($phpgw_info["user"]["preferences"]["common"]["useframes"] && $phpgw_info["server"]["useframes"] == "allowed")
        || ($phpgw_info["server"]["useframes"] == "always")) {
        $phpgw_info["flags"]["navbar_target"] = "phpgw_body";
     }

     /**************************************************************************\
     * Verify that the users session is still active otherwise kick them out    *
     \**************************************************************************/
     if ($phpgw_info["flags"]["currentapp"] != "home" && $phpgw_info["flags"]["currentapp"] != "logout"
        && $phpgw_info["flags"]["currentapp"] != "preferences" && $phpgw_info["flags"]["currentapp"] != "about") {

        if (! $phpgw_info["user"]["apps"][$phpgw_info["flags"]["currentapp"]]) {
           $phpgw->common->phpgw_header();
           echo "<p><center><b>" . lang("Access not permitted") . "</b></center>";
           exit;
        }
     }

     /**************************************************************************\
     * Load the header unless the developer turns it off                        *
     \**************************************************************************/
     if (! $phpgw_info["flags"]["noheader"]) {
        $phpgw->common->phpgw_header();
     }

     /**************************************************************************\
     * Load the app include files if the exists                                 *
     \**************************************************************************/
     /* Then the include file */
     if (file_exists ($phpgw_info["server"]["app_inc"]."/functions.inc.php")){
        include($phpgw_info["server"]["app_inc"]."/functions.inc.php");
     }

     if (! $phpgw_info["flags"]["noheader"] && !$phpgw_info["flags"]["noappheader"] && file_exists ($phpgw_info["server"]["app_inc"]."/header.inc.php")) {
        include($phpgw_info["server"]["app_inc"]."/header.inc.php");
     }
  }
  error_reporting(7);
