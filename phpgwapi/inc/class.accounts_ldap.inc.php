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
		var $ds;
		var $account_id;
		var $data;
		var $user_context  = '';
		var $group_context = '';

		function accounts_()
		{
			/* This does not get called */
		}

		function read_repository()
		{
			$acct_type = $this->get_type($this->account_id);

			/* search the dn for the given uid */
			if ( ($acct_type == 'g') && $this->group_context )
			{
				$sri = ldap_search($this->ds, $this->group_context, 'gidnumber='.$this->account_id);
			}
			else
			{
				$sri = ldap_search($this->ds, $this->user_context, 'uidnumber='.$this->account_id);
			}
			$allValues = ldap_get_entries($this->ds, $sri);

			/* Now dump it into the array; take first entry found */
			if($acct_type =='g')
			{
				$this->data['account_id']   = $allValues[0]['gidnumber'][0];
				$this->data['account_lid']  = $allValues[0]['cn'][0];
				$this->data['firstname']    = $allValues[0]['cn'][0];
				$this->data['lastname']     = 'Group';
			}
			else
			{
				$this->data['account_id']   = $allValues[0]['uidnumber'][0];
				$this->data['account_lid']  = $allValues[0]['uid'][0];
				$this->data['firstname']    = $allValues[0]['givenname'][0];
				$this->data['lastname']     = $allValues[0]['sn'][0];
			}
			$this->data['account_dn']  = $allValues[0]['dn'];
			$this->data['fullname']    = $allValues[0]['cn'][0];

			if ($GLOBALS['phpgw_info']['server']['ldap_extra_attributes'])
			{
				$this->data['homedirectory']  = $allValues[0]['homedirectory'][0];
				$this->data['loginshell'] = $allValues[0]['loginshell'][0];
			}

			$this->data['lastlogin']         = $allValues[0]['phpgwaccountlastlogin'][0];
			$this->data['lastloginfrom']     = $allValues[0]['phpgwaccountlastloginfrom'][0];
			$this->data['lastpasswd_change'] = $allValues[0]['phpgwlastpasswdchange'][0];
			$this->data['status']            = trim($allValues[0]['phpgwaccountstatus'][0]);
			$this->data['type']              = $allValues[0]['phpgwaccounttype'][0];
			$this->data['expires']           = $allValues[0]['phpgwaccountexpires'][0];

			return $this->data;
		}

		function save_repository()
		{
			$acct_type = $this->get_type($this->account_id);

			/* search the dn for the given u/gidnumber */
			if ( ($acct_type == 'g') && $this->group_context )
			{
				$sri = ldap_search($this->ds, $this->group_context, 'gidnumber='.$this->account_id);
			}
			else
			{
				$sri = ldap_search($this->ds, $this->user_context, 'uidnumber='.$this->account_id);
			}
			$allValues = ldap_get_entries($this->ds, $sri);

			$this->data['account_type'] = $allValues[0]['phpgwaccounttype'][0];
			
			if($acct_type == 'u')
			{
				$entry['cn']                 = sprintf("%s %s", $this->data['firstname'], $this->data['lastname']);
			}
			else
			{
				$entry['cn'] 	              = $this->data['firstname'];
			}

			$entry['sn']                    = $this->data['lastname'];
			$entry['givenname']             = $this->data['firstname'];
			$entry['phpgwaccountlastlogin']     = $this->data['lastlogin'];
			$entry['phpgwaccountlastloginfrom'] = $this->data['lastloginfrom'];
			$entry['phpgwlastpasswdchange'] = $this->data['lastpasswd_change'];
			$entry['phpgwaccountstatus']    = $this->data['status'];
			$entry['phpgwaccounttype']      = $this->data['type'];
			$entry['phpgwaccountexpires']   = $this->data['expires'];

			if ($GLOBALS['phpgw_info']['server']['ldap_extra_attributes'])
			{
				$entry['homedirectory'] = $this->data['homedirectory'];
				$entry['loginshell']    = $this->data['loginshell'];
			}

			/*
			Changing the uid:  Need to delete and add new, since
			PHP cannot change the dn for the entry.
			*/
			if ($acct_type == 'g')
			{
				$test = $allValues[0]['cn'][0];
			}
			else
			{
				$test = $allValues[0]['uid'][0];
			}
			if ($test != $this->data['account_lid'])
			{
				ldap_delete($this->ds,$allValues[0]['dn']);
				unset($allValues[0]['dn']);
				while (list($key,$val) = each($allValues[0]))
				{
					/* Don't include row count and headers */
					if (is_string($key) && $key != 'count')
					{
						if (is_array($val))
						{
							if (count($val) == 1)
							{
								if($val[0])
								{
									$entry[$key] = $val[0];
								}
							}
							else
							{
								for ($i=0;$i<count($val);$i++)
								{
									if($val[$i])
									{
										$entry[$key][$i] = $val[$i];
									}
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
				if ($this->data['account_type'] == 'g' && $this->group_context )
				{
					$dn = 'cn='.$this->data['account_lid'].','.$this->group_context;
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
					$dn = 'uid='.$this->data['account_lid'].','.$this->user_context;
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

					if ($GLOBALS['phpgw_info']['server']['ldap_extra_attributes'])
					{
						$entry['homedirectory'] = $this->data['homedirectory'];
						$entry['loginshell']    = $this->data['loginshell'];
					}
				}
				/* print_r($entry); exit;*/
				ldap_add($this->ds, $dn, $entry);
			}
			/* Normal behavior for save_repository */
			else
			{
				if ($this->data['account_type'] == 'g' && $this->group_context )
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
							ldap_mod_add($this->ds, $allValues[0]['dn'], $tmpentry);
						}
						else
						{
							/* attribute was in LDAP, modify it */
							ldap_modify($this->ds, $allValues[0]['dn'], $tmpentry);
						}
					}
				}
				/* If status is to be set inactive, insert a space here.  This is trimmed in read_repository. */
				if (!$entry['phpgwaccountstatus'])
				{
					if (!$allValues[0]['phpgwaccountstatus'][0])
					{
						/* attribute was not in LDAP, add it */
						ldap_mod_add($this->ds, $allValues[0]['dn'], array('phpgwaccountstatus' => ' '));
					}
					else
					{
						/* attribute was in LDAP, modify it */
						ldap_modify($this->ds, $allValues[0]['dn'], array('phpgwaccountstatus' => ' '));
					}
				}
			}
		}

		function isin_array($needle,$haystack='') 
		{
			if(gettype($haystack) != 'array')
			{
				return False;
			}
			for($i=0;$i<count($haystack) && $haystack[$i] !=$needle;$i++); 
				return ($i!=count($haystack)); 
		}

		function delete($accountid = '')
		{
			$account_id = get_account_id($accountid);
			$account_lid = $this->id2name($account_id);
			
			$sri = ldap_search($this->ds, $this->group_context, 'gidnumber='.$account_id);
			$allValues = ldap_get_entries($this->ds, $sri);

			if(!$allValues[0][dn])
			{
				$sri = ldap_search($this->ds, $this->user_context, 'uid='.$account_lid);
				$allValues = ldap_get_entries($this->ds, $sri);
			}

			if ($allValues[0]['dn'])
			{
				$del = ldap_delete($this->ds, $allValues[0]['dn']);
			}
		}

		function get_list($_type='both', $start = '',$sort = '', $order = '', $query = '', $offset = '')
		{
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

			if ($_type == 'both' || $_type == 'accounts')
			{
				$sri = ldap_search($this->ds, $this->user_context, '(&(uidnumber=*)(phpgwaccounttype=u))');
				$allValues = ldap_get_entries($this->ds, $sri);
				while (list($null,$allVals) = @each($allValues))
				{
					settype($allVals,'array');
					$test = @$allVals['uid'][0];
					if (!$GLOBALS['phpgw_info']['server']['global_denied_users'][$test] && $allVals['uid'][0])
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
				$sri = ldap_search($this->ds, $this->group_context, '(&(gidnumber=*)(phpgwaccounttype=g))');
				$allValues = ldap_get_entries($this->ds, $sri);
				while (list($null,$allVals) = @each($allValues))
				{
					settype($allVals,'array');
					$test = $allVals['cn'][0];
					if (!$GLOBALS['phpgw_info']['server']['global_denied_groups'][$test] && $allVals['cn'][0])
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
			static $name_list;

			if(@isset($name_list[$account_lid]) && $name_list[$account_lid])
			{
				return $name_list[$account_lid];
			}

			$sri = ldap_search($this->ds, $this->group_context, "(&(cn=$account_lid)(phpgwaccounttype=g))");
			$allValues = ldap_get_entries($this->ds, $sri);
			
			if (@$allValues[0]['gidnumber'][0])
			{
				$name_list[$account_lid] = intval($allValues[0]['gidnumber'][0]);
			}

			$sri = ldap_search($this->ds, $this->user_context, "(&(uid=$account_lid)(phpgwaccounttype=u))");

			$allValues = ldap_get_entries($this->ds, $sri);

			if (@$allValues[0]['uidnumber'][0])
			{
				$name_list[$account_lid] = intval($allValues[0]['uidnumber'][0]);
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

			$allValues = array();
			$sri = ldap_search($this->ds, $this->group_context, "(&(gidnumber=$account_id)(phpgwaccounttype=g))");
			$allValues = ldap_get_entries($this->ds, $sri);

			if ($allValues[0]['cn'][0])
			{
				$id_list[$account_id] = $allValues[0]['cn'][0];
				return $id_list[$account_id];
			}

			$allValues = array();
			$sri = ldap_search($this->ds, $this->user_context, "(&(uidnumber=$account_id)(phpgwaccounttype=u))");
			$allValues = ldap_get_entries($this->ds, $sri);

			if ($allValues[0]['uid'][0])
			{
				$id_list[$account_id] = $allValues[0]['uid'][0];
				return $id_list[$account_id];
			}

			return $id_list[$account_id];
		}

		function get_type($accountid = '')
		{
			if (isset($this->account_type))
			{
				return $this->account_type;
			}

			static $account_type;

			$account_id = get_account_id($accountid);
			if(@isset($account_type[$account_id]) && @$account_type[$account_id])
			{
				return $account_type[$account_id];
			}
			
			$allValues = array();
			$sri = ldap_search($this->ds, $this->user_context, "(&(uidnumber=$account_id)(phpgwaccounttype=u))");
			$allValues = ldap_get_entries($this->ds, $sri);

			if ($allValues[0]['phpgwaccounttype'][0])
			{
				$allValues[0]['phpgwaccounttype'][0];
				$account_type[$account_id] = $allValues[0]['phpgwaccounttype'][0];
				return $account_type[$account_id];
			}

			$allValues = array();
			$sri = ldap_search($this->ds, $this->group_context, "(&(gidnumber=$account_id)(phpgwaccounttype=g))");
			$allValues = ldap_get_entries($this->ds, $sri);

			if ($allValues[0]['phpgwaccounttype'][0])
			{
				$account_type[$account_id] = $allValues[0]['phpgwaccounttype'][0];
				return $account_type[$account_id];
			}

			return $account_type[$account_id];
		}

		/*
		 * returns nonzero if $account exists in LDAP: 0: nowhere 1: user accounts, 2: group accounts, 3: both
		 * $account can be an account_id (LDAP: uidnumber) or an account_lid (LDAP: uid) (is determinded by gettype($account) == 'integer')
		 */
		function exists($account)
		{
			/* This sets up internal caching variables for this functon */
			static $by_id, $by_lid;
			$users  = array();
			$groups = array();

			if(is_int($account))
			{
				$ldapgroup = 'gidnumber';
				$ldapacct  = 'uidnumber';
				/* If data is cached, use it. */
				if(@isset($by_id[$account]) && @$by_id[$account])
				{
					return $by_id[$account];
				}
			}
			else
			{
				$ldapgroup = 'cn';
				$ldapacct  = 'uid';
				/* If data is cached, use it. */
				if(@isset($by_lid[$account]) && @$by_lid[$account])
				{
					return $by_lid[$account];
				}
			}

			$acct_type = $this->get_type($account);

			if ($acct_type == 'g' && $this->group_context)
			{
				$sri = ldap_search($this->ds, $this->group_context, $ldapgroup . '=' . $account);
				$groups = ldap_get_entries($this->ds, $sri);
			}
			$sri = ldap_search($this->ds, $this->user_context, $ldapacct . '=' . $account);
			$users = ldap_get_entries($this->ds, $sri);

			if ($users[0]['dn'])
			{
				$in += 1;
			}
			if ($groups[0]['dn'])
			{
				$in += 2;
			}
			/* This sets up internal caching for this function */
			if($ldapgroup == 'gidnumber')
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
			/* echo '<br>in create for account_lid: "'.$account_lid.'"'; */
			if (empty($account_info['account_id']) || !$account_info['account_id'])
			{
				$account_id = $this->get_nextid($account_info['account_type']);
				/* echo '<br>using'.$account_id;exit; */
			}
			else
			{
				$account_id = $account_info['account_id'];
			}
			$entry['userpassword']              = $account_info['account_passwd'];
			$entry['phpgwaccounttype']          = $account_info['account_type'];
			$entry['phpgwaccountexpires']       = $account_info['account_expires'];

			if ($account_info['account_type'] == 'g')
			{
				$sri = ldap_search($this->ds, $this->group_context, 'cn=' . $account_info['account_lid']);
			}
			else
			{
				$sri = ldap_search($this->ds, $this->user_context, 'uid=' . $account_info['account_lid']);
			}
			$allValues = ldap_get_entries($this->ds, $sri);

			if ($GLOBALS['phpgw_info']['server']['ldap_extra_attributes'] && $account_info['account_type'] != 'g')
			{
				$entry['homedirectory'] = $account_info['homedirectory'] && $account_info['homedirectory'] != $GLOBALS['phpgw_info']['server']['ldap_account_home'] ? $account_info['homedirectory'] : $GLOBALS['phpgw_info']['server']['ldap_account_home'].SEP.$account_info['account_lid'];
				$entry['loginshell'] = $account_info['loginshell'] ? $account_info['loginshell'] : $GLOBALS['phpgw_info']['server']['ldap_account_shell'];
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
							ldap_mod_add($this->ds, $allValues[0]["dn"], $tmpentry);
						}
						else
						{
							/* attribute was in LDAP, modify it */
							ldap_modify($this->ds, $allValues[0]["dn"], $tmpentry);
						}
					}
				}

				if ($account_info['account_type'] == 'g')
				{
					$tmpentry['objectclass'][0] = 'top';
					$tmpentry['objectclass'][1] = 'posixGroup';
					$tmpentry['objectclass'][2] = 'phpgwAccount';
				}
				else
				{
					$tmpentry['uidnumber']      = $account_id;
					$tmpentry['objectclass'][0] = 'top';
					$tmpentry['objectclass'][1] = 'person';
					$tmpentry['objectclass'][2] = 'organizationalPerson';
					$tmpentry['objectclass'][3] = 'inetOrgPerson';
					$tmpentry['userpassword']   = $GLOBALS['phpgw']->common->encrypt_password($account_info['account_passwd']);
					/* $tmpentry['objectclass'][4] = 'account'; Causes problems with some LDAP servers */
					$tmpentry['objectclass'][4] = 'posixAccount';
					$tmpentry['objectclass'][5] = 'shadowAccount';
					$tmpentry['objectclass'][6] = 'phpgwAccount';
					$tmpentry['phpgwaccountstatus']    = $account_info['account_status'];
					$tmpentry['phpgwaccounttype']      = $account_info['account_type'];
					$tmpentry['phpgwaccountexpires']   = $account_info['account_expires'];
				}
				ldap_modify($this->ds, $allValues[0]["dn"], $tmpentry);
			}
			else
			{
				/* Not already there, we will add it */
				if ($account_info['account_type'] == 'g')
				{
					$dn = 'cn='.$account_info['account_lid'] . ',' . $this->group_context;
					unset($entry['homedirectory']);
					unset($entry['loginshell']);
					$entry['objectclass'][0] = 'top';
					$entry['objectclass'][1] = 'posixGroup';
					$entry['objectclass'][2] = 'phpgwAccount';
					$entry['cn']             = $account_info['account_lid'];
					$entry['gidnumber']      = $account_id;
					$entry['userpassword']   = $GLOBALS['phpgw']->common->encrypt_password($account_info['account_passwd']);
					$entry['description']    = 'phpgw-created group';
				}
				else
				{
					$dn = 'uid=' . $account_info['account_lid'] . ',' . $this->user_context;
					$entry['cn']        = sprintf("%s %s", $account_info['account_firstname'], $account_info['account_lastname']);
					$entry['sn']        = $account_info['account_lastname'];
					$entry['givenname'] = $account_info['account_firstname'];
					$entry['uid']       = $account_info['account_lid'];
					$entry['uidnumber'] = $account_id;
					if ($GLOBALS['phpgw_info']['server']['ldap_group_id'])
					{
						$entry['gidnumber'] = $GLOBALS['phpgw_info']['server']['ldap_group_id'];
					}
					else
					{
						$entry['gidnumber'] = $account_id;
					}
					$entry['userpassword']   = $GLOBALS['phpgw']->common->encrypt_password($account_info['account_passwd']);
					$entry['objectclass'][0] = 'top';
					$entry['objectclass'][1] = 'person';
					$entry['objectclass'][2] = 'organizationalPerson';
					$entry['objectclass'][3] = 'inetOrgPerson';
					/* $entry['objectclass'][4] = 'account'; Causes problems with some LDAP servers */
					$entry['objectclass'][4] = 'posixAccount';
					$entry['objectclass'][5] = 'shadowAccount';
					$entry['objectclass'][5] = 'phpgwAccount';
				}

				/* _debug_array($entry);exit; */

				ldap_add($this->ds, $dn, $entry);
			}
			/* print ldap_error($this->ds); */
		}

		function auto_add($accountname, $passwd, $default_prefs = False, $default_acls = False, $expiredate = 0, $account_status = 'A')
		{
			return False;

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

		function get_account_name($accountid,&$lid,&$fname,&$lname)
		{
			static $account_name;
			
			$account_id = get_account_id($accountid);
			if(isset($account_name[$account_id]))
			{
				$lid = $account_name[$account_id]['lid'];
				$fname = $account_name[$account_id]['fname'];
				$lname = $account_name[$account_id]['lname'];
				return;
			}
			$acct_type = $this->get_type($account_id);

			/* search the dn for the given uid */
			if ( ($acct_type == 'g') && $this->group_context )
			{
				$sri = ldap_search($this->ds, $this->group_context, 'gidnumber='.$account_id);
			}
			else
			{
				$sri = ldap_search($this->ds, $this->user_context, 'uidnumber='.$account_id);
			}
			$allValues = ldap_get_entries($this->ds, $sri);

			if($acct_type =='g')
			{
				$account_name[$account_id]['lid']   = $allValues[0]['cn'][0];
				$account_name[$account_id]['fname'] = $allValues[0]['cn'][0];
				$account_name[$account_id]['lname'] = 'Group';
			}
			else
			{
				$account_name[$account_id]['lid']   = $allValues[0]['uid'][0];
				$account_name[$account_id]['fname'] = $allValues[0]['givenname'][0];
				$account_name[$account_id]['lname'] = $allValues[0]['sn'][0];
			}
			$lid = $account_name[$account_id]['lid'];
			$fname = $account_name[$account_id]['fname'];
			$lname = $account_name[$account_id]['lname'];
			return;
		}

		function getDNforID($_accountid = '')
		{
			$_account_id = get_account_id($_accountid);

			$sri = ldap_search($this->ds, $this->user_context, "uidnumber=$_account_id");
			$allValues = ldap_get_entries($this->ds, $sri);

			return $allValues[0]['dn'];
		}
	}
