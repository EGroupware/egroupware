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

  	/* Dont know where to put this (seek3r)
	 This is where it belongs (jengo)
	 This is where it ended up (milosch) 
	 Since LDAP will return system accounts, there are a few we don't want to login.
	*/
	$GLOBALS['phpgw_info']['server']['global_denied_users'] = array(
		'root'     => True, 'bin'      => True, 'daemon'   => True,
		'adm'      => True, 'lp'       => True, 'sync'     => True,
		'shutdown' => True, 'halt'     => True, 'ldap'     => True,
		'mail'     => True, 'news'     => True, 'uucp'     => True,
		'operator' => True, 'games'    => True, 'gopher'   => True,
		'nobody'   => True, 'xfs'      => True, 'pgsql'    => True,
		'mysql'    => True, 'postgres' => True, 'oracle'   => True,
		'ftp'      => True, 'gdm'      => True, 'named'    => True,
		'alias'    => True, 'web'      => True, 'sweep'    => True,
		'cvs'      => True, 'qmaild'   => True, 'qmaill'   => True,
		'qmaillog' => True, 'qmailp'   => True, 'qmailq'   => True,
		'qmailr'   => True, 'qmails'   => True, 'rpc'      => True,
		'rpcuser'  => True, 'amanda'   => True, 'apache'   => True,
		'pvm'      => True, 'squid'    => True, 'ident'    => True,
		'nscd'     => True, 'mailnull' => True, 'cyrus'    => True
	);

	class accounts_
	{
		var $db;
		var $account_id;
		var $data;

		function accounts_()
		{
			$this->db = $GLOBALS['phpgw']->db;
		}

		function read_repository()
		{
			/* get an ldap connection handle */
			$ds = $GLOBALS['phpgw']->common->ldapConnect();

			// search the dn for the given uid
			$sri = ldap_search($ds, $GLOBALS['phpgw_info']['server']['ldap_context'], 'uidnumber='.$this->account_id);
			$allValues = ldap_get_entries($ds, $sri);

			/* Now dump it into the array; take first entry found */
			$this->data['account_id']  = $allValues[0]['uidnumber'][0];
			$this->data['account_lid'] = $allValues[0]['uid'][0];
			$this->data['account_dn']  = $allValues[0]['dn'];
			$this->data['firstname']   = $allValues[0]['givenname'][0];
			$this->data['lastname']    = $allValues[0]['sn'][0];
			$this->data['fullname']    = $allValues[0]['cn'][0];
			if ($GLOBALS['phpgw_info']['server']['ldap_extra_attributes'])
			{
				$this->data['homedirectory'] = $allValues[0]['homedirectory'][0];
				$this->data['loginshell']    = $allValues[0]['loginshell'][0];
			}
			$this->db->query("SELECT * FROM phpgw_accounts WHERE account_id='" . $this->data['account_id'] . "'",__LINE__,__FILE__);
			$this->db->next_record();

			$this->data['lastlogin']         = $this->db->f('account_lastlogin');
			$this->data['lastloginfrom']     = $this->db->f('account_lastloginfrom');
			$this->data['lastpasswd_change'] = $this->db->f('account_lastpwd_change');
			$this->data['status']            = $this->db->f('account_status');
			$this->data['expires'] = -1;

			return $this->data;
		}

		function save_repository()
		{
			$ds = $GLOBALS['phpgw']->common->ldapConnect();

			/* search the dn for the given uid */
			$sri = ldap_search($ds, $GLOBALS['phpgw_info']['server']['ldap_context'], 'uidnumber='.$this->account_id);
			$allValues = ldap_get_entries($ds, $sri);

			$entry['cn']        = sprintf("%s %s", $this->data['firstname'], $this->data['lastname']);
			$entry['sn']        = $this->data['lastname'];
			$entry['givenname'] = $this->data['firstname'];

			if ($GLOBALS['phpgw_info']['server']['ldap_extra_attributes'])
			{
				$entry['homedirectory'] = $this->data['homedirectory'];
				$entry['loginshell']    = $this->data['loginshell'];
			}

			while (list($key,$val) = each($entry))
			{
				$tmpentry = '';
				$tmpentry[$key] = trim($val); /* must trim! */
				/* echo '<br>'.$key.' '.$val; */
				if ($tmpentry[$key] && $key)
				{
					if (!$allValues[0][$key][0])
					{
						/* attribute was not in LDAP, add it */
						ldap_mod_add($ds, $allValues[0]['dn'], $tmpentry);
					}
					else
					{
						/* attribute was in LDAP, modify it */
						/* echo $val.' '; */
						ldap_modify($ds, $allValues[0]['dn'], $tmpentry);
					}
				}
			}

			$this->db->query("update phpgw_accounts set account_firstname='" . $this->data['firstname']
				. "', account_lastname='" . $this->data['lastname'] . "', account_status='"
				. $this->data['status']
				. "' where account_id='" . $this->account_id . "'",__LINE__,__FILE__);
		}

		function delete($accountid = '')
		{
			$account_id = get_account_id($accountid);
			$account_lid = $this->id2name($account_id);
			$ds = $GLOBALS['phpgw']->common->ldapConnect();
			$sri = ldap_search($ds, $GLOBALS['phpgw_info']['server']['ldap_context'], 'uid='.$account_lid);
			$allValues = ldap_get_entries($ds, $sri);

			if ($allValues[0]['dn'])
			{
				$del = ldap_delete($ds, $allValues[0]['dn']);
			}

			/* Do this last since we are depending upon this record to get the account_lid above */
			$tables_array = Array('phpgw_accounts');
			$this->db->lock($tables_array);
			$this->db->query('DELETE FROM phpgw_accounts WHERE account_id='.$account_id);
			$this->db->unlock();
		}

		function get_list($_type='both', $start = '',$sort = '', $order = '', $query = '', $offset = '')
		{
			if ($offset)
			{
				$limitclause = $GLOBALS['phpgw']->db->limit($start,$offset);
			}
			elseif ($start && !$offset)
			{
				$limitclause = $GLOBALS['phpgw']->db->limit($start);
			}

			if (! $sort)
			{
				$sort = 'desc';
			}

			if ($order)
			{
				$orderclause = "ORDER BY $order $sort";
			}
			else
			{
				$orderclause = 'ORDER BY account_lid,account_lastname,account_firstname ASC';
			}

			$ds = $GLOBALS['phpgw']->common->ldapConnect();

			switch($_type)
			{
				case 'accounts':
					$whereclause = "WHERE account_type = 'u'";
					break;
				case 'groups':
					$whereclause = "WHERE account_type = 'g'";
					break;
				default:
					$whereclause = '';
			}

			$sql = "SELECT * FROM phpgw_accounts $whereclause $orderclause $limitclause";
			$this->db->query($sql,__LINE__,__FILE__);
			while ($this->db->next_record())
			{
				// get user information from ldap only, if it's a user, not a group
				if ($this->db->f('account_type') == 'u')
				{
					$sri = ldap_search($ds, $GLOBALS['phpgw_info']['server']['ldap_context'], 'uidnumber='.$this->db->f('account_id'));
					$allValues = ldap_get_entries($ds, $sri);
					$accounts[] = Array(
						'account_id' => $allValues[0]['uidnumber'][0],
						'account_lid' => $allValues[0]['uid'][0],
						'account_type' => $this->db->f('account_type'),
						'account_firstname' => $allValues[0]['givenname'][0],
						'account_lastname' => $allValues[0]['sn'][0],
						'account_status' => $this->db->f('account_status')
					);
				}
				else
				{
					$accounts[] = Array(
						'account_id' => $this->db->f('account_id'),
						'account_lid' => $this->db->f('account_lid'),
						'account_type' => $this->db->f('account_type'),
						'account_firstname' => $this->db->f('account_firstname'),
						'account_lastname' => $this->db->f('account_lastname'),
						'account_status' => $this->db->f('account_status')
					);
				}
			}

			return $accounts;
		}

		function name2id($account_lid)
		{
			static $name_list;

			if(@isset($name_list[$account_lid]))
			{
				return $name_list[$account_lid];
			}

			$this->db->query("SELECT account_id FROM phpgw_accounts WHERE account_lid='".$account_lid."'",__LINE__,__FILE__);
			if($this->db->num_rows())
			{
				$this->db->next_record();
				$name_list[$account_lid] = intval($this->db->f('account_id'));
			}
			else
			{
				$name_list[$account_lid] = False;
			}
			return $name_list[$account_lid];
		}

		function id2name($account_id)
		{
			static $id_list;

			if(isset($id_list[$account_id]))
			{
				return $id_list[$account_id];
			}				
	
			$this->db->query("SELECT account_lid FROM phpgw_accounts WHERE account_id='".$account_id."'",__LINE__,__FILE__);
			if($this->db->num_rows())
			{
				$this->db->next_record();
				$id_list[$account_id] = $this->db->f('account_lid');
			}
			else
			{
				$id_list[$account_id] = False;
			}
			return $id_list[$account_id];
		}

		function get_type($accountid = '')
		{
			static $account_type;

			$account_id = get_account_id($accountid);
			if(@isset($account_type[$account_id]))
			{
				return $account_type[$account_id];
			}
			$this->db->query("SELECT account_type FROM phpgw_accounts WHERE account_id='".$account_id."'",__LINE__,__FILE__);
			if ($this->db->num_rows())
			{
				$this->db->next_record();
				$account_type[$account_id] = $this->db->f('account_type');
			}
			else
			{
				$account_type[$account_id] = False;
			}
			return $account_type[$account_id];
		}

		/*
		 * returns nonzero if $account exists in SQL or LDAP: 0: nowhere 1: SQL, 2: LDAP, 3: SQL+LDAP
		 * $account can be an account_id (LDAP: uidnumber) or an account_lid (LDAP: uid) (is determinded by gettype($account) == 'interger')
		 */
		function exists($account)
		{
			/* This sets up internal caching variables for this functon */
			static $by_id, $by_lid;

			if(gettype($account) == 'integer')
			{
				$sql_name  = 'account_id';
				$ldap_name = 'uidnumber';
				/* If data is cached, use it. */
				if(@isset($by_id[$account]))
				{
					return $by_id[$account];
				}
			}
			else
			{
				$sql_name  = 'account_lid';
				$ldap_name = 'uid';
				/* If data is cached, use it. */
				if(@isset($by_lid[$account]))
				{
					return $by_lid[$account];
				}
			}
			$this->db->query("SELECT count(*) FROM phpgw_accounts WHERE $sql_name='$account'",__LINE__,__FILE__);
			$this->db->next_record();
			if ($this->db->f(0))
			{
				$in += 1;
			}

			$ds = $GLOBALS['phpgw']->common->ldapConnect();
			$sri = ldap_search($ds, $GLOBALS['phpgw_info']['server']['ldap_context'], "$ldap_name=$account");
			$allValues = ldap_get_entries($ds, $sri);

			if ($allValues[0]['dn'])
			{
				$in += 2;
			}
			/* echo "<p>class_accounts_ldap->exists('$account') == $in</p>"; */

			/* This sets up internal caching for this functon */
			if($sql_name == 'account_id')
			{
				$by_id[$account] = $in;
				$by_lid[$this->id2name($account)] = $in;
			}
			else
			{
				$by_lid[$account] = $in;
				$by_id[$this->name2id($account)] = $in;
			}
			
			return $in;
		}

		function create($account_info)
		{
			$ds = $GLOBALS['phpgw']->common->ldapConnect();

			if (!($account_id = $account_info['account_id']))
			{
				if ($GLOBALS['phpgw_info']['server']['account_min_id'])
				{
					$min = $GLOBALS['phpgw_info']['server']['account_min_id'];
				}
				if ($GLOBALS['phpgw_info']['server']['account_max_id'])
				{
					$max = $GLOBALS['phpgw_info']['server']['account_max_id'];
				}

				$nextid = $GLOBALS['phpgw']->common->last_id('accounts_ldap',$min,$max);

				/* Loop until we find a free id */
				$free = 0;
				while (!$free)
				{
					$ldap_fields = '';
					$sri = ldap_search($ds, $GLOBALS['phpgw_info']['server']['ldap_context'],'uidnumber='.$nextid);
					$ldap_test = ldap_get_entries($ds, $sri);
					if ($ldap_test[0]['dn'][0])
					{
						$nextid = $GLOBALS['phpgw']->common->next_id('accounts_ldap',$min,$max);
					}
					else
					{
						$free = True;
					}
				}

				if ($GLOBALS['phpgw_info']['server']['account_max_id'] &&
					($nextid > $GLOBALS['phpgw_info']['server']['account_max_id']))
				{
					return False;
				}
				$account_id = $nextid;
				/* echo $account_id;exit; */
			}

			$this->db->query("INSERT INTO phpgw_accounts (account_id, account_lid, account_type, account_pwd, "
				. "account_firstname, account_lastname, account_status, account_expires) VALUES ('" . $account_id . "','" . $account_info['account_lid']
				. "','" . $account_info['account_type'] . "','" . md5($account_info['account_passwd']) . "', '" . $account_info['account_firstname']
				. "','" . $account_info['account_lastname'] . "','" . $account_info['account_status'] . "'," . $account_info['account_expires'] . ")",__LINE__,__FILE__);


			$sri = ldap_search($ds, $GLOBALS['phpgw_info']['server']['ldap_context'],'uid=' . $account_info['account_lid']);
			$allValues = ldap_get_entries($ds, $sri);

			$entry['uidnumber']    = $account_id;
			$entry['uid']          = $account_info['account_lid'];
			$entry['cn']           = sprintf('%s %s', $account_info['account_firstname'], $account_info['account_lastname']);
			$entry['sn']           = $account_info['account_lastname'];
			$entry['givenname']    = $account_info['account_firstname'];
			$entry['userpassword'] = $GLOBALS['phpgw']->common->encrypt_password($account_info['account_passwd']);

			if ($GLOBALS['phpgw_info']['server']['ldap_extra_attributes'] && $account_info['account_type'] == 'u')
			{
				$entry['homedirectory'] = $account_info['homedirectory'] ? $account_info['homedirectory'] : $GLOBALS['phpgw_info']['server']['ldap_account_home'].SEP.$account_info['account_lid'];

				$entry['loginshell'] = $account_info['loginshell'] ? $account_info['loginshell'] : $GLOBALS['phpgw_info']['server']['ldap_account_shell'];
			}

			if ($allValues[0]['dn'])
			{
				/* This should keep the password from being overwritten here ? */
				unset($entry['userpassword']);

				while (list($key,$val) = each($entry))
				{
					$tmpentry = '';
					$tmpentry[$key] = trim($val); /* must trim! */
					/* echo '<br>'.$key.' '.$val; */
					if ($tmpentry[$key])
					{
						if (!$allValues[0][$key][0])
						{
							/* attribute was not in LDAP, add it */
							ldap_mod_add($ds, $allValues[0]['dn'], $tmpentry);
						}
						else
						{
							/* attribute was in LDAP, modify it */
							ldap_modify($ds, $allValues[0]['dn'], $tmpentry);
						}
					}
				}
				/*
				if ($account_type == 'g')
				{
					$tmpentry['objectclass'][0] = 'top';
					$tmpentry['objectclass'][1] = 'posixGroup';
				}
				else
				*/
				if ($account_info['account_type'] == 'u')
				{
					$tmpentry['objectclass'][0] = 'top';
					$tmpentry['objectclass'][1] = 'person';
					$tmpentry['objectclass'][2] = 'organizationalPerson';
					$tmpentry['objectclass'][3] = 'inetOrgPerson';
					$tmpentry['objectclass'][4] = 'account';
					$tmpentry['objectclass'][5] = 'posixAccount';
					$tmpentry['objectclass'][6] = 'shadowAccount';
				}

				ldap_modify($ds, $allValues[0]['dn'], $tmpentry);
			}
			else
			{
				/*
				if ($account_type == 'g')
				{
					$entry['objectclass'][0] = 'top';
					$entry['objectclass'][1] = 'posixGroup';
				}
				else
				*/
				if ($account_info['account_type'] == 'u')
				{
					$dn = 'uid=' . $account_info['account_lid'] . ',' . $GLOBALS['phpgw_info']['server']['ldap_context'];
					$entry['objectclass'][0] = 'top';
					$entry['objectclass'][1] = 'person';
					$entry['objectclass'][2] = 'organizationalPerson';
					$entry['objectclass'][3] = 'inetOrgPerson';
					$entry['objectclass'][4] = 'account';
					$entry['objectclass'][5] = 'posixAccount';
					$entry['objectclass'][6] = 'shadowAccount';

					ldap_add($ds, $dn, $entry);
				}
				/* ldap_add($ds, $dn, $entry); */
			}
			/* print ldap_error($ds); */
		}

		function auto_add($accountname, $passwd, $default_prefs = False, $default_acls = False, $expiredate = 0, $account_status = 'A')
		{
			echo 'not yet implemented - auto_generate class.accounts_ldap.inc.php<br>';
			exit;

			if (!$expiredate)
			{
				/* expire in 30 days by default */
				$expiredate = time() + ( ( 60 * 60 ) * (30 * 24) );
			}

			$acct_info = array(
				'account_lid'       => $accountname,
				'account_type'      => 'u',
				'account_passwd'    => $passwd,
				'account_firstname' => '',
				'account_lastname'  => '',
				'account_status'    => $account_status,
				'account_expires'   => mktime(2,0,0,date('n',$expiredate), intval(date('d',$expiredate)), date('Y',$expiredate))
			);
			$this->create($acct_info);
			$accountid = $this->name2id($accountname);

			$this->db->transaction_begin();
			if ($default_prefs == False)
			{
				$defaultprefs = 'a:5:{s:6:"common";a:10:{s:9:"maxmatchs";s:2:"15";s:12:"template_set";s:8:"verdilak";s:5:"theme";s:6:"purple";s:13:"navbar_format";s:5:"icons";s:9:"tz_offset";N;s:10:"dateformat";s:5:"m/d/Y";s:10:"timeformat";s:2:"12";s:4:"lang";s:2:"en";s:11:"default_app";N;s:8:"currency";s:1:"$";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}:s:8:"calendar";a:4:{s:13:"workdaystarts";s:1:"7";s:11:"workdayends";s:2:"15";s:13:"weekdaystarts";s:6:"Monday";s:15:"defaultcalendar";s:9:"month.php";}}';
/*				$defaultprefs = 'a:5:{s:6:"common";a:1:{s:0:"";s:2:"en";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}s:8:"calendar";a:1:{s:0:"";s:13:"workdaystarts";}i:15;a:1:{s:0:"";s:11:"workdayends";}s:6:"Monday";a:1:{s:0:"";s:13:"weekdaystarts";}}'; */
				$this->db->query("insert into phpgw_preferences (preference_owner, preference_value) values ('".$accountid."', '$defaultprefs')");
			}

			if ($default_acls == False)
			{
				$default_group_lid = $GLOBALS['phpgw_info']['server']['default_group_lid'];
				$default_group_id  = $this->name2id($default_group_lid);
				$defaultgroupid = $default_group_id ? $default_group_id : $this->name2id('Default');
				if($defaultgroupid)
				{
					$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('phpgw_group', "
						. $defaultgroupid . ", " . $accountid . ", 1)",__LINE__,__FILE__);
				}
				$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights)values('preferences', 'changepassword', ".$accountid.", 1)",__LINE__,__FILE__);
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

		function getDNforID($_accountid = '')
		{
			$_account_id = get_account_id($_accountid);

			$ds = $GLOBALS['phpgw']->common->ldapConnect();

			$sri = ldap_search($ds, $GLOBALS['phpgw_info']['server']['ldap_context'], "uidnumber=$_account_id");
			$allValues = ldap_get_entries($ds, $sri);

			return $allValues[0]['dn'];
		}
	}
