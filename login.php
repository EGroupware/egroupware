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

  function show_cookie($code,$lastloginid,$new_loginid)
  {
    if ($code != 5)
    return $lastloginid;
  }

  function check_logoutcode($code)
  {
    if ($code == "1") {
      return lang_login("You have been successfully logged out");
    }
    else if ($code == "2") {
      return lang_login("Sorry, your login has expired");
    }
    else if ($code == "5") {
      return "<font color=FF0000>" . lang_login("Bad login or password") . "</font>";
    }
    else {
      return "&nbsp;";
    }
  }

  /* Program starts here */

  if ($deny_login) {
    deny_login();
  }

  if ($submit) {
    if (getenv(REQUEST_METHOD) != "POST") {
      Header("Location: " . $phpgw->link("", "cd=5"));
    }

    $phpgw->db->query("SELECT * FROM accounts WHERE loginid = '$login' AND "
                 . "passwd='" . md5($passwd) . "' AND status ='A'");

    $phpgw->db->next_record();

    if (! $phpgw->db->f("loginid")) {
      Header("Location: " . $phpgw_info["server"]["webserver_url"] . "/login.php?cd=5");
    } else {
      // Make sure the server allows us to use cookies
      if (! $phpgw_info["server"]["usecookies"]) {
        $usecookies = False;
      }
      $phpgw->session->create($phpgw->db->f("loginid"),$passwd, $usecookies);

      // Create the users private_dir if not exist
/*
        $sep = $phpgw->common->filesystem_sepeartor();
        $basedir = $phpgw_info["server"]["server_root"] . $sep . "filemanager" . $sep
                 . "users" . $sep;
        if(!is_dir($basedir . $phpgw->db->f("loginid")))
          mkdir($basedir . $phpgw->db->f("loginid"), 0707);
*/
//      Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"]
//                                                           . "/", $usecookies));
      Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"]
                                                           . "/", "cd=yes"));

      exit;
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
  $tmpl->set_var("cookie",show_cookie($cd,$last_loginid,$login));

  $tmpl->set_var("lang_username",lang_login("username"));
  $tmpl->set_var("lang_password",lang_login("password"));
  $tmpl->set_var("lang_login",lang_login("login"));

  if ($phpgw_info["server"]["usecookies"]) {
    $tmpl->set_var("use_cookies","<tr><td bgcolor=#FFFFFF height=\"25\" width=\"29%\" "
	    . "colspan=\"2\">" . lang_login("use cookies") . "<input type=\"checkbox\" "
	    . "name=\"usecookies\" value=\"True\"></td></tr>");
  }
  $tmpl->parse("login2out","login2");
  $tmpl->parse("loginout", "login");
  $tmpl->p("loginout");
?>
