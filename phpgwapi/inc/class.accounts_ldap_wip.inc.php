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

  	// Dont know where to put this (seek3r)
	// This is where it belongs (jengo)
	// This is where it ended up (milosch)
	/* Since LDAP will return system accounts, there are a few we don't want to login. */
	$phpgw_info["server"]["global_denied_users"] = array(
		'root'     => True,
		'bin'      => True,
		'daemon'   => True,
		'adm'      => True,
		'lp'       => True,
		'sync'     => True,
		'shutdown' => True,
		'halt'     => True,
		'ldap'     => True,
		'mail'     => True,
		'news'     => True,
		'uucp'     => True,
		'operator' => True,
		'games'    => True,
		'gopher'   => True,
		'nobody'   => True,
		'xfs'      => True,
		'pgsql'    => True,
		'mysql'    => True,
		'postgres' => True,
		'oracle'   => True,
		'ftp'      => True,
		'gdm'      => True,
		'named'    => True,
		'alias'    => True,
		'web'      => True,
		'sweep'    => True,
		'cvs'      => True,
		'qmaild'   => True,
		'qmaill'   => True,
		'qmaillog' => True,
		'qmailp'   => True,
		'qmailq'   => True,
		'qmailr'   => True,
		'qmails'   => True
	);

	$phpgw_info["server"]["global_excluded_groups"] = array(
		'root'      => True,
		'bin'       => True,
		'daemon'    => True,
		'sys'       => True,
		'adm'       => True,
		'tty'       => True,
		'disk'      => True,
		'lp'        => True,
		'mem'       => True,
		'kmem'      => True,
		'wheel'     => True,
		'mail'      => True,
		'uucp'      => True,
		'man'       => True,
		'games'     => True,
		'dip'       => True,
		'ftp'       => True,
		'nobody'    => True,
		'floppy'    => True,
		'xfs'       => True,
		'console'   => True,
		'utmp'      => True,
		'pppusers'  => True,
		'popusers'  => True,
		'slipusers' => True,
		'slocate'   => True,
		'mysql'     => True,
		'dnstools'  => True,
		'web'       => True,
		'named'     => True,
		'dba'       => True,
		'oinstall'  => True,
		'oracle'    => True,
		'gdm'       => True,
		'sweep'     => True,
		'cvs'       => True,
		'postgres'  => True,
		'qmail'     => True,
		'nofiles'   => True,
		'ldap'      => True
	);

	class accounts_
	{
		var $db;
		var $account_id;
		var $data;

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
			$acct_type = $this->get_type($this->account_id);

			// search the dn for the given uid
			if ( ($acct_type == "g") && $phpgw_info["server"]["ldap_group_context"] )
			{
				$sri = ldap_search($ds, $phpgw_info["server"]["ldap_group_context"], "gidnumber=".$this->account_id);
			}
			else
			{
				$sri = ldap_search($ds, $phpgw_info["server"]["ldap_context"], "uidnumber=".$this->account_id);
			}
			$allValues = ldap_get_entries($ds, $sri);

			/* Now dump it into the array; take first entry found */
			if($acct_type =='g')
			{
				$this->data["account_id"]   = $allValues[0]["gidnumber"][0];
			}
			else
			{
				$this->data["account_id"]	= $allValues[0]["uidnumber"][0];
			}
			$this->data["account_lid"] 	= $allValues[0]["uid"][0];
			$this->data["account_dn"]  	= $allValues[0]["dn"];
			$this->data["firstname"]   	= $allValues[0]["givenname"][0];
			$this->data["lastname"]    	= $allValues[0]["sn"][0];
			$this->data["fullname"]    	= $allValues[0]["cn"][0];
			if ($phpgw_info["server"]["ldap_extra_attributes"]) {
				$this->data["homedirectory"]  = $allValues[0]["homedirectory"][0];
				$this->data["loginshell"] = $allValues[0]["loginshell"][0];
			}
			$this->db->query("select * from phpgw_accounts where account_id='" . $this->data["account_id"] . "'",__LINE__,__FILE__);
			$this->db->next_record();

			$this->data["lastlogin"]         = $this->db->f("account_lastlogin");
			$this->data["lastloginfrom"]     = $this->db->f("account_lastloginfrom");
			$this->data["lastpasswd_change"] = $this->db->f("account_lastpwd_change");
			$this->data["status"]            = $this->db->f("account_status");
			$this->data["account_type"]      = $this->db->f("account_type");

			return $this->data;
		}

		function save_repository()
		{
			global $phpgw_info, $phpgw;

			$ds = $phpgw->common->ldapConnect();
			$acct_type = $this->get_type($this->account_id);

			// search the dn for the given u/gidnumber
			if ( ($acct_type == "g") && $phpgw_info["server"]["ldap_group_context"] )
			{
				$sri = ldap_search($ds, $phpgw_info["server"]["ldap_group_context"], "gidnumber=".$this->account_id);
			}
			else
			{
				$sri = ldap_search($ds, $phpgw_info["server"]["ldap_context"], "uidnumber=".$this->account_id);
			}
			$allValues = ldap_get_entries($ds, $sri);

			$entry["cn"]        = sprintf("%s %s", $this->data["firstname"], $this->data["lastname"]);
			$entry["sn"]        = $this->data["lastname"];
			$entry["givenname"] = $this->data["firstname"];

			if ($phpgw_info["server"]["ldap_extra_attributes"]) {
				$entry["homedirectory"] = $this->data["homedirectory"];
				$entry["loginshell"]    = $this->data["loginshell"];
			}

			// Changing the uid:  Need to delete and add new, since
			// PHP cannot change the dn for the entry.
			if ($acct_type == 'g') { $test = $allValues[0]["cn"][0];  }
			else                   { $test = $allValues[0]["uid"][0]; }
			if ($test != $this->data["account_lid"])
			{
				echo $allValues[0]["cn"][0]; exit;
				ldap_delete($ds,$allValues[0]["dn"]);
				unset($allValues[0]["dn"]);
				while (list($key,$val) = each($allValues[0]))
				{
					// Don't include row count and headers
					if (gettype($key) == "string" && $key != "count")
					{
						if (is_array($val))
						{
							if (count($val) == 1)
							{
								if($val[0]) { $entry[$key] = $val[0]; }
							}
							else
							{
								for ($i=0;$i<count($val);$i++)
								{
									if($val[$i]) { $entry[$key][$i] = $val[$i]; }
								}
							}
						}
						else
						{
							$entry[$key] = $val;
						}
					}
				}

				// Groups
				if ($this->data["account_type"] == "g" && $phpgw_info["server"]["ldap_group_context"] )
				{
					$dn = 'uid='.$this->data["account_lid"].','.$phpgw_info["server"]["ldap_group_context"];
					$entry["cn"] = $this->data["account_lid"];
					$entry["gidnumber"] = $this->data["account_id"];
					$entry["objectclass"] = "";
					$entry["objectclass"][0] = 'top';
					$entry["objectclass"][1] = 'posixGroup';
					$members = $this->memberships($this->data["account_id"]);
					for ($i=0;$i<count($members);$i++)
					{
						$entry["memberuid"][$i] = $this->id2name($members[$i]['account_id']);
					}
				}
				// Accounts
				else
				{
					$dn = 'uid='.$this->data["account_lid"].','.$phpgw_info["server"]["ldap_context"];
					$entry["uidnumber"]      = $this->data["account_id"];
					$entry["cn"] = sprintf("%s %s", $this->data["firstname"], $this->data["lastname"]);
					$entry["uid"]       = $this->data["account_lid"];
					$entry["givenname"]      = $this->data["firstname"];
					$entry["sn"]             = $this->data["lastname"];
					$entry["objectclass"]    = "";
					$entry["objectclass"][0] = 'top';
					$entry["objectclass"][1] = 'person';
					$entry["objectclass"][2] = 'organizationalPerson';
					$entry["objectclass"][3] = 'inetOrgPerson';
					//$entry["objectclass"][4] = 'account'; Causes problems with some LDAP servers
					$entry["objectclass"][4] = 'posixAccount';
					$entry["objectclass"][5] = 'shadowAccount';

					if ($phpgw_info["server"]["ldap_extra_attributes"])
					{
						$entry["homedirectory"] = $this->data["homedirectory"];
						$entry["loginshell"]    = $this->data["loginshell"];
					}
				}
				/*
				echo '<br>dn: '.$dn;
				while (list($key,$val) = each($entry)) {
					if (is_array($val)) {
						for ($i=0;$i<count($val);$i++) {
							if ($val[$i]) { echo "<br>\n".$key.': '.$val[$i]; }
						}
					} else {
						if ($val) { echo '<br>'.$key.': '.$val; }
					}
				}
				exit;*/
				ldap_add($ds, $dn, $entry);
			}
			// Normal behavior for save_repository
			else
			{
				if ($this->data["account_type"] == "g" && $phpgw_info["server"]["ldap_group_context"] )
				{
					$members = $this->memberships($this->data["account_id"]);
					for ($i=0;$i<count($members);$i++)
					{
						$entry["memberuid"][$i] = $this->id2name($members[$i]['account_id']);
					}
					unset($entry["givenname"]);
					unset($entry["sn"]);
				}
				while (list($key,$val) = each($entry))
				{
					$tmpentry = '';
					$tmpentry[$key] = trim($val); // must trim!
					//echo '<br>'.$key.' '.$val;
					if ($tmpentry[$key] && $key)
					{
						if (!$allValues[0][$key][0])
						{
							// attribute was not in LDAP, add it
							ldap_mod_add($ds, $allValues[0]["dn"], $tmpentry);
						}
						else
						{
							// attribute was in LDAP, modify it
							//echo $val.' ';
							ldap_modify($ds, $allValues[0]["dn"], $tmpentry);
						}
					}
				}
			}

			$this->db->query("update phpgw_accounts set account_lid='" . $this->data['account_lid']
				. "', account_firstname='" . $this->data['firstname']
				. "', account_lastname='"  . $this->data['lastname']
				. "', account_status='"    . $this->data['status']
				. "' where account_id='"   . $this->account_id . "'",__LINE__,__FILE__);

		}

		function add($account_name, $account_type, $first_name, $last_name, $passwd = False) 
		{
			$this->create($account_name, $account_type, $first_name, $last_name, $passwd);
		}

		function delete($accountid = '')
		{
			global $phpgw, $phpgw_info;

			$account_id = get_account_id($accountid);
			$account_lid = $this->id2name($account_id);
			$ds = $phpgw->common->ldapConnect();
			$sri = ldap_search($ds, $phpgw_info['server']['ldap_context'], 'uid='.$account_lid);
			$allValues = ldap_get_entries($ds, $sri);

			if ($allValues[0]['dn']) {
				$del = ldap_delete($ds, $allValues[0]['dn']);
			}

			// Do this last since we are depending upon this record to get the account_lid above
			$tables_array = Array('phpgw_accounts');
			$this->db->lock($tables_array);
			$this->db->query('DELETE FROM phpgw_accounts WHERE account_id='.$account_id);
			$this->db->unlock();
		}

		function get_list($_type='both', $start = '',$sort = '', $order = '', $query = '', $offset = '')
		{
			global $phpgw,$phpgw_info;

			if ($offset)
			{
				$limitclause = $phpgw->db->limit($start,$offset);
			}
			elseif ($start && !$offset)
			{
				$limitclause = $phpgw->db->limit($start);
			}

			if (! $sort)
			{
				$sort = "desc";
			}

			if ($order)
			{
				$orderclause = "order by $order $sort";
			}
			else
			{
				$orderclause = "order by account_lid,account_lastname,account_firstname asc";
			}

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

			$sql = "select * from phpgw_accounts $whereclause $orderclause $limitclause";
			$this->db->query($sql,__LINE__,__FILE__);
			while ($this->db->next_record()) {
				// get user information from ldap only, if it's a user, not a group
				if ($this->db->f("account_type") == 'u')
				{
					$sri = ldap_search($ds, $phpgw_info["server"]["ldap_context"], "uidnumber=".$this->db->f("account_id"));
					$allValues = ldap_get_entries($ds, $sri);
					$accounts[] = Array(
						"account_id" => $allValues[0]["uidnumber"][0],
						"account_lid" => $allValues[0]["uid"][0],
						"account_type" => $this->db->f("account_type"),
						"account_firstname" => $allValues[0]["givenname"][0],
						"account_lastname" => $allValues[0]["sn"][0],
						"account_status" => $this->db->f("account_status")
					);
				} else {
					$sri = ldap_search($ds, $phpgw_info["server"]["ldap_group_context"], "gidnumber=".$this->db->f("account_id"));
					$allValues = ldap_get_entries($ds, $sri);
					$accounts[] = Array(
						"account_id" => $allValues[0]["gidnumber"][0],
						"account_lid" => $allValues[0]["cn"][0],
						"account_type" => $this->db->f("account_type"),
						"account_firstname" => $this->db->f("account_firstname"),
						"account_lastname" => $this->db->f("account_lastname"),
						"account_status" => $this->db->f("account_status")
					);
				}
			}

			return $accounts;
		}

		function name2id($account_lid)
		{
			global $phpgw, $phpgw_info;

			$this->db->query("SELECT account_id FROM phpgw_accounts WHERE account_lid='".$account_lid."'",__LINE__,__FILE__);
			if($this->db->num_rows()) {
				$this->db->next_record();
				return intval($this->db->f('account_id'));
			} else {
				return False;
			}
		}

		function id2name($account_id)
		{
			global $phpgw, $phpgw_info;
	
			$this->db->query("SELECT account_lid FROM phpgw_accounts WHERE account_id='".$account_id."'",__LINE__,__FILE__);
			if($this->db->num_rows()) {
				$this->db->next_record();
				return $this->db->f('account_lid');
			} else {
				return False;
			}
		}

		function get_type($accountid = '')
		{
			global $phpgw, $phpgw_info;

	    	$account_id = get_account_id($accountid);
			$this->db->query("SELECT account_type FROM phpgw_accounts WHERE account_id='".$account_id."'",__LINE__,__FILE__);
			if ($this->db->num_rows()) {
				$this->db->next_record();
				return $this->db->f("account_type");
	    	} else {
				return False;
			}
		}

		function exists($account_lid)
		{
			global $phpgw, $phpgw_info;

			if(gettype($account_lid) == 'integer')
			{
				$account_id = $account_lid;
				settype($account_lid,'string');
				$account_lid = $this->id2name($account_id);
			}

			$this->db->query("SELECT count(*) FROM phpgw_accounts WHERE account_lid='".$account_lid."'",__LINE__,__FILE__);
			$this->db->next_record();
			if ($this->db->f(0))
			{
				$insql = True;
			}
			else
			{
				$insql = False;
			}

			$ds = $phpgw->common->ldapConnect();
			$acct_type = $this->get_type($account_id);
			if ($acct_type == 'g' && $phpgw_info["server"]["ldap_group_context"])
			{
				$sri = ldap_search($ds, $phpgw_info["server"]["ldap_group_context"], "cn=".$account_lid);
			}
			else
			{
				$sri = ldap_search($ds, $phpgw_info["server"]["ldap_context"], "uid=".$account_lid);
			}
			$allValues = ldap_get_entries($ds, $sri);

			if ($allValues[0]["dn"]) {
				$inldap = True;
			} else {
				$inldap = False;
			}

			$rtrn = $insql || $inldap;
			return $rtrn;
		}

		function create($account_type, $account_lid, $account_pwd, $account_firstname, $account_lastname, $account_status, $account_id='',$account_home='',$account_shell='')
		{
			global $phpgw_info, $phpgw;

			$ds = $phpgw->common->ldapConnect();

			if (!$account_id) {
				if ($phpgw_info["server"]["account_min_id"]) { $min = $phpgw_info["server"]["account_min_id"]; }
				if ($phpgw_info["server"]["account_max_id"]) { $max = $phpgw_info["server"]["account_max_id"]; }

				$nextid = $phpgw->common->last_id("accounts_ldap",$min,$max);

				// Loop until we find a free id
				$free = 0;
				while (!$free) {
					if ($this->exists($nextid))
					{
						$nextid = $phpgw->common->next_id("accounts_ldap",$min,$max);
					} else {
						$free = True;
					}
				}
				if ($phpgw_info["server"]["account_max_id"] && ($nextid > $phpgw_info["server"]["account_max_id"])) {
					return False;
				}
				$account_id = $nextid;
				//echo $account_id;exit;
			}

			$this->db->query("insert into phpgw_accounts (account_id, account_lid, account_type, account_pwd, "
				. "account_firstname, account_lastname, account_status) values ('" . $account_id . "','" . $account_lid
				. "','" . $account_type . "','" . md5($account_pwd) . "', '" . $account_firstname
				. "','" . $account_lastname . "','" . $account_status . "')",__LINE__,__FILE__);

			if ($account_type == "g")
			{
				$sri = ldap_search($ds, $phpgw_info["server"]["ldap_group_context"], "cn=$account_lid");
			}
			else
			{
				$sri = ldap_search($ds, $phpgw_info["server"]["ldap_context"], "uid=$account_lid");
			}
			$allValues = ldap_get_entries($ds, $sri);

			$entry["gidnumber"]          = $account_id;
			$entry["userpassword"]       = $phpgw->common->encrypt_password($account_pwd);

			if ($phpgw_info["server"]["ldap_extra_attributes"] && $account_type != 'g') {
				if ($account_home)
				{
					$entry["homedirectory"]     = $account_home;
				}
				else
				{
					$entry["homedirectory"]     = $phpgw_info["server"]["ldap_account_home"].SEP.$account_lid;
				}

				if ($account_shell)
				{
					$entry["loginshell"]        = $account_shell;
				}
				else
				{
					$entry["loginshell"]        = $phpgw_info["server"]["ldap_account_shell"];
				}
			}

			if ($allValues[0]["dn"]) {
				// This should keep the password from being overwritten here on ldap import
				unset($entry["userpassword"]);

				while (list($key,$val) = each($entry))
				{
					$tmpentry = '';
					$tmpentry[$key] = trim($val); // must trim!
					//echo '<br>'.$key.' '.$val;
					if ($tmpentry[$key])
					{
						if (!$allValues[0][$key][0])
						{
							// attribute was not in LDAP, add it
							ldap_mod_add($ds, $allValues[0]["dn"], $tmpentry);
						}
						else
						{
							// attribute was in LDAP, modify it
							ldap_modify($ds, $allValues[0]["dn"], $tmpentry);
						}
					}
				}

				if ($account_type == "g")
				{
					$tmpentry["objectclass"][0] = 'top';
					$tmpentry["objectclass"][1] = 'posixGroup';
				}
				else
				{
					$tmpentry["uidnumber"]      = $account_id;
					$tmpentry["objectclass"][0] = 'top';
					$tmpentry["objectclass"][1] = 'person';
					$tmpentry["objectclass"][2] = 'organizationalPerson';
					$tmpentry["objectclass"][3] = 'inetOrgPerson';
					//$tmpentry["objectclass"][4] = 'account'; Causes problems with some LDAP servers
					$tmpentry["objectclass"][5] = 'posixAccount';
					$tmpentry["objectclass"][6] = 'shadowAccount';
				}
				ldap_modify($ds, $allValues[0]["dn"], $tmpentry);
			} else {
				if ($account_type == "g")
				{
					$dn = 'cn='.$account_lid.','.$phpgw_info["server"]["ldap_group_context"];
					unset($entry["homedirectory"]);
					unset($entry["loginshell"]);
					$entry["cn"] = $account_lid;
					$entry["objectclass"][0] = 'top';
					$entry["objectclass"][1] = 'posixGroup';
				}
				else
				{
					$dn = 'uid=' . $account_lid . ',' . $phpgw_info["server"]["ldap_context"];
					$entry["cn"]             = sprintf("%s %s", $account_firstname, $account_lastname);
					$entry["sn"]             = $account_lastname;
					$entry["givenname"]      = $account_firstname;
					$entry["uid"]            = $account_lid;
					$entry["uidnumber"]      = $account_id;
					$entry["objectclass"][0] = 'top';
					$entry["objectclass"][1] = 'person';
					$entry["objectclass"][2] = 'organizationalPerson';
					$entry["objectclass"][3] = 'inetOrgPerson';
					//$entry["objectclass"][4] = 'account'; Causes problems with some LDAP servers
					$entry["objectclass"][4] = 'posixAccount';
					$entry["objectclass"][5] = 'shadowAccount';
				}

				ldap_add($ds, $dn, $entry);
			}
			//print ldap_error($ds);
		}

		function auto_add($account_name, $passwd, $default_prefs=False, $default_acls= False)
		{
			print "not done yet auto_generate class.accounts_ldap.inc.php<br>";
			exit();
			global $phpgw, $phpgw_info;

			if ($phpgw_info["server"]["account_min_id"]) { $min = $phpgw_info["server"]["account_min_id"]; }
			if ($phpgw_info["server"]["account_max_id"]) { $max = $phpgw_info["server"]["account_max_id"]; }

			$nextid = $phpgw->common->last_id("accounts_ldap",$min,$max);

			// Loop until we find a free id
			$free = 0;
			while (!$free) {
				if ($this->exists($nextid))
				{
					$nextid = $phpgw->common->next_id("accounts_ldap",$min,$max);
				} else {
					$free = True;
				}
			}
			if ($phpgw_info["server"]["account_max_id"] && ($nextid > $phpgw_info["server"]["account_max_id"])) {
				return False;
			}
			$account_id = $nextid;
			//echo $account_id;exit;

			if ($defaultprefs =="") {
				$defaultprefs = 'a:5:{s:6:"common";a:10:{s:9:"maxmatchs";s:2:"15";s:12:"template_set";s:8:"verdilak";s:5:"theme";s:6:"purple";s:13:"navbar_format";s:5:"icons";s:9:"tz_offset";N;s:10:"dateformat";s:5:"m/d/Y";s:10:"timeformat";s:2:"12";s:4:"lang";s:2:"en";s:11:"default_app";N;s:8:"currency";s:1:"$";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}:s:8:"calendar";a:4:{s:13:"workdaystarts";s:1:"7";s:11:"workdayends";s:2:"15";s:13:"weekdaystarts";s:6:"Monday";s:15:"defaultcalendar";s:9:"month.php";}}';
//				$defaultprefs = 'a:5:{s:6:"common";a:1:{s:0:"";s:2:"en";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}s:8:"calendar";a:1:{s:0:"";s:13:"workdaystarts";}i:15;a:1:{s:0:"";s:11:"workdayends";}s:6:"Monday";a:1:{s:0:"";s:13:"weekdaystarts";}}';
			}
			$sql = "insert into phpgw_accounts";
			$sql .= "(account_id, account_lid, account_pwd, account_firstname, account_lastname, account_lastpwd_change, account_status, account_type)";
			$sql .= "values (".$accountid.", '".$accountname."', '".md5($passwd)."', '".$accountname."', 'AutoCreated', ".time().", 'A','u')";
			$this->db->query($sql);
			$this->db->query("insert into phpgw_preferences (preference_owner, preference_value) values ('".$accountid."', '$defaultprefs')");
			$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights)values('preferences', 'changepassword', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
			$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('phpgw_group', '1', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
			$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('addressbook', 'run', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
			$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('filemanager', 'run', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
			$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('calendar', 'run', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
			$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('email', 'run', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
			$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('notes', 'run', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
			$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_account_type, acl_rights) values('todo', 'run', ".$accountid.", 'u', 1)",__LINE__,__FILE__);
			return $accountid;
		}

		function getDNforID($_accountid = '')
		{
			global $phpgw;

			$_account_id = get_account_id($_accountid);

			$ds = $phpgw->common->ldapConnect();

			$sri = ldap_search($ds, $phpgw_info["server"]["ldap_context"], "uidnumber=$_account_id");
			$allValues = ldap_get_entries($ds, $sri);

			return $allValues[0]["dn"];
		}
	}
