<?php
	/**************************************************************************\
	* eGroupWare API - Accounts manager for SQL                                *
	* Written by Joseph Engo <jengo@phpgroupware.org>                          *
	*        and Dan Kuykendall <seek3r@phpgroupware.org>                      *
	*        and Bettina Gille [ceb@phpgroupware.org]                          *
	* View and manipulate account records using SQL                            *
	* Copyright (C) 2000 - 2002 Joseph Engo                                    *
	* Copyright (C) 2003 Joseph Engo, Bettina Gille                            *
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

	/*!
	 @class_start accounts
	 @abstract Class for handling user and group accounts
	*/
	class accounts_
	{
		var $db;
		var $account_id;
		var $data;
		var $total;

		function accounts_()
		{
			//copyobj($GLOBALS['phpgw']->db,$this->db);
			$this->db = is_object($GLOBALS['phpgw']->db) ? $GLOBALS['phpgw']->db : $GLOBALS['phpgw_setup']->db;
			
			$this->table = 'phpgw_accounts';
			$this->db->set_app('phpgwapi');	// to load the right table-definitions for insert, select, update, ...
		}

		function list_methods($_type='xmlrpc')
		{
			if (is_array($_type))
			{
				$_type = $_type['type'] ? $_type['type'] : $_type[0];
			}

			switch($_type)
			{
				case 'xmlrpc':
					$xml_functions = array(
						'get_list' => array(
							'function'  => 'get_list',
							'signature' => array(array(xmlrpcStruct)),
							'docstring' => lang('Returns a full list of accounts on the system.  Warning: This is return can be quite large')
						),
						'list_methods' => array(
							'function'  => 'list_methods',
							'signature' => array(array(xmlrpcStruct,xmlrpcString)),
							'docstring' => lang('Read this list of methods.')
						)
					);
					return $xml_functions;
					break;
				case 'soap':
					return $this->soap_functions;
					break;
				default:
					return array();
					break;
			}
		}

		/*!
		@function read_repository
		@abstract grabs the records from the data store
		*/
		function read_repository()
		{
			$this->db->select($this->table,'*',array('account_id'=>$this->account_id),__LINE__,__FILE__);
			$this->db->next_record();

			$this->data['userid']            = $this->db->f('account_lid');
			$this->data['account_id']        = $this->db->f('account_id');
			$this->data['account_lid']       = $this->db->f('account_lid');
			$this->data['firstname']         = $this->db->f('account_firstname');
			$this->data['lastname']          = $this->db->f('account_lastname');
			$this->data['fullname']          = $this->db->f('account_firstname') . ' ' . $this->db->f('account_lastname');
			$this->data['lastlogin']         = $this->db->f('account_lastlogin');
			$this->data['lastloginfrom']     = $this->db->f('account_lastloginfrom');
			$this->data['lastpasswd_change'] = $this->db->f('account_lastpwd_change');
			$this->data['status']            = $this->db->f('account_status');
			$this->data['expires']           = $this->db->f('account_expires');
			$this->data['person_id']         = $this->db->f('person_id');
			$this->data['account_primary_group'] = $this->db->f('account_primary_group');
			$this->data['email']             = $this->db->f('account_email');

			return $this->data;
		}

		/*!
		@function save_repository
		@abstract saves the records to the data store
		*/
		function save_repository()
		{
			$this->db->update($this->table,array(
				'account_firstname' => $this->data['firstname'],
				'account_lastname'  => $this->data['lastname'],
				'account_status'    => $this->data['status'],
				'account_expires'   => $this->data['expires'],
				'account_lid'       => $this->data['account_lid'],
				'person_id'         => $this->data['person_id'],
				'account_primary_group' => $this->data['account_primary_group'],
				'account_email'     => $this->data['email'],
			),array(
				'account_id'        => $this->account_id
			),__LINE__,__FILE__);
		}

		function delete($accountid = '')
		{
			$account_id = get_account_id($accountid);

			/* Do this last since we are depending upon this record to get the account_lid above */
			$this->db->lock(Array($this->table));
			$this->db->delete($this->table,array('account_id'=>$account_id),__LINE__,__FILE__);
			$this->db->unlock();
		}

		function get_list($_type='both',$start = '',$sort = '', $order = '', $query = '', $offset = '',$query_type='')
		{
			if (! $sort)
			{
				$sort = "DESC";
			}

			if (!empty($order) && preg_match('/^[a-zA-Z_0-9, ]+$/',$order) && (empty($sort) || preg_match('/^(DESC|ASC|desc|asc)$/',$sort)))
			{
				$orderclause = "ORDER BY $order $sort";
			}
			else
			{
				$orderclause = "ORDER BY account_lid ASC";
			}

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

			if ($query)
			{
				if ($whereclause)
				{
					$whereclause .= ' AND ( ';
				}
				else
				{
					$whereclause = ' WHERE ( ';
				}
				switch($query_type)
				{
					case 'all':
					default:
						$query = '%'.$query;
						// fall-through
					case 'start':
						$query .= '%';
						// fall-through
					case 'exact':
						$query = $this->db->quote($query);
						$whereclause .= " account_firstname LIKE $query OR account_lastname LIKE $query OR account_lid LIKE $query )";
						break;
					case 'firstname':
					case 'lastname':
					case 'lid':
					case 'email':
						$query = $this->db->quote('%'.$query.'%');
						$whereclause .= " account_$query_type LIKE $query )";
						break;
				}
			}

			$sql = "SELECT * FROM $this->table $whereclause $orderclause";
			if ($offset)
			{
				$this->db->limit_query($sql,$start,__LINE__,__FILE__,$offset);
			}
			elseif (is_numeric($start))
			{
				$this->db->limit_query($sql,$start,__LINE__,__FILE__);
			}
			else
			{
				$this->db->query($sql,__LINE__,__FILE__);
			}
			while ($this->db->next_record())
			{
				$accounts[] = Array(
					'account_id'        => $this->db->f('account_id'),
					'account_lid'       => $this->db->f('account_lid'),
					'account_type'      => $this->db->f('account_type'),
					'account_firstname' => $this->db->f('account_firstname'),
					'account_lastname'  => $this->db->f('account_lastname'),
					'account_status'    => $this->db->f('account_status'),
					'account_expires'   => $this->db->f('account_expires'),
					'person_id'         => $this->db->f('person_id'),
					'account_primary_group' => $this->db->f('account_primary_group'),
					'account_email'     => $this->db->f('account_email'),
				);
			}
			$this->db->query("SELECT count(*) FROM $this->table $whereclause");
			$this->db->next_record();
			$this->total = $this->db->f(0);

			return $accounts;
		}

		/**
		 * converts a name / unique value from the accounts-table (account_lid,account_email) to an id
		 */
		function name2id($name,$which='account_lid')
		{
			$this->db->select($this->table,'account_id',array($which=>$name),__LINE__,__FILE__);
			if($this->db->next_record())
			{
				return (int)$this->db->f('account_id');
			}
			return False;
		}

		/**
		 * converts an id to the corresponding value of the accounts-table (account_lid,account_email,account_firstname,...)
		 */
		function id2name($account_id,$which='account_lid')
		{
			$this->db->select($this->table,$this->db->name_quote($which),array('account_id'=>$account_id),__LINE__,__FILE__);
			if($this->db->next_record())
			{
				return $this->db->f(0);
			}
			return False;
		}

		function get_type($account_id)
		{
			return $this->id2name($account_id,'account_type');
		}

		function exists($account_lid)
		{
			static $by_id, $by_lid;

			$where = array();
			if(is_numeric($account_lid))
			{
				if(@isset($by_id[$account_lid]) && $by_id[$account_lid] != '')
				{
					return $by_id[$account_lid];
				}
				$where['account_id'] = $account_lid;
			}
			else
			{
				if(@isset($by_lid[$account_lid]) && $by_lid[$account_lid] != '')
				{
					return $by_lid[$account_lid];
				}
				$where['account_lid'] = $account_lid;
			}

			$this->db->select($this->table,'count(*)',$where,__LINE__,__FILE__);
			$this->db->next_record();
			$ret_val = $this->db->f(0) > 0;
			if(is_numeric($account_lid))
			{
				$by_id[$account_lid] = $ret_val;
				$by_lid[$this->id2name($account_lid)] = $ret_val;
			}
			else
			{
				$by_lid[$account_lid] = $ret_val;
				$by_id[$this->name2id($account_lid)] = $ret_val;
			}
			return $ret_val;
		}

		function create($account_info)
		{
			$account_data = array(
				'account_lid'			=> $account_info['account_lid'],
				'account_pwd'			=> $GLOBALS['phpgw']->common->encrypt_password($account_info['account_passwd'],True),
				'account_firstname'		=> $account_info['account_firstname'],
				'account_lastname'		=> $account_info['account_lastname'],
				'account_status'		=> $account_info['account_status'],
				'account_expires'		=> $account_info['account_expires'],
				'account_type'			=> $account_info['account_type'],
				'person_id'				=> $account_info['person_id'],
				'account_primary_group'	=> $account_info['account_primary_group'],
				'account_email'			=> $account_info['email'],
			);
			if (isset($account_info['account_id']) && (int)$account_info['account_id'] && !$this->id2name($account_info['account_id']))
			{
				// only use account_id, if it's not already used
				$account_data['account_id'] = $account_info['account_id'];
			}
			$this->db->insert($this->table,$account_data,False,__LINE__,__FILE__);

			return $this->db->get_last_insert_id($this->table,'account_id');
		}

		function auto_add($accountname, $passwd, $default_prefs = False, $default_acls = False, $expiredate = 0, $account_status = 'A')
		{
			if ($expiredate)
			{
				$expires = mktime(2,0,0,date('n',$expiredate), (int)date('d',$expiredate), date('Y',$expiredate));
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
						$expires   = mktime(2,0,0,date('n',$expiredate), (int)date('d',$expiredate), date('Y',$expiredate));
					}
				}
				else
				{
					/* expire in 30 days by default */
					$expiredate = time() + ( ( 60 * 60 ) * (30 * 24) );
					$expires   = mktime(2,0,0,date('n',$expiredate), (int)date('d',$expiredate), date('Y',$expiredate));
				}
			}
			$default_group_id  = $this->name2id($GLOBALS['phpgw_info']['server']['default_group_lid']);
			if (!$default_group_id)
			{
				$default_group_id = (int) $this->name2id('Default');
			}
			$primary_group = $GLOBALS['auto_create_acct']['primary_group'] &&
				$this->get_type((int)$GLOBALS['auto_create_acct']['primary_group']) == 'g' ?
				(int) $GLOBALS['auto_create_acct']['primary_group'] : $default_group_id;

			$acct_info = array(
				'account_id'        => (int) $GLOBALS['auto_create_acct']['id'],
				'account_lid'       => $accountname,
				'account_type'      => 'u',
				'account_passwd'    => $passwd,
				'account_firstname' => $GLOBALS['auto_create_acct']['firstname'],
				'account_lastname'  => $GLOBALS['auto_create_acct']['lastname'],
				'account_status'    => $account_status,
				'account_expires'   => $expires,
				'account_primary_group' => $primary_group,
				'account_email'     => $GLOBALS['auto_create_acct']['email'],
			);

			$this->db->transaction_begin();
			$this->create($acct_info);
			$accountid = $this->name2id($accountname);
			// if we have a primary_group, add it as "regular" eGW group (via ACL) too
			if ($accountid && $primary_group)
			{
				$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('phpgw_group', "
					. $primary_group . ', ' . $accountid . ', 1)',__LINE__,__FILE__);
			}
			// if we have an mail address set it as email pref
			if ($accountid && @$GLOBALS['auto_create_acct']['email'])
			{
				$GLOBALS['phpgw']->acl->acl($accountid);	// needed als preferences::save_repository calls acl
				$GLOBALS['phpgw']->preferences->preferences($accountid);
				$GLOBALS['phpgw']->preferences->read_repository();
				$GLOBALS['phpgw']->preferences->add('email','address',$GLOBALS['auto_create_acct']['email']);
				$GLOBALS['phpgw']->preferences->save_repository();
			}
			// commit the new account transaction
			$this->db->transaction_commit();
			
			$GLOBALS['hook_values']['account_lid']	= $acct_info['account_lid'];
			$GLOBALS['hook_values']['account_id']	= $accountid;
			$GLOBALS['hook_values']['new_passwd']	= $acct_info['account_passwd'];
			$GLOBALS['hook_values']['account_status'] = $acct_info['account_status'];
			$GLOBALS['hook_values']['account_firstname'] = $acct_info['account_lid'];
			$GLOBALS['hook_values']['account_lastname'] = 'eGW Account';
			$GLOBALS['hook_values']['account_email'] = $acct_info['account_passwd'];
			$GLOBALS['phpgw']->hooks->process($GLOBALS['hook_values']+array(
				'location' => 'addaccount'
			),False,True);  // called for every app now, not only enabled ones
			
			return $accountid;
		}

		function get_account_name($accountid,&$lid,&$fname,&$lname)
		{
			$this->db->select($this->table,'account_lid,account_firstname,account_lastname',array('account_id'=>$accountid),__LINE__,__FILE__);
			if (!$this->db->next_record())
			{
				return False;
			}
			$lid   = $this->db->f('account_lid');
			$fname = $this->db->f('account_firstname');
			$lname = $this->db->f('account_lastname');

			return True;
		}
	}
	/*!
	 @class_end accounts
	*/
