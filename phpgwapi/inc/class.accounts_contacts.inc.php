<?php
  /**************************************************************************\
  * phpGroupWare API - Accounts manager for the contacts class               *
  * This file written by Miles Lott <milosch@phpgroupware.org>               *
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

	$phpgw_info["server"]["global_denied_users"] = array();
	$phpgw_info["server"]["global_denied_groups"] = array();

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

		function makeobj()
		{
			if(!$this->contacts)
			{
				$this->contacts = CreateObject('phpgwapi.contacts','0');
			}
		}

		function read_repository()
		{
			$qcols = array(
				'n_given'                => 'n_given',
				'n_family'               => 'n_family',
				'account_lastlogin'      => 'account_lastlogin',
				'account_lastloginfrom'  => 'account_lastloginfrom',
				'account_lastpwd_change' => 'account_lastpwd_change',
				'account_status'         => 'account_status'
			);

			$allValues = $this->contacts->read_single_entry($this->account_id,$qcols);

			/* Now dump it into the array */
			$this->data["account_id"]	     = $allValues[0]["id"];
			$this->data["account_lid"] 	     = $allValues[0]["lid"];
			$this->data["account_type"]      = $allValues[0]["tid"];
			$this->data["firstname"]   	     = $allValues[0]["n_given"];
			$this->data["lastname"]    	     = $allValues[0]["n_family"];
			$this->data["fullname"]    	     = $allValues[0]["fn"];
			$this->data["lastlogin"]         = $allValues[0]["account_lastlogin"];
			$this->data["lastloginfrom"]     = $allValues[0]["account_lastloginfrom"];
			$this->data["lastpasswd_change"] = $allValues[0]["account_lastpwd_change"];
			$this->data["status"]            = $allValues[0]["account_status"];
			$this->data["status"] = 'A';

			return $this->data;
		}

		function save_repository()
		{
			$entry["id"]                        = $this->data["account_id"];
			$entry["lid"]                       = $this->data["account_lid"];
			$entry["tid"]                       = $this->data["account_type"];
			$entry["fn"]                        = sprintf("%s %s", $this->data["firstname"], $this->data["lastname"]);
			$entry["n_family"]                  = $this->data["lastname"];
			$entry["n_given"]                   = $this->data["firstname"];
			$entry["account_lastlogin"]         = $this->data["lastlogin"];
			$entry["account_lastloginfrom"]     = $this->data["lastloginfrom"];
			$entry["account_lastpasswd_change"] = $this->data["lastpwd_change"];
			$entry["account_status"]    = $this->data["status"];		

			$this->contacts->update($this->account_id,$entry);
		}

		function add($account_name, $account_type, $first_name, $last_name, $passwd = False) 
		{
			$this->create($account_name, $account_type, $first_name, $last_name, $passwd);
		}

		function delete($accountid = '')
		{
			global $phpgw, $phpgw_info;

			$account_id = get_account_id($accountid);
			$this->contacts->delete($account_id);

			// Do this last since we are depending upon this record to get the account_lid above
			$this->db->query('DELETE FROM phpgw_accounts WHERE account_id='.$account_id);
		}

		function get_list($_type='both')
		{
			global $phpgw;

			switch($_type)
			{
				case 'accounts':
					$filter = "tid=u";
					break;
				case 'groups':
					$filter = "tid=g";
					break;
				default:
					$filter = "tid=u,tid=g";
			}

			$allValues = $this->contacts->read(0,0,$qcols,'',$filter);

			// get user information for each user/group
			for($i=0;$i<count($allValues);$i++) {
				$accounts[] = Array(
					"account_id"        => $allValues[$i]["id"],
					"account_lid"       => $allValues[$i]["lid"],
					"account_type"      => $allValues[$i]["tid"],
					"account_firstname" => $allValues[$i]["n_given"],
					"account_lastname"  => $allValues[$i]["n_family"]
				);

				$this->db->query("select account_status from phpgw_accounts where account_id='" . $allValues[$i]["id"] . "'",__LINE__,__FILE__);
				$this->db->next_record();
				$accounts[$i]["account_status"] = $this->db->f("account_status");
			}

			return $accounts;
		}

		function name2id($account_lid)
		{
			$qcols = array('id' => 'id');
			$this->makeobj();
			$allValues = $this->contacts->read(0,0,$qcols,'',"lid=".$account_lid);

			if($allValues[0]['id']) {
				return intval($allValues[0]['id']);
			} else {
				return False;
			}
		}

		function id2name($account_id)
		{
			global $phpgw, $phpgw_info;
			$this->makeobj();
			$allValues = $this->contacts->read_single_entry($account_id);
			echo '<br>id2name: '.$allValues[0]['lid'];

			if($allValues[0]['lid']) {

				return intval($allValues[0]['lid']);
			} else {
				return False;
			}
		}

		function get_type($accountid = '')
		{
			global $phpgw, $phpgw_info;
			$this->makeobj();
			$account_id = get_account_id($accountid);

			$allValues = $this->contacts->read_single_entry($account_id);

			if ($allValues[0]['tid']) {
				return $allValues[0]['tid'];
			}
			else
			{
				return False;
			}
		}

		function exists($account_lid)
		{
			$this->makeobj();
			if(gettype($account_lid) == 'integer')
			{
				$account_id = $account_lid;
				settype($account_lid,'string');
				$account_lid = $this->id2name($account_id);
			}

			$allValues = $this->contacts->read(0,0,$qcols,'',"lid=".$account_lid);
			if ($allValues[0]['id'])
			{
				return True;
			}
			else
			{
				return False;
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
			$this->contacts->add($owner,$entry,'public','',$account_type);
			return;
		}

		function auto_add($account_name, $passwd, $default_prefs=False, $default_acls= False)
		{
			print "not done until now auto_generate class.accounts_contacts.inc.php<br>";
			exit();
		}
	}
