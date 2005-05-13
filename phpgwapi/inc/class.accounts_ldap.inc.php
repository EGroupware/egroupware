<?php
	/**************************************************************************\
	* eGroupWare API - Accounts manager for LDAP                               *
	* Written by Joseph Engo <jengo@phpgroupware.org>                          *
	*        and Lars Kneschke <lkneschke@phpgw.de>                            *
	*        and Miles Lott <milos@groupwhere.org>                             *
	*        and Bettina Gille <ceb@phpgroupware.org>                          *
	* View and manipulate account records using LDAP                           *
	* Copyright (C) 2000 - 2002 Joseph Engo, Lars Kneschke                     *
	* Copyright (C) 2003 Lars Kneschke, Bettina Gille                          *
	* ------------------------------------------------------------------------ *
	* This library is part of the eGroupWare API                               *
	* http://www.egroupware.org                                                *
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
		var $total;

		function accounts_()
		{
			$this->ds = $GLOBALS['egw']->common->ldapConnect();
			if(!@is_object($GLOBALS['egw']->translation))
			{
				$GLOBALS['egw']->translation =& CreateObject('phpgwapi.translation');
			}
			$this->user_context  = $GLOBALS['egw_info']['server']['ldap_context'];
			$this->group_context = $GLOBALS['egw_info']['server']['ldap_group_context'];
		}

		function read_repository()
		{
			$acct_type = $this->get_type($this->account_id);

			/* search the dn for the given uid */
			if(($acct_type == 'g') && $this->group_context)
			{
				$sri = ldap_search($this->ds, $this->group_context, 'gidnumber=' . (int)$this->account_id);
			}
			else
			{
				$sri = ldap_search($this->ds, $this->user_context, 'uidnumber=' . (int)$this->account_id);
			}
			$allValues = ldap_get_entries($this->ds, $sri);

			/* Now dump it into the array; take first entry found */
			if($acct_type =='g')
			{
				$this->data['account_id']   = $allValues[0]['gidnumber'][0];
				$this->data['account_lid']  = $allValues[0]['cn'][0];
				$this->data['firstname']    = $GLOBALS['egw']->translation->convert($allValues[0]['cn'][0],'utf-8');
				$this->data['lastname']     = lang('Group');
			}
			else
			{
				$this->data['account_id']  = $allValues[0]['uidnumber'][0];
				$this->data['account_primary_group'] = $allValues[0]['gidnumber'][0];
				$this->data['account_lid'] = $allValues[0]['uid'][0];
				$this->data['firstname']   = $GLOBALS['egw']->translation->convert($allValues[0]['givenname'][0],'utf-8');
				$this->data['lastname']    = $GLOBALS['egw']->translation->convert($allValues[0]['sn'][0],'utf-8');
				if(isset($allValues[0]['mail'][0]))
				{
					$this->data['email'] = $allValues[0]['mail'][0];
				}
			}
			$this->data['account_dn']  = $allValues[0]['dn'];
			$this->data['fullname']    = $GLOBALS['egw']->translation->convert($allValues[0]['cn'][0],'utf-8');

			if ($GLOBALS['egw_info']['server']['ldap_extra_attributes'])
			{
				$this->data['homedirectory']  = $allValues[0]['homedirectory'][0];
				$this->data['loginshell'] = $allValues[0]['loginshell'][0];
			}

			$this->data['lastlogin']         = $allValues[0]['phpgwaccountlastlogin'][0];
			$this->data['lastloginfrom']     = $allValues[0]['phpgwaccountlastloginfrom'][0];
			$this->data['lastpasswd_change'] = @$allValues[0]['phpgwlastpasswdchange'][0];
			$this->data['status']            = trim($allValues[0]['phpgwaccountstatus'][0]);
			$this->data['type']              = $allValues[0]['phpgwaccounttype'][0];
			$this->data['expires']           = $allValues[0]['phpgwaccountexpires'][0];

			return $this->data;
		}

		function save_repository()
		{
			#_debug_array($this->data);
			$acct_type = $this->get_type($this->account_id);

			/* search the dn for the given u/gidnumber */
			if(($acct_type == 'g') && $this->group_context)
			{
				$sri = ldap_search($this->ds, $this->group_context, 'gidnumber=' . (int)$this->account_id);
			}
			else
			{
				$sri = ldap_search($this->ds, $this->user_context, 'uidnumber=' . (int)$this->account_id);
			}
			$allValues = ldap_get_entries($this->ds, $sri);

			$this->data['account_type'] = $allValues[0]['phpgwaccounttype'][0];

			if($acct_type == 'u')
			{
				// data for posixaccount
				$newData['cn'] = $GLOBALS['egw']->translation->convert(sprintf("%s %s",
					$this->data['firstname'],
					$this->data['lastname']),$GLOBALS['egw']->translation->charset(),'utf-8'
				);
				$newData['uid'] = $GLOBALS['egw']->translation->convert(
					$this->data['account_lid'],
					$GLOBALS['egw']->translation->charset(),'utf-8'
				);
				if($this->data['lastname'])
				{
					$newData['sn'] = $GLOBALS['egw']->translation->convert(
						$this->data['lastname'],
						$GLOBALS['egw']->translation->charset(),'utf-8'
					);
				}

				if($this->data['firstname'])
				{
					$newData['givenname'] = $GLOBALS['egw']->translation->convert(
						$this->data['firstname'],
						$GLOBALS['egw']->translation->charset(),'utf-8'
					);
				}
				if ($GLOBALS['egw_info']['server']['ldap_extra_attributes'])
				{
					$newData['homedirectory'] = $this->data['homedirectory'];
					$newData['loginshell']    = $this->data['loginshell'];
				}
				else
				{
					// the posixaccount schema requires this
					$entry['homedirectory'] = '/home/'.$this->data['account_lid'];
					$entry['loginshell']    = '/bin/false';
				}
				if($this->data['account_primary_group'])
				{
					$newData['gidnumber'] = $this->data['account_primary_group'];
				}
				if($this->data['lastlogin'])
				{
					$newData['phpgwaccountlastlogin'] = $this->data['lastlogin'];
				}
				if($this->data['lastloginfrom'])
				{
					$newData['phpgwaccountlastloginfrom'] = $this->data['lastloginfrom'];
				}
				if($this->data['lastpasswd_change'])
				{
					$newData['phpgwlastpasswdchange'] = $this->data['lastpasswd_change'];
				}
				if($this->data['status'])
				{
					$newData['phpgwaccountstatus'] = $this->data['status'];
				}
				else
				{
					$newData['phpgwaccountstatus'] = array();
				}
				if($this->data['expires'])
				{
					$newData['phpgwaccountexpires'] = $this->data['expires'];
				}
				if($this->data['email'])
				{
					$newData['mail'] = $this->data['email'];
				}

				$newAccountID = $newData['uid'];
				$oldAccountID = $newData['uid'];
			}
			else
			{
				// data for posixgroup
				$newData['cn'] = $GLOBALS['egw']->translation->convert(
					$this->data['account_lid'],
					$GLOBALS['egw']->translation->charset(), 'utf-8'
				);
				$newData['gidnumber'] = $this->account_id;
				$newGroupID = $newData['cn'];
				$oldGroupID = $newData['cn'];
			}
			if($this->data['account_type'])
			{
				$newData['phpgwaccounttype'] = $this->data['account_type'];
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
			if($GLOBALS['egw']->translation->convert($test,'utf-8') != $this->data['account_lid'])
			{
				$oldData = $allValues[0];
				$oldDN   = $oldData['dn'];
				// remove all unneeded fields
				unset($oldData['dn']);
				unset($oldData['count']);
				foreach($oldData as $key => $value)
				{
					if(is_numeric($key))
					{
						// remove the key, its no ldap key
						unset($oldData[$key]);
					}
					else
					{
						// remove the count key
						if($oldData[$key]['count'] == 1)
						{
							$oldData[$key] = $value[0];
						}
						else
						{
							unset($oldData[$key]['count']);
						}
					}
				}

				$oldAccountID = $oldData['uid'];
				$oldGroupID   = $oldData['cn'];

				// merge the old data with the new one
				$newData = array_merge($oldData, $newData);

				/* Groups */
				if($this->data['account_type'] == 'g' && $this->group_context )
				{
					$newDN = 'cn='.$this->data['account_lid'].','.$this->group_context;
					$members = $this->member($this->account_id);
					$newData['memberuid'] = array();
					for($i=0;$i<count($members);$i++)
					{
						$currname = $this->id2name($members[$i]['account_id']);
						if(!in_array($currname,$entry['memberuid']))
						{
							$newData['memberuid'][] = $currname;
						}
					}
				}
				/* Accounts */
				else
				{
					$newDN = 'uid='.$this->data['account_lid'].','.$this->user_context;
				}
				// delete the old account
				ldap_delete($this->ds,$oldDN);

				// add the new account
				#_debug_array($newData);
				ldap_add($this->ds, $newDN, $newData);
			}
			/* Normal behavior for save_repository
			   update Account */
			else
			{
				// add the list group members
				if($this->data['account_type'] == 'g' && $this->group_context )
				{
					$members = $this->member($this->account_id);
					#_debug_array($members);
					$newData['memberuid'] = array();
					for($i=0;$i<count($members);$i++)
					{
						$currname = $this->id2name($members[$i]['account_id']);
						if(!in_array($currname,$newData['memberuid']))
						{
							$newData['memberuid'][] = $currname;
						}
					}
				}
				// modify the DN
				ldap_modify($this->ds, $allValues[0]['dn'], $newData);
			}

			if ($this->data['account_type'] == 'u')
			{
				// lets check for groups, the user needs to be removed

				// first lets search for the groups, the user is currently member of
				// and from which he needs to be removed
				$filter    = "(&(objectclass=posixgroup)(memberuid=" . (int)$oldAccountID . "))";
				$justThese = array('memberuid','gidnumber');
				$sri = ldap_search($this->ds, $this->group_context, $filter, $justThese);
				if($sri)
				{
					$allValues = ldap_get_entries($this->ds, $sri);
					if($allValues['count'] > 0)
					{
						unset($allValues['count']);
						foreach($allValues as $key)
						{
							#_debug_array($key);
							#_debug_array($this->data['account_groups']);
							// delete the old accountid from any group
							if($newAccountID != $oldAccountID)
							{
								$dn = $key['dn'];
								$newData = array();
								$newData['memberuid'] = $key['memberuid'];
								unset($newData['memberuid']['count']);
								// remove the uid from memberuid
								$newData['memberuid'] = array_flip($newData['memberuid']);
								unset($newData['memberuid'][$oldAccountID]);
								# $newData['memberuid'] = array_values(sort(array_flip($newData['memberuid'])));
								$newData['memberuid'] = array_values(array_flip($newData['memberuid']));
								ldap_mod_replace($this->ds, $dn, $newData);
								#print ldap_error($this->ds);
							}
							else
							{
								if(!in_array($key['gidnumber'][0],$this->data['account_groups']))
								{
									$dn = $key['dn'];
									$newData = array();
									$newData['memberuid'] = $key['memberuid'];
									unset($newData['memberuid']['count']);
									// remove the uid from memberuid
									$newData['memberuid'] = array_flip($newData['memberuid']);
									unset($newData['memberuid'][$oldAccountID]);
									$newData['memberuid'] = array_values(sort(array_flip($newData['memberuid'])));
									ldap_mod_replace($this->ds, $dn, $newData);
									#print ldap_error($this->ds);
								}
							}
						}
					}
				}

				// lets check group the user needs to be added
				foreach($this->data['account_groups'] as $key => $value)
				{
					// search for the group
					$filter    = 'gidnumber=' . (int)$value;
					$justThese = array('memberuid');
					$sri = ldap_search($this->ds, $this->group_context, $filter, $justThese);
					if($sri)
					{
						$allValues = ldap_get_entries($this->ds, $sri);
						// if the user is not member of this group, add him
						if(is_array($allValues[0]['memberuid']))
						{
							// this group has already some members
							if(!in_array($newData['uid'],$allValues[0]['memberuid']))
							{
								$dn = $allValues[0]['dn'];
								$newData = array();
								$newData['memberuid'] = $allValues[0]['memberuid'];
								unset($newData['memberuid']['count']);
								$newData['memberuid'][] = $newAccountID;
								$newData['memberuid'] = array_values(array_unique($newData['memberuid']));
								ldap_mod_replace($this->ds, $dn, $newData);
							}
						}
						else
						{
							// this group has no members
							$dn = $allValues[0]['dn'];
							$newData = array();
							$newData['memberuid'][] = $newAccountID;
							ldap_mod_replace($this->ds, $dn, $newData);
						}
					}
				}
			}
		}

		function delete($accountid = '')
		{
			$account_id = get_account_id($accountid);
			$account_lid = $this->id2name((int)$account_id);

			$filter = 'gidnumber=' . (int)$account_id;
			$sri = ldap_search($this->ds, $this->group_context, $filter);
			if($sri)
			{
				$allValues = ldap_get_entries($this->ds, $sri);
			}

			if(!$allValues[0]['dn'])
			{
				$sri = ldap_search($this->ds, $this->user_context, 'uid=' . (string)$account_lid);
				if($sri)
				{
					$allValues = ldap_get_entries($this->ds, $sri);
					$accountID = $allValues['0']['uid'][0];
					$wasAccount = True;
				}
			}

			if ($allValues[0]['dn'])
			{
				$del = ldap_delete($this->ds, $allValues[0]['dn']);
			}

			if($wasAccount)
			{
				// remove the user from any group he is member of
				$filter    = "(&(objectclass=posixgroup)(memberuid=" . (int)$accountID . "))";
				$justThese = array('memberuid','gidnumber');
				$sri = ldap_search($this->ds, $this->group_context, $filter, $justThese);
				if($sri)
				{
					$allValues = ldap_get_entries($this->ds, $sri);
					if($allValues['count'] > 0)
					{
						unset($allValues['count']);
						foreach($allValues as $key)
						{
							$dn = $key['dn'];
							$newData = array();
							$newData['memberuid'] = $key['memberuid'];
							unset($newData['memberuid']['count']);
							// remove the uid from memberuid
							$newData['memberuid'] = array_flip($newData['memberuid']);
							unset($newData['memberuid'][$accountID]);
							$newData['memberuid'] = array_unique(array_flip($newData['memberuid']));
							ldap_mod_replace($this->ds, $dn, $newData);
						}
					}
				}
			}
		}

		function get_list($_type='both', $start = '',$sort = '', $order = '', $query = '', $offset = '',$query_type='')
		{
			//print "\$_type=$_type, \$start=$start , \$sort=$sort, \$order=$order, \$query=$query, \$offset=$offset, \$query_type=$query_type<br>";
			$query = strtolower($query);

			if($_type == 'accounts' || $_type == 'both')
			{
				$filter = "(&(uidnumber=*)(phpgwaccounttype=u)";
				if (!empty($query) && $query != '*')
				{
					switch($query_type)
					{
						case 'all':
						default:
							$query = '*'.$query;
							// fall-through
						case 'start':
							$query .= '*';
							// fall-through
						case 'exact':
							$filter .= "(|(uid=$query)(sn=$query)(cn=$query)(givenname=$query)(mail=$query))";
							break;
						case 'firstname':
						case 'lastname':
						case 'lid':
						case 'email':
							$to_ldap = array(
								'firstname' => 'givenname',
								'lastname'  => 'sn',
								'lid'       => 'uid',
								'email'     => 'mail',
							);
							$filter .= '('.$to_ldap[$query_type].'=*'.$query.'*)';
							break;
					}
				}
				$filter .= ')';

				$sri = ldap_search($this->ds, $this->user_context, $filter);
				$allValues = ldap_get_entries($this->ds, $sri);
				while (list($null,$allVals) = @each($allValues))
				{
					settype($allVals,'array');
					$test = @$allVals['uid'][0];
					if (!$GLOBALS['egw_info']['server']['global_denied_users'][$test] && $allVals['uid'][0])
					{
						$accounts[] = Array(
							'account_id'        => $allVals['uidnumber'][0],
							'account_lid'       => $allVals['uid'][0],
							'account_type'      => $allVals['phpgwaccounttype'][0],
							'account_firstname' => $GLOBALS['egw']->translation->convert($allVals['givenname'][0],'utf-8'),
							'account_lastname'  => $GLOBALS['egw']->translation->convert($allVals['sn'][0],'utf-8'),
							'account_status'    => $allVals['phpgwaccountstatus'][0],
							'account_email'     => $allVals['mail'][0],
						);
					}
				}
			}
			if ($_type == 'groups' || $_type == 'both')
			{
				if(empty($query) || $query == '*')
				{
					$filter = '(&(gidnumber=*)(phpgwaccounttype=g))';
				}
				else
				{
					$filter = "(&(gidnumber=*)(phpgwaccounttype=g)(|(uid=*$query*)(sn=*$query*)(cn=*$query*)(givenname=*$query*)))";
				}
				$sri = ldap_search($this->ds, $this->group_context, $filter);
				$allValues = ldap_get_entries($this->ds, $sri);
				while (list($null,$allVals) = @each($allValues))
				{
					settype($allVals,'array');
					$test = $allVals['cn'][0];
					if (!$GLOBALS['egw_info']['server']['global_denied_groups'][$test] && $allVals['cn'][0])
					{
						$accounts[] = Array(
							'account_id'        => $allVals['gidnumber'][0],
							'account_lid'       => $allVals['cn'][0],
							'account_type'      => $allVals['phpgwaccounttype'][0],
							'account_firstname' => $GLOBALS['egw']->translation->convert($allVals['givenname'][0],'utf-8'),
							'account_lastname'  => $GLOBALS['egw']->translation->convert($allVals['sn'][0],'utf-8'),
							'account_status'    => $allVals['phpgwaccountstatus'][0],
							'account_email'     => $allVals['mail'][0],
						);
					}
				}
			}
			// sort the array
			$arrayFunctions =& CreateObject('phpgwapi.arrayfunctions');
			if(empty($order))
			{
				$order = 'account_lid';
			}
			$sortedAccounts = $arrayFunctions->arfsort($accounts,explode(',',$order),$sort);
			$this->total = count($accounts);

			// return only the wanted accounts

			if (is_array($sortedAccounts))
			{
				reset($sortedAccounts);
				if(is_int($start) && is_int($offset))
				{
					return array_slice($sortedAccounts, $start, $offset);
				}
				elseif(is_int($start))
				{
					return array_slice($sortedAccounts, $start, $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs']);
				}
				else
				{
					return $sortedAccounts;
				}
			}
			return False;
		}

		function name2id($name,$which='account_lid')
		{
			if ($which == 'account_lid')	// groups only support account_lid
			{
				$sri = ldap_search($this->ds, $this->group_context, '(&(cn=' . (string)$name . ')(phpgwaccounttype=g))');
				$allValues = ldap_get_entries($this->ds, $sri);
	
				if (@$allValues[0]['gidnumber'][0])
				{
					return (int)$allValues[0]['gidnumber'][0];
				}
			}
			$to_ldap = array(
				'account_lid'   => 'uid',
				'account_email' => 'mail',
			);
			if (!isset($to_ldap[$which])) return False;

			$sri = ldap_search($this->ds, $this->user_context, '(&('.$to_ldap[$which].'=' . (string)$name . ')(phpgwaccounttype=u))');

			$allValues = ldap_get_entries($this->ds, $sri);

			if (@$allValues[0]['uidnumber'][0])
			{
				return (int)$allValues[0]['uidnumber'][0];
			}

			return False;
		}

		function id2name($account_id,$which='account_lid')
		{
			if ($which == 'account_lid' || $which == 'account_type')	// groups only support account_lid and account_type
			{
				$allValues = array();
				$sri = ldap_search($this->ds, $this->group_context, '(&(gidnumber=' . (int)$account_id . ')(phpgwaccounttype=g))');
				$allValues = ldap_get_entries($this->ds, $sri);
	
				$attr = $which == 'account_lid' ? 'cn' : 'phpgwaccounttype';
				if (@$allValues[0]['cn'][0])
				{
					return $allValues[0]['cn'][0];
				}
			}
			$to_ldap = array(
				'account_lid'   => 'uid',
				'account_email' => 'mail',
				'account_firstname' => 'surname',
				'account_lastname'  => 'cn',
				'account_type'      => 'phpgwaccounttype',
			);
			if (!isset($to_ldap[$which])) return False;

			$allValues = array();
			$sri = ldap_search($this->ds, $this->user_context, '(&(uidnumber=' . (int)$account_id . ')(phpgwaccounttype=u))');
			$allValues = ldap_get_entries($this->ds, $sri);

			if (@$allValues[0][$to_ldap[$which]][0])
			{
				return $allValues[0][$to_ldap[$which]][0];
			}
			return False;
		}

		function get_type($account_id)
		{
			$allValues = array();
			$sri = ldap_search($this->ds, $this->user_context, '(&(uidnumber=' . (int)$account_id . ')(phpgwaccounttype=u))');
			$allValues = ldap_get_entries($this->ds, $sri);

			if ($allValues[0]['phpgwaccounttype'][0])
			{
				return $allValues[0]['phpgwaccounttype'][0];
			}

			$allValues = array();
			$sri = ldap_search($this->ds, $this->group_context, '(&(gidnumber=' . (int)$account_id . ')(phpgwaccounttype=g))');
			$allValues = ldap_get_entries($this->ds, $sri);

			if ($allValues[0]['phpgwaccounttype'][0])
			{
				return $allValues[0]['phpgwaccounttype'][0];
			}
			return False;
		}

		/*
		 * returns nonzero if $account exists in LDAP: 0: nowhere 1: user accounts, 2: group accounts, 3: both
		 * $account can be an account_id (LDAP: uidnumber) or an account_lid (LDAP: uid) (is determinded by ettype($account) == 'integer')
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
				$account   = (int)$account;
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

		function create($account_info,$default_prefs=True)
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

			if($account_info['account_type'] == 'g')
			{
				$sri = ldap_search($this->ds, $this->group_context, 'cn=' . (string)$account_info['account_lid']);
			}
			else
			{
				$sri = ldap_search($this->ds, $this->user_context, 'uid=' . (string)$account_info['account_lid']);
			}
			$allValues = ldap_get_entries($this->ds, $sri);

			if ($GLOBALS['egw_info']['server']['ldap_extra_attributes'] && $account_info['account_type'] != 'g')
			{
				$entry['homedirectory'] = $account_info['homedirectory'] && $account_info['homedirectory'] != $GLOBALS['egw_info']['server']['ldap_account_home'] ? $account_info['homedirectory'] : $GLOBALS['egw_info']['server']['ldap_account_home'].SEP.$account_info['account_lid'];
				$entry['loginshell'] = $account_info['loginshell'] ? $account_info['loginshell'] : $GLOBALS['egw_info']['server']['ldap_account_shell'];
			}
			elseif($account_info['account_type'] != 'g')
			{
				$entry['homedirectory'] = '/home/'.$account_info['account_lid'];
				$entry['loginshell'] = '/bin/false';
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
							ldap_mod_add($this->ds, $allValues[0]['dn'], $tmpentry);
						}
						else
						{
							/* attribute was in LDAP, modify it */
							ldap_modify($this->ds, $allValues[0]['dn'], $tmpentry);
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
					$tmpentry['userpassword']   = $GLOBALS['egw']->common->encrypt_password($account_info['account_passwd'],False);
					/* $tmpentry['objectclass'][4] = 'account'; Causes problems with some LDAP servers */
					$tmpentry['objectclass'][4] = 'posixAccount';
					$tmpentry['objectclass'][5] = 'shadowAccount';
					$tmpentry['objectclass'][6] = 'phpgwAccount';
					$tmpentry['phpgwaccountstatus']    = $account_info['account_status'];
					$tmpentry['phpgwaccounttype']      = $account_info['account_type'];
					$tmpentry['phpgwaccountexpires']   = $account_info['account_expires'];
				}
				ldap_modify($this->ds, $allValues[0]['dn'], $tmpentry);
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
					$entry['cn']             = $GLOBALS['egw']->translation->convert($account_info['account_lid'],$GLOBALS['egw']->translation->charset(),'utf-8');
					$entry['gidnumber']      = $account_id;
					#$entry['userpassword']   = $GLOBALS['egw']->common->encrypt_password($account_info['account_passwd']);
					$entry['description']    = 'eGW-created group';
				}
				else
				{
					$dn = 'uid=' . $account_info['account_lid'] . ',' . $this->user_context;

					$entry['cn'] = $GLOBALS['egw']->translation->convert(
						sprintf(
							"%s %s",
							$account_info['account_firstname'],
							$account_info['account_lastname']
						),
						$GLOBALS['egw']->translation->charset(),
						'utf-8'
					);

					$entry['sn'] = $GLOBALS['egw']->translation->convert(
						$account_info['account_lastname'] ? $account_info['account_lastname'] : 'not set',
						$GLOBALS['egw']->translation->charset(),
						'utf-8'
					);

					if($account_info['account_firstname'])
					{
						$entry['givenname'] = $GLOBALS['egw']->translation->convert(
							$account_info['account_firstname'],
							$GLOBALS['egw']->translation->charset(),
							'utf-8'
						);
					}
					if($account_info['account_email'])
					{
						$entry['mail'] = $GLOBALS['egw']->translation->convert(
							$account_info['account_email'],
							$GLOBALS['egw']->translation->charset(),
							'utf-8'
						);
					}
					$entry['uid']            = $account_info['account_lid'];
					$entry['uidnumber']      = $account_id;
					$entry['gidnumber']      = $account_info['account_primary_group'];
					$entry['userpassword']   = $GLOBALS['egw']->common->encrypt_password($account_info['account_passwd']);
					$entry['objectclass'][0] = 'top';
					$entry['objectclass'][1] = 'person';
					$entry['objectclass'][2] = 'organizationalPerson';
					$entry['objectclass'][3] = 'inetOrgPerson';
					$entry['objectclass'][4] = 'posixAccount';
					$entry['objectclass'][5] = 'shadowAccount';
					$entry['objectclass'][6] = 'phpgwAccount';
					if($account_info['account_status'])
					{
						$entry['phpgwaccountstatus']    = $account_info['account_status'];
					}
					$entry['phpgwaccounttype']      = $account_info['account_type'];
					$entry['phpgwaccountexpires']   = $account_info['account_expires'];
				}

				#_debug_array($entry);

				// stop processing if ldap_add fails
				if(!ldap_add($this->ds, $dn, $entry))
				{
					return false;
				}
			}
			// print ldap_error($this->ds);

			// lets check group the user needs to be added
			if($account_info['account_type'] == 'u')
			{
				@settype($account_info['account_groups'],'array');
				foreach($account_info['account_groups'] as $key => $value)
				{
					// search for the group
					$filter    = 'gidnumber=' . (int)$value;
					$justThese = array('memberuid');
					$sri = ldap_search($this->ds, $this->group_context, $filter, $justThese);
					if($sri)
					{
						$allValues = ldap_get_entries($this->ds, $sri);
						// if the user is not member of this group, add him
						if(is_array($allValues[0]['memberuid']))
						{
							// this group has already some members
							if(!in_array($account_info['account_lid'],$allValues[0]['memberuid']))
							{
								$dn = $allValues[0]['dn'];
								$newData = array();
								$newData['memberuid'] = $allValues[0]['memberuid'];
								unset($newData['memberuid']['count']);
								$newData['memberuid'][]	= $account_info['account_lid'];
								$newData['memberuid'] = array_unique($newData['memberuid']);
								ldap_mod_replace($this->ds, $dn, $newData);
								#print ldap_error($this->ds)."<br>";
							}
						}
						else
						{
							// this group has no members
							$dn = $allValues[0]['dn'];
							$newData = array();
							$newData['memberuid'][] = $account_info['account_lid'];
							ldap_mod_replace($this->ds, $dn, $newData);
						}
					}
				}
			}

			if($account_id && is_object($GLOBALS['egw']->preferences) && $default_prefs)
			{
				$GLOBALS['egw']->preferences->create_defaults($account_id);
			}

			return $account_id;
		}

		function auto_add($accountname, $passwd, $default_prefs = False, $default_acls = False, $expiredate = 0, $account_status = 'A')
		{
			if ($expiredate == 0)
			{
				if(isset($GLOBALS['egw_info']['server']['auto_create_expire']) == True)
				{
					if($GLOBALS['egw_info']['server']['auto_create_expire'] == 'never')
					{
						$expires = -1;
					}
					else
					{
						$expiredate = time() + $GLOBALS['egw_info']['server']['auto_create_expire'];
					}
				}
			}
			else
			{
				/* expire in 30 days by default */
				$expiredate = time() + ((60 * 60) * (30 * 24));
			}

			if ($expires != -1)
			{
				$expires = mktime(2,0,0,date('n',$expiredate), (int)date('d',$expiredate), date('Y',$expiredate));
			}

			$default_group_id  = $this->name2id($GLOBALS['egw_info']['server']['default_group_lid']);
			if (!$default_group_id)
			{
				$default_group_id = (int) $this->name2id('Default');
			}
			$primary_group = $GLOBALS['auto_create_acct']['primary_group'] &&
				$this->get_type((int)$GLOBALS['auto_create_acct']['primary_group']) == 'g' ?
				(int) $GLOBALS['auto_create_acct']['primary_group'] : $default_group_id;

			$acct_info = array(
				'account_lid'       => $accountname,
				'account_type'      => 'u',
				'account_passwd'    => $passwd,
				'account_firstname' => $GLOBALS['auto_create_acct']['firstname'] ? $GLOBALS['auto_create_acct']['firstname'] : 'New',
				'account_lastname'  => $GLOBALS['auto_create_acct']['lastname'] ? $GLOBALS['auto_create_acct']['lastname'] : 'User',
				'account_status'    => $account_status,
				'account_expires'   => $expires,
				'account_primary_group' => $primary_group,
			);

			/* attempt to set an email address */
			if (isset($GLOBALS['auto_create_acct']['email']) == True && $GLOBALS['auto_create_acct']['email'] != '')
			{
				$acct_info['account_email'] = $GLOBALS['auto_create_acct']['email'];
			}
			elseif(isset($GLOBALS['egw_info']['server']['mail_suffix']) == True && $GLOBALS['egw_info']['server']['mail_suffix'] != '')
			{
				$acct_info['account_email'] = $accountname . '@' . $GLOBALS['egw_info']['server']['mail_suffix'];
			}

			$this->db->transaction_begin();
 
			$this->create($acct_info,$default_prefs);  /* create the account */

			$accountid = $this->name2id($accountname); /* grab the account id or an error code */

			if ($accountid) /* begin account setup */
			{
				if($primary_group)
				{
					$this->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES('phpgw_group', "
						. $primary_group . ", " . $accountid . ", 1)",__LINE__,__FILE__);
				}

				/* FIXME - we are assuming the auth method is capable of password changing
				 * $this->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights)VALUES('preferences', 'changepassword', ".$accountid.", 1)",__LINE__,__FILE__);
				 */

				/* if we have an mail address set it in the uesrs' email preference */
				if (isset($GLOBALS['auto_create_acct']['email']) && $GLOBALS['auto_create_acct']['email'] != '')
				{
					$GLOBALS['egw']->acl->acl($accountid);        /* needed als preferences::save_repository calls acl */
					$GLOBALS['egw']->preferences->preferences($accountid);
					$GLOBALS['egw']->preferences->read_repository();
					$GLOBALS['egw']->preferences->add('email','address',$GLOBALS['auto_create_acct']['email']);
					$GLOBALS['egw']->preferences->save_repository();
				}
				/* use the default mail domain to set the uesrs' email preference  */
				elseif(isset($GLOBALS['egw_info']['server']['mail_suffix']) && $GLOBALS['egw_info']['server']['mail_suffix'] != '')
				{
					$GLOBALS['egw']->acl->acl($accountid);        /* needed als preferences::save_repository calls acl */
					$GLOBALS['egw']->preferences->preferences($accountid);
					$GLOBALS['egw']->preferences->read_repository();
					$GLOBALS['egw']->preferences->add('email','address', $accountname . '@' . $GLOBALS['egw_info']['server']['mail_suffix']);
					$GLOBALS['egw']->preferences->save_repository();
				}

				/* commit the new account transaction */
				$this->db->transaction_commit();

				/* does anyone know what the heck this is required for? */
				$GLOBALS['hook_values']['account_lid']	= $acct_info['account_lid'];
				$GLOBALS['hook_values']['account_id']	= $accountid;
				$GLOBALS['hook_values']['new_passwd']	= $acct_info['account_passwd'];
				$GLOBALS['hook_values']['account_status'] = $acct_info['account_status'];
				$GLOBALS['hook_values']['account_firstname'] = $acct_info['account_firstname'];
				$GLOBALS['hook_values']['account_lastname'] = $acct_info['account_lastname'];
				$GLOBALS['egw']->hooks->process($GLOBALS['hook_values']+array(
					'location' => 'addaccount',
					// at login-time only the hooks from the following apps will be called
					'order' => array('felamimail'),
				),False,True);  // called for every app now, not only enabled ones
			} /* end account setup */
			else /* if no account id abort the account creation */
			{
				$this->db->transaction_abort();
			}

			/*
			 * If we succeeded in creating the account (above), return the accountid, else,
			 * return the error value from $this->name2id($accountname)
			 */
			return $accountid;
		} /* end auto_add() */

		function get_account_name($account_id,&$lid,&$fname,&$lname)
		{
			$acct_type = $this->get_type($account_id);

			/* search the dn for the given uid */
			if(($acct_type == 'g') && $this->group_context)
			{
				$sri = ldap_search($this->ds, $this->group_context, 'gidnumber=' . (int)$account_id);
			}
			else
			{
				$sri = ldap_search($this->ds, $this->user_context, 'uidnumber=' . (int)$account_id);
			}
			$allValues = ldap_get_entries($this->ds, $sri);

			if($acct_type =='g')
			{
				$lid   = $GLOBALS['egw']->translation->convert($allValues[0]['cn'][0],'utf-8');
				$fname = $GLOBALS['egw']->translation->convert($allValues[0]['cn'][0],'utf-8');
				$lname = lang('Group');
			}
			else
			{
				$lid   = $GLOBALS['egw']->translation->convert($allValues[0]['uid'][0],'utf-8');
				$fname = $GLOBALS['egw']->translation->convert($allValues[0]['givenname'][0],'utf-8');
				$lname = $GLOBALS['egw']->translation->convert($allValues[0]['sn'][0],'utf-8');
			}
			return !empty($lid);
		}

		function getDNforID($_accountid = '')
		{
			$_account_id = get_account_id($_accountid);

			$sri = ldap_search($this->ds, $this->user_context, 'uidnumber=' . (int)$_account_id);
			$allValues = ldap_get_entries($this->ds, $sri);

			return $allValues[0]['dn'];
		}
	}
