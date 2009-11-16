<?php
  /**************************************************************************\
  * eGroupWare API - XML-RPC Server                                          *
  * This file written by Miles Lott <milos@groupwhere.org>                   *
  * Copyright (C) 2003 Miles Lott                                            *
  * -------------------------------------------------------------------------*
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

	class xmlrpc_server extends xmlrpc_server_shared
	{
		var $server = '';
		var $authed = True;
		var $log = False; //'/tmp/xmlrpc.log';
		var $last_method = '';

		function xmlrpc_server($dispMap='', $serviceNow=0)
		{
			$this->server = xmlrpc_server_create();
			if($dispMap)
			{
				$this->dmap = $dispMap;
				if($serviceNow)
				{
					$this->service();
				}
			}
		}

		function serializeDebug()
		{
		}

		function service($r = False)
		{
			if (!$r)	// do we have a response, or we need to parse the request
			{
				$r = $this->parseRequest();
			}
			if(!$r)
			{
				header('WWW-Authenticate: Basic realm="eGroupWare xmlrpc"');
				header('HTTP/1.0 401 Unauthorized');
				// for the log:
				$payload = "WWW-Authenticate: Basic realm=\"eGroupWare xmlrpc\"\nHTTP/1.0 401 Unauthorized\n";
				echo $payload;
			}
			else
			{
//				$payload = '<?xml version="1.0"?\>' . "\n" . $this->serializeDebug() . $r->serialize();
//				Header("Content-type: text/xml\r\nContent-length: " . bytes($payload));
//				print $payload;
				echo $r;
			}

			if($this->log)
			{
				$fp = fopen($this->log,'a+');
				fwrite($fp,"\n\n" . date('Y-m-d H:i:s') . " authorized="
					. ($this->authed ? $GLOBALS['egw_info']['user']['account_lid'] : 'False')
					. ", method='$this->last_method'\n");
				fwrite($fp,"==== GOT ============================\n" . $GLOBALS['HTTP_RAW_POST_DATA']
					. "\n==== RETURNED =======================\n");
				fputs($fp,$payload);
				fclose($fp);
			}

			if($this->debug)
			{
				$this->echoInput();

				$fp = fopen('/tmp/xmlrpc_debug.out','a+');
				fputs($fp,$payload);
				fclose($fp);
			}
		}

		function add_to_map($methodname,$function,$sig,$doc)
		{
			xmlrpc_server_register_method($this->server,$methodname,$function);
//			xmlrpc_server_register_method($this->server,$methodname,'xmlrpc_call_wrapper');
//			$descr =  array(
//				'function'  => $function,
//				'signature' => $sig,
//				'docstring' => $doc
//			);
//			xmlrpc_server_set_method_description($this->server,$methodname,$descr);

			$this->dmap[$methodname] = array(
				'function'  => $function,
				'signature' => $sig,
				'docstring' => $doc
			);
		}

		function verifySignature($in, $sig)
		{
			return array(1);

			for($i=0; $i<sizeof($sig); $i++)
			{
				// check each possible signature in turn
				$cursig = $sig[$i];
				if (sizeof($cursig) == $in->getNumParams()+1)
				{
					$itsOK = 1;
					for($n=0; $n<$in->getNumParams(); $n++)
					{
						$p = $in->getParam($n);
						// print "<!-- $p -->\n";
						if ($p->kindOf() == 'scalar')
						{
							$pt = $p->scalartyp();
						}
						else
						{
							$pt = $p->kindOf();
						}
						// $n+1 as first type of sig is return type
						if ($pt != $cursig[$n+1])
						{
							$itsOK  = 0;
							$pno    = $n+1;
							$wanted = $cursig[$n+1];
							$got    = $pt;
							break;
						}
					}
					if ($itsOK)
					{
						return array(1);
					}
				}
			}
			return array(0, "Wanted $wanted, got $got at param $pno)");
		}

		function parseRequest($data='')
		{
			if($data == '')
			{
				$data = $GLOBALS['HTTP_RAW_POST_DATA'];
			}
//			return $this->echoInput($data);

			/* Decode to extract methodName */
			$params = xmlrpc_decode_request($data, &$methName);
			$this->last_method = $methName;
			$syscall = 0;

			/* Setup dispatch map based on the function, if this is a system call */
			if(preg_match('/^system\./', $methName))
			{
				foreach($GLOBALS['_xmlrpcs_dmap'] as $meth => $dat)
				{
					$this->add_to_map($meth,$dat['function'],$dat['signature'],$dat['docstring']);
				}
				$sysCall = 1;
				$dmap = $this->dmap;
			}
			elseif(preg_match('/^examples\./',$methName) ||
				preg_match('/^validator1\./',$methName) ||
				ereg('^interopEchoTests\.', $methName)
			)
			{
				$dmap = $this->dmap;
				$sysCall = 1;
			}

			/* verify dispatch map, or try to fix it for non-trivial system calls */
			if(!isset($this->dmap[$methName]['function']))
			{
				if($sysCall)
				{
					/* Bad or non-existent system call, return error */
					$r = CreateObject('phpgwapi.xmlrpcresp',
						'',
						$GLOBALS['xmlrpcerr']['unknown_method'],
						$GLOBALS['xmlrpcstr']['unknown_method'] . ': ' . $methName
					);
					return $r;
				}
				if($this->authed)
				{
					$method = $methName;
					list($app,$class,$method) = explode('.',$methName);

					switch($app)
					{
						case 'server':
						case 'phpgwapi':
							/* Server role functions only - api access */
							if($GLOBALS['egw']->acl->get_role() >= EGW_ACL_SERVER)
							{
								$dmap = ExecMethod(sprintf('%s.%s.%s','phpgwapi',$class,'list_methods'),'xmlrpc');
							}
							break;
						case 'service':
							/* Service functions, user-level */
							$t = 'phpgwapi.' . $class . '.exec';
							$dmap = ExecMethod($t,array($service,'list_methods','xmlrpc'));
							break;
						default:
							/* User-level application access */
							if($GLOBALS['egw']->acl->check('run',EGW_ACL_READ,$app))
							{
								$dmap = ExecMethod(sprintf('',$app,$class,'list_methods'),'xmlrpc');
							}
							else
							{
								$r = CreateObject('phpgwapi.xmlrpcresp',
									'',
									$GLOBALS['xmlrpcerr']['no_access'],
									$GLOBALS['xmlrpcstr']['no_access']
								);
								return $r;
							}
					}
				}
			}

			/* add the functions from preset $dmap OR list_methods() to the server map */
			foreach($dmap as $meth => $dat)
			{
				$this->add_to_map($meth,$dat['function'],$dat['signature'],$dat['docstring']);
			}
		
			/* _debug_array($this->dmap);exit; */

			/* Now make the call */
			if(isset($dmap[$methName]['function']))
			{
				// dispatch if exists
				if(isset($dmap[$methName]['signature']))
				{
					$sr = $this->verifySignature($m, $dmap[$methName]['signature'] );
				}
				if((!isset($dmap[$methName]['signature'])) || $sr[0])
				{
					// if no signature or correct signature
					$r = xmlrpc_server_call_method($this->server,$data,$params);
				}
				else
				{
					$r = CreateObject('phpgwapi.xmlrpcresp',
						'',
						$GLOBALS['xmlrpcerr']['incorrect_params'],
						$GLOBALS['xmlrpcstr']['incorrect_params'] . ': ' . $sr[1]
					);
				}
			}
			else
			{
				// else prepare error response
				if(!$this->authed)
				{
//					$r = False;
					// send 401 header to force authorization
					$r = CreateObject('phpgwapi.xmlrpcresp',
						CreateObject('phpgwapi.xmlrpcval',
							'UNAUTHORIZED',
							'string'
						)
					);
				}
				else
				{
					$r = CreateObject('phpgwapi.xmlrpcresp',
						'',
						$GLOBALS['xmlrpcerr']['unknown_method'],
						$GLOBALS['xmlrpcstr']['unknown_method'] . ': ' . $methName
					);
				}
			}
			xmlrpc_server_destroy($xmlrpc_server);
			return $r;
		}

		function echoInput()
		{
			// a debugging routine: just echos back the input
			// packet as a string value

			/* TODO */
//			$r = CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',"'Aha said I: '" . $HTTP_RAW_POST_DATA,'string'));
			return $GLOBALS['HTTP_RAW_POST_DATA'];
		}

		function xmlrpc_custom_error($error_number, $error_string, $filename, $line, $vars)
		{
			if(error_reporting() & $error_number)
			{
				$error_string .= sprintf("\nFilename: %s\nLine: %s",$filename,$line);

				xmlrpc_error(1005,$error_string);
			}
		}
/*
		function xmlrpc_error($error_number, $error_string)
		{
			$values = array(
				'faultString' => $error_string,
				'faultCode'   => $error_number
			);

			echo xmlrpc_encode_request(NULL,$values);

			xmlrpc_server_destroy($GLOBALS['xmlrpc_server']);
			exit;
		}
*/
		function xmlrpc_error($error_number, $error_string)
		{
			$r = CreateObject('phpgwapi.xmlrpcresp',
				'',
				$error_number,
				$error_string . ': ' . $this->last_method
			);
			$this->service($r);
			xmlrpc_server_destroy($GLOBALS['xmlrpc_server']);
			exit;
		}
	}
