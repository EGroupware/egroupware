<?php
  /**************************************************************************\
  * phpGroupWare API - Core class and functions for phpGroupWare             *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * and Joseph Engo <jengo@phpgroupware.org>                                 *
  * This is the central class for the phpGroupWare API                       *
  * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
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
  
  /****************************************************************************\
  * Required classes                                                           *
  \****************************************************************************/
  /* Load selected database class */
  if (empty($phpgw_info["server"]["db_type"])){$phpgw_info["server"]["db_type"] = "mysql";}
  if (empty($phpgw_info["server"]["translation_system"])){$phpgw_info["server"]["translation_system"] = "sql";}

  /****************************************************************************\
  * Our API class starts here                                                  *
  \****************************************************************************/
  class phpgw
  {
    var $accounts;
    var $acl;
    var $auth;
    var $db;
    var $debug = 0;		// This will turn on debugging information.
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
      /************************************************************************\
      * Required classes                                                       *
      \************************************************************************/
      $this->db = CreateObject("phpgwapi.db");
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
           $phpgw_info["server"][$this->db->f("config_name")] = stripslashes($this->db->f("config_value"));
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
           $phpgw_info["server"][$this->db->f("config_name")] = stripslashes($this->db->f("config_value"));
         }
      }

      /************************************************************************\
      * Continue adding the classes                                            *
      \************************************************************************/
      $this->common = CreateObject("phpgwapi.common");
      $this->hooks = CreateObject("phpgwapi.hooks");

      /* Load selected authentication class */
      if (empty($phpgw_info["server"]["auth_type"])){$phpgw_info["server"]["auth_type"] = "sql";}
      $this->auth = CreateObject("phpgwapi.auth");

      /* Load selected accounts class */
      if (empty($phpgw_info["server"]["account_repository"])){$phpgw_info["server"]["account_repository"] = $phpgw_info["server"]["auth_type"];}
      $this->accounts = CreateObject("phpgwapi.accounts");
      $this->preferences = CreateObject("phpgwapi.preferences", 0);
      $this->session = CreateObject("phpgwapi.sessions");

      if ($phpgw_info["flags"]["currentapp"] == "login") {
        $log = explode("@",$login);
        $this->preferences = CreateObject("phpgwapi.preferences", $log[0]);
      }else{
        if (! $this->session->verify()) {
          $this->db->query("select config_value from config where config_name='webserver_url'",__LINE__,__FILE__);
          $this->db->next_record();
          Header("Location: " . $this->redirect($this->link($this->db->f("config_value")."/login.php","cd=10")));
          exit;
        }
        $this->preferences = CreateObject("phpgwapi.preferences", intval($phpgw_info["user"]["account_id"]));
     }

      $this->translation = CreateObject("phpgwapi.translation");
      $this->acl = CreateObject("phpgwapi.acl");

      $sep = filesystem_separator();
      $template_root = $this->common->get_tpl_dir();

      if (is_dir($template_root)) {
        $this->template = CreateObject("phpgwapi.Template", $template_root);
      }
    } 

    /**************************************************************************\
    * Core functions                                                           *
    \**************************************************************************/

    /* A function to handle session support via url session id, or cookies */
    function link($url = "", $extravars = "")
    {
      global $phpgw, $phpgw_info, $usercookie, $kp3, $PHP_SELF;
      if ($url == $PHP_SELF){ $url = ""; } //fix problems when PHP_SELF if used as the param
      if (! $kp3) { $kp3 = $phpgw_info["user"]["kp3"]; }
      if (! $url) {
        $url_root = split ("/", $phpgw_info["server"]["webserver_url"]);
        /* Some hosting providers have their paths screwy.
           If the value from $PHP_SELF is not what you expect, you can use this to patch it
           It will need to be adjusted to your specific problem tho.
        */
        //$patched_php_self = str_replace("/php4/php/phpgroupware", "/phpgroupware", $PHP_SELF);
        $patched_php_self = $PHP_SELF;
        $url = (strlen($url_root[0])? $url_root[0].'//':'') . $url_root[2] . $patched_php_self;
      }

      if (isset($phpgw_info["server"]["usecookies"]) &&
	      $phpgw_info["server"]["usecookies"]) {
        if ($extravars) { $url .= "?$extravars"; }
      } else {
        $url .= "?sessionid=" . $phpgw_info["user"]["sessionid"];
        $url .= "&kp3=" . $kp3;
        $url .= "&domain=" . $phpgw_info["user"]["domain"];
        // This doesn't belong in the API.
      	// Its up to the app to pass this value. (jengo)
        // Putting it into the app requires a massive number of updates in email app. 
        // Until that happens this needs to stay here (seek3r)
        if ($phpgw_info["flags"]["newsmode"]) { $url .= "&newsmode=on"; }
        if ($extravars) { $url .= "&$extravars"; }
      }

      $url = str_replace("/?", "/index.php?", $url);
      $webserver_url_count = strlen($phpgw_info["server"]["webserver_url"]);
      $slash_check = strtolower(substr($url ,0,1));
      if(substr($url ,0,$webserver_url_count) != $phpgw_info["server"]["webserver_url"]) {
        $app = $phpgw_info["flags"]["currentapp"];
        if($slash_check == "/") {
          $url = $phpgw_info["server"]["webserver_url"].$url; 
        } elseif ($app == "home" || $app == "logout" || $app == "login"){
          $url = $phpgw_info["server"]["webserver_url"]."/".$url; 
        }else{ 
          $url = $phpgw_info["server"]["webserver_url"]."/".$app."/".$url; 
        }
      } 
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

