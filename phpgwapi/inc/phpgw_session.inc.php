<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * and Dan Kuykendall <dan@kuykendall.org>                                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  class sessions
  {
    function getuser_ip()
    {
       global $REMOTE_ADDR, $HTTP_X_FORWARDED_FOR;
       
       if ($HTTP_X_FORWARDED_FOR) {
          return $HTTP_X_FORWARDED_FOR;
       } else {
          return $REMOTE_ADDR;
       }
    }

    function verify()
    {
       global $phpgw, $phpgw_info, $sessionid, $kp3;
       $db  = $phpgw->db;
       $db2 = $phpgw->db;

       $phpgw->common->key  = $kp3;
       $phpgw->common->iv   = $phpgw_info["server"]["mcrypt_iv"];
       $phpgw->crypto = new crypto($phpgw->common->key,$phpgw->common->iv);

       $db->query("select * from phpgw_sessions where session_id='$sessionid'",__LINE__,__FILE__);
       $db->next_record();
       
       if ($db->f("session_info") == "" || $db->f("session_info") == "NULL") {
          $phpgw_info["user"]["account_lid"] = $db->f("session_lid");
          $phpgw_info["user"]["sessionid"]   = $sessionid;
          $phpgw_info["user"]["session_ip"]  = $db->f("session_ip");
          
          $t = explode("@",$db->f("session_lid"));
          $phpgw_info["user"]["userid"]      = $t[0];
          
          $phpgw->accounts->sync(__LINE__,__FILE__);
          
          // Now we need to re-read eveything
          $db->query("select * from phpgw_sessions where session_id='$sessionid'",__LINE__,__FILE__);
          $db->next_record();    
       }

       $phpgw_info["user"]["kp3"] = $kp3;

       $phpgw_info_flags    = $phpgw_info["flags"];
       $phpgw_info          = $phpgw->crypto->decrypt($db->f("session_info"));

       $phpgw_info["flags"] = $phpgw_info_flags;

       $userid_array = explode("@",$db->f("session_lid"));
       $phpgw_info["user"]["userid"] = $userid_array[0];

       if ($userid_array[1] != $phpgw_info["user"]["domain"]) {
          return False;
       }

       if (PHP_OS != "Windows" && (! $phpgw_info["user"]["session_ip"] || $phpgw_info["user"]["session_ip"] != $this->getuser_ip())){
          return False;
       }

       $this->update_dla();

       if (! $phpgw_info["user"]["userid"] ) {
          return False;
       } else {
          $phpgw->preferences->preferences = $phpgw_info["user"]["preferences"];
          $phpgw->preferences->account_id = $phpgw_info["user"]["account_id"];
          return True;
       }
    }

    // This will remove stale sessions out of the database
    function clean_sessions()
    {
       global $phpgw_info, $phpgw;

       // Note: I need to add a db->lock() in here

       if (!isset($phpgw_info["server"]["cron_apps"]) || ! $phpgw_info["server"]["cron_apps"]) {
          $phpgw->db->query("delete from phpgw_sessions where session_dla <= '" . (time() -  7200)
                          . "'",__LINE__,__FILE__);
       }
    }

    function create($login,$passwd)
    {
       global $phpgw_info, $phpgw;
 
       $this->clean_sessions();
 
       $login_array = explode("@", $login);
       $phpgw_info["user"]["userid"] = $login_array[0];
 
       if ($phpgw_info["server"]["global_denied_users"][$phpgw_info["user"]["userid"]]) {
          return False;
       }
 
       if (!$phpgw->auth->authenticate($phpgw_info["user"]["userid"], $passwd)) {
          return False;
          exit;
       }
       
       $phpgw_info["user"]["sessionid"] = md5($phpgw->common->randomstring(10));
       $phpgw_info["user"]["kp3"]       = md5($phpgw->common->randomstring(15));

       $phpgw->common->key  = $phpgw_info["user"]["kp3"];
       $phpgw->common->iv   = $phpgw_info["server"]["mcrypt_iv"];
       $phpgw->crypto = new crypto($phpgw->common->key,$phpgw->common->iv);

       //$phpgw_info["user"]["passwd"]    = $phpgw->common->encrypt($passwd);
 
       if ($phpgw_info["server"]["usecookies"]) {
          Setcookie("sessionid",$phpgw_info["user"]["sessionid"]);
          Setcookie("kp3",$phpgw_info["user"]["kp3"]);
          Setcookie("domain",$phpgw_info["user"]["domain"]);
          Setcookie("last_domain",$phpgw_info["user"]["domain"],time()+1209600);
          if ($phpgw_info["user"]["domain"] ==$phpgw_info["server"]["default_domain"]) {
             Setcookie("last_loginid",$phpgw_info["user"]["userid"],time()+1209600);  // For 2 weeks
          } else {
             Setcookie("last_loginid",$loginid,time()+1209600);  // For 2 weeks
          }
          unset ($phpgw_info["server"]["default_domain"]); // we kill this for security reasons
       }

       //$phpgw->accounts->accounts_const();

       $phpgw_info["user"]["session_ip"] = $this->getuser_ip();

       $phpgw->db->query("insert into phpgw_sessions values ('" . $phpgw_info["user"]["sessionid"]
                       . "','".$login."','" . $this->getuser_ip() . "','"
                       . time() . "','" . time() . "','')",__LINE__,__FILE__);
       $phpgw->accounts->sync(__LINE__,__FILE__);
 
       $phpgw->db->query("insert into phpgw_access_log values ('" . $phpgw_info["user"]["sessionid"] . "','"
                       . "$login','" . $this->getuser_ip() . "','" . time()
                       . "','') ",__LINE__,__FILE__);
 
       $phpgw->db->query("update accounts set account_lastloginfrom='"
    	                . $this->getuser_ip() . "', account_lastlogin='" . time()
                       . "' where account_lid='".$login."'",__LINE__,__FILE__);
 
       return $phpgw_info["user"]["sessionid"];
    }

    // This will update the DateLastActive column, so the login does not expire
    function update_dla()
    {
       global $phpgw_info, $phpgw;
 
       $phpgw->db->query("update phpgw_sessions set session_dla='" . time() . "' where session_id='"
                       . $phpgw_info["user"]["sessionid"]."'",__LINE__,__FILE__);
    }
    
    function destroy()
    {
       global $phpgw, $phpgw_info, $sessionid, $kp3;
       $phpgw_info["user"]["sessionid"] = $sessionid;
       $phpgw_info["user"]["kp3"] = $kp3;
 
       $phpgw->db->query("delete from phpgw_sessions where session_id='"
                       . $phpgw_info["user"]["sessionid"] . "'",__LINE__,__FILE__);
       $phpgw->db->query("delete from phpgw_app_sessions where sessionid='"
                       . $phpgw_info["user"]["sessionid"] . "'",__LINE__,__FILE__);
       $phpgw->db->query("update phpgw_access_log set lo='" . time() . "' where sessionid='"
                       . $phpgw_info["user"]["sessionid"] . "'",__LINE__,__FILE__);
       if ($phpgw_info["server"]["usecookies"]) {
          Setcookie("sessionid");
          Setcookie("kp3");
          if ($phpgw_info["multiable_domains"]) {
             Setcookie("domain");
          }
       }
       $this->clean_sessions();
       return True;
    }

  }
?>
