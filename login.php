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
  include("./header.inc.php");

  $deny_login = False;

/*
  if ($code != 10 && $phpgw_info["server"]["usecookies"] == False) {
    Setcookie("sessionid");
    Setcookie("kp3");
    Setcookie("domain");
  }
*/

/* This is not working yet because I need to figure out a way to clear the $cd =1
  if(isset($PHP_AUTH_USER) && $cd == "1") { 
    Header("HTTP/1.0 401 Unauthorized"); 
    Header("WWW-Authenticate: Basic realm=\"phpGroupWare\""); 
    echo "You have to re-authentificate yourself \n"; 
    exit; 
  } 
*/

  $phpgw_info["server"]["template_dir"] = PHPGW_SERVER_ROOT."/phpgwapi/templates/default";
  $tmpl = CreateObject("phpgwapi.Template", $phpgw_info["server"]["template_dir"]);

  if (! $deny_login && ! $phpgw_info["server"]["show_domain_selectbox"]) {
     $tmpl->set_file(array("login_form"  => "login.tpl"));
  } else if ($phpgw_info["server"]["show_domain_selectbox"]) {
     $tmpl->set_file(array("login_form"  => "login_selectdomain.tpl"));  
  } else {
     $tmpl->set_file(array("login_form"  => "login_denylogin.tpl"));
  }

  // When I am updating my server, I don't want people logging in a messing 
  // things up.
  function deny_login()
  {
    global $tmpl;
    $tmpl->parse("loginout", "login_form");
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
  
  if ($phpgw_info["server"]["auth_type"] == "http" && isset($PHP_AUTH_USER)) {
    $submit = True;
    $login = $PHP_AUTH_USER;
    $passwd = $PHP_AUTH_PW;
  }

  if (isset($submit) && $submit) {
    if (getenv(REQUEST_METHOD) != "POST" && !isset($PHP_AUTH_USER)) {
       $phpgw->redirect($phpgw->link("","code=5"));
    }
    $sessionid = $phpgw->session->create($login,$passwd);
    if (!isset($sessionid) || !$sessionid) {
       $phpgw->redirect($phpgw_info["server"]["webserver_url"]."/login.php?cd=5");
    } else {
       if ($phpgw_forward) {
          while (list($name,$value) = each($HTTP_GET_VARS)) {
             if (ereg("phpgw_",$name)) {
                $extra_vars .= "&" . $name . "=" . urlencode($value);
             }
          }
       }
       $phpgw->redirect($phpgw->link($phpgw_info["server"]["webserver_url"] . "/index.php", "cd=yes$extra_vars"));
    }
  } else {
    // !!! DONT CHANGE THESE LINES !!!
    // If there is something wrong with this code TELL ME!
    // Commenting out the code will not fix it. (jengo)
    if (isset($last_loginid)) {
      $prefs = CreateObject("phpgwapi.preferences", $last_loginid);
      if ($prefs->account_id == ""){
        $phpgw_info["user"]["preferences"]["common"]["lang"] = "en";
      }else{
        $phpgw_info["user"]["preferences"] = $prefs->read_repository();
      }
      #print "LANG:".$phpgw_info["user"]["preferences"]["common"]["lang"]."<br>";
      $phpgw->translation->add_app("login");
      $phpgw->translation->add_app("loginscreen");
      if (lang("loginscreen_message") != "loginscreen_message*") {
         $tmpl->set_var("lang_message",stripslashes(lang("loginscreen_message")));
      }
    } else {
       // If the lastloginid cookies isn't set, we will default to english.
       // Change this if you need.
       $phpgw_info["user"]["preferences"]["common"]["lang"] = "en";
       $phpgw->translation->add_app("login");
       $phpgw->translation->add_app("loginscreen");
       if (lang("loginscreen_message") != "loginscreen_message*") {
          $tmpl->set_var("lang_message",stripslashes(lang("loginscreen_message")));
       }
    }
  }

  if(!isset($cd) || !$cd) $cd="";

  if ($phpgw_info["server"]["show_domain_selectbox"]) {
     reset($phpgw_domain);
     unset($domain_select);      // For security ... just in case
     while ($domain = each($phpgw_domain)) {
        $domain_select .= '<option value="' . $domain[0] . '"';
        if ($domain[0] == $last_domain) {
           $domain_select .= " selected";
        }
        $domain_select .= '>' . $domain[0] . '</option>';
     }
     $tmpl->set_var("select_domain",$domain_select);
  }

  while (list($name,$value) = each($HTTP_GET_VARS)) {
     if (ereg("phpgw_",$name)) {
        $extra_vars .= "&" . $name . "=" . urlencode($value);
     }
  }
  if ($extra_vars) {
     $extra_vars = "?" . substr($extra_vars,1,strlen($extra_vars));
  }

  $tmpl->set_var("login_url", $phpgw_info["server"]["webserver_url"] . "/login.php" . $extra_vars);
  $tmpl->set_var("website_title", $phpgw_info["server"]["site_title"]);
  $tmpl->set_var("cd",check_logoutcode($cd));
  $tmpl->set_var("cookie",show_cookie());
  $tmpl->set_var("lang_username","username");
  $tmpl->set_var("lang_phpgw_login","phpGroupWare login");
  $tmpl->set_var("version",$phpgw_info["server"]["versions"]["phpgwapi"]);
  $tmpl->set_var("lang_password","password");
  $tmpl->set_var("lang_login","login");
  $tmpl->set_var("template_set","default"); 
  $tmpl->parse("loginout", "login_form");
  $tmpl->p("loginout");
?>
