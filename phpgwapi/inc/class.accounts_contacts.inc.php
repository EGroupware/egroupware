<?php
  /**************************************************************************\
  * phpGroupWare API - Accounts manager for the contacts class               *
  * This file written by Miles Lott <milosch@phpgroupware.org>               *
  * and Lars Kneschke <kneschke@phpgroupware.org>                            *
  * View and manipulate account records using the contacts class             *
  * Copyright (C) 2000, 2001 Miles Lott                                      *
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


	// THIS NEEDS WORK!!!!!!!!! - Milosch

  	// Dont know where to put this (seek3r)
	// This is where it belongs (jengo)
	// This is where it ended up (milosch)
	/* Since LDAP will return system accounts, there are a few we don't want to login. */
	$phpgw_info["server"]["global_denied_users"] = array();

	class accounts_
	{
		var $db;
		var $contacts;
		var $account_id;
		var $data;

		function accounts_()
		{
			global $phpgw;
			$this->db       = $phpgw->db;
			$this->contacts = CreateObject('phpgwapi.contacts');
		}

		function read_repository()
		{
			global $phpgw, $phpgw_info;

			$qcols = array(
				'n_given'  => 'n_given',
				'n_family' => 'n_family',
			);

			$allValues = $this->contacts->read_single_entry($this->account_id,$qcols);

			/* Now dump it into the array */
			$this->data["account_id"]	= $allValues[0]["id"];
			$this->data["account_lid"] 	= $allValues[0]["lid"];
			$this->data["account_type"] = $allValues[0]["tid"];
			$this->data["firstname"]   	= $allValues[0]["n_given"];
			$this->data["lastname"]    	= $allValues[0]["n_family"];
			$this->data["fullname"]    	= $allValues[0]["fn"];

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

			$entry["fn"] 		= sprintf("%s %s", $this->data["firstname"], $this->data["lastname"]);
			$entry["n_family"]	= $this->data["lastname"];
			$entry["n_given"]	= $this->data["firstname"];

			$this->contacts->update($this->account_id,$entry);

			$this->db->query("update phpgw_accounts set account_firstname='" . $this->data['firstname']
				. "', account_lastname='" . $this->data['lastname'] . "', account_status='"
				. $this->data['status'] . "' where account_id='" . $this->account_id . "'",__LINE__,__FILE__);

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
			$this->db->query('DELETE FROM phpgw_accounts WHERE account_id='.$account_id);
		}

		function get_list($_type='both')
		{
			global $phpgw;

			switch($_type)
			{
				case 'accounts':
					$whereclause = "u";
					break;
				case 'groups':
					$whereclause = "g";
					break;
				default:
					$whereclause = "u,tid=g";
			}

			$allValues = $contacts->read(0,0,$qcols,'',"tid=".$whereclause);

			// get user information from ldap only, if it's a user, not a group
			for($i=0;$i<count($allValues);$i++) {
				$accounts[] = Array(
					"account_id"        => $allValues[$i]["id"],
					"account_lid"       => $allValues[$i]["lid"],
					"account_type"      => $allValues[$i]["tid"],
					"account_firstname" => $allValues[$i]["n_given"],
					"account_lastname"  => $allValues[$i]["n_family"]
				);

				$this->db->query("select account_status from phpgw_accounts where account_id='" . $allValues[$i]["id"] . "'",__LINE__,__FILE__);
				$this->db->next_record()) {
				$accounts[$i]["account_status"] = $this->db->f("account_status");
			}

			return $accounts;
		}

		function name2id($account_lid)
		{
			global $phpgw, $phpgw_info;

			$qcols = array('id' => 'id');

			$allValues = $contacts->read(0,0,$qcols,'',"lid=".$account_lid);

			if($allValues[0]['id']) {
				return intval($allValues[0]['id']);
			} else {
				return False;
			}
		}

		function id2name($account_id)
		{
			global $phpgw, $phpgw_info;

			$allValues = $this->contacts->read_single_entry($account_id);

			if($allValues[0]['lid']) {
				return intval($allValues[0]['lid']);
			} else {
				return False;
			}
		}

		function get_type($accountid = '')
		{
			global $phpgw, $phpgw_info;

	    	$account_id = get_account_id($accountid);

			$allValues = $this->contacts->read_single_entry($account_id);

			if ($allValues[0]['tid']) {
				return $allValues[0]['tid'];
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
				settype($acount_lid,'string');
				$account_lid = $this->id2name($account_id);
			}

			$allValues = $contacts->read(0,0,$qcols,'',"lid=".$account_lid);
			if $allValues[0]['id'])
			{
				return True;
			}
			else
			{
				return  False;
			}
		}

		function create($account_type, $account_lid, $account_pwd, $account_firstname, $account_lastname, $account_status, $account_id='',$account_home='',$account_shell='')
		{
			global $phpgw_info, $phpgw;

			$owner = $phpgw_info["user"]["account_id"];
			$entry['n_given']  = $account_firstname;
			$entry['n_family'] = $account_lastname;
			$entry['password'] = $account_pwd;
			$entry['status']   = $account_status;

			// 'public' access, no category id, tid set to account_type
			$contacts->add($owner,$entry,'public','',$account_type);
			return;
		}

		function auto_add($account_name, $passwd, $default_prefs=False, $default_acls= False)
		{
			print "not done until now auto_generate class.accounts_ldap.inc.php<br>";
			exit();
		}
	}
