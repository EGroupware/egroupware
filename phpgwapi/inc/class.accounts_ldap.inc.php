<?php
  /**************************************************************************\
  * phpGroupWare API - Accounts manager for LDAP                             *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * and Lars Kneschke <kneschke@phpgroupware.org>                            *
  * View and manipulate account records using LDAP                           *
  * Copyright (C) 2000, 2001 Joseph Engo                                     *
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
  
  class accounts_
  {
    var $db;
    var $account_id;
    var $data;
    var $memberships = Array();
    var $members;

    function accounts_()
    {
       global $phpgw;

       $this->db = $phpgw->db;
    }
   
    function read_repository()
    {
	global $phpgw, $phpgw_info;
	
	// get a ldap connection handle
	$ds = $phpgw->common->ldapConnect();
	
	// search the dn for the given uid
	$sri = ldap_search($ds, $phpgw_info["server"]["ldap_context"], "uidnumber=".$this->account_id);
	$allValues = ldap_get_entries($ds, $sri);
	
	/* Now dump it into the array; take first entry found */
	$this->data["account_id"]	= $allValues[0]["uidnumber"][0];
	$this->data["account_lid"] 	= $allValues[0]["uid"][0];
	$this->data["account_dn"]  	= $allValues[0]["dn"];
	$this->data["firstname"]   	= $allValues[0]["givenname"][0];
	$this->data["lastname"]    	= $allValues[0]["sn"][0];
	$this->data["fullname"]    	= $allValues[0]["cn"][0];
	
	$this->db->query("select * from phpgw_accounts where account_id='" . $this->data["account_id"] . "'",__LINE__,__FILE__);
	$this->db->next_record();
	
	$this->data["lastlogin"]         = $this->db->f("account_lastlogin");
	$this->data["lastloginfrom"]     = $this->db->f("account_lastloginfrom");
	$this->data["lastpasswd_change"] = $this->db->f("account_lastpwd_change");
	$this->data["status"]            = $this->db->f("account_status");
	
	return $this->data;
    }

    function save_repository()
    {
       global $phpgw_info, $phpgw;

	$ds = $phpgw->common->ldapConnect();

	// search the dn for the given uid
	$sri = ldap_search($ds, $phpgw_info["server"]["ldap_context"], "uidnumber=".$this->account_id);
	$allValues = ldap_get_entries($ds, $sri);
	
	$entry["cn"] 		= sprintf("%s %s", $this->data["firstname"], $this->data["lastname"]);
	$entry["sn"]		= $this->data["lastname"];
	$entry["givenname"]	= $this->data["firstname"];
	
	ldap_modify($ds, $allValues[0]["dn"], $entry);
	#print ldap_error($ds);
    }
    
    function add($account_name, $account_type, $first_name, $last_name, $passwd = False) 
    {
    }
    
    function delete($account_id) 
    {
    }
    
	function get_list($_type='both')
	{
		global $phpgw;

		$ds = $phpgw->common->ldapConnect();

		switch($_type)
		{
			case 'accounts':
				$whereclause = "where account_type = 'u'";
				break;
			case 'groups':
				$whereclause = "where account_type = 'g'";
				break;
			default:
				$whereclause = "";
		}

		$sql = "select * from phpgw_accounts $whereclause";
		$this->db->query($sql,__LINE__,__FILE__);
		while ($this->db->next_record()) {
			// get user information from ldap only, if it's a user, not a group
			if ($this->db->f("account_type") == 'u')
			{
				$sri = ldap_search($ds, $phpgw_info["server"]["ldap_context"], "uidnumber=".$this->db->f("account_id"));
				$allValues = ldap_get_entries($ds, $sri);
				$accounts[] = Array(
					"account_id" => $allValues[$i]["uidnumber"][0],
					"account_lid" => $allValues[$i]["uid"][0],
					"account_type" => $this->db->f("account_type"),
					"account_firstname" => $allValues[$i]["givenname"][0],
					"account_lastname" => $allValues[$i]["sn"][0],
					"account_status" => $this->db->f("account_status")
				);
			}
			else
			{
				$accounts[] = Array(
					"account_id" => $this->db->f("account_id"),
					"account_lid" => $this->db->f("account_lid"),
					"account_type" => $this->db->f("account_type"),
					"account_firstname" => $this->db->f("account_firstname"),
					"account_lastname" => $this->db->f("account_lastname"),
					"account_status" => $this->db->f("account_status")
				);
			}
		}
		
		
		return $accounts;
	}
    
    function name2id($account_name)
    {
      global $phpgw, $phpgw_info;

      $this->db->query("SELECT account_id FROM phpgw_accounts WHERE account_lid='".$account_name."'",__LINE__,__FILE__);
      if($this->db->num_rows()) {
        $this->db->next_record();

        return $this->db->f("account_id");
      }else{
        return False;
      }
    }

    function id2name($account_id)
    {
      global $phpgw, $phpgw_info;

      $this->db->query("SELECT account_lid FROM phpgw_accounts WHERE account_id='".$account_id."'",__LINE__,__FILE__);
      if($this->db->num_rows()) {
        $this->db->next_record();
        return $this->db->f("account_lid");
      }else{
        return False;
      }
    }

    function get_type($account_id)
    {
    	global $phpgw, $phpgw_info;
    	
    	$this->db->query("SELECT account_type FROM phpgw_accounts WHERE account_id='".$account_id."'",__LINE__,__FILE__);
    	if ($this->db->num_rows()) 
    	{
    		$this->db->next_record();
    		return $this->db->f("account_type");
    	} 
    	else 
    	{
    		return False;
    	}
    }

    function exists($account_id)
    {
      global $phpgw, $phpgw_info;
      if (gettype($account_id) == "string") {
        $account_id = $this->name2id($account_id);
      }
      $sql = "SELECT account_id FROM phpgw_accounts WHERE account_id='".$account_id."'";
      $this->db->query($sql,__LINE__,__FILE__);
      if ($this->db->num_rows()) {
         return True;
      } else {
         return False;
      }
    }                                                    
                                                                                     
    function auto_add($account_name, $passwd, $default_prefs=False, $default_acls= False)
    {
       print "not done until now auto_generate class.accounts_ldap.inc.php<br>";
       exit();
       global $phpgw, $phpgw_info;
       $accountid = mt_rand (100, 600000);
       if ($defaultprefs =="") {
          $defaultprefs = 'a:5:{s:6:"common";a:1:{s:0:"";s:2:"en";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}s:8:"calendar";a:1:{s:0:"";s:13:"workdaystarts";}i:15;a:1:{s:0:"";s:11:"workdayends";}s:6:"Monday";a:1:{s:0:"";s:13:"weekdaystarts";}}';
       }
       $sql = "insert into phpgw_accounts";
       $sql .= "(account_id, account_lid, account_pwd, account_firstname, account_lastname, account_lastpwd_change, account_status, account_type)";
       $sql .= "values (".$accountid.", '".$accountname."', '".md5($passwd)."', '".$accountname."', 'AutoCreated', ".time().", 'A','u')";
       $this->db->query($sql);
       $this->db->query("insert into preferences (preference_owner, preference_value) values ('".$accountid."', '$defaultprefs')");
       $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights)values('preferences', 'changepassword', ".$accountid.", 'u', 0)",__LINE__,__FILE__);
       $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('phpgw_group', '1', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
       $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('addressbook', 'run', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
       $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('filemanager', 'run', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
       $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('calendar', 'run', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
       $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('email', 'run', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
       $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('notes', 'run', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
       $this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('todo', 'run', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
       return $accountid;
    }
	
	function getDNforID($_account_id)
	{
		global $phpgw;
		
		$ds = $phpgw->common->ldapConnect();
		
		$sri = ldap_search($ds, $phpgw_info["server"]["ldap_context"], "uidnumber=$_account_id");
		$allValues = ldap_get_entries($ds, $sri);
		
		return $allValues[0]["dn"];
	}
  }
