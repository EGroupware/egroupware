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

	/*
	THIS NEEDS WORK!!!!!!!!! - Milosch
	*/
	$GLOBALS['phpgw_info']['server']['global_denied_users'] = array(
		'root'     => True, 'bin'      => True, 'daemon' => True,
		'adm'      => True, 'lp'       => True, 'sync'   => True,
		'shutdown' => True, 'halt'     => True, 'ldap'   => True,
		'mail'     => True, 'news'     => True, 'uucp'   => True,
		'operator' => True, 'games'    => True, 'gopher' => True,
		'nobody'   => True, 'xfs'      => True, 'pgsql'  => True,
		'mysql'    => True, 'postgres' => True, 'oracle' => True,
		'ftp'      => True, 'gdm'      => True, 'named'  => True,
		'alias'    => True, 'web'      => True, 'sweep'  => True,
		'cvs'      => True, 'qmaild'   => True, 'qmaill' => True,
		'qmaillog' => True, 'qmailp'   => True, 'qmailq' => True,
		'qmailr'   => True, 'qmails'   => True, 'rpc'    => True,
		'rpcuser'  => True, 'amanda'   => True, 'apache' => True,
		'pvm'      => True, 'squid'    => True, 'ident'  => True,
		'nscd'     => True, 'mailnull' => True, 'cyrus'  => True,
		'backup'   => True
	);

	$GLOBALS['phpgw_info']['server']['global_denied_groups'] = array(
		'root'      => True, 'bin'      => True, 'daemon'   => True,
		'sys'       => True, 'adm'      => True, 'tty'      => True,
		'disk'      => True, 'lp'       => True, 'mem'      => True,
		'kmem'      => True, 'wheel'    => True, 'mail'     => True,
		'uucp'      => True, 'man'      => True, 'games'    => True,
		'dip'       => True, 'ftp'      => True, 'nobody'   => True,
		'floppy'    => True, 'xfs'      => True, 'console'  => True,
		'utmp'      => True, 'pppusers' => True, 'popusers' => True,
		'slipusers' => True, 'slocate'  => True, 'mysql'    => True,
		'dnstools'  => True, 'web'      => True, 'named'    => True,
		'dba'       => True, 'oinstall' => True, 'oracle'   => True,
		'gdm'       => True, 'sweep'    => True, 'cvs'      => True,
		'postgres'  => True, 'qmail'    => True, 'nofiles'  => True,
		'ldap'      => True, 'backup'   => True
	);

	class accounts_
	{
		var $db;
		var $contacts;
		var $account_id;
		var $data;
		var $debug = False;
		var $qcols = array(
			'fn'                     => 'fn',
			'n_given'                => 'n_given',
			'n_family'               => 'n_family',
			'account_lastlogin'      => 'account_lastlogin',
			'account_lastloginfrom'  => 'account_lastloginfrom',
			'account_lastpwd_change' => 'account_lastpwd_change',
			'account_status'         => 'account_status',
			'account_expires'        => 'account_expires'
		);

		function accounts_()
		{
			$this->db       = $GLOBALS['phpgw']->db;
			$this->contacts = CreateObject('phpgwapi.contacts',0);
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
			$this->makeobj();

			$allValues = $this->contacts->read_single_entry($this->account_id,$this->qcols);

			/* Now dump it into the array */
			$this->data['userid']            = $allValues[0]['lid'];
			$this->data['account_id']        = $allValues[0]['id'];
			$this->data['account_lid']       = $allValues[0]['lid'];
			$this->data['account_type']      = $allValues[0]['tid'];
			$this->data['firstname']         = $allValues[0]['n_given'];
			$this->data['lastname']          = $allValues[0]['n_family'];
			$this->data['fullname']          = $allValues[0]['fn'];
			$this->data['lastlogin']         = $allValues[0]['account_lastlogin'];
			$this->data['lastloginfrom']     = $allValues[0]['account_lastloginfrom'];
			$this->data['lastpasswd_change'] = $allValues[0]['account_lastpwd_change'];
			$this->data['status']            = $allValues[0]['account_status'];
			$this->data['expires']           = $allValues[0]['account_expires'];

			return $this->data;
		}

		function save_repository()
		{
			$this->makeobj();

			$entry['id']                        = $this->data['account_id'];
			$entry['lid']                       = $this->data['account_lid'];
			$entry['tid']                       = $this->data['account_type'];
			$entry['fn']                        = sprintf("%s %s", $this->data['firstname'], $this->data['lastname']);
			$entry['n_family']                  = $this->data['lastname'];
			$entry['n_given']                   = $this->data['firstname'];
			$entry['account_lastlogin']         = $this->data['lastlogin'];
			$entry['account_lastloginfrom']     = $this->data['lastloginfrom'];
			$entry['account_lastpasswd_change'] = $this->data['lastpwd_change'];
			$entry['account_status']            = $this->data['status'];
			$entry['account_expires']           = $this->data['expires'];

			if($this->debug) { echo '<br>Updating entry:<br>' . var_dump($entry); }
			$this->contacts->update($entry['id'],0,$entry,'public','',$entry['tid']);
		}

		function add($account_name, $account_type, $first_name, $last_name, $passwd = False) 
		{
			$this->create($account_name, $account_type, $first_name, $last_name, $passwd);
		}

		function delete($accountid = '')
		{
			$this->makeobj();

			if($this->debug) { echo '<br>Deleting entry:<br>' . $account_id; }
			$account_id = get_account_id($accountid);
			$this->contacts->delete($account_id);
		}

		function get_list($_type='both')
		{
			$this->makeobj();

			switch($_type)
			{
				case 'accounts':
					$filter = 'tid=u';
					break;
				case 'groups':
					$filter = 'tid=g';
					break;
				default:
					$filter = 'tid=u,tid=g';
			}

			$allValues = $this->contacts->read(0,0,$this->qcols,'',$filter);

			/* get user information for each user/group */
			for($i=0;$i<count($allValues);$i++)
			{
				$accounts[] = Array(
					'account_id'        => $allValues[$i]['id'],
					'account_lid'       => $allValues[$i]['lid'],
					'account_type'      => $allValues[$i]['tid'],
					'account_firstname' => $allValues[$i]['n_given'],
					'account_lastname'  => $allValues[$i]['n_family'],
					'account_status'    => $allValues[$i]['account_status'],
					'account_expires'   => $allValues[$i]['account_expires']
				);
			}

			return $accounts;
		}

		function name2id($account_lid)
		{
			$qcols = array('id' => 'id');
			$this->makeobj();
			$allValues = $this->contacts->read(0,0,$qcols,'',"lid=".$account_lid);

			if($allValues[0]['id'])
			{
				return intval($allValues[0]['id']);
			}
			else
			{
				return False;
			}
		}

		function id2name($account_id)
		{
			$this->makeobj();

			$allValues = $this->contacts->read_single_entry($account_id);
			if($this->debug) { echo '<br>id2name: '.$allValues[0]['lid']; }

			if($allValues[0]['lid'])
			{
				return $allValues[0]['lid'];
			}
			else
			{
				return False;
			}
		}

		function get_type($accountid = '')
		{
			$this->makeobj();
			$account_id = get_account_id($accountid);

			$allValues = $this->contacts->read_single_entry($account_id);

			if ($allValues[0]['tid'])
			{
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

			$allValues = $this->contacts->read(0,0,array('n_given' => 'n_given'),'','lid='.$account_lid);

			if ($allValues[0]['id'])
			{
				return True;
			}
			else
			{
				return False;
			}
		}

		function create($account_info)
		{
			$this->makeobj();

			if (!$$account_info['account_id'])
			{
				$account_info['account_id'] = $this->get_nextid();
			}
			$owner = $GLOBALS['phpgw_info']['user']['account_id'];
			$entry['id']       = $account_info['account_id'];
			$entry['lid']      = $account_info['account_lid'];
			$entry['n_given']  = $account_info['account_firstname'];
			$entry['n_family'] = $account_info['account_lastname'];
			$entry['password'] = $account_info['account_passwd'];
			$entry['account_status']   = $account_info['account_status'];
			$entry['account_expires']  = $account_info['account_expires'];

			if($this->debug) { echo '<br>Adding entry:<br>' . var_dump($entry); }
			/* 'public' access, no category id, tid set to account_type */
			$this->contacts->add(0,$entry,'public','',$account_info['account_type']);
			return;
		}

		function auto_add($accountname, $passwd, $default_prefs = False, $default_acls = False, $expiredate = 0, $account_status = 'A')
		{
			if ($expiredate)
			{
				$expires = mktime(2,0,0,date('n',$expiredate), intval(date('d',$expiredate)), date('Y',$expiredate));
			}
			else
			{
				if($GLOBALS['phpgw_info']['server']['auto_create_expire'])
				{
					if($GLOBALS['phpgw_info']['server']['auto_create_expire'] == 'never')
					{
						$expires = -1;
					}
					else
					{
						$expiredate = time() + $GLOBALS['phpgw_info']['server']['auto_create_expire'];
						$expires   = mktime(2,0,0,date('n',$expiredate), intval(date('d',$expiredate)), date('Y',$expiredate));
					}
				}
				else
				{
					/* expire in 30 days by default */
					$expiredate = time() + ( ( 60 * 60 ) * (30 * 24) );
					$expires   = mktime(2,0,0,date('n',$expiredate), intval(date('d',$expiredate)), date('Y',$expiredate));
				}
			}

			$acct_info = array(
				'account_lid'       => $accountname,
				'account_type'      => 'u',
				'account_passwd'    => $passwd,
				'account_firstname' => '',
				'account_lastname'  => '',
				'account_status'    => $account_status,
				'account_expires'   => $expires
			);
			$this->create($acct_info);
			$accountid = $this->name2id($accountname);

			$this->db->transaction_begin();
			if (!$default_prefs)
			{
				$default_prefs = 'a:5:{s:6:"common";a:10:{s:9:"maxmatchs";s:2:"15";s:12:"template_set";s:8:"verdilak";s:5:"theme";s:6:"purple";s:13:"navbar_format";s:5:"icons";s:9:"tz_offset";N;s:10:"dateformat";s:5:"m/d/Y";s:10:"timeformat";s:2:"12";s:4:"lang";s:2:"en";s:11:"default_app";N;s:8:"currency";s:1:"$";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}:s:8:"calendar";a:4:{s:13:"workdaystarts";s:1:"7";s:11:"workdayends";s:2:"15";s:13:"weekdaystarts";s:6:"Monday";s:15:"defaultcalendar";s:9:"month.php";}}';
//				$defaultprefs = 'a:5:{s:6:"common";a:1:{s:0:"";s:2:"en";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}s:8:"calendar";a:1:{s:0:"";s:13:"workdaystarts";}i:15;a:1:{s:0:"";s:11:"workdayends";}s:6:"Monday";a:1:{s:0:"";s:13:"weekdaystarts";}}';
				$this->db->query("insert into phpgw_preferences (preference_owner, preference_value) values ('".$accountid."', '$default_prefs')");
			}

			if (!$default_acls)
			{
				$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights)values('preferences', 'changepassword', ".$accountid.", 1)",__LINE__,__FILE__);
				$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('phpgw_group', '1', ".$accountid.", 1)",__LINE__,__FILE__);
				$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('addressbook', 'run', ".$accountid.", 1)",__LINE__,__FILE__);
				$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('filemanager', 'run', ".$accountid.", 1)",__LINE__,__FILE__);
				$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('calendar', 'run', ".$accountid.", 1)",__LINE__,__FILE__);
				$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('email', 'run', ".$accountid.", 1)",__LINE__,__FILE__);
				$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('notes', 'run', ".$accountid.", 1)",__LINE__,__FILE__);
				$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('todo', 'run', ".$accountid.", 1)",__LINE__,__FILE__);
			}
			$this->db->transaction_commit();
			return $accountid;
		}
	}
