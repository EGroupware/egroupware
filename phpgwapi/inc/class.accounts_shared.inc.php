<?php
	/**************************************************************************\
	* phpGroupWare API - Accounts manager shared functions                     *
	* This file written by Joseph Engo <jengo@phpgroupware.org>                *
	* shared functions for other account repository managers                   *
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

	class accounts extends accounts_
	{
		var $memberships = Array();
		var $members = Array();

		/**************************************************************************\
		* Standard constructor for setting $this->account_id                       *
		* This constructor sets the account id, if string is sent, converts to id  *
		* I might move this to the accounts_shared if it stays around              *
		\**************************************************************************/
		function accounts($account_id = '', $account_type='')
		{
			$this->db = $GLOBALS['phpgw']->db;

			if($account_id != '')
			{
				$this->account_id = get_account_id($account_id);
			}

			if($account_type != '')
			{
				$this->account_type = $account_type;
			}

			if($GLOBALS['phpgw_info']['server']['account_repository'] == 'ldap')
			{
				$this->ds = $GLOBALS['phpgw']->common->ldapConnect();
				$this->user_context  = $GLOBALS['phpgw_info']['server']['ldap_context'];
				$this->group_context = $GLOBALS['phpgw_info']['server']['ldap_group_context'];
			}
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
			$account_id = get_account_id($accountid);

			$security_equals = Array();
			$security_equals = $GLOBALS['phpgw']->acl->get_location_list_for_id('phpgw_group', 1, $account_id);

			if ($security_equals == False)
			{
				return False;
			}

			$this->memberships = Array();

			for ($idx=0; $idx<count($security_equals); $idx++)
			{
				$groups = intval($security_equals[$idx]);
				$this->memberships[] = Array('account_id' => $groups, 'account_name' => $this->id2name($groups));
			}

			return $this->memberships;
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
				$name = $this->id2name(intval($security_equals[$idx]));
				$this->members[] = Array('account_id' => intval($security_equals[$idx]), 'account_name' => $name);
			}

			return $this->members;
		}

		/*!
		@function get_nextid
		@@abstract Using the common functions next_id and last_id, find the next available account_id
		@@param $account_type (optional, default to 'u'
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
			$nextid = intval($GLOBALS['phpgw']->common->last_id($type,$min,$max));

			/* Loop until we find a free id */
			$free = 0;
			while (!$free)
			{
				$account_lid = '';
				//echo '<br>calling search for id: '.$nextid;
				if ($this->exists($nextid))
				{
					$nextid = intval($GLOBALS['phpgw']->common->next_id($type,$min,$max));
				}
				else
				{
					$account_lid = $this->id2name($nextid);
					/* echo '<br>calling search for lid: '.$account_lid . '(from account_id=' . $nextid . ')'; */
					if ($this->exists($account_lid))
					{
						$nextid = intval($GLOBALS['phpgw']->common->next_id($type,$min,$max));
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
	}
?>
