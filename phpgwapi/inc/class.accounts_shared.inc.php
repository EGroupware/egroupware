<?php
	/**************************************************************************\
	* phpGroupWare API - Accounts manager shared functions                     *
	* Written by Joseph Engo <jengo@phpgroupware.org>                          *
	*        and Bettina Gille [ceb@phpgroupware.org]                          *
	* shared functions for other account repository managers                   *
	* Copyright (C) 2000 - 2002 Joseph Engo                                    *
	* Copyright (C) 2003 Joseph Engo, Bettina Gille                            *
	* -------------------------------------------------------------------------*
	* This library is part of the phpGroupWare API                             *
	* http://www.phpgroupware.org                                              * 
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

	/*!
	 @class_start accounts
	 @abstract Class for handling user and group accounts
	*/

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
			if($this->data['expires'] != -1 && $this->data['expires'] < time())
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
			if(count($this->data) == 0)
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

			if($security_equals == False)
			{
				return False;
			}

			$this->memberships = Array();

			for($idx=0; $idx<count($security_equals); $idx++)
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

			if($security_equals == False)
			{
				return False;
			}

			for($idx=0; $idx<count($security_equals); $idx++)
			{
				$name = $this->id2name(intval($security_equals[$idx]));
				$this->members[] = Array('account_id' => intval($security_equals[$idx]), 'account_name' => $name);
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

			if($account_type == 'g')
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
			while(!$free)
			{
				$account_lid = '';
				//echo '<br>calling search for id: '.$nextid;
				if($this->exists($nextid))
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
			if($GLOBALS['phpgw_info']['server']['account_max_id'] &&
				($nextid > $GLOBALS['phpgw_info']['server']['account_max_id']))
			{
				return False;
			}
			/* echo '<br>using'.$nextid;exit; */
			return $nextid;
		}

		function accounts_popup($app)
		{
			$group_id = get_var('group_id',array('GET','POST'));

			/*if(isset($_GET['group_id']))
			{
				$group_id = $_GET['group_id'];
			}*/

			if(isset($_POST['query']))
			{
				$GLOBALS['query'] = $_POST['query'];
			}
			
			if(isset($_POST['start']))
			{
				$start = intval($_POST['start']);
			}
			else
			{
				$start = 0;
			}

			if(isset($_GET['order']))
			{
				$order = $_GET['order'];
			}
			else
			{
				$order = 'account_lid';
			}
			
			if(isset($_GET['sort']))
			{
				$sort = $_GET['sort'];
			}
			else
			{
				$sort = 'ASC';
			}

			$this->nextmatchs = CreateObject('phpgwapi.nextmatchs');

			$GLOBALS['phpgw']->template->set_root($GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi'));

			$GLOBALS['phpgw']->template->set_file(array('accounts_list_t' => 'accounts_popup.tpl'));
			$GLOBALS['phpgw']->template->set_block('accounts_list_t','group_cal','cal');
			$GLOBALS['phpgw']->template->set_block('accounts_list_t','group_other','other');
			$GLOBALS['phpgw']->template->set_block('accounts_list_t','group_all','all');

			$GLOBALS['phpgw']->template->set_block('accounts_list_t','bla_intro','ibla');
			$GLOBALS['phpgw']->template->set_block('accounts_list_t','other_intro','iother');
			$GLOBALS['phpgw']->template->set_block('accounts_list_t','all_intro','iall');


			$GLOBALS['phpgw']->template->set_block('accounts_list_t','accounts_list','list');


			$GLOBALS['phpgw']->template->set_var('title',$GLOBALS['phpgw_info']['site_title']);
			$GLOBALS['phpgw']->template->set_var('charset',$GLOBALS['phpgw']->translation->translate('charset'));
			$GLOBALS['phpgw']->template->set_var('font',$GLOBALS['phpgw_info']['theme']['font']);
			$GLOBALS['phpgw']->template->set_var('lang_search',lang('search'));
			$GLOBALS['phpgw']->template->set_var('lang_groups',lang('user groups'));
			$GLOBALS['phpgw']->template->set_var('lang_accounts',lang('user accounts'));
			$GLOBALS['phpgw']->template->set_var('lang_perm',lang('Groups with permission for %1',$app));
			$GLOBALS['phpgw']->template->set_var('lang_nonperm',lang('Groups without permission for %1',$app));

			$GLOBALS['phpgw']->template->set_var('img',$GLOBALS['phpgw']->common->image('phpgwapi','select'));
			$GLOBALS['phpgw']->template->set_var('lang_select_user',lang('Select user'));
			$GLOBALS['phpgw']->template->set_var('lang_select_group',lang('Select group'));
			$GLOBALS['phpgw']->template->set_var('css_file',$GLOBALS['phpgw_info']['server']['webserver_url'] . SEP . 'phpgwapi' . SEP . 'templates'
															. SEP . 'idots' . SEP . 'css' . SEP . 'idots.css');

			switch($app)
			{
				case 'calendar':
					$action = 'calendar.uicalendar.accounts_popup';
					$GLOBALS['phpgw']->template->set_var('select_name',"participants[]'][0]");
					$GLOBALS['phpgw']->template->set_var('js_function','ExchangeAccountSelect');
					$GLOBALS['phpgw']->template->fp('ibla','bla_intro',True);
					$GLOBALS['phpgw']->template->fp('iall','all_intro',True);
					break;
				case 'admin':
					$action = 'admin.uiaccounts.accounts_popup';
					$GLOBALS['phpgw']->template->set_var('select_name',"account_user[]']");
					$GLOBALS['phpgw']->template->set_var('js_function','ExchangeAccountSelect');
					$GLOBALS['phpgw']->template->set_var('lang_perm',lang('group name'));
					$GLOBALS['phpgw']->template->fp('iother','other_intro',True);
					break;
				case 'projects':
					$action = 'projects.uiprojects.accounts_popup';
					$GLOBALS['phpgw']->template->set_var('select_name',"values[coordinator]']");
					$GLOBALS['phpgw']->template->set_var('js_function','ExchangeAccountText');
					$GLOBALS['phpgw']->template->fp('iother','other_intro',True);
					$GLOBALS['phpgw']->template->fp('iall','all_intro',True);
					break;
			}

			$link_data = array
			(
				'menuaction'	=> $action,
				'group_id'		=> $group_id
			);

			$app_groups = array();

			if ($app != 'admin')
			{
				$user_groups = $this->membership($this->account);

				$app_user = $GLOBALS['phpgw']->acl->get_ids_for_location('run',1,$app);
				for ($i = 0;$i<count($app_user);$i++)
				{
					$type = $this->get_type($app_user[$i]);
					if($type == 'g')
					{
						$app_groups[] = $app_user[$i];
						$members[] = $GLOBALS['phpgw']->acl->get_ids_for_location($app_user[$i],1,'phpgw_group');
					}
				}

				$i = count($app_user);
				while(is_array($members) && list(,$mem) = each($members))
				{
					for($j=0;$j<count($mem);$j++)
					{
						$app_user[$i] = $mem[$j];
						$i++;
					}
				}
				//_debug_array($app_user);
			}
			else
			{
				$all_groups	= $this->get_list('groups');
				$all_user	= $this->get_list('accounts');

				while(is_array($all_groups) && list(,$agroup) = each($all_groups))
				{
					$user_groups[] = array
					(
						'account_id'	=> $agroup['account_id'],
						'account_name'	=> $agroup['account_firstname']
					);
				}

				for($j=0;$j<count($user_groups);$j++)
				{
					$app_groups[$i] = $user_groups[$j]['account_id'];
					$i++;
				}

				for($j=0;$j<count($all_user);$j++)
				{
					$app_user[$i] = $all_user[$j]['account_id'];
					$i++;
				}
			}

			while (is_array($user_groups) && list(,$group) = each($user_groups))
			{
				if (in_array($group['account_id'],$app_groups))
				{
					$GLOBALS['phpgw']->template->set_var('tr_color',$this->nextmatchs->alternate_row_color($tr_color));
					//$link_data['group_id'] = $group['account_id'];
					$GLOBALS['phpgw']->template->set_var('link_user_group',$GLOBALS['phpgw']->link('/index.php','menuaction=' . $action . '&group_id=' . $group['account_id']));
					$GLOBALS['phpgw']->template->set_var('name_user_group',$group['account_name']);
					$GLOBALS['phpgw']->template->set_var('account_display',$GLOBALS['phpgw']->common->grab_owner_name($group['account_id']));
					$GLOBALS['phpgw']->template->set_var('accountid',$group['account_id']);
					switch($app)
					{
						case 'calendar':	$GLOBALS['phpgw']->template->fp('cal','group_cal',True); break;
						default:			$GLOBALS['phpgw']->template->fp('other','group_other',True); break;
					}
				}
				else
				{
					if ($app != 'admin')
					{
						$GLOBALS['phpgw']->template->set_var('link_all_group',$GLOBALS['phpgw']->link('/index.php','menuaction=' . $action . '&group_id=' . $group['account_id']));
						$GLOBALS['phpgw']->template->set_var('name_all_group',$group['account_name']);
						$GLOBALS['phpgw']->template->set_var('accountid',$group['account_id']);
						$GLOBALS['phpgw']->template->fp('all','group_all',True);
					}
				}
			}

			if (!$GLOBALS['query'])
			{
				if (isset($group_id) && !empty($group_id))
				{
					//echo 'GROUP_ID: ' . $group_id;
					$users = $GLOBALS['phpgw']->acl->get_ids_for_location($group_id,1,'phpgw_group');

					for ($i=0;$i<count($users);$i++)
					{
						if (in_array($users[$i],$app_user))
						{
							$GLOBALS['phpgw']->accounts->account_id = $users[$i];
							$GLOBALS['phpgw']->accounts->read_repository();

							switch ($order)
							{
								case 'account_firstname':
									$id = $GLOBALS['phpgw']->accounts->data['firstname'];
									break;
								case 'account_lastname':
									$id = $GLOBALS['phpgw']->accounts->data['lastname'];
									break;
								case 'account_lid':
								default:
									$id = $GLOBALS['phpgw']->accounts->data['account_lid'];
									break;
							}
							$id .= $GLOBALS['phpgw']->accounts->data['lastname'];	// default sort-order
							$id .= $GLOBALS['phpgw']->accounts->data['firstname'];
							$id .= $GLOBALS['phpgw']->accounts->data['account_id'];	// make our index unique

							$val_users[$id] = array
							(
								'account_id'		=> $GLOBALS['phpgw']->accounts->data['account_id'],
								'account_lid'		=> $GLOBALS['phpgw']->accounts->data['account_lid'],
								'account_firstname'	=> $GLOBALS['phpgw']->accounts->data['firstname'],
								'account_lastname'	=> $GLOBALS['phpgw']->accounts->data['lastname']
							);
						}
					}

					if (is_array($val_users))
					{
						if ($sort != 'DESC')
						{
							ksort($val_users);
						}
						else
						{
							krsort($val_users);
						}
					}
					$val_users = array_values($val_users);	// get a numeric index
				}
				$total = count($val_users);
			}
			else
			{
				switch($app)
				{
					case 'calendar':	$select = 'both'; break;
					default:			$select = 'accounts'; break;
				}
				$entries	= $this->get_list($select,$start,$sort,$order,$GLOBALS['query']);
				$total		= $this->total;
				for ($i=0;$i<count($entries);$i++)
				{
					if (in_array($entries[$i]['account_id'],$app_user))
					{
						$val_users[] = array
						(
							'account_id'		=> $entries[$i]['account_id'],
							'account_lid'		=> $entries[$i]['account_lid'],
							'account_firstname'	=> $entries[$i]['account_firstname'],
							'account_lastname'	=> $entries[$i]['account_lastname']
						);
					}
				}
			}

// --------------------------------- nextmatch ---------------------------

			$left = $this->nextmatchs->left('/index.php',$start,$total,$link_data);
			$right = $this->nextmatchs->right('/index.php',$start,$total,$link_data);
			$GLOBALS['phpgw']->template->set_var('left',$left);
			$GLOBALS['phpgw']->template->set_var('right',$right);

			$GLOBALS['phpgw']->template->set_var('lang_showing',$this->nextmatchs->show_hits($total,$start));

// -------------------------- end nextmatch ------------------------------------

			$GLOBALS['phpgw']->template->set_var('search_action',$GLOBALS['phpgw']->link('/index.php',$link_data));
			$GLOBALS['phpgw']->template->set_var('search_list',$this->nextmatchs->search(array('query' => $GLOBALS['query'], 'search_obj' => 1)));

// ---------------- list header variable template-declarations --------------------------

// -------------- list header variable template-declaration ------------------------
			$GLOBALS['phpgw']->template->set_var('th_bg',$GLOBALS['phpgw_info']['theme']['th_bg']);
			$GLOBALS['phpgw']->template->set_var('sort_lid',$this->nextmatchs->show_sort_order($sort,'account_lid',$order,'/index.php',lang('LoginID'),$link_data));
			$GLOBALS['phpgw']->template->set_var('sort_firstname',$this->nextmatchs->show_sort_order($sort,'account_firstname',$order,'/index.php',lang('Firstname'),$link_data));
			$GLOBALS['phpgw']->template->set_var('sort_lastname',$this->nextmatchs->show_sort_order($sort,'account_lastname',$order,'/index.php',lang('Lastname'),$link_data));

// ------------------------- end header declaration --------------------------------
			$stop = $start + $this->nextmatchs->maxmatches;
			for ($i=$start;$i<count($val_users)&&$i<$stop;$i++)
			{
				$GLOBALS['phpgw']->template->set_var('tr_color',$this->nextmatchs->alternate_row_color($tr_color));
				$firstname = $val_users[$i]['account_firstname'];
				if (!$firstname) { $firstname = '&nbsp;'; }
				$lastname = $val_users[$i]['account_lastname'];
				if (!$lastname) { $lastname = '&nbsp;'; }

// ---------------- template declaration for list records -------------------------- 

				$GLOBALS['phpgw']->template->set_var(array
				(
					'lid'				=> $val_users[$i]['account_lid'],
					'firstname'			=> $firstname,
					'lastname'			=> $lastname,
					'accountid'			=> $val_users[$i]['account_id'],
					'account_display'	=> $GLOBALS['phpgw']->common->grab_owner_name($val_users[$i]['account_id'])
				));

				$GLOBALS['phpgw']->template->fp('list','accounts_list',True);
			}

			$GLOBALS['phpgw']->template->set_var('start',$start);
			$GLOBALS['phpgw']->template->set_var('sort',$sort);
			$GLOBALS['phpgw']->template->set_var('order',$order);
			$GLOBALS['phpgw']->template->set_var('query',$GLOBALS['query']);
			$GLOBALS['phpgw']->template->set_var('group_id',$group_id);

			$GLOBALS['phpgw']->template->set_var('lang_done',lang('done'));
			$GLOBALS['phpgw']->template->pfp('out','accounts_list_t',True);
			$GLOBALS['phpgw']->common->phpgw_exit();
		}
	}
	/*!
	 @class_end accounts
	*/
