<?php
  /**************************************************************************\
  * phpGroupWare API - phpgw Inter-server communications class               *
  * This file written by Miles Lott <milosch@phpgroupware.org>               *
  * Maintain list and provide send interface to remote phpgw servers         *
  * Copyright (C) 2001 Miles Lott                                            *
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

	class interserver
	{
		var $db;
		var $accounts;
		var $table = 'phpgw_interserv';
		var $total = 0;
		var $result = '';

		var $servers = array();
		var $serverid = 0;
		var $security = '';
		var $mode = '';
		var $authed = False;
		var $sessionid = '';
		var $kp3 = '';
		var $urlparts = array(
			'xmlrpc' => '/phpgroupware/xmlrpc.php',
			'soap'   => '/phpgroupware/soap.php'
		);

		/*
		0/none == no access
		1/apps == read app data only
		99/all == read accounts and other api data
		Two servers must have each other setup as 'all' for full coop
		*/
		var $trust_levels = array(
			'none' => 0,
			'apps' => 1,
			'all'  => 99
		);

		/*
		0 - No trust, but they may trust us
		1 - Trust to make requests of us
		2 - Trust remote server's trusts also
		3 - We both trust each other
		4 - We both trust each other, and we trust the remote server's trusts also
		*/
		var $trust_relationships = array(
			'outbound'       => 0,
			'inbound'        => 1,
			'passthrough'    => 2,
			'bi-directional' => 3,
			'bi-dir passthrough' => 4
		);

		var $security_types = array(
			'standard' => '',
			'ssl'      => 'ssl'
		);

		var $server_modes = array(
			'XML-RPC' => 'xmlrpc',
			'SOAP'    => 'soap'
		);

		function interserver($serverid='')
		{
			$this->db = $GLOBALS['phpgw']->db;
			if($serverid)
			{
				$this->serverid = intval($serverid);
				$this->setup();
			}
		}

		function debug($str,$debug=False)
		{
			if($debug)
			{
				$this->_debug($str);
			}
		}

		function _debug($err='')
		{
			if(!$err)
			{
				return;
			}
			echo $err . '&nbsp;';
		}

		function setup()
		{
			$this->read_repository();
			if($this->server['trust_level'])
			{
				$this->accounts = CreateObject('phpgwapi.accounts');
				$this->accounts->server = $this->serverid;
			}
			$this->security = $this->server['server_security'];
			$this->mode = $this->server['server_mode'];
		}

		/* send command to remote server */
		function send($method_name, $args, $url, $debug=True)
		{
			$cmd = '$this->_send_' . $this->mode . '_' . $this->security . '($method_name, $args, $url, $debug);';
			eval($cmd);
			return $this->result;
		}

		function _split_url($url)
		{
			preg_match('/^(.*?\/\/.*?)(\/.*)/',$url,$matches);
			$hostpart = $matches[1];
			$uri = $matches[2];
			return array($uri,$hostpart);
		}

		function _send_xmlrpc_ssl($method_name, $args, $url, $debug=True)
		{
			list($uri,$hostpart) = $this->_split_url($url . $this->urlparts['xmlrpc']);
			$hostpart = ereg_replace('https://','',$hostpart);
			$hostpart = ereg_replace('http://','',$hostpart);
			if(gettype($args) != 'array')
			{
				$arr[] = CreateObject('phpgwapi.xmlrpcval',$args,'string');
				$f = CreateObject('phpgwapi.xmlrpcmsg', $method_name, $arr,'string');
			}
			else
			{
				while(list($key,$val) = @each($args))
				{
					if(gettype($val) == 'array')
					{
						while(list($x,$y) = each($val))
						{
							$tmp[$x] = CreateObject('phpgwapi.xmlrpcval',$y, 'string');
						}
						$ele[$key] = CreateObject('phpgwapi.xmlrpcval',$tmp,'struct');
					}
					else
					{
						$ele[$key] = CreateObject('phpgwapi.xmlrpcval',$val, 'string');
					}
					$arr[] = CreateObject('phpgwapi.xmlrpcval',$ele,'struct');
					$f = CreateObject('phpgwapi.xmlrpcmsg', $method_name, $arr,'struct');
				}
			}

			$this->debug("<pre>" . htmlentities($f->serialize()) . "</pre>\n",$debug);
			$c = CreateObject('phpgwapi.xmlrpc_client',$this->urlparts['xmlrpc'], $hostpart, 80);
			$c->username = $this->sessionid;
			$c->password = $this->kp3;
			$c->setDebug(0);
			$r = $c->send($f,0,True);
			if (!$r)
			{
				$this->debug('send failed');
			}
			$v = $r->value();
			if (!$r->faultCode())
			{
				$this->debug('<hr>I got this value back<br><pre>' . htmlentities($r->serialize()) . '</pre><hr>',$debug);
			}
			else
			{
				$this->debug('Fault Code: ' . $r->faultCode() . ' Reason "' . $r->faultString() . '"<br>',$debug);
			}

			$this->result = xmlrpc_decode($v);
			return $this->result;
		}

		function _send_xmlrpc_($method_name, $args, $url, $debug=True)
		{
			list($uri,$hostpart) = $this->_split_url($url . $this->urlparts['xmlrpc']);
			$hostpart = ereg_replace('https://','',$hostpart);
			$hostpart = ereg_replace('http://','',$hostpart);
			if(gettype($args) != 'array')
			{
				$arr[] = CreateObject('phpgwapi.xmlrpcval',$args,'string');
				$f = CreateObject('phpgwapi.xmlrpcmsg', $method_name, $arr,'string');
			}
			else
			{
				while(list($key,$val) = @each($args))
				{
					if(gettype($val) == 'array')
					{
						while(list($x,$y) = each($val))
						{
							$tmp[$x] = CreateObject('phpgwapi.xmlrpcval',$y, 'string');
						}
						$ele[$key] = CreateObject('phpgwapi.xmlrpcval',$tmp,'struct');
					}
					else
					{
						$ele[$key] = CreateObject('phpgwapi.xmlrpcval',$val, 'string');
					}
				}
				$arr[] = CreateObject('phpgwapi.xmlrpcval',$ele,'struct');
				$f = CreateObject('phpgwapi.xmlrpcmsg', $method_name, $arr,'struct');
			}

			$this->debug('<pre>' . htmlentities($f->serialize()) . '</pre>' . "\n",$debug);
			$c = CreateObject('phpgwapi.xmlrpc_client',$this->urlparts['xmlrpc'], $hostpart, 80);
			$c->username = $this->sessionid;
			$c->password = $this->kp3;
//			_debug_array($c);
			$c->setDebug(0);
			$r = $c->send($f);
			if (!$r)
			{
				$this->debug('send failed');
			}
			$v = $r->value();
			if (!$r->faultCode())
			{
				$this->debug('<hr>I got this value back<br><pre>' . htmlentities($r->serialize()) . '</pre><hr>',$debug);
			}
			else
			{
				$this->debug('Fault Code: ' . $r->faultCode() . ' Reason "' . $r->faultString() . '"<br>',$debug);
			}

			$this->result = xmlrpc_decode($v);
			return $this->result;
		}

		function _send_soap_ssl($method_name, $args, $url, $debug=True)
		{
			$method_name = str_replace('.','_',$method_name);
			list($uri,$hostpart) = $this->_split_url($url . $this->urlparts['soap']);
			$hostpart = ereg_replace('https://','',$hostpart);
			$hostpart = ereg_replace('http://','',$hostpart);
			if(gettype($args) != 'array')
			{
				$arr[] = CreateObject('phpgwapi.soapval','','string',$args);
			}
			else
			{
				while(list($key,$val) = @each($args))
				{
					if(gettype($val) == 'array')
					{
						while(list($x,$y) = each($val))
						{
							$tmp[] = CreateObject('phpgwapi.soapval',$x,'string',$y);
						}
						$arr[] = CreateObject('phpgwapi.soapval',$key, 'array',$tmp);
					}
					else
					{
						$arr[] = CreateObject('phpgwapi.soapval',$key, 'string',$val);
					}
				}
			}

			$soap_message = CreateObject('phpgwapi.soapmsg',$method_name,$arr);
			/* print_r($soap_message);exit; */
			$soap = CreateObject('phpgwapi.soap_client',$uri,$hostpart);
			$soap->username = $this->sessionid;
			$soap->password = $this->kp3;
			/* _debug_array($soap);exit; */
			if($r = $soap->send($soap_message,$method_name))
			{
				$this->debug('<hr>I got this value back<br><pre>' . htmlentities($r->serialize()) . '</pre><hr>',$debug);
				$this->result = $r->decode();
				return $this->result;
			}
			else
			{
				$this->debug('Fault Code: ' . $r->ernno . ' Reason "' . $r->errstring . '"<br>',$debug);
			}
		}

		function _send_soap_($method_name, $args, $url, $debug=True)
		{
			$method_name = str_replace('.','_',$method_name);
			list($uri,$hostpart) = $this->_split_url($url . $this->urlparts['soap']);
			$hostpart = ereg_replace('https://','',$hostpart);
			$hostpart = ereg_replace('http://','',$hostpart);
			if(gettype($args) != 'array')
			{
				$arr[] = CreateObject('phpgwapi.soapval','','string',$args);
			}
			else
			{
				while(list($key,$val) = @each($args))
				{
					if(gettype($val) == 'array')
					{
						while(list($x,$y) = each($val))
						{
							$tmp[] = CreateObject('phpgwapi.soapval',$x,'string',$y);
						}
						$arr[] = CreateObject('phpgwapi.soapval',$key, 'array',$tmp);
					}
					else
					{
						$arr[] = CreateObject('phpgwapi.soapval',$key, 'string',$val);
					}
				}
			}
			$soap_message = CreateObject('phpgwapi.soapmsg',$method_name,$arr);
			$soap = CreateObject('phpgwapi.soap_client',$uri,$hostpart);
			$soap->username = $this->sessionid;
			$soap->password = $this->kp3;
			/* _debug_array($soap_message); */
			if($r = $soap->send($soap_message,$method_name))
			{
				_debug_array(htmlentities($soap->outgoing_payload));
				$this->debug('<hr>I got this value back<br><pre>' . htmlentities($r->serialize()) . '</pre><hr>',$debug);
				$this->result = $r->decode();
				return $this->result;
			}
			else
			{
				_debug_array($soap->outgoing_payload);
				$this->debug('Fault Code: ' . $r->ernno . ' Reason "' . $r->errstring . '"<br>',$debug);
			}
		}

		/* Following are for server list management and query */
		function read_repository($serverid='')
		{
			if(!$serverid)
			{
				$serverid = $this->serverid;
			}
			$sql = "SELECT * FROM $this->table WHERE server_id=" . intval($serverid);
			$this->db->query($sql,__LINE__,__FILE__);
			if($this->db->next_record())
			{
				$this->server['server_name'] = $this->db->f('server_name');
				$this->server['server_url']  = $this->db->f('server_url');
				$this->server['server_mode'] = $this->db->f('server_mode');
				$this->server['server_security'] = $this->db->f('server_security');
				$this->server['trust_level'] = $this->db->f('trust_level');
				$this->server['trust_rel']   = $this->db->f('trust_rel');
				$this->server['username']    = $this->db->f('username');
				$this->server['password']    = $this->db->f('password');
				$this->server['admin_name']  = $this->db->f('admin_name');
				$this->server['admin_email'] = $this->db->f('admin_email');
			}
			return $this->server;
		}

		function save_repository($serverid='')
		{
			if(!$serverid)
			{
				$serverid = $this->serverid;
			}
			if($serverid && gettype($this->server) == 'array')
			{
				$sql = "UPDATE $this->table SET "
					. "server_name='" . $this->server['server_name'] . "',"
					. "server_url='"  . $this->server['server_url']  . "',"
					. "server_mode='" . $this->server['server_mode']  . "',"
					. "server_security='" . $this->server['server_security']  . "',"
					. "trust_level="  . intval($this->server['trust_level']) . ","
					. "trust_rel="    . intval($this->server['trust_rel']) . ","
					. "username='"    . $this->server['username']  . "',"
					. "password='"    . $this->server['password']  . "',"
					. "admin_name='"  . $this->server['admin_name']  . "',"
					. "admin_email='" . $this->server['admin_email'] . "' "
					. "WHERE server_id=" . intval($serverid);
				$this->db->query($sql,__LINE__,__FILE__);
				return True;
			}
			return False;
		}

		function create($server_info='')
		{
			if(gettype($server_info) != 'array')
			{
				return False;
			}
			$sql = "INSERT INTO $this->table (server_name,server_url,server_mode,server_security,"
				. "trust_level,trust_rel,username,password,admin_name,admin_email) "
				. "VALUES('" . $server_info['server_name'] . "','" . $server_info['server_url'] . "','"
				. $server_info['server_mode'] . "','" . $server_info['server_security'] . "',"
				. intval($server_info['trust_level']) . "," . intval($server_info['trust_rel']) . ",'"
				. $server_info['username'] . "','" . $server_info['password'] . "','"
				. $server_info['admin_name'] . "','" . $server_info['admin_email'] . "')";
			$this->db->query($sql,__LINE__,__FILE__);

			$sql = "SELECT server_id FROM $this->table WHERE server_name='" . $server_info['server_name'] . "'";
			$this->db->query($sql,__LINE__,__FILE__);
			if($this->db->next_record())
			{
				$server_info['server_id'] = $this->db->f(0);
				$this->serverid = $server_info['server_id'];
				$this->server   = $server_info;
				return $this->serverid;
			}
			return False;
		}

		function delete($serverid='')
		{
			if(!$serverid)
			{
				$serverid = $this->serverid;
			}
			if($serverid)
			{
				$sql = "DELETE FROM $this->table WHERE server_id=$serverid";
				$this->db->query($sql,__LINE__,__FILE__);
				return True;
			}
			return False;
		}

		function get_list($start='',$sort='',$order='',$query='',$offset='')
		{
			$sql = "SELECT * FROM $this->table";
			$this->db->query($sql,__LINE__,__FILE__);
			
			while ($this->db->next_record())
			{
				$this->servers[$this->db->f('server_name')]['server_id']   = $this->db->f('server_id');
				$this->servers[$this->db->f('server_name')]['server_name'] = $this->db->f('server_name');
				$this->servers[$this->db->f('server_name')]['server_url']  = $this->db->f('server_url');
				$this->servers[$this->db->f('server_name')]['server_mode'] = $this->db->f('server_mode');
				$this->servers[$this->db->f('server_name')]['server_security'] = $this->db->f('server_security');
				$this->servers[$this->db->f('server_name')]['trust_level'] = $this->db->f('trust_level');
				$this->servers[$this->db->f('server_name')]['trust_rel']   = $this->db->f('trust_rel');
				$this->servers[$this->db->f('server_name')]['admin_name']  = $this->db->f('admin_name');
				$this->servers[$this->db->f('server_name')]['admin_email'] = $this->db->f('admin_email');
			}
			$this->total = $this->db->num_rows() + 1;
			return $this->servers;
		}

		function formatted_list($server_id=0,$java=False)
		{
			if ($java)
			{
				$jselect = ' onChange="this.form.submit();"';
			}
			$select  = "\n" .'<select name="server_id"' . $jselect . ">\n";
			$select .= '<option value="0"';
			if(!$server_id)
			{
				$select .= ' selected';
			}
			$select .= '>' . lang('Please Select') . '</option>'."\n";

			$slist = $this->get_list();
			while (list($key,$val) = each($slist))
			{
				$foundservers = True;
				$select .= '<option value="' . $val['server_id'] . '"';
				if ($val['server_id'] == $server_id)
				{
					$select .= ' selected';
				}
				$select .= '>' . $val['server_name'] . '</option>'."\n";
			}

			$select .= '</select>'."\n";
			$select .= '<noscript><input type="submit" name="server_id_select" value="' . lang('Select') . '"></noscript>' . "\n";
			if(!$foundservers)
			{
				$select = '';
			}

			return $select;
		}

		function name2id($server_name='')
		{
			if(!$server_name)
			{
				$server_name = $this->server['server_name'];
			}
			if($server_name)
			{
				$sql = "SELECT server_id FROM $this->table WHERE server_name='$server_name'";
				$this->db->query($sql,__LINE__,__FILE__);
				if($this->db->next_record())
				{
					$serverid = $this->db->f(0);
					return $serverid;
				}
			}
			return False;
		}

		function id2name($serverid='')
		{
			if(!$serverid)
			{
				$serverid = $this->serverid;
			}
			if($serverid)
			{
				$sql = "SELECT server_name FROM $this->table WHERE serverid=$serverid";
				$this->db->query($sql,__LINE__,__FILE__);
				if($this->db->next_record())
				{
					$server_name = $this->db->f(0);
					return $server_name;
				}
			}
			return False;
		}

		function exists($serverdata='')
		{
			if(!$serverdata)
			{
				return False;
			}
			if(gettype($serverdata) == 'integer')
			{
				$serverid = $serverdata;
				settype($server_name,'string');
				$server_name = $this->id2name($serverid);
			}
			else
			{
				$server_name = $serverdata;
			}
			$sql = "SELECT server_id FROM $this->table WHERE server_name='$server_name'";
			$this->db->query($sql,__LINE__,__FILE__);
			if($this->db->next_record())
			{
				return True;
			}
			return False;
		}

		function auth($serverdata='')
		{
			if(!$serverdata || gettype($serverdata) != 'array')
			{
				return False;
			}
			$server_name = $serverdata['server_name'];
			$username    = $serverdata['username'];
			$password    = $serverdata['password'];

			$sql = "SELECT server_id,trust_rel FROM $this->table WHERE server_name='$server_name'";
			$this->db->query($sql,__LINE__,__FILE__);
			if($this->db->next_record())
			{
				if ($username == $GLOBALS['phpgw_info']['server']['site_username'] &&
					$password == $GLOBALS['phpgw_info']['server']['site_password'] &&
					$this->db->f('trust_rel') >= 1)
				{
					$this->authed = True;
					return True;
				}
			}
			return False;
		}
	}
?>
