<?php
  /**************************************************************************\
  * phpGroupWare API - Accounts manager for SQL                              *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * and Dan Kuykendall <seek3r@phpgroupware.org>                             *
  * View and manipulate account records using SQL                            *
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

	/* These are needed here also to exclude these on ldap import into SQL */
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
		'backup'   => True
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
		'ldap'      => True, 'backup'    => True
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
			$this->db->query("SELECT * FROM phpgw_accounts WHERE account_id='" . $this->account_id . "'",__LINE__,__FILE__);
			$this->db->next_record();

			$this->data['userid']            = $this->db->f('account_lid');
			$this->data['account_id']        = $this->db->f('account_id');
			$this->data['account_lid']       = $this->db->f('account_lid');
			$this->data['firstname']         = $this->db->f('account_firstname');
			$this->data['lastname']          = $this->db->f('account_lastname');
			$this->data['fullname']          = $this->db->f('account_firstname') . ' '
														. $this->db->f('account_lastname');
			$this->data['lastlogin']         = $this->db->f('account_lastlogin');
			$this->data['lastloginfrom']     = $this->db->f('account_lastloginfrom');
			$this->data['lastpasswd_change'] = $this->db->f('account_lastpwd_change');
			$this->data['status']            = $this->db->f('account_status');
			$this->data['expires']           = $this->db->f('account_expires');
			return $this->data;
		}

		function save_repository()
		{
			$this->db->query("UPDATE phpgw_accounts SET account_firstname='" . $this->data['firstname']
				. "', account_lastname='" . $this->data['lastname'] . "', account_status='"
				. $this->data['status'] . "', account_expires='" . $this->data['expires'] . "' WHERE account_id='"
				. $this->account_id . "'",__LINE__,__FILE__);
		}

		function delete($accountid = '')
		{
			global $phpgw, $phpgw_info;

			$account_id = get_account_id($accountid);

			// Do this last since we are depending upon this record to get the account_lid above
			$tables_array = Array('phpgw_accounts');
			$this->db->lock($tables_array);
			$this->db->query('DELETE FROM phpgw_accounts WHERE account_id='.$account_id);
			$this->db->unlock();
		}

		function get_list($_type='both',$start = '',$sort = '', $order = '', $query = '', $offset = '')
		{
			global $phpgw, $phpgw_info;

			if ($offset)
			{
				$limitclause = $phpgw->db->limit($start,$offset);
			}
			elseif ($start && !$offset)
			{
				$limitclause = $phpgw->db->limit($start);
			}

			if (! $sort)
			{
				$sort = "DESC";
			}

			if ($order)
			{
				$orderclause = "ORDER BY $order $sort";
			}
			else
			{
				$orderclause = "ORDER BY account_lid,account_lastname,account_firstname ASC";
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
					$whereclause = "";
			}

			if ($query)
			{
				if ($whereclause)
				{
					$whereclause .= ' AND ( ';
				}
				else
				{
					$whereclause .= ' WHERE ';
				}

				$whereclause .= " account_firstname LIKE '%$query%' OR account_lastname LIKE "
					. "'%$query%' OR account_lid LIKE '%$query%' ";
				if ($whereclause)
				{
					$whereclause .= ' ) ';
				}
			}

			$sql = "SELECT * FROM phpgw_accounts $whereclause $orderclause $limitclause";
			$this->db->query($sql,__LINE__,__FILE__);
			while ($this->db->next_record()) {
				$accounts[] = Array(
					'account_id'        => $this->db->f('account_id'),
					'account_lid'       => $this->db->f('account_lid'),
					'account_type'      => $this->db->f('account_type'),
					'account_firstname' => $this->db->f('account_firstname'),
					'account_lastname'  => $this->db->f('account_lastname'),
					'account_status'    => $this->db->f('account_status'),
					'account_expires'   => $this->db->f('account_expires')
				);
			}
			return $accounts;
		}

		function name2id($account_lid)
		{
			global $phpgw, $phpgw_info;
			static $name_list;

			if($name_list[$account_lid] && $name_list[$account_lid] != '')
			{
				return $name_list[$account_lid];
			}

			$this->db->query("SELECT account_id FROM phpgw_accounts WHERE account_lid='".$account_lid."'",__LINE__,__FILE__);
			if($this->db->num_rows())
			{
				$this->db->next_record();
				$name_list[$account_lid] = $this->db->f('account_id');
				return $this->db->f('account_id');
			}
			else
			{
				return False;
			}
		}

		function id2name($account_id)
		{
			global $phpgw, $phpgw_info;
			static $id_list;

			if($id_list[$account_id])
			{
				return $id_list[$account_id];
			}				

			$this->db->query("SELECT account_lid FROM phpgw_accounts WHERE account_id='".$account_id."'",__LINE__,__FILE__);
			if($this->db->num_rows())
			{
				$this->db->next_record();
				$id_list[$account_id] = $this->db->f('account_lid');
				return $this->db->f('account_lid');
			}
			else
			{
				return False;
			}
		}

		function get_type($accountid)
		{
			global $phpgw, $phpgw_info;

			$account_id = get_account_id($accountid);
			$this->db->query("SELECT account_type FROM phpgw_accounts WHERE account_id='".$account_id."'",__LINE__,__FILE__);
			if ($this->db->num_rows())
			{
				$this->db->next_record();
				return $this->db->f('account_type');
			}
			else
			{
				return False;
			}
		}

		function exists($account_lid)
		{
			$sql = "SELECT count(account_id) FROM phpgw_accounts WHERE ";
			if(gettype($account_lid) == 'integer')
			{
				$sql .= "account_id = ".$account_lid;
			}
			else
			{
				$sql .= "account_lid = '".$account_lid."'";
			}

			$this->db->query($sql,__LINE__,__FILE__);
			$this->db->next_record();
			return $this->db->f(0) > 0;
		}

		function create($account_info)
		{
			if (empty($account_info['account_id']) || !$account_info['account_id'])
			{
				$account_id = $this->get_nextid($account_info['account_type']);
				/* echo '<br>using'.$account_id;exit; */
			}
			$this->db->query("insert into phpgw_accounts (account_id,account_lid, account_type, account_pwd, "
				. "account_firstname, account_lastname, account_status, account_expires) values (" . $account_info['account_id'] . ",'" . $account_info['account_lid']
				. "','" . $account_info['account_type'] . "','" . md5($account_info['account_passwd']) . "', '" . $account_info['account_firstname']
				. "','" . $account_info['account_lastname'] . "','" . $account_info['account_status'] . "','" . $account_info['account_expires']
				. "')",__LINE__,__FILE__);
		}

		function auto_add($accountname, $passwd, $default_prefs = False, $default_acls = False, $expiredate = 0, $account_status = 'A')
		{
			global $phpgw, $phpgw_info;

			if (! $expiredate)
			{
				// expire in 30 days by default
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
				$default_prefs = 'a:5:{s:6:"common";a:10:{s:9:"maxmatchs";s:2:"15";s:12:"template_set";s:8:"verdilak";s:5:"theme";s:6:"purple";s:13:"navbar_format";s:5:"icons";s:9:"tz_offset";N;s:10:"dateformat";s:5:"m/d/Y";s:10:"timeformat";s:2:"12";s:4:"lang";s:2:"en";s:11:"default_app";N;s:8:"currency";s:1:"$";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}:s:8:"calendar";a:4:{s:13:"workdaystarts";s:1:"7";s:11:"workdayends";s:2:"15";s:13:"weekdaystarts";s:6:"Monday";s:15:"defaultcalendar";s:9:"month.php";}}';
//				$defaultprefs = 'a:5:{s:6:"common";a:1:{s:0:"";s:2:"en";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}s:8:"calendar";a:1:{s:0:"";s:13:"workdaystarts";}i:15;a:1:{s:0:"";s:11:"workdayends";}s:6:"Monday";a:1:{s:0:"";s:13:"weekdaystarts";}}';
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
	} //end of class
?>
