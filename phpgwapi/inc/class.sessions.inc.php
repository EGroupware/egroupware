<?php
  /**************************************************************************\
  * phpGroupWare API - Session management                                    *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * and Joseph Engo <jengo@phpgroupware.org>                                 *
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

       // PHP 3 complains that these are not defined when the already are defined.
       $phpgw->common->key  = $phpgw_info["server"]["encryptkey"];
       $phpgw->common->key .= $sessionid;
       $phpgw->common->key .= $kp3;
       $phpgw->common->iv   = $phpgw_info["server"]["mcrypt_iv"];

       $cryptovars[0] = $phpgw->common->key;      
       $cryptovars[1] = $phpgw->common->iv;      
       $phpgw->crypto = CreateObject("phpgwapi.crypto", $cryptovars);

       $db->query("select * from phpgw_sessions where session_id='$sessionid'",__LINE__,__FILE__);
       $db->next_record();
       
       if ($db->f("session_info") == "" || $db->f("session_info") == "NULL") {
          $phpgw_info["user"]["account_lid"] = $db->f("session_lid");
          $phpgw_info["user"]["sessionid"]   = $sessionid;
          $phpgw_info["user"]["session_ip"]  = $db->f("session_ip");

          $t = explode("@",$db->f("session_lid"));
          $phpgw_info["user"]["userid"]      = $t[0];

//          $phpgw->accounts->account_id = $phpgw->accounts->name2id($phpgw_info["user"]["account_lid"]);
//          $phpgw_info["user"] = $phpgw->accounts->read_repository();

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
//          return False;
       }
       if (PHP_OS != "Windows" && (! $phpgw_info["user"]["session_ip"] || $phpgw_info["user"]["session_ip"] != $this->getuser_ip())){
          return False;
       }

       $this->update_dla();

       if (! $phpgw_info["user"]["userid"] ) {
          return False;
       } else {
          // PHP 3 complains that these are not defined when the already are defined.
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
 
       if (! $phpgw->auth->authenticate($phpgw_info["user"]["userid"], $passwd)) {
          return False;
          exit;
       }
       //$accts = CreateObject("phpgwapi.accounts");
       
       //if (!$accts->exists($phpgw_info["user"]["userid"])) {
       //   $accts->auto_generate($phpgw_info["user"]["userid"], $passwd);
       //}

       $phpgw->accounts->account_id = $phpgw->accounts->name2id($phpgw_info["user"]["userid"]);

       $t_domain = $phpgw_info["user"]["domain"];        // We loose this info on the next line
       $phpgw_info["user"] = $phpgw->accounts->read_repository();
       $phpgw_info["user"]["domain"] = $t_domain;
       $phpgw_info["user"]["sessionid"] = md5($phpgw->common->randomstring(10));
       $phpgw_info["user"]["kp3"]       = md5($phpgw->common->randomstring(15));

       $phpgw->common->key  = $phpgw_info["server"]["encryptkey"];
       $phpgw->common->key .= $phpgw_info["user"]["sessionid"];
       $phpgw->common->key .= $phpgw_info["user"]["kp3"];
       $phpgw->common->iv   = $phpgw_info["server"]["mcrypt_iv"];
       $cryptovars[0] = $phpgw->common->key;      
       $cryptovars[1] = $phpgw->common->iv;      
       $phpgw->crypto = CreateObject("phpgwapi.crypto", $cryptovars);

       $phpgw_info["user"]["passwd"]    = $phpgw->common->encrypt($passwd);
 
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

       $phpgw_info["user"]["session_ip"] = $this->getuser_ip();
       $phpgw_info["user"]["session_lid"] = $phpgw_info["user"]["account_lid"]."@".$phpgw_info["user"]["domain"];
       $phpgw_info_temp["user"]        = $phpgw_info["user"];
       $phpgw_info_temp["apps"]        = $phpgw_info["apps"];
       $phpgw_info_temp["server"]      = $phpgw_info["server"];
       $phpgw_info_temp["hooks"]       = $phpgw->hooks->read();
       $phpgw_info_temp["user"]["preferences"] = $phpgw_info["user"]["preferences"];
       $phpgw_info_temp["user"]["kp3"] = "";
       if ($PHP_VERSION < "4.0.0") {
          $info_string = addslashes($phpgw->crypto->encrypt($phpgw_info_temp));
       } else {
          $info_string = $phpgw->crypto->encrypt($phpgw_info_temp);       
       }
       $phpgw->db->query("insert into phpgw_sessions values ('" . $phpgw_info["user"]["sessionid"]
                       . "','".$login."','" . $this->getuser_ip() . "','"
                       . time() . "','" . time() . "','".$info_string."')",__LINE__,__FILE__);

       //$phpgw->accounts->save_repository();

       $phpgw->db->query("insert into phpgw_access_log values ('" . $phpgw_info["user"]["sessionid"] . "','"
                       . "$login','" . $this->getuser_ip() . "','" . time()
                       . "','') ",__LINE__,__FILE__);

       $phpgw->auth->update_lastlogin($login,$this->getuser_ip());

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