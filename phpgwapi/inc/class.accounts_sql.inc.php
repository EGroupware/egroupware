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

	class accounts_
	{
		var $db;
		var $account_id;
		var $data;
		var $total;

		function accounts_()
		{
			/* Don't think this ever gets called */
			$this->db = $GLOBALS['phpgw']->db;
		}

		function read_repository()
		{
			$this->db->query("SELECT * FROM phpgw_accounts WHERE account_id='" . $this->account_id . "'",__LINE__,__FILE__);
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

			return $this->data;
		}

		function save_repository()
		{
			$this->db->query("UPDATE phpgw_accounts SET account_firstname='" . $this->data['firstname']
				. "', account_lastname='" . $this->data['lastname'] . "', account_status='"
				. $this->data['status'] . "', account_expires='" . $this->data['expires']
				. "' WHERE account_id='"
				. $this->account_id . "'",__LINE__,__FILE__);
		}

		function delete($accountid = '')
		{
			$account_id = get_account_id($accountid);

			/* Do this last since we are depending upon this record to get the account_lid above */
			$tables_array = Array('phpgw_accounts');
			$this->db->lock($tables_array);
			$this->db->query('DELETE FROM phpgw_accounts WHERE account_id='.$account_id);
			$this->db->unlock();
		}

		function get_list($_type='both',$start = '',$sort = '', $order = '', $query = '', $offset = '')
		{
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
					$whereclause .= ' WHERE ';
				}

				$whereclause .= " account_firstname LIKE '%$query%' OR account_lastname LIKE "
					. "'%$query%' OR account_lid LIKE '%$query%' ";
				if ($whereclause)
				{
					$whereclause .= ' ) ';
				}
			}

			$sql = "SELECT * FROM phpgw_accounts $whereclause $orderclause";
			if ($offset)
			{
				$this->db->limit_query($sql,$start,__LINE__,__FILE__);
			}
			elseif ($start)
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
					'account_expires'   => $this->db->f('account_expires')
				);
			}
			$this->total = count($accounts);
			return $accounts;
		}

		function name2id($account_lid)
		{
			static $name_list;

			if (! $account_lid)
			{
				return False;
			}

			if($name_list[$account_lid] && $name_list[$account_lid] != '')
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

			if (! $account_id)
			{
				return False;
			}

			if($id_list[$account_id])
			{
				return $id_list[$account_id];
			}				
	
			$this->db->query("SELECT account_lid FROM phpgw_accounts WHERE account_id=".$account_id,__LINE__,__FILE__);
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

		function get_type($accountid)
		{
			static $account_type;

			$account_id = get_account_id($accountid);
			if(@isset($account_type[$account_id]) && @$account_type[$account_id])
			{
				return $account_type[$account_id];
			}
			$this->db->query("SELECT account_type FROM phpgw_accounts WHERE account_id=".$account_id,__LINE__,__FILE__);
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

		function exists($account_lid)
		{
			static $by_id, $by_lid;

			$sql = "SELECT count(account_id) FROM phpgw_accounts WHERE ";
			if(is_integer($account_lid))
			{
				if(@isset($by_id[$account_lid]) && $by_id[$account_lid] != '')
				{
					return $by_id[$account_lid];
				}
				$sql .= "account_id = ".$account_lid;
			}
			else
			{
				if(@isset($by_lid[$account_lid]) && $by_lid[$account_lid] != '')
				{
					return $by_lid[$account_lid];
				}
				$sql .= "account_lid = '".$account_lid."'";
			}

			$this->db->query($sql,__LINE__,__FILE__);
			$this->db->next_record();
			$ret_val = $this->db->f(0) > 0;
			if(gettype($account_lid) == 'integer')
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
			$this->db->query("insert into phpgw_accounts (account_lid, account_type, account_pwd, "
				. "account_firstname, account_lastname, account_status, account_expires) values ('"
				. $account_info['account_lid'] . "','" . $account_info['account_type'] . "','"
				. md5($account_info['account_passwd']) . "', '" . $account_info['account_firstname']
				. "','" . $account_info['account_lastname'] . "','" . $account_info['account_status']
				. "','" . $account_info['account_expires'] . "')",__LINE__,__FILE__);
		}

		function auto_add($accountname, $passwd, $default_prefs = False, $default_acls = False, $expiredate = 0, $account_status = 'A')
		{
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

			$this->db->transaction_begin();
			$this->create($acct_info);
			$accountid = $this->name2id($accountname);

			if($default_prefs)
			{
				$GLOBALS['phpgw']->preferences->create_defaults($accountid);
			}

			if ($default_acls == False)
			{
				$default_group_lid = $GLOBALS['phpgw_info']['server']['default_group_lid'];
				$default_group_id  = $this->name2id($default_group_lid);
				$defaultgroupid = $default_group_id ? $default_group_id : $this->name2id('Default');
				if ($defaultgroupid)
				{
					$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('phpgw_group', "
						. $defaultgroupid . ", " . $accountid . ", 1)",__LINE__,__FILE__);
				}
				else
				{
					// If they don't have a default group, they need some sort of permissions.
					// This generally doesn't / shouldn't happen, but will (jengo)
					$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('preferences', 'changepassword', ".$accountid.", 1)",__LINE__,__FILE__);
					$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('addressbook', 'run', ".$accountid.", 1)",__LINE__,__FILE__);
					$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('filemanager', 'run', ".$accountid.", 1)",__LINE__,__FILE__);
					$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('calendar', 'run', ".$accountid.", 1)",__LINE__,__FILE__);
					$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('email', 'run', ".$accountid.", 1)",__LINE__,__FILE__);
					$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('notes', 'run', ".$accountid.", 1)",__LINE__,__FILE__);
					$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('todo', 'run', ".$accountid.", 1)",__LINE__,__FILE__);
				}
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
			$db = $GLOBALS['phpgw']->db;
			$db->query('select account_lid,account_firstname,account_lastname from phpgw_accounts where account_id='.$account_id,__LINE__,__FILE__);
			$db->next_record();
			$account_name[$account_id]['lid']   = $db->f('account_lid');
			$account_name[$account_id]['fname'] = $db->f('account_firstname');
			$account_name[$account_id]['lname'] = $db->f('account_lastname');
			$lid   = $account_name[$account_id]['lid'];
			$fname = $account_name[$account_id]['fname'];
			$lname = $account_name[$account_id]['lname'];
			return;
		}
	} //end of class
?>
