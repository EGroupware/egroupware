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

  $phpgw_info["flags"] = array("disable_template_class" => True, "login" => True, "currentapp" => "login", "noheader"  => True);
  include("header.inc.php");
//  include($phpgw_info["server"]["include_root"] . "/lang/" . "en" . "_login.inc.php");
  include($phpgw_info["server"]["api_dir"] . "/phpgw_template.inc.php");
/*
  if ($code != 10 && $phpgw_info["server"]["usecookies"] == False) {
    Setcookie("sessionid");
    Setcookie("kp3");
    Setcookie("domain");
  }
*/
  $deny_login = False;  

  $tmpl = new Template($phpgw_info["server"]["template_dir"]);
  $tmpl->set_file(array("login_form"  => "login.tpl",
                        "domain_row"  => "login_domain_row.tpl"));
  $tmpl->set_block("login_form","domain_row");

  // When I am updating my server, I don't want people logging in a messing 
  // things up.
  function deny_login()
  {
    global $tmpl;
    $tmpl->set_var("updating","<center>Opps! You caught us in the middle of a system"
                 . " upgrade.<br>Please, check back with us shortly.</center>");
    $tmpl->parse("loginout", "login");
    $tmpl->p("loginout");
    exit;
  }

  function show_cookie()
  {
    global $phpgw_info, $code, $last_loginid, $login;
    /* This needs to be this way, because if someone doesnt want to use cookies, we shouldnt sneak one in */
    if ($code != 5 && (isset($phpgw_info["server"]["usecookies"]) && $phpgw_info["server"]["usecookies"])){
       return $last_loginid;
    }
  }

  function check_logoutcode($code)
  {
    global $phpgw_info;
    switch($code){
      case "1":
        return "You have been successfully logged out";
        break;
      case "2":
        return "Sorry, your login has expired";
        break;
      case "5":
        return "<font color=FF0000>" . "Bad login or password" . "</font>";
        break;
      case "10":
        Setcookie("sessionid");
        Setcookie("kp3");
        Setcookie("domain");
        return "<font color=FF0000>" . "Your session could not be verified." . "</font>";
        break;
      default:
      return "&nbsp;";
    }
  }

  /* Program starts here */

  if ($deny_login) {
     deny_login();
  }
  
  if (isset($submit) && $submit) {
    if (getenv(REQUEST_METHOD) != "POST") {
       Header("Location: ".$phpgw->link("","code=5"));
    }

    $sessionid = $phpgw->session->create($login,$passwd);
    if (!isset($sessionid) || !$sessionid) {
       Header("Location: ".$phpgw_info["server"]["webserver_url"]."/login.php?cd=5");
    } else {
       Header("Location: ".$phpgw->link($phpgw_info["server"]["webserver_url"] . "/index.php", "cd=yes"));
    }

  } else {
    // !!! DONT CHANGE THESE LINES !!!
    // If there is something wrong with this code TELL ME!
    // Commenting out the code will not fix it. (jengo)
    if (isset($last_loginid)) {
       $phpgw->preferences->read_preferences("common",$last_loginid); 
       #print "LANG:".$phpgw_info["user"]["preferences"]["common"]["lang"]."<br>";
       $phpgw->translation->add_app("login");
       $phpgw->translation->add_app("loginscreen");
       if (lang("loginscreen_message") != "loginscreen_message*") {
          $tmpl->set_var("lang_message",lang("loginscreen_message"));
       }
    } else {
       $tmpl->set_var("lang_message","");
    }
  }

  if(!isset($cd) || !$cd) $cd="";
  
  $tmpl->set_var("login_url", $phpgw_info["server"]["webserver_url"] . "/login.php");
  $tmpl->set_var("website_title", $phpgw_info["server"]["site_title"]);
  $tmpl->set_var("cd",check_logoutcode($cd));
  $tmpl->set_var("cookie",show_cookie());
  $tmpl->set_var("lang_username","username");
  $tmpl->set_var("lang_phpgw_login","phpGroupWare login");
  $tmpl->set_var("version",$phpgw_info["server"]["version"]);
  $tmpl->set_var("lang_password","password");
  $tmpl->set_var("lang_login","login");

  $tmpl->parse("loginout", "login_form");
  $tmpl->p("loginout");
?>
