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

  $phpgw_flags = array("disable_message_class"    => True, "disable_send_class"     => True,
			        "disable_nextmatchs_class" => True, "disable_template_class" => True,
			        "login"				=> True, "currentapp"		    => "login",
			        "noheader"				=> True
			       );

  include("header.inc.php");
  include($phpgw_info["server"]["include_root"] . "/lang/" . "en" . "_login.inc.php");
  include($phpgw_info["server"]["api_dir"] . "/phpgw_template.inc.php");

  $deny_login = False; 

  $tmpl = new Template($phpgw_info["server"]["template_dir"]);

  $tmpl->set_file(array("login"  => "login.tpl",
                        "login2" => "login2.tpl"));

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
        return lang_login("You have been successfully logged out");
        break;
      case "2":
        return lang_login("Sorry, your login has expired");
        break;
      case "5":
        return "<font color=FF0000>" . lang_login("Bad login or password") . "</font>";
        break;
      case "10":
        return "<font color=FF0000>" . lang_login("Your session could not be verified.") . "</font>";
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
    if (!$sessionid) {
      Header("Location: " . $phpgw_info["server"]["webserver_url"] . "/login.php?cd=5");
    } else {
      Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"]) . "/", "cd=yes");
    }

  } else {
    if ($last_loginid) {
      $phpgw->db->query("select value from preferences where owner='$last_loginid' "
                     . "and name='lang'");
      $phpgw->db->next_record();
      if (! $phpgw->db->f("value")) {
        $users_lang = "en";
//      } else {
//           $users_lang = $phpgw->db->f("value");
//           include($phpgw_info["server"]["include_root"] . "/lang/$users_lang/" . $users_lang
//	         . "_login.inc.php");
      }
    }
  }
  $tmpl->set_var("login_url", $phpgw_info["server"]["webserver_url"] . "/login.php");
  $tmpl->set_var("cd",check_logoutcode($cd));
  $tmpl->set_var("cookie",show_cookie());
  $tmpl->set_var("lang_username",lang_login("username"));
  $tmpl->set_var("lang_password",lang_login("password"));
  $tmpl->set_var("lang_login",lang_login("login"));

  $tmpl->parse("login2out","login2");
  $tmpl->parse("loginout", "login");
  $tmpl->p("loginout");
?>