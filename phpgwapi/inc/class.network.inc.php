<?php
  /**************************************************************************\
  * phpGroupWare API - Network                                               *
  * This file written by Mark Peters <skeeter@phpgroupware.org>              *
  * Handles opening network socket connections, taking proxy into account    *
  * Copyright (C) 2000, 2001 Mark Peters                                     *
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

	class network
	{
		var $socket;
		var $addcrlf = TRUE;
		var $error;
		var $errorset = 0;

		function network($addcrlf=true)
		{
			$this->errorset = 0;
			$this->set_addcrlf($addcrlf);
		}

		function set_addcrlf($value)
		{
			$this->addcrlf = $value;
		}

		function add_crlf($str)
		{
			if($this->addcrlf)
			{
				$str .= "\r\n";
			}
			return $str;
		}

		function set_error($code,$msg,$desc)
		{
			$this->error = array('code','msg','desc');
			$this->error['code'] = $code;
			$this->error['msg'] = $msg;
			$this->error['desc'] = $desc;
	//		$this->close_port();
			$this->errorset = 1;
			return 0;
		}

		function open_port($server,$port,$timeout=15)
		{
			switch($port)
			{
				case 80:
				case 443:
					if((isset($GLOBALS['phpgw_info']['server']['httpproxy_server']) && $GLOBALS['phpgw_info']['server']['httpproxy_server']) &&
						(isset($GLOBALS['phpgw_info']['server']['httpproxy_port']) && $GLOBALS['phpgw_info']['server']['httpproxy_port']))
					{
						$server = $GLOBALS['phpgw_info']['server']['httpproxy_server'];
						$port   = (int)$GLOBALS['phpgw_info']['server']['httpproxy_port'];
					}
					break;
			}
			if(floor(phpversion()) == 4)
			{
				$this->socket = fsockopen($server,$port,$errcode,$errmsg,$timeout);
			}
			else
			{
				$this->socket = fsockopen($server,$port,$errcode,$errmsg);
			}
			if(!$this->socket)
			{
				return $this->set_error('Error',$errcode.':'.$errmsg,'Connection to '.$server.':'.$port.' failed - could not open socket.');
			}
			else
			{
				return 1;
			}
		}

		function close_port()
		{
			return fclose($this->socket);
		}

		function read_port()
		{
			return fgets($this->socket, 1024);
		}

		function bs_read_port($bytes)
		{
			return fread($this->socket, $bytes);
		}

		function write_port($str)
		{
			$ok = fputs($this->socket,$this->add_crlf($str));
			if(!$ok)
			{
				return $this->set_error('Error','Connection Lost','lost connection to server');
			}
			else
			{
				return 1;
			}
		}

		function bs_write_port($str,$bytes=0)
		{
			if($bytes)
			{
				$ok = fwrite($this->socket,$this->add_crlf($str),$bytes);
			}
			else
			{
				$ok = fwrite($this->socket,$this->add_crlf($str));
			}
			if(!$ok)
			{
				return $this->set_error('Error','Connection Lost','lost connection to server');
			}
			else
			{
				return 1;
			}
		}

		function msg2socket($str,$expected_response,&$response)
		{
			if(!$this->socket)
			{
				return $this->set_error('521','socket does not exist',
					'The required socked does not exist.  The settings for your mail server may be wrong.');
			}
			if(!$this->write_port($str))
			{
				if(substr($expected_response,1,1) == '+')
				{
					return $this->set_error('420','lost connection','Lost connection to pop server.');
				}
				else
				{
					return 0;
				}
			}
			$response = $this->read_port();
			if(!ereg(strtoupper($expected_response),strtoupper($response)))
			{
				if(substr($expected_response,1,1) == '+')
				{
					return $this->set_error('550','','');
				}
				$pos = strpos(' ',$response);
				return $this->set_error(substr($response,0,$pos),
					'invalid response('.$expected_response.')',
					substr($response,($pos + 1),(strlen($response)-$pos)));
			}
			else
			{
				return 1;
			}
		}

		/*
		 Return contents of a web url as an array or false if timeout.
		 If $string is set (True), a string is returned with all \r and \n removed.
		 This allows for parsing of xml where the formatting was not ideal (elements
		 opened and closed on a single line).
		*/
		function gethttpsocketfile($file,$string=False)
		{
			$server = str_replace('http://','',$file);
			$file = strstr($server,'/');
			$server = str_replace($file,'',$server);
			if($GLOBALS['phpgw_info']['server']['httpproxy_server'])
			{
				if ($this->open_port($server,80, 15))
				{
					if(!$this->write_port('GET http://' . $server . $file . ' HTTP/1.0'."\n\n"))
					{
						return False;
					}
					$i = 0;
					while($line = $this->read_port())
					{
						if(feof($this->socket))
						{
							break;
						}
						if($string)
						{
							$line = ereg_replace("\n",'',$line);
							$line = ereg_replace("\r",'',$line);
							$lines .= $line;
						}
						else
						{
							$lines[] = $line;
						}
						$i++;
					}
					$this->close_port();
					return $lines;
				}
				else
				{
					return False;
				}
			}
			else
			{
				if($this->open_port($server, 80, 15))
				{
					if(!$this->write_port('GET '.$file.' HTTP/1.0'."\n".'Host: '.$server."\n\n"))
					{
						return 0;
					}
					while($line = $this->read_port())
					{
						if($string)
						{
							$line = ereg_replace("\n",'',$line);
							$line = ereg_replace("\r",'',$line);
							$lines .= $line;
						}
						else
						{
							$lines[] = $line;
						}
					}
					$this->close_port();
					return $lines;
				}
				else
				{
					return 0;
				}
			}
		}
	}
?>
