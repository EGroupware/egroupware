<?php
// Copyright (c) 1999,2000,2001 Edd Dumbill.
// All rights reserved.
//
// Redistribution and use in source and binary forms, with or without
// modification, are permitted provided that the following conditions
// are met:
//
//    * Redistributions of source code must retain the above copyright
//      notice, this list of conditions and the following disclaimer.
//
//    * Redistributions in binary form must reproduce the above
//      copyright notice, this list of conditions and the following
//      disclaimer in the documentation and/or other materials provided
//      with the distribution.
//
//    * Neither the name of the "XML-RPC for PHP" nor the names of its
//      contributors may be used to endorse or promote products derived
//      from this software without specific prior written permission.
//
// THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
// "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
// LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
// FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
// REGENTS OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
// INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
// (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
// SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
// HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
// STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
// ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
// OF THE POSSIBILITY OF SUCH DAMAGE.

	class xmlrpc_client
	{
		var $path;
		var $server;
		var $port;
		var $errno;
		var $errstring;
		var $debug = 0;
		var $username = '';
		var $password = '';
		var $cert     = '';
		var $certpass = '';

		function xmlrpc_client($path='', $server='', $port=0)
		{
			$this->port   = $port;
			$this->server = $server;
			$this->path   = $path;
		}

		function setDebug($in)
		{
			if($in)
			{
				$this->debug = 1;
			}
			else
			{
				$this->debug = 0;
			}
		}

		function setCredentials($u, $p)
		{
			$this->username = $u;
			$this->password = $p;
		}

		function setCertificate($cert, $certpass)
		{
			$this->cert     = $cert;
			$this->certpass = $certpass;
		}

		function send($msg, $timeout=0, $method='http')
		{
			/* where msg is an xmlrpcmsg */
			$msg->debug = $this->debug;
 
			if($method == 'https')
			{
				return $this->sendPayloadHTTPS(
					$msg,
					$this->server,
					$this->port,
					$timeout,
					$this->username,
					$this->password,
					$this->cert,
					$this->certpass
				);
			}
			else
			{
				return $this->sendPayloadHTTP10(
					$msg,
					$this->server,
					$this->port,
					$timeout,
					$this->username, 
					$this->password
				);
			}
		}

		function sendPayloadHTTP10($msg, $server, $port, $timeout=0,$username='', $password='')
		{
			if($port == 0)
			{
				$port = 80;
			}
			if($timeout>0)
			{
				$fp = fsockopen($server, $port, $this->errno, $this->errstr, $timeout);
			}
			else
			{
				$fp = fsockopen($server, $port, $this->errno, $this->errstr);
			}
			if(!$fp)
			{
				$r = CreateObject(
					'phpgwapi.xmlrpcresp',
					'',
					$GLOBALS['xmlrpcerr']['http_error'],
					$GLOBALS['xmlrpcstr']['http_error']
				);
				return $r;
			}
			// Only create the payload if it was not created previously
			if(empty($msg->payload))
			{
				$msg->createPayload();
			}

			// thanks to Grant Rauscher <grant7@firstworld.net>
			// for this
			$credentials = '';
			if($username && $password)
			{
				$credentials = 'Authorization: Basic ' . base64_encode($username . ':' . $password) . "\r\n";
			}

			$op = 'POST ' . $this->path . " HTTP/1.0\r\nUser-Agent: phpGroupWare\r\n"
				. 'Host: '. $this->server . "\r\n"
				. 'X-PHPGW-Server: '  . $this->server . ' ' . "\r\n"
				. 'X-PHPGW-Version: ' . $GLOBALS['phpgw_info']['server']['versions']['phpgwapi'] . "\r\n"
				. $credentials
				. "Content-Type: text/xml\r\nContent-Length: "
				. strlen($msg->payload) . "\r\n\r\n"
				. $msg->payload;

			if(!fputs($fp, $op, strlen($op)))
			{
				$this->errstr = 'Write error';
				return CreateObject(
					'phpgwapi.xmlrpcresp',
					'',
					$GLOBALS['xmlrpcerr']['http_error'],
					$GLOBALS['xmlrpcstr']['http_error']
				);
			}
			$resp = $msg->parseResponseFile($fp);
			fclose($fp);
			return $resp;
		}

		/* contributed by Justin Miller <justin@voxel.net> - requires curl to be built into PHP */
		function sendPayloadHTTPS($msg, $server, $port, $timeout=0,$username='', $password='', $cert='',$certpass='')
		{
			if(!function_exists('curl_init'))
			{
				return CreateObject(
					'phpgwapi.xmlrpcresp',
					'',
					$GLOBALS['xmlrpcerr']['no_ssl'],
					$GLOBALS['xmlrpcstr']['no_ssl']
				);
			}

			if($port == 0)
			{
				$port = 443;
			}
			/* Only create the payload if it was not created previously */
			if(empty($msg->payload))
			{
				$msg->createPayload();
			}

			$curl = curl_init('https://' . $server . ':' . $port . $this->path);

			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			// results into variable
			if($this->debug)
			{
				curl_setopt($curl, CURLOPT_VERBOSE, 1);
			}
			curl_setopt($curl, CURLOPT_USERAGENT, 'phpGroupWare');
			// required for XMLRPC
			curl_setopt($curl, CURLOPT_POST, 1);
			// post the data
			curl_setopt($curl, CURLOPT_POSTFIELDS, $msg->payload);
			// the data
			curl_setopt($curl, CURLOPT_HEADER, 1);
			// return the header too
			curl_setopt($curl, CURLOPT_HTTPHEADER, array(
				'X-PHPGW-Server: '  . $this->server,
				'X-PHPGW-Version: ' . $GLOBALS['phpgw_info']['server']['versions']['phpgwapi'],
				'Content-Type: text/xml'
			));
			if($timeout)
			{
				curl_setopt($curl, CURLOPT_TIMEOUT, $timeout == 1 ? 1 : $timeout - 1);
			}
			if($username && $password)
			{
				curl_setopt($curl, CURLOPT_USERPWD, "$username:$password");
			}
			if($cert)
			{
				curl_setopt($curl, CURLOPT_SSLCERT, $cert);
			}
			if($certpass)
			{
				curl_setopt($curl, CURLOPT_SSLCERTPASSWD,$certpass);
			}
			// set cert password

			$result = curl_exec($curl);

			if(!$result)
			{
				$this->errstr = 'Write error';
				$resp = CreateObject(
					'phpgwapi.xmlrpcresp',
					'',
					$GLOBALS['xmlrpcerr']['curl_fail'],
					$GLOBALS['xmlrpcstr']['curl_fail'] . ': ' . curl_error($curl)
				);
			}
			else
			{
				$resp = $msg->parseResponse($result);
			}
			curl_close($curl);

			return $resp;
		}
	}
?>
