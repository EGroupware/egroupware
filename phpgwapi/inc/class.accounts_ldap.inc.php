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
	$phpgw_info['server']['global_denied_users'] = array(
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
		'nscd'     => True, 'mailnull' => True, 'cyrus'	   => True,
		'backup'    => True
	);

	$phpgw_info['server']['global_denied_groups'] = array(
		'root'      => True, 'bin'       => True, 'daemon'    => True,
		'sys'       => True, 'adm'       => True, 'tty'       => True,
		'disk'      => True, 'lp'        => True, 'mem'       => True,
		'kmem'      => True, 'wheel'     => True, 'mail'      => True,
		'uucp'      => True, 'man'       => True, 'games'     => True,
		'dip'       => True, 'ftp'       => True, 'nobody'    => True,
		'floppy'    => True, 'xfs'       => True, 'console'   => True,
		'utmp'      => True, 'pppusers'  => True, 'popusers'  => True,
		'slipusers' => True, 'slocate'   => True, 'mysql'     => True,
		'dnstools'  => True, 'web'       => True, 'named'     => True,
		'dba'       => True, 'oinstall'  => True, 'oracle'    => True,
		'gdm'       => True, 'sweep'     => True, 'cvs'       => True,
		'postgres'  => True, 'qmail'     => True, 'nofiles'   => True,
		'ldap'      => True. 'backup'    => True
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

			/* get an ldap connection handle */
			$ds = $phpgw->common->ldapConnect();
			$acct_type = $this->get_type($this->account_id);

			/* search the dn for the given uid */
			if ( ($acct_type == 'g') && $phpgw_info['server']['ldap_group_context'] )
			{
				$sri = ldap_search($ds, $phpgw_info['server']['ldap_group_context'], 'gidnumber='.$this->account_id);
			}
			else
			{
				$sri = ldap_search($ds, $phpgw_info['server']['ldap_context'], 'uidnumber='.$this->account_id);
			}
			$allValues = ldap_get_entries($ds, $sri);

			/* Now dump it into the array; take first entry found */
			if($acct_type =='g')
			{
				$this->data['account_id']   = $allValues[0]['gidnumber'][0];
				$this->data['account_lid']  = $allValues[0]['cn'][0];
			}
			else
			{
				$this->data['account_id']	= $allValues[0]['uidnumber'][0];
				$this->data['account_lid']  = $allValues[0]['uid'][0];
			}
			$this->data['account_dn']  	= $allValues[0]['dn'];
			$this->data['firstname']   	= $allValues[0]['givenname'][0];
			$this->data['lastname']    	= $allValues[0]['sn'][0];
			$this->data['fullname']    	= $allValues[0]['cn'][0];
			if ($phpgw_info['server']['ldap_extra_attributes'])
			{
				$this->data['homedirectory']  = $allValues[0]['homedirectory'][0];
				$this->data['loginshell'] = $allValues[0]['loginshell'][0];
			}

			$this->data['lastlogin']         = $allValues[0]['phpgwaccountlastlogin'][0];
			$this->data['lastloginfrom']     = $allValues[0]['phpgwaccountlastloginfrom'][0];
			$this->data['lastpasswd_change'] = $allValues[0]['phpgwlastpasswdchange'][0];
			$this->data['status']            = $allValues[0]['phpgwaccountstatus'][0];
			$this->data['type']              = $allValues[0]['phpgwaccounttype'][0];
			$this->data['expires']           = $allValues[0]['phpgwaccountexpires'][0];

			return $this->data;
		}

		function save_repository()
		{
			global $phpgw_info, $phpgw;

			$ds = $phpgw->common->ldapConnect();
			$acct_type = $this->get_type($this->account_id);

			/* search the dn for the given u/gidnumber */
			if ( ($acct_type == 'g') && $phpgw_info['server']['ldap_group_context'] )
			{
				$sri = ldap_search($ds, $phpgw_info['server']['ldap_group_context'], 'gidnumber='.$this->account_id);
			}
			else
			{
				$sri = ldap_search($ds, $phpgw_info['server']['ldap_context'], 'uidnumber='.$this->account_id);
			}
			$allValues = ldap_get_entries($ds, $sri);

			$entry['cn']                    = sprintf("%s %s", $this->data['firstname'], $this->data['lastname']);
			$entry['sn']                    = $this->data['lastname'];
			$entry['givenname']             = $this->data['firstname'];
			$entry['phpgwaccountlastlogin']     = $this->data['lastlogin'];
			$entry['phpgwaccountlastloginfrom'] = $this->data['lastloginfrom'];
			$entry['phpgwlastpasswdchange'] = $this->data['lastpasswd_change'];
			$entry['phpgwaccountstatus']    = $this->data['status'];
			$entry['phpgwaccounttype']      = $this->data['type'];
			$entry['phpgwaccountexpires']   = $this->data['expires'];

			if ($phpgw_info['server']['ldap_extra_attributes'])
			{
				$entry['homedirectory'] = $this->data['homedirectory'];
				$entry['loginshell']    = $this->data['loginshell'];
			}

			/*
			Changing the uid:  Need to delete and add new, since
			PHP cannot change the dn for the entry.
			*/
			if ($acct_type == 'g') { $test = $allValues[0]['cn'][0];  }
			else                   { $test = $allValues[0]['uid'][0]; }
			if ($test != $this->data['account_lid'])
			{
				ldap_delete($ds,$allValues[0]['dn']);
				unset($allValues[0]['dn']);
				while (list($key,$val) = each($allValues[0]))
				{
					/* Don't include row count and headers */
					if (gettype($key) == 'string' && $key != 'count')
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

				/* Groups */
				if ($this->data['account_type'] == 'g' && $phpgw_info['server']['ldap_group_context'] )
				{
					$dn = 'cn='.$this->data['account_lid'].','.$phpgw_info['server']['ldap_group_context'];
					$entry['cn'] = $this->data['account_lid'];
					$entry['gidnumber'] = $this->data['account_id'];
					/* $entry["objectclass"] = ''; */
					$entry['objectclass'][0] = 'top';
					$entry['objectclass'][1] = 'posixGroup';
					$members = $this->members($this->data["account_id"]);
					$entry['memberuid'] = array();
					for ($i=0;$i<count($members);$i++)
					{
						$currname = $this->id2name($members[$i]['account_id']);
						if (!$this->isin_array($currname,$entry['memberuid']))
						{
							$entry['memberuid'][] = $currname;
						}
					}
				}
				/* Accounts */
				else
				{
					$dn = 'uid='.$this->data['account_lid'].','.$phpgw_info['server']['ldap_context'];
					$entry['uidnumber']      = $this->data['account_id'];
					$entry['cn'] = sprintf("%s %s", $this->data['firstname'], $this->data['lastname']);
					$entry['uid']       = $this->data['account_lid'];
					$entry['givenname']      = $this->data['firstname'];
					$entry['sn']             = $this->data['lastname'];
					$entry['objectclass']    = '';
					$entry['objectclass'][0] = 'top';
					$entry['objectclass'][1] = 'person';
					$entry['objectclass'][2] = 'organizationalPerson';
					$entry['objectclass'][3] = 'inetOrgPerson';
					/* $entry['objectclass'][4] = 'account'; Causes problems with some LDAP servers */
					$entry['objectclass'][4] = 'posixAccount';
					$entry['objectclass'][5] = 'shadowAccount';
					$entry['objectclass'][6] = 'phpgwAccount';

					if ($phpgw_info['server']['ldap_extra_attributes'])
					{
						$entry['homedirectory'] = $this->data['homedirectory'];
						$entry['loginshell']    = $this->data['loginshell'];
					}
				}
				/* print_r($entry); exit;*/
				ldap_add($ds, $dn, $entry);
			}
			/* Normal behavior for save_repository */
			else
			{
				if ($this->data['account_type'] == 'g' && $phpgw_info['server']['ldap_group_context'] )
				{
					$members = $this->members($this->data['account_id']);
					$entry['memberuid'] = array();
					for ($i=0;$i<count($members);$i++)
					{
						$currname = $this->id2name($members[$i]['account_id']);
						if (!$this->isin_array($currname,$entry['memberuid']))
						{
							$entry['memberuid'][] = $currname;
						}
					}
					unset($entry['givenname']);
					unset($entry['sn']);
				}
				while (list($key,$val) = each($entry))
				{
					$tmpentry = '';
					if(is_array($val))
					{
						$tmpentry[$key] = $val;
					}
					else
					{
						$tmpentry[$key] = trim($val); /* must trim! */
					}
					
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
							ldap_modify($ds, $allValues[0]['dn'], $tmpentry);
						}
					}
				}
			}
		}

		function isin_array($needle,$haystack=array()) 
		{ 
			for($i=0;$i<count($haystack) && $haystack[$i] !=$needle;$i++); 
				return ($i!=count($haystack)); 
		}

		function delete($accountid = '')
		{
			global $phpgw, $phpgw_info;

			$account_id = get_account_id($accountid);
			$account_lid = $this->id2name($account_id);
			$ds = $phpgw->common->ldapConnect();
			$sri = ldap_search($ds, $phpgw_info['server']['ldap_context'], 'uid='.$account_lid);
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
			global $phpgw,$phpgw_info;

			if ($offset)
			{
				$limitclause = '';//$phpgw->db->limit($start,$offset);
			}
			elseif ($start && !$offset)
			{
				$limitclause = '';//$phpgw->db->limit($start);
			}

			if (! $sort)
			{
				$sort = '';//"desc";
			}

			if ($order)
			{
				$orderclause = '';//"order by $order $sort";
			}
			else
			{
				$orderclause = '';//"order by account_lid,account_lastname,account_firstname asc";
			}

			$ds = $phpgw->common->ldapConnect();

			if ($_type == 'both' || $_type == 'accounts')
			{
				$sri = ldap_search($ds, $phpgw_info["server"]["ldap_context"], '(&(uidnumber=*)(phpgwaccounttype=u))');
				$allValues = ldap_get_entries($ds, $sri);
				while (list($null,$allVals) = @each($allValues))
				{
					$test = $allVals['uid'][0];
					if (!$phpgw_info['server']['global_denied_users'][$test])
					{
						$accounts[] = Array(
							'account_id'        => $allVals['uidnumber'][0],
							'account_lid'       => $allVals['uid'][0],
							'account_type'      => $allVals['phpgwaccounttype'],
							'account_firstname' => $allVals['givenname'][0],
							'account_lastname'  => $allVals['sn'][0],
							'account_status'    => $allVals['phpgwaccountstatus'][0]
						);
					}
				}
			}
			elseif ($_type == 'both' || $_type == 'groups')
			{
				$sri = ldap_search($ds, $phpgw_info['server']['ldap_group_context'], '(|(gidnumber=*)(phpgwaccounttype=g))');
				$allValues = ldap_get_entries($ds, $sri);
				while (list($null,$allVals) = @each($allValues))
				{
					$test = $allVals['cn'][0];
					if (!$phpgw_info['server']['global_denied_groups'][$test])
					{
						$accounts[] = Array(
							'account_id'        => $allVals['gidnumber'][0],
							'account_lid'       => $allVals['cn'][0],
							'account_type'      => $allVals['phpgwaccounttype'],
							'account_firstname' => $allVals['givenname'][0],
							'account_lastname'  => $allVals['sn'][0],
							'account_status'    => $allVals['phpgwaccountstatus'][0]
						);
					}
				}
			}
			$this->total = count($accounts);
			return $accounts;
		}

		function name2id($account_lid)
		{
			global $phpgw, $phpgw_info;

			$ds = $phpgw->common->ldapConnect();
			$sri = ldap_search($ds, $phpgw_info['server']['ldap_context'], "uid=$account_lid");
			$allValues = ldap_get_entries($ds, $sri);

			if ($allValues[0]['uidnumber'][0])
			{
				return $allValues[0]['uidnumber'][0];
			}		

			$sri = ldap_search($ds, $phpgw_info['server']['ldap_group_context'], "cn=$account_id");
			$allValues = ldap_get_entries($ds, $sri);

			if ($allValues[0]['gidnumber'][0])
			{
				return $allValues[0]['gidnumber'][0];
			}

			return False;
		}

		function id2name($account_id)
		{
			global $phpgw, $phpgw_info;

			$ds = $phpgw->common->ldapConnect();
			$sri = ldap_search($ds, $phpgw_info['server']['ldap_context'], "uidnumber=$account_id");
			$allValues = ldap_get_entries($ds, $sri);

			if ($allValues[0]['uid'][0])
			{
				return $allValues[0]['uid'][0];
			}

			$sri = ldap_search($ds, $phpgw_info['server']['ldap_group_context'], "gidnumber=$account_id");
			$allValues = ldap_get_entries($ds, $sri);

			if ($allValues[0]['uid'][0])
			{
				return $allValues[0]['uid'][0];
			}

			return False;
		}

		function get_type($accountid = '')
		{
			global $phpgw, $phpgw_info;

			$account_id = get_account_id($accountid);
			$ds = $phpgw->common->ldapConnect();

			$sri = ldap_search($ds, $phpgw_info['server']['ldap_context'], "uid=$account_id");
			$allValues = ldap_get_entries($ds, $sri);

			if ($allValues[0]['phpgwaccounttype'][0])
			{
				return $allValues[0]['phpgwaccounttype'][0];
			}

			$allValues = array();

			$sri = ldap_search($ds, $phpgw_info['server']['ldap_group_context'], "cn=$account_id");
			$allValues = ldap_get_entries($ds, $sri);

			if ($allValues[0]['phpgwaccounttype'][0])
			{
				return $allValues[0]['phpgwaccounttype'][0];
			}

			return False;
		}

		function exists($account_lid)
		{
			global $phpgw, $phpgw_info;

			if(gettype($account_lid) == 'integer')
			{
				$account_id = $account_lid;
				settype($account_lid,'string');
				$account_lid = $this->id2name($account_id);
				$searchlid = 0;
			}
			else
			{
				$searchlid = 1;
				$account_id = $this->name2id($account_lid);
			}

			$ds = $phpgw->common->ldapConnect();
			$acct_type = $this->acct_type;

			if ($acct_type == 'g' && $phpgw_info['server']['ldap_group_context'])
			{
				if($searchlid)
				{
					/* echo '<br>searching LDAP groups for lid: '.$account_lid; */
					$sri = ldap_search($ds, $phpgw_info['server']['ldap_group_context'], 'cn='.$account_lid);
				}
				else
				{
					/* echo '<br>searching LDAP groups for id: '.$account_id; */
					$sri = ldap_search($ds, $phpgw_info['server']['ldap_group_context'], 'gidnumber='.$account_id);
				}
			}
			else
			{
				if($searchlid)
				{
					/* echo '<br>searching LDAP accounts for lid: '.$account_lid; */
					$sri = ldap_search($ds, $phpgw_info['server']['ldap_context'], 'uid='.$account_lid);
				}
				else
				{
					/* echo '<br>searching LDAP accounts for id: '.$account_id; */
					$sri = ldap_search($ds, $phpgw_info['server']['ldap_context'], 'uidnumber='.$account_id);
				}
			}
			$allValues   = ldap_get_entries($ds, $sri);

			if ($allValues[0]['dn'])
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
			global $phpgw_info, $phpgw;

			$ds = $phpgw->common->ldapConnect();
			$this->acct_type = $account_type;

			/* echo '<br>in create for account_lid: "'.$account_lid.'"'; */
			if (empty($account_info['id']) || !$account_info['id'])
			{
				$account_id = $this->get_nextid();
				/* echo '<br>using'.$account_id;exit; */
			}
			$entry['userpasswd']                = $account_info['account_passwd'];
//			$entry['phpgwaccountlastlogin']     = $account_info['lastlogin'];
//			$entry['phpgwaccountlastloginfrom'] = $account_info['lastloginfrom'];
//			$entry['phpgwlastpasswdchange']     = $account_info['lastpasswd_change'];
//			$entry['phpgwaccountstatus']        = $account_info['account_status'];
			$entry['phpgwaccounttype']          = $account_info['account_type'];
			$entry['phpgwaccountexpires']       = $account_info['account_expires'];

			if ($account_type == 'g')
			{
				$sri = ldap_search($ds, $phpgw_info['server']['ldap_group_context'], 'cn=' . $account_info['account_lid']);
			}
			else
			{
				$sri = ldap_search($ds, $phpgw_info['server']['ldap_context'], 'uid=' . $account_info['account_lid']);
			}
			$allValues = ldap_get_entries($ds, $sri);

			if ($phpgw_info['server']['ldap_extra_attributes'] && $account_info['account_type'] != 'g')
			{
				if ($account_home)
				{
					$entry['homedirectory'] = $account_info['homedirectory'];
				}
				else
				{
					$entry['homedirectory'] = $phpgw_info['server']['ldap_account_home'].SEP.$account_info['account_lid'];
				}

				if ($account_shell)
				{
					$entry['loginshell'] = $account_info['loginshell'];
				}
				else
				{
					$entry['loginshell'] = $phpgw_info['server']['ldap_account_shell'];
				}
			}

			if ($allValues[0]['dn'])
			{
				/* This should keep the password from being overwritten here on ldap import */
				unset($entry['userpassword']);
				$entry['gidnumber'] = $account_id;

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
							ldap_mod_add($ds, $allValues[0]["dn"], $tmpentry);
						}
						else
						{
							/* attribute was in LDAP, modify it */
							ldap_modify($ds, $allValues[0]["dn"], $tmpentry);
						}
					}
				}

				if ($account_type == 'g')
				{
					$tmpentry['objectclass'][0] = 'top';
					$tmpentry['objectclass'][1] = 'posixGroup';
				}
				else
				{
					$tmpentry['uidnumber']      = $account_id;
					$tmpentry['objectclass'][0] = 'top';
					$tmpentry['objectclass'][1] = 'person';
					$tmpentry['objectclass'][2] = 'organizationalPerson';
					$tmpentry['objectclass'][3] = 'inetOrgPerson';
					$tmpentry['userpassword']   = $phpgw->common->encrypt_password($account_info['account_passwd']);
					/* $tmpentry['objectclass'][4] = 'account'; Causes problems with some LDAP servers */
					$tmpentry['objectclass'][4] = 'posixAccount';
					$tmpentry['objectclass'][5] = 'shadowAccount';
					$tmpentry['objectclass'][6] = 'phpgwAccount';
					$tmpentry['phpgwaccountstatus']    = $account_info['account_status'];
					$tmpentry['phpgwaccounttype']      = $account_info['account_type'];
					$tmpentry['phpgwaccountexpires']          = $account_info['account_expires'];
				}
				ldap_modify($ds, $allValues[0]["dn"], $tmpentry);
			}
			else
			{
				/* Not already there, we will add it */
				if ($account_type == "g")
				{
					$dn = 'cn='.$account_info['account_lid'] . ',' . $phpgw_info['server']['ldap_group_context'];
					unset($entry['homedirectory']);
					unset($entry['loginshell']);
					$entry['objectclass'][0] = 'top';
					$entry['objectclass'][1] = 'posixGroup';
					$entry['cn']             = $account_info['account_lid'];
					$entry['gidnumber']      = $account_id;
					$entry['userpassword']   = $phpgw->common->encrypt_password($account_info['account_passwd']);
					$entry['description']    = 'phpgw-created group';
				}
				else
				{
					$dn = 'uid=' . $account_info['account_lid'] . ',' . $phpgw_info['server']['ldap_context'];
					$entry['cn']        = sprintf("%s %s", $account_info['account_firstname'], $account_info['account_lastname']);
					$entry['sn']        = $account_info['account_lastname'];
					$entry['givenname'] = $account_info['account_firstname'];
					$entry['uid']       = $account_info['account_lid'];
					$entry['uidnumber'] = $account_id;
					$entry['uidnumber'] = $phpgw->common->encrypt_password($account_info['account_passwd']);
					if ($phpgw_info['server']['ldap_group_id'])
					{
						$entry['gidnumber'] = $phpgw_info['server']['ldap_group_id'];
					}
					else
					{
						$entry['gidnumber'] = $account_id;
					}
					$entry['userpassword']   = $phpgw->common->encrypt_password($account_info['account_passwd']);
					$entry['objectclass'][0] = 'top';
					$entry['objectclass'][1] = 'person';
					$entry['objectclass'][2] = 'organizationalPerson';
					$entry['objectclass'][3] = 'inetOrgPerson';
					/* $entry['objectclass'][4] = 'account'; Causes problems with some LDAP servers */
					$entry['objectclass'][4] = 'posixAccount';
					$entry['objectclass'][5] = 'shadowAccount';
					$entry['objectclass'][5] = 'phpgwAccount';
				}

				ldap_add($ds, $dn, $entry);
			}
			/* print ldap_error($ds); */
		}

		function auto_add($accountname, $passwd, $default_prefs = False, $default_acls = False, $expiredate = 0, $account_status = 'A')
		{
			return False;

			global $phpgw, $phpgw_info;

			if (! $expiredate)
			{
				/* expire in 30 days by default */
				$expiredate = time() + ( ( 60 * 60 ) * (30 * 24) );
			}

			$acct_info = array(
				'account_lid'       => $accountname,
				'account_type'      => 'u',
				'account_passwd'    => $passwd,
				'account_firstname' => 'New',
				'account_lastname'  => 'User',
				'account_status'    => $account_status,
				'account_expires'   => mktime(2,0,0,date('n',$expiredate), intval(date('d',$expiredate)), date('Y',$expiredate))
			);
			$this->create($acct_info);
			$accountid = $this->name2id($accountname);

			$this->db->transaction_begin();
			if ($default_prefs == False)
			{
				$default_prefs = 'a:5:{s:6:"common";a:10:{s:9:"maxmatchs";s:2:"15";s:12:"template_set";s:8:"verdilak";s:5:"theme";s:6:"purple";s:13:"navbar_format";s:5:"icons";s:9:"tz_offset";N;s:10:"dateformat";s:5:"m/d/Y";s:10:"timeformat";s:2:"12";s:4:"lang";s:2:"en";s:11:"default_app";N;s:8:"currency";s:1:"$";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}:s:8:"calendar";a:4:{s:13:"workdaystarts";s:1:"7";s:11:"workdayends";s:2:"15";s:13:"weekdaystarts";s:6:"Monday";s:15:"defaultcalendar";s:9:"month.php";}}';
				$this->db->query("insert into phpgw_preferences (preference_owner, preference_value) values ('".$accountid."', '$default_prefs')");
			}

			if ($default_acls == False)
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

		function getDNforID($_accountid = '')
		{
			global $phpgw;

			$_account_id = get_account_id($_accountid);

			$ds = $phpgw->common->ldapConnect();

			$sri = ldap_search($ds, $phpgw_info['server']['ldap_context'], "uidnumber=$_account_id");
			$allValues = ldap_get_entries($ds, $sri);

			return $allValues[0]['dn'];
		}
	}
