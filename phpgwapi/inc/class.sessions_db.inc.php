<?php
  /**************************************************************************\
  * phpGroupWare API - Session management                                    *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * and Joseph Engo <jengo@phpgroupware.org>                                 *
  * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
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

	class sessions_
	{
		function sessions_()
		{
			// empty for now, but needed
		}
		
		function read_session($sessionid)
		{
			$this->db->query("SELECT * FROM phpgw_sessions WHERE session_id='" . $this->sessionid . "'",__LINE__,__FILE__);
			$this->db->next_record();
			
			return $this->db->Record;
		}

		// This will remove stale sessions out of the database
		function clean_sessions()
		{
			// If you plan on using the cron apps, please remove the following lines.
			// I am going to make this a config option durring 0.9.11, instead of an application (jengo)

			$GLOBALS['phpgw']->db->query("DELETE FROM phpgw_sessions WHERE session_dla <= '" . (time() - $GLOBALS['phpgw_info']['server']['sessions_timeout'])
				. "' AND session_flags !='A'",__LINE__,__FILE__);

			// This is set a little higher, we don't want to kill session data for anonymous sessions.
			$GLOBALS['phpgw']->db->query("DELETE FROM phpgw_app_sessions WHERE session_dla <= '" . (time() - $GLOBALS['phpgw_info']['server']['sessions_timeout'])
				. "'",__LINE__,__FILE__);
		}

		function set_cookie_params($domain)
		{
			// only for php4-sessions
		}

		function register_session($login,$user_ip,$now,$session_flags)
		{
			$GLOBALS['phpgw']->db->query("INSERT INTO phpgw_sessions VALUES ('" . $this->sessionid
				. "','".$login."','" . $user_ip . "','"
				. $now . "','" . $now . "','" . $GLOBALS['PHP_SELF'] . "','" . $session_flags
				. "')",__LINE__,__FILE__);
		}

		// This will update the DateLastActive column, so the login does not expire
		function update_dla()
		{
			if (@isset($_GET['menuaction']))
			{
				$action = $_GET['menuaction'];
			}
			else
			{
				$action = $_SERVER['PHP_SELF'];
			}

			// This way XML-RPC users aren't always listed as
			// xmlrpc.php
			if ($this->xmlrpc_method_called)
			{
				$action = $this->xmlrpc_method_called;
			}

			$GLOBALS['phpgw']->db->query("UPDATE phpgw_sessions SET session_dla='" . time() . "', session_action='$action' "
				. "WHERE session_id='" . $this->sessionid."'",__LINE__,__FILE__);

			$GLOBALS['phpgw']->db->query("UPDATE phpgw_app_sessions SET session_dla='" . time() . "' "
				. "WHERE sessionid='" . $this->sessionid."'",__LINE__,__FILE__);
			return True;
		}

		function destroy($sessionid, $kp3)
		{
			if (! $sessionid && $kp3)
			{
				return False;
			}

			$GLOBALS['phpgw']->db->transaction_begin();
			$GLOBALS['phpgw']->db->query("DELETE FROM phpgw_sessions WHERE session_id='"
				. $sessionid . "'",__LINE__,__FILE__);
			$GLOBALS['phpgw']->db->query("DELETE FROM phpgw_app_sessions WHERE sessionid='"
				. $sessionid . "'",__LINE__,__FILE__);
			$this->log_access($this->sessionid);	// log logout-time

			// Only do the following, if where working with the current user
			if ($sessionid == $GLOBALS['phpgw_info']['user']['sessionid'])
			{
				$this->clean_sessions();
			}
			$GLOBALS['phpgw']->db->transaction_commit();

			return True;
		}

		/*************************************************************************\
		* Functions for appsession data and session cache                         *
		\*************************************************************************/

		function delete_cache($accountid='')
		{
			$account_id = get_account_id($accountid,$this->account_id);

			$query = "DELETE FROM phpgw_app_sessions WHERE loginid = '".$account_id."'"
				." AND app = 'phpgwapi' AND location = 'phpgw_info_cache'";

			$GLOBALS['phpgw']->db->query($query);
		}

		function appsession($location = 'default', $appname = '', $data = '##NOTHING##')
		{
			if (! $appname)
			{
				$appname = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}
			
			/* This allows the user to put '' as the value. */
			if ($data == '##NOTHING##')
			{
				$query = "SELECT content FROM phpgw_app_sessions WHERE"
					." sessionid='".$this->sessionid."' AND loginid='".$this->account_id."'"
					." AND app = '".$appname."' AND location='".$location."'";
	
				$GLOBALS['phpgw']->db->query($query,__LINE__,__FILE__);
				$GLOBALS['phpgw']->db->next_record();

				// I added these into seperate steps for easier debugging
				$data = $GLOBALS['phpgw']->db->f('content');
				// Changed by Skeeter 2001 Mar 04 0400Z
				// This was not properly decoding structures saved into session data properly
//				$data = $GLOBALS['phpgw']->common->decrypt($data);
//				return stripslashes($data);
				// Changed by milosch 2001 Dec 20
				// do not stripslashes here unless this proves to be a problem.
				// Changed by milosch 2001 Dec 25
				/* do not decrypt and return if no data (decrypt returning garbage) */
				if($data)
				{
					$data = $GLOBALS['phpgw']->crypto->decrypt($data);
//					echo 'appsession returning: '; _debug_array($data);
					return $data;
				}
			}
			else
			{
				$GLOBALS['phpgw']->db->query("SELECT content FROM phpgw_app_sessions WHERE "
					. "sessionid = '".$this->sessionid."' AND loginid = '".$this->account_id."'"
					. " AND app = '".$appname."' AND location = '".$location."'",__LINE__,__FILE__);

				$encrypteddata = $GLOBALS['phpgw']->crypto->encrypt($data);
				$encrypteddata = $GLOBALS['phpgw']->db->db_addslashes($encrypteddata);

				if ($GLOBALS['phpgw']->db->num_rows()==0)
				{
					$GLOBALS['phpgw']->db->query("INSERT INTO phpgw_app_sessions (sessionid,loginid,app,location,content,session_dla) "
						. "VALUES ('".$this->sessionid."','".$this->account_id."','".$appname
						. "','".$location."','".$encrypteddata."','" . time() . "')",__LINE__,__FILE__);
				}
				else
				{
					$GLOBALS['phpgw']->db->query("UPDATE phpgw_app_sessions SET content='".$encrypteddata."'"
						. "WHERE sessionid = '".$this->sessionid."'"
						. "AND loginid = '".$this->account_id."' AND app = '".$appname."'"
						. "AND location = '".$location."'",__LINE__,__FILE__);
				}
				return $data;
			}
		}

		function list_sessions($start,$order,$sort)
		{
			$values = array();
			
			$ordermethod = 'order by session_dla asc';
			$this->db->limit_query("select * from phpgw_sessions where session_flags != 'A' order by $sort $order",$start,__LINE__,__FILE__);

			while ($this->db->next_record())
			{
				$values[] = array(
					'session_id'        => $this->db->f('session_id'),
					'session_lid'       => $this->db->f('session_lid'),
					'session_ip'        => $this->db->f('session_ip'),
					'session_logintime' => $this->db->f('session_logintime'),
					'session_action'    => $this->db->f('session_action'),
					'session_dla'       => $this->db->f('session_dla')
				);
			}
			return $values;
		}
		
		/*!
		@function total
		@abstract get number of normal / non-anonymous sessions
		*/
		function total()
		{
			$this->db->query("select count(*) from phpgw_sessions where session_flags != 'A'",__LINE__,__FILE__);
			$this->db->next_record();

			return $this->db->f(0);
		}
	}
?>
