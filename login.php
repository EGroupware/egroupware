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

  $phpgw_info["flags"] = array("disable_message_class"    => True, "disable_send_class"     => True,
			        "disable_nextmatchs_class" => True, "disable_template_class" => True,
			        "login"				=> True, "currentapp"		    => "login",
			        "noheader"				=> True
			       );

  include("header.inc.php");
//  include($phpgw_info["server"]["include_root"] . "/lang/" . "en" . "_login.inc.php");
  include($phpgw_info["server"]["api_dir"] . "/phpgw_template.inc.php");

/*
  if ($code != 10 && $phpgw_info["server"]["usecookies"] == False) {
    Setcookie("sessionid");
    Setcookie("kp3");
  }
*/
  $deny_login = False;

  $tmpl = new Template($phpgw_info["server"]["template_dir"]);
  $tmpl->set_file(array("login"  => "login.tpl"));

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
    global $phpgw_info, $code, $lastloginid, $login;
    /* This needs to be this way, because if someone doesnt want to use cookies, we shouldnt sneak one in */
    if ($code != 5 && $phpgw_info["server"]["usecookies"] == True){
      if (!empty($login)) {
        SetCookie("lastloginid",$login, time() + (24 * 3600 * 14),"/");
      }
      return $lastloginid;
    }
  }

  function check_logoutcode($code)
  {
    global $phpgw_info;
    switch($code){
      case "1":
        return lang("You have been successfully logged out");
        break;
      case "2":
        return lang("Sorry, your login has expired");
        break;
      case "5":
        return "<font color=FF0000>" . lang("Bad login or password") . "</font>";
        break;
      case "10":
        Setcookie("sessionid");
        Setcookie("kp3");
        return "<font color=FF0000>" . lang("Your session could not be verified.") . "</font>";
        break;
      default:
      return "&nbsp;";
    }
  }

  /* Program starts here */

  if ($deny_login) {
     deny_login();
  }

  if ($submit) {
    if (getenv(REQUEST_METHOD) != "POST") {
       Header("Location: " . $phpgw->link("", "code=5"));
    }

    $sessionid = $phpgw->session->create($login,$passwd);
    if (! $sessionid) {
       Header("Location: " . $phpgw_info["server"]["webserver_url"] . "/login.php?cd=5");
    } else {
       Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/index.php", "cd=yes"));
    }

  } else {
    if ($last_loginid) {
       $phpgw->db->query("select preference_value from preferences where preference_owner='$last_loginid' "
                       . "and preference_name='lang'");
       $phpgw->db->next_record();
       if (! $phpgw->db->f("preference_value")) {
          $users_lang = "en";
//      } else {
//           $users_lang = $phpgw->db->f("preference_value");
//           include($phpgw_info["server"]["include_root"] . "/lang/$users_lang/" . $users_lang
//	         . "_login.inc.php");
      }
    }
  }
  $tmpl->set_var("login_url", $phpgw_info["server"]["webserver_url"] . "/login.php");
  $tmpl->set_var("website_title", $phpgw_info["server"]["site_title"]);
  $tmpl->set_var("cd",check_logoutcode($cd));
  $tmpl->set_var("cookie",show_cookie());
  $tmpl->set_var("lang_username",lang("username"));
  $tmpl->set_var("lang_password",lang("password"));
  $tmpl->set_var("lang_login",lang("login"));

  $tmpl->parse("loginout", "login");
  $tmpl->p("loginout");
?>
