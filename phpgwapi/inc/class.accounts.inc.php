<?php
	/**************************************************************************\
	* eGroupWare API - Accounts manager shared functions                       *
	* Written by Joseph Engo <jengo@phpgroupware.org>                          *
	*        and Bettina Gille [ceb@phpgroupware.org]                          *
	* shared functions for other account repository managers                   *
	* Copyright (C) 2000 - 2002 Joseph Engo                                    *
	* Copyright (C) 2003 Joseph Engo, Bettina Gille                            *
	* -------------------------------------------------------------------------*
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

	if (empty($GLOBALS['phpgw_info']['server']['account_repository']))
	{
		if (!empty($GLOBALS['phpgw_info']['server']['auth_type']))
		{
			$GLOBALS['phpgw_info']['server']['account_repository'] = $GLOBALS['phpgw_info']['server']['auth_type'];
		}
		else
		{
			$GLOBALS['phpgw_info']['server']['account_repository'] = 'sql';
		}
	}
	include_once(PHPGW_API_INC . '/class.accounts_' . $GLOBALS['phpgw_info']['server']['account_repository'] . '.inc.php');

	/*
	  Dont know where to put this (seek3r)
	  This is where it belongs (jengo)
	  This is where it ended up (milosch)
	  Moved again at least temporarily since sql and ldap use it.
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
		'nscd'     => True, 'mailnull' => True, 'cyrus'    => True,
		'backup'    => True
	);

	$GLOBALS['phpgw_info']['server']['global_denied_groups'] = array(
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
		'ldap'      => True, 'backup'    => True
	);

	/*!
	 @class_start accounts
	 @abstract Class for handling user and group accounts
	*/

	class accounts extends accounts_
	{
		var $memberships    = array();
		var $members        = array();
		var $xmlrpc_methods = array();
		// enables the session-cache
		var $use_session_cache = True;

		/**************************************************************************\
		* Standard constructor for setting $this->account_id                       *
		* This constructor sets the account id, if string is sent, converts to id  *
		* I might move this to the accounts_shared if it stays around              *
		\**************************************************************************/
		function accounts($account_id = '', $account_type='')
		{
			// enable the caching in the session onyl for ldap
			$this->use_session_cache = $GLOBALS['phpgw_info']['server']['account_repository'] == 'ldap';

			$this->db = $GLOBALS['phpgw']->db;

			if($account_id != '')
			{
				$this->account_id = get_account_id($account_id);
			}

			if($account_type != '')
			{
				$this->account_type = $account_type;
			}

			$this->query_types = array(
				'all' => 'all fields',
				'firstname' => 'firstname',
				'lastname' => 'lastname',
				'lid' => 'LoginID',
				'email' => 'email',	// sql-constructor unsets this again, til the email column is added
				'start' => 'start with',
				'exact' => 'exact',
			);
			$this->accounts_();			// call constructor of extended class

			$this->xmlrpc_methods[] = array(
				'name'        => 'get_list',
				'description' => 'Returns a list of accounts and/or groups'
			);
			$this->xmlrpc_methods[] = array(
				'name'        => 'name2id',
				'description' => 'Cross reference account_lid with account_id'
			);
			$this->xmlrpc_methods[] = array(
				'name'        => 'id2name',
				'description' => 'Cross reference account_id with account_lid'
			);
		}

		/**
		* Sets up the account-data cache
		*
		* The cache is shared between all instances of the account-class and it can be save in the session,
		* if use_session_cache is set to True
		*/
		function setup_cache()
		{
			if ($this->use_session_cache &&		// are we supposed to use a session-cache
				!@$GLOBALS['phpgw_info']['accounts']['session_cache_setup'] &&	// is it already setup
				// is the account-class ready (startup !)
				is_object($GLOBALS['phpgw']->session) && $GLOBALS['phpgw']->session->account_id)
			{
				// setting up the session-cache
				$GLOBALS['phpgw_info']['accounts']['cache'] = $GLOBALS['phpgw']->session->appsession('accounts_cache','phpgwapi');
				$GLOBALS['phpgw_info']['accounts']['session_cache_setup'] = True;
				//echo "accounts::setup_cache() cache=<pre>".print_r($GLOBALS['phpgw_info']['accounts']['cache'],True)."</pre>\n";
			}
			if (!isset($this->cache))
			{
				$this->cache = &$GLOBALS['phpgw_info']['accounts']['cache'];
			}
		}

		/**
		* Saves the account-data cache in the session
		*
		* Gets called from common::phpgw_final()
		*/
		function save_session_cache()
		{
			if ($this->use_session_cache &&		// are we supposed to use a session-cache
				$GLOBALS['phpgw_info']['accounts']['session_cache_setup'] &&	// is it already setup
				// is the account-class ready (startup !)
				is_object($GLOBALS['phpgw']->session))
			{
				$GLOBALS['phpgw']->session->appsession('accounts_cache','phpgwapi',$GLOBALS['phpgw_info']['accounts']['cache']);
			}
		}

		function get_list($_type='both',$start = '',$sort = '', $order = '', $query = '', $offset = '',$query_type='')
		{
			//echo "<p>accounts::get_list(".print_r($_type,True).",start='$start',sort='$sort',order='$order',query='$query',offset='$offset')</p>\n";
			$this->setup_cache();
			$account_list = &$this->cache['account_list'];

			// For XML-RPC
			if (is_array($_type))
			{
				$p      = $_type[0];
				$_type  = $p['type'];
				$start  = $p['start'];
				$order  = $p['order'];
				$query  = $p['query'];
				$offset = $p['offset'];
				$query_type = $p['query_type'];
			}
			else
			{
				$p = array(
					'type' => $_type,
					'start' => $start,
					'order' => $order,
					'query' => $query,
					'offset' => $offset,
					'query_type' => $query_type ,
				);
			}
			$serial = serialize($p);

			if (isset($account_list[$serial]))
			{
				$this->total = $account_list[$serial]['total'];
			}
			else
			{
				$account_list[$serial]['data'] = accounts_::get_list($_type,$start,$sort,$order,$query,$offset,$query_type);
				$account_list[$serial]['total'] = $this->total;
			}
			return $account_list[$serial]['data'];
		}

		function is_expired()
		{
			if ($this->data['expires'] != -1 && $this->data['expires'] < time())
			{
				return True;
			}
			else
			{
				return False;
			}
		}

		/**
		* Invalidate the cache (or parts of it) after change in $account_id
		*
		* Atm simplest approach - delete it all ;-)
		*/
		function cache_invalidate($account_id)
		{
			//echo "<p>accounts::cache_invalidate($account_id)</p>\n";
			$GLOBALS['phpgw_info']['accounts']['cache'] = array();
		}

		function save_repository()
		{
			$this->cache_invalidate($this->account_id);
			accounts_::save_repository();
		}

		function delete($accountid)
		{
			$this->cache_invalidate($accountid);
			accounts_::delete($accountid);
		}

		function create($account_info,$default_prefs=True)
		{
			$account_id = accounts_::create($account_info,$default_prefs);
			$this->cache_invalidate($account_id);

			return $account_id;
		}

		function read_repository()
		{
			$this->setup_cache();
			$account_data = &$this->cache['account_data'];

			if (isset($account_data[$this->account_id]))
			{
				return $this->data = $account_data[$this->account_id];
			}
			return $account_data[$this->account_id] = accounts_::read_repository();
		}

		function read()
		{
			if (count($this->data) == 0)
			{
				$this->read_repository();
			}

			reset($this->data);
			return $this->data;
		}

		function update_data($data)
		{
			reset($data);
			$this->data = Array();
			$this->data = $data;

			reset($this->data);
			return $this->data;
		}

		function membership($accountid = '')
		{
			$this->setup_cache();
			$membership_list = &$this->cache['membership_list'];

			$account_id = get_account_id($accountid);

			if (isset($membership_list[$account_id]))
			{
				return $membership_list[$account_id];
			}

			$security_equals = Array();
			$security_equals = $GLOBALS['phpgw']->acl->get_location_list_for_id('phpgw_group', 1, $account_id);

			if ($security_equals == False)
			{
				return $membership_list[$account_id] = False;
			}

			$this->memberships = Array();

			for ($idx=0; $idx<count($security_equals); $idx++)
			{
				$groups = (int)$security_equals[$idx];
				$this->memberships[] = Array('account_id' => $groups, 'account_name' => $this->id2name($groups));
			}

			return $membership_list[$account_id] = $this->memberships;
		}

		function member($accountid = '')
		{
			$account_id = get_account_id($accountid);

			$security_equals = Array();
			$acl = CreateObject('phpgwapi.acl');
			$security_equals = $acl->get_ids_for_location($account_id, 1, 'phpgw_group');
			unset($acl);

			if ($security_equals == False)
			{
				return False;
			}

			for ($idx=0; $idx<count($security_equals); $idx++)
			{
				$name = $this->id2name((int)$security_equals[$idx]);
				$this->members[] = Array('account_id' => (int)$security_equals[$idx], 'account_name' => $name);
			}

			return $this->members;
		}

		/*!
		@function get_nextid
		@abstract Using the common functions next_id and last_id, find the next available account_id
		@param $account_type (optional, default to 'u')
		*/
		function get_nextid($account_type='u')
		{
			$min = $GLOBALS['phpgw_info']['server']['account_min_id'] ? $GLOBALS['phpgw_info']['server']['account_min_id'] : 0;
			$max = $GLOBALS['phpgw_info']['server']['account_max_id'] ? $GLOBALS['phpgw_info']['server']['account_max_id'] : 0;

			if ($account_type == 'g')
			{
				$type = 'groups';
			}
			else
			{
				$type = 'accounts';
			}
			$nextid = (int)$GLOBALS['phpgw']->common->last_id($type,$min,$max);

			/* Loop until we find a free id */
			$free = 0;
			while (!$free)
			{
				$account_lid = '';
				//echo '<br>calling search for id: '.$nextid;
				if ($this->exists($nextid))
				{
					$nextid = (int)$GLOBALS['phpgw']->common->next_id($type,$min,$max);
				}
				else
				{
					$account_lid = $this->id2name($nextid);
					/* echo '<br>calling search for lid: '.$account_lid . '(from account_id=' . $nextid . ')'; */
					if ($this->exists($account_lid))
					{
						$nextid = (int)$GLOBALS['phpgw']->common->next_id($type,$min,$max);
					}
					else
					{
						$free = True;
					}
				}
			}
			if	($GLOBALS['phpgw_info']['server']['account_max_id'] &&
				($nextid > $GLOBALS['phpgw_info']['server']['account_max_id']))
			{
				return False;
			}
			/* echo '<br>using'.$nextid;exit; */
			return $nextid;
		}

		/**
		* returns a array of users and groups seperated, including all members of groups, which i.e. 
		* have acl access for an application
		* needed to stay compatible with phpgw
		*
		* @param $app_users (array, default to 0)
		*/
		function return_members($app_users = 0)
		{
			for ($i = 0;$i<count($app_users);$i++)
			{
				$type = $GLOBALS['phpgw']->accounts->get_type($app_users[$i]);
				if($type == 'g')
				{
					$add_users['groups'][] = $app_users[$i];
					$members[] = $GLOBALS['phpgw']->acl->get_ids_for_location($app_users[$i],1,'phpgw_group');
				}
				else
				{
					$add_users['users'][] = $app_users[$i];
				}
			}

			if(is_array($add_users['groups']))
			{
				$add_users['groups'] = array_unique($add_users['groups']);
				sort($add_users['groups']);
			}

			#$i = count($add_users['users']);

			while(is_array($members) && list(,$mem) = each($members))
			{
				for($j=0;$j<count($mem);$j++)
				{
					$add_users['users'][] = $mem[$j];
				}
			}
			if(is_array($add_users['users']))
			{
				$add_users['users'] = array_unique($add_users['users']);
				sort($add_users['users']);
			}
			return $add_users;
		}

		function name2id($account_lid)
		{
			$this->setup_cache();
			$name_list = &$this->cache['name_list'];

			if(@isset($name_list[$account_lid]) && $name_list[$account_lid])
			{
				return $name_list[$account_lid];
			}

			/* Don't bother searching for empty account_lid */
			if(empty($account_lid))
			{
				return False;
			}
			return $name_list[$account_lid] = accounts_::name2id($account_lid);
		}

		function id2name($account_id)
		{
			$this->setup_cache();
			$id_list = &$this->cache['id_list'];

			if (! $account_id)
			{
				return False;
			}

			if($id_list[$account_id])
			{
				return $id_list[$account_id];
			}
			return $id_list[$account_id] = accounts_::id2name($account_id);
		}

		function get_type($accountid)
		{
			$this->setup_cache();
			$account_type = &$this->cache['account_type'];

			$account_id = get_account_id($accountid);

			if (isset($this->account_type) && $account_id == $this->account_id)
			{
				return $this->account_type;
			}

			if(@isset($account_type[$account_id]) && @$account_type[$account_id])
			{
				return $account_type[$account_id];
			}
			elseif($account_id == '')
			{
				return False;
			}
			return $account_type[$account_id] = accounts_::get_type($account_id);
		}

		function get_account_name($accountid,&$lid,&$fname,&$lname)
		{
			$this->setup_cache();
			$account_name = &$this->cache['account_name'];

			$account_id = get_account_id($accountid);
			if(isset($account_name[$account_id]))
			{
				$lid = $account_name[$account_id]['lid'];
				$fname = $account_name[$account_id]['fname'];
				$lname = $account_name[$account_id]['lname'];
				return $account_name[$account_id] !== False;
			}
			$Ok = accounts_::get_account_name($accountid,$lid,$fname,$lname);

			$account_name[$account_id] = array(
				'lid' => $lid,
				'fname' => $fname,
				'lname' => $lname,
			);
			return $Ok;
		}

		function get_account_data($account_id)
		{
			$this->account_id = $account_id;
			$this->read_repository();

			$data[$this->data['account_id']]['lid']       = $this->data['account_lid'];
			$data[$this->data['account_id']]['firstname'] = $this->data['firstname'];
			$data[$this->data['account_id']]['lastname']  = $this->data['lastname'];
			$data[$this->data['account_id']]['fullname']  = $this->data['fullname'];
			$data[$this->data['account_id']]['type']      = $this->data['account_type'];

			return $data;
		}
	}
