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
			                   "login"                    => True, "currentapp"             => "login",
			                   "noheader"                 => True
			                  );

  include("./header.inc.php");
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
    global $phpgw_info, $code, $lastloginid, $login;
    /* This needs to be this way, because if someone doesnt want to use cookies, we shouldnt sneak one in */
    if ($code != 5 && (isset($phpgw_info["server"]["usecookies"]) && $phpgw_info["server"]["usecookies"])){
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
    if (isset($last_loginid) && $last_loginid) {
       $phpgw->db->query("select account_id from accounts where account_lid='$last_loginid'");
       $phpgw->db->next_record();
    
       $phpgw->db->query("select preference_value from preferences where preference_owner='" . $phpgw->db->f("account_id") . "' "
                       . "and preference_name='lang'");
       $phpgw->db->next_record();
       $phpgw_info["user"]["preferences"]["common"]["lang"] = $phpgw->db->f("preference_value");
       $phpgw->translation->add_app("login");
    }
  }
/*  This has been put on hold until 0.9.4pre1, we have a different method of doing it (jengo)
  if ($phpgw_info["server"]["multiable_domains"]) {
     $tmpl->set_var("lang_domain",lang("Domain"));
     if ($phpgw_info["server"]["multiable_domains_use_select_box"]) {
        $domains_select = '<select name="domain">';
     
        $phpgw->db->query("select domain_id,domain_name from domains where domain_status='Active' "
        				. "order by domain_name");
        while ($phpgw->db->next_record()) {
          $domains_select .= '<option value="' . $phpgw->db->f("domain_id") . '">'
          				 . $phpgw->db->f("domain_name") . '</option>';
        }
        $domains_select .= "</select>";
        $tmpl->set_var("domain_input",$domains_select);
        $tmpl->parse("domain_row_out","domain_row");
     } else {
        $tmpl->set_var("domain_input",'<input name="domain">');
        $tmpl->parse("domain_row_out","domain_row");     
     }
  } else {
     $tmpl->set_var("domain_row","");
     $tmpl->parse("null","domain_row");
  }
*/

  if(!isset($cd) || !$cd) $cd="";
  
  $tmpl->set_var("login_url", $phpgw_info["server"]["webserver_url"] . "/login.php");
  $tmpl->set_var("website_title", $phpgw_info["server"]["site_title"]);
  $tmpl->set_var("cd",check_logoutcode($cd));
  $tmpl->set_var("cookie",show_cookie());
  $tmpl->set_var("lang_username",lang("username"));
  $tmpl->set_var("lang_phpgw_login",lang("phpGroupWare login"));
  $tmpl->set_var("version",$phpgw_info["server"]["version"]);
  $tmpl->set_var("lang_password",lang("password"));
  $tmpl->set_var("lang_login",lang("login"));

  $tmpl->parse("loginout", "login_form");
  $tmpl->p("loginout");
?>
