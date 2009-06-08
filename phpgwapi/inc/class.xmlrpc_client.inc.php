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

  /* $Id$ */

	class xmlrpc_client
	{
		var $path;
		var $server;
		var $port=0;
		var $method='http';
		var $errno;
		var $errstr;
		var $debug=0;
		var $username='';
		var $password='';
		var $cert='';
		var $certpass='';
		var $verifypeer=1;
		var $verifyhost=1;
		var $no_multicall=False;
		var $proxy = '';
		var $proxyport=0;
		var $proxy_user = '';
		var $proxy_pass = '';
		/**
		* List of http compression methods accepted by the client for responses.
		* NB: PHP supports deflate, gzip compressions out of the box if compiled w. zlib
		*
		* NNB: you can set it to any non-empty array for HTTP11 and HTTPS, since
		* in those cases it will be up to CURL to decide the compression methods
		* it supports. You might check for the presence of 'zlib' in the output of
		* curl_version() to determine wheter compression is supported or not
		*/
		var $accepted_compression = array();
		/**
		* Name of compression scheme to be used for sending requests.
		* Either null, gzip or deflate
		*/
		var $request_compression = '';
		/**
		* CURL handle: used for keep-alive connections (PHP 4.3.8 up, see:
		* http://curl.haxx.se/docs/faq.html#7.3)
		*/
		var $xmlrpc_curl_handle = null;
		/// Whether to use persistent connections for http 1.1 and https
		var $keepalive = false;

		function xmlrpc_client($path, $server='', $port='', $method='')
		{
			// allow user to specify all params in $path
			if($server == '' and $port == '' and $method == '')
			{
				$parts = parse_url($path);
				$server = $parts['host'];
				$path = $parts['path'];
				if(isset($parts['query']))
				{
					$path .= '?'.$parts['query'];
				}
				if(isset($parts['fragment']))
				{
					$path .= '#'.$parts['fragment'];
				}
				if(isset($parts['port']))
				{
					$port = $parts['port'];
				}
				if(isset($parts['scheme']))
				{
					$method = $parts['scheme'];
				}
				if(isset($parts['user']))
				{
					$this->username = $parts['user'];
				}
				if(isset($parts['pass']))
				{
					$this->password = $parts['pass'];
				}
			}
			if($path == '' || $path[0] != '/')
			{
				$this->path='/'.$path;
			}
			else
			{
				$this->path=$path;
			}
			$this->server=$server;
			if($port != '')
			{
				$this->port=$port;
			}
			if($method != '')
			{
				$this->method=$method;
			}

			// if ZLIB is enabled, let the server by default accept compressed requests
			if(function_exists('gzinflate') || (
				function_exists('curl_init') && (($info = curl_version()) &&
				((is_string($info) && strpos($info, 'zlib') !== null) || isset($info['libz_version'])))
			))
			{
				$this->accepted_compression = array('gzip', 'deflate');
			}

			// keepalives: enabled by default ONLY for PHP >= 4.3.8
			// (see http://curl.haxx.se/docs/faq.html#7.3)
			if(version_compare(phpversion(), '4.3.8') >= 0)
			{
				$this->keepalive = true;
			}
		}

		function setDebug($in)
		{
			if ($in)
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
			$this->username=$u;
			$this->password=$p;
		}

		function setCertificate($cert, $certpass)
		{
			$this->cert = $cert;
			$this->certpass = $certpass;
		}

		function setSSLVerifyPeer($i)
		{
			$this->verifypeer = $i;
		}

		function setSSLVerifyHost($i)
		{
			$this->verifyhost = $i;
		}
		/**
		* set proxy info
		*
		* @param    string $proxyhost
		* @param    string $proxyport. Defaults to 8080 for HTTP and 443 for HTTPS
		* @param    string $proxyusername
		* @param    string $proxypassword
		* @access   public
		*/
		function setProxy($proxyhost, $proxyport, $proxyusername = '', $proxypassword = '')
		{
			$this->proxy = $proxyhost;
			$this->proxyport = $proxyport;
			$this->proxy_user = $proxyusername;
			$this->proxy_pass = $proxypassword;
		}

		function& send($msg, $timeout=0, $method='')
		{
			// if user does not specify http protocol, use native method of this client
			// (i.e. method set during call to constructor)
			if($method == '')
			{
				$method = $this->method;
			}

			if(is_array($msg))
			{
				// $msg is an array of xmlrpcmsg's
				$r =& $this->multicall($msg, $timeout, $method);
				return $r;
			}
			elseif(is_string($msg))
			{
				$n = new xmlrpcmsg('');
				$n->payload = $msg;
				$msg = $n;
			}

			// where msg is an xmlrpcmsg
			$msg->debug=$this->debug;

			switch($method)
			{
				case 'https':
					$r =& $this->sendPayloadHTTPS(
						$msg,
						$this->server,
						$this->port,
						$timeout,
						$this->username,
						$this->password,
						$this->cert,
						$this->certpass,
						$this->proxy,
						$this->proxyport,
						$this->proxy_user,
						$this->proxy_pass,
						'https',
						$this->keepalive
					);
					break;
				case 'http11':
					$r =& $this->sendPayloadCURL(
						$msg,
						$this->server,
						$this->port,
						$timeout,
						$this->username,
						$this->password,
						null,
						null,
						$this->proxy,
						$this->proxyport,
						$this->proxy_user,
						$this->proxy_pass,
						'http',
						$this->keepalive
					);
					break;
				case 'http10':
				default:
					$r =& $this->sendPayloadHTTP10(
						$msg,
						$this->server,
						$this->port,
						$timeout,
						$this->username,
						$this->password,
						$this->proxy,
						$this->proxyport,
						$this->proxy_user,
						$this->proxy_pass
					);
			}

			return $r;
		}

		function &sendPayloadHTTP10($msg, $server, $port, $timeout=0,$username='', $password='',
			$proxyhost='', $proxyport=0, $proxyusername='', $proxypassword='')
		{
			if($port==0)
			{
				$port=80;
			}

			// Only create the payload if it was not created previously
			if(empty($msg->payload))
			{
				$msg->createPayload();
			}

			// Deflate request body and set appropriate request headers
			if(function_exists('gzdeflate') && ($this->request_compression == 'gzip' || $this->request_compression == 'deflate'))
			{
				if($this->request_compression == 'gzip')
				{
					$a = @gzencode($msg->payload);
					if($a)
					{
						$msg->payload = $a;
						$encoding_hdr = "Content-Encoding: gzip\r\n";
					}
				}
				else
				{
					$a = @gzdeflate($msg->payload);
					if($a)
					{
						$msg->payload = $a;
						$encoding_hdr = "Content-Encoding: deflate\r\n";
					}
				}
			}
			else
			{
				$encoding_hdr = '';
			}

			// thanks to Grant Rauscher <grant7@firstworld.net>
			// for this
			$credentials='';
			if($username!='')
			{
				$credentials='Authorization: Basic ' . base64_encode($username . ':' . $password) . "\r\n";
			}

			$accepted_encoding = '';
			if(is_array($this->accepted_compression) && count($this->accepted_compression))
			{
				$accepted_encoding = 'Accept-Encoding: ' . implode(', ', $this->accepted_compression) . "\r\n";
			}

			$proxy_credentials = '';
			if($proxyhost)
			{
				if($proxyport == 0)
				{
					$proxyport = 8080;
				}
				$connectserver = $proxyhost;
				$connectport = $proxyport;
				$uri = 'http://'.$server.':'.$port.$this->path;
				if($proxyusername != '')
				{
					$proxy_credentials = 'Proxy-Authorization: Basic ' . base64_encode($proxyusername.':'.$proxypassword) . "\r\n";
				}
			}
			else
			{
				$connectserver = $server;
				$connectport = $port;
				$uri = $this->path;
			}

			$op= "POST " . $uri. " HTTP/1.0\r\n"
				. "User-Agent: " . $GLOBALS['xmlrpcName'] . " " . $GLOBALS['xmlrpcVersion'] . "\r\n"
				. 'X-EGW-Server: '  . $this->server . ' ' . "\r\n"
				. 'X-EGW-Version: ' . $GLOBALS['egw_info']['server']['versions']['phpgwapi'] . "\r\n"
				. "Host: ". $this->server . "\r\n"
				. $credentials
				. $proxy_credentials
				. $accepted_encoding
				. $encoding_hdr
				. "Accept-Charset: " . $GLOBALS['xmlrpc_defencoding'] . "\r\n"
				. "Content-Type: text/xml\r\nContent-Length: "
				. strlen($msg->payload) . "\r\n\r\n"
				. $msg->payload;

			if($timeout>0)
			{
				$fp = @fsockopen($connectserver, $connectport, $this->errno, $this->errstr, $timeout);
			}
			else
			{
				$fp = @fsockopen($connectserver, $connectport, $this->errno, $this->errstr);
			}
			if($fp)
			{
				if($timeout>0 && function_exists('stream_set_timeout'))
				{
					stream_set_timeout($fp, $timeout);
				}
			}
			else
			{
				$this->errstr='Connect error: '.$this->errstr;
				$r = CreateObject(
					'phpgwapi.xmlrpcresp',
					'',
					$GLOBALS['xmlrpcerr']['http_error'],
					$GLOBALS['xmlrpcstr']['http_error']
				);
				return $r;
			}

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
			else
			{
				// should we reset errno and errstr on succesful socket connection?
			}
			$resp =& $msg->parseResponseFile($fp);
			// shall we move this into parseresponsefile, cuz' we have to close the socket 1st
			// and do the parsing second if we want to have recursive calls
			// (i.e. redirects)
			fclose($fp);
			return $resp;
		}

		// contributed by Justin Miller <justin@voxel.net>
		// requires curl to be built into PHP
		// NB: CURL versions before 7.11.10 cannot use proxy to talk to https servers!
		function &sendPayloadHTTPS($msg, $server, $port, $timeout=0,$username='', $password='', $cert='',$certpass='',
			$proxyhost='', $proxyport=0, $proxyusername='', $proxypassword='', $keepalive=false)
		{
			$r =& $this->sendPayloadCURL($msg, $server, $port, $timeout, $username, $password, $cert, $certpass,
				$proxyhost, $proxyport, $proxyusername, $proxypassword, $keepalive);
			return $r;
		}

		function &sendPayloadCURL($msg, $server, $port, $timeout=0, $username='', $password='', $cert='', $certpass='',
			$proxyhost='', $proxyport=0, $proxyusername='', $proxypassword='', $method='https', $keepalive=false)
		{
			if(!function_exists('curl_init'))
			{
				$r = CreateObject(
					'phpgwapi.xmlrpcresp',
					'',
					$GLOBALS['xmlrpcerr']['no_curl'],
					$GLOBALS['xmlrpcstr']['no_curl']
				);
				return $r;
			}

			if($method == 'https')
			{
				if(($info = curl_version()) &&
					((is_string($info) && strpos($info, 'OpenSSL') === null) || (is_array($info) && !isset($info['ssl_version']))))
				{
					$this->errstr = 'SSL unavailable on this install';
					$r = CreateObject(
						'phpgwapi.xmlrpcresp',
						'',
						$GLOBALS['xmlrpcerr']['no_ssl'],
						$GLOBALS['xmlrpcstr']['no_ssl']
					);
					return $r;
				}
			}

			if($port == 0)
			{
				if($method == 'http')
				{
					$port = 80;
				}
				else
				{
					$port = 443;
				}
			}

			// Only create the payload if it was not created previously
			if(empty($msg->payload))
			{
				$msg->createPayload();
			}

			// Deflate request body and set appropriate request headers
			if(function_exists('gzdeflate') && ($this->request_compression == 'gzip' || $this->request_compression == 'deflate'))
			{
				if($this->request_compression == 'gzip')
				{
					$a = @gzencode($msg->payload);
					if($a)
					{
						$msg->payload = $a;
						$encoding_hdr = "Content-Encoding: gzip";
					}
				}
				else
				{
					$a = @gzdeflate($msg->payload);
					if($a)
					{
						$msg->payload = $a;
						$encoding_hdr = "Content-Encoding: deflate";
					}
				}
			}
			else
			{
				$encoding_hdr = '';
			}

			if(!$keepalive || !$this->xmlrpc_curl_handle)
			{
				$curl = curl_init($method . '://' . $server . ':' . $port . $this->path);
				if($keepalive)
				{
					$this->xmlrpc_curl_handle = $curl;
				}
			}
			else
			{
				$curl = $this->xmlrpc_curl_handle;
			}

			// results into variable
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

			if($this->debug)
			{
				curl_setopt($curl, CURLOPT_VERBOSE, 1);
			}
			curl_setopt($curl, CURLOPT_USERAGENT, $GLOBALS['xmlrpcName'].' '.$GLOBALS['xmlrpcVersion']);
			// required for XMLRPC: post the data
			curl_setopt($curl, CURLOPT_POST, 1);
			// the data
			curl_setopt($curl, CURLOPT_POSTFIELDS, $msg->payload);

			// return the header too
			curl_setopt($curl, CURLOPT_HTTPHEADER, array(
				'X-EGW-Server: '  . $this->server,
				'X-EGW-Version: ' . $GLOBALS['egw_info']['server']['versions']['phpgwapi'],
				'Content-Type: text/xml'
			));

			// will only work with PHP >= 5.0
			// NB: if we set an empty string, CURL will add http header indicating
			// ALL methods it is supporting. This is possibly a better option than
			// letting the user tell what curl can / cannot do...
			if(is_array($this->accepted_compression) && count($this->accepted_compression))
			{
				//curl_setopt($curl, CURLOPT_ENCODING, implode(',', $this->accepted_compression));
				curl_setopt($curl, CURLOPT_ENCODING, '');
			}
			// extra headers
			$headers = array('Content-Type: text/xml', 'Accept-Charset: '.$GLOBALS['xmlrpc_internalencoding']);
			// if no keepalive is wanted, let the server know it in advance
			if(!$keepalive)
			{
				$headers[] = 'Connection: close';
			}
			// request compression header
			if($encoding_hdr)
			{
				$headers[] = $encoding_hdr;
			}

			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			// timeout is borked
			if($timeout)
			{
				curl_setopt($curl, CURLOPT_TIMEOUT, $timeout == 1 ? 1 : $timeout - 1);
			}

			if($username && $password)
			{
				curl_setopt($curl, CURLOPT_USERPWD,"$username:$password");
			}

			if($method == 'https')
			{
				// set cert file
				if($cert)
				{
					curl_setopt($curl, CURLOPT_SSLCERT, $cert);
				}
				// set cert password
				if($certpass)
				{
					curl_setopt($curl, CURLOPT_SSLCERTPASSWD, $certpass);
				}
				// whether to verify remote host's cert
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $this->verifypeer);
				// whether to verify cert's common name (CN); 0 for no, 1 to verify that it exists, and 2 to verify that it matches the hostname used
				curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $this->verifyhost);
			}

			// proxy info
			if($proxyhost)
			{
				if($proxyport == 0)
				{
					$proxyport = 8080; // NB: even for HTTPS, local connection is on port 8080
				}
				curl_setopt($curl, CURLOPT_PROXY,$proxyhost.':'.$proxyport);
				//curl_setopt($curl, CURLOPT_PROXYPORT,$proxyport);
				if($proxyusername)
				{
					curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxyusername.':'.$proxypassword);
				}
			}

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
				if(!$keepalive)
				{
					curl_close($curl);
				}
			}
			else
			{
				if(!$keepalive)
				{
					curl_close($curl);
				}
				$resp =& $msg->parseResponse($result, true);
			}
			return $resp;
		}

		function& multicall($msgs, $timeout=0, $method='http')
		{
			$results = false;

			if(!$this->no_multicall)
			{
				$results = $this->_try_multicall($msgs, $timeout, $method);
				if($results !== false)
				{
					// Either the system.multicall succeeded, or the send
					// failed (e.g. due to HTTP timeout). In either case,
					// we're done for now.
					return $results;
				}
				else
				{
					// system.multicall unsupported by server,
					// don't try it next time...
					$this->no_multicall = true;
				}
			}

			// system.multicall is unupported by server:
			//   Emulate multicall via multiple requests
			$results = array();
			foreach($msgs as $msg)
			{
				$results[] =& $this->send($msg, $timeout, $method);
			}
			return $results;
		}

		// Attempt to boxcar $msgs via system.multicall.
		function _try_multicall($msgs, $timeout, $method)
		{
			// Construct multicall message
			$calls = array();
			foreach($msgs as $msg)
			{
				$call['methodName'] = new xmlrpcval($msg->method(),'string');
				$numParams = $msg->getNumParams();
				$params = array();
				for($i = 0; $i < $numParams; $i++)
				{
					$params[$i] = $msg->getParam($i);
				}
				$call['params'] = new xmlrpcval($params, 'array');
				$calls[] = new xmlrpcval($call, 'struct');
			}
			$multicall = new xmlrpcmsg('system.multicall');
			$multicall->addParam(new xmlrpcval($calls, 'array'));

			// Attempt RPC call
			$result =& $this->send($multicall, $timeout, $method);
			//if(!is_object($result))
			//{
			//	return ($result || 0); // transport failed
			//}

			if($result->faultCode() != 0)
			{
				return false;		// system.multicall failed
			}

			// Unpack responses.
			$rets = $result->value();
			if($rets->kindOf() != 'array')
			{
				return false;		// bad return type from system.multicall
			}
			$numRets = $rets->arraysize();
			if($numRets != count($msgs))
			{
				return false;		// wrong number of return values.
			}

			$response = array();
			for($i = 0; $i < $numRets; $i++)
			{
				$val = $rets->arraymem($i);
				switch($val->kindOf())
				{
					case 'array':
						if($val->arraysize() != 1)
						{
							return false;		// Bad value
						}
						// Normal return value
						$response[$i] = new xmlrpcresp($val->arraymem(0));
						break;
					case 'struct':
						$code = $val->structmem('faultCode');
						if($code->kindOf() != 'scalar' || $code->scalartyp() != 'int')
						{
							return false;
						}
						$str = $val->structmem('faultString');
						if($str->kindOf() != 'scalar' || $str->scalartyp() != 'string')
						{
							return false;
						}
						$response[$i] = new xmlrpcresp(0, $code->scalarval(), $str->scalarval());
						break;
					default:
						return false;
				}
			}
			return $response;
		}
	} // end class xmlrpc_client
?>
