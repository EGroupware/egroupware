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

	/* BEGIN server class */
	class xmlrpc_server
	{
		var $dmap = array();
		var $authed = False;
		var $req_array = array();
		var $resp_struct = array();
		var $debug = False;
		var $method_requested;
		var $log = False; //'/tmp/xmlrpc.log';

		function xmlrpc_server($dispMap='', $serviceNow=0)
		{
			global $HTTP_RAW_POST_DATA;

			// dispMap is a despatch array of methods
			// mapped to function names and signatures
			// if a method
			// doesn't appear in the map then an unknown
			// method error is generated
			if($dispMap)
			{
				$this->dmap = $dispMap;
				if ($serviceNow)
				{
					$this->service();
				}
			}
		}

		function serializeDebug()
		{
			if ($GLOBALS['_xmlrpc_debuginfo'] != '')
			{
				return "<!-- DEBUG INFO:\n\n" . $GLOBALS['_xmlrpc_debuginfo'] . "\n-->\n";
			}
			else
			{
				return '';
			}
		}

		function service()
		{
			global $HTTP_RAW_POST_DATA;

			$r = $this->parseRequest();
			if ($r == False)
			{
				header('WWW-Authenticate: Basic realm="eGroupWare xmlrpc"');
				header('HTTP/1.0 401 Unauthorized');
				// for the log:
				$payload = "WWW-Authenticate: Basic realm=\"eGroupWare xmlrpc\"\nHTTP/1.0 401 Unauthorized\n";
			}
			else
			{
				$payload = "<?xml version=\"1.0\"?>\n" . $this->serializeDebug() . $r->serialize();
				Header("Content-type: text/xml\r\nContent-length: " . strlen($payload));
				echo $GLOBALS['phpgw']->translation->convert($payload,$GLOBALS['phpgw']->translation->charset(),'utf-8');
			}

			if ($this->log)
			{
				$fp = fopen($this->log,'a+');
				fwrite($fp,"\n\n".date('Y-m-d H:i:s')." authorized=".
					($this->authed?$GLOBALS['phpgw_info']['user']['account_lid']:'False').
					", method='$this->last_method'\n");
				fwrite($fp,"==== GOT ============================\n".$HTTP_RAW_POST_DATA.
					"\n==== RETURNED =======================\n");
				fputs($fp,$payload);
				fclose($fp);
			}

			if ($this->debug)
			{
				$this->echoInput();

				$fp = fopen('/tmp/xmlrpc_debug.out','w');
				fputs($fp,$payload);
				fclose($fp);
			}

		}

		/*
		add a method to the dispatch map
		*/
		function add_to_map($methodname,$function,$sig,$doc)
		{
			$this->dmap[$methodname] = array(
				'function'  => $function,
				'signature' => $sig,
				'docstring' => $doc
			);
		}

		function verifySignature($in, $sig)
		{
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

		function reqtoarray($_req,$recursed=False)
		{
			switch(gettype($_req))
			{
				case 'object':
					if($recursed)
					{
						return $_req->getval();
					}
					else
					{
						$this->req_array = $_req->getval();
					}
					break;
				case 'array':
					@reset($_req);
					$ele = array();
					while(list($key,$val) = @each($_req))
					{
						if($recursed)
						{
							$ele[$key] = $this->reqtoarray($val,True);
						}
						else
						{
							$this->req_array[$key] = $this->reqtoarray($val,True);
						}
					}
					if($recursed)
					{
						return $ele;
					}
					break;
				case 'string':
				case 'integer':
					if($recursed)
					{
						return $_req;
					}
					else
					{
						$this->req_array[] = $_req;
					}
					break;
				default:
					break;
			}
		}

		function build_resp($_res)
		{
			if (is_array($_res))
			{
				foreach($_res as $key => $val)
				{
					$ele[$key] = $this->build_resp($val,True);
				}
				return CreateObject('phpgwapi.xmlrpcval',$ele,'struct');
			}
			$_type = (is_integer($_res) ? 'int' : gettype($_res));

			if ($_type == string && ereg('^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}$',$_res))
			{
				$_type = 'dateTime.iso8601';
			}
			// Passing an integer of 0 to the xmlrpcval constructor results in the value being lost. (jengo)
			if ($_type == 'int' && $_res == 0)
			{
				return CreateObject('phpgwapi.xmlrpcval','0',$_type);
			}
			return CreateObject('phpgwapi.xmlrpcval',$_res,$_type);
		}

		function parseRequest($data='')
		{
			global $HTTP_RAW_POST_DATA;

			$r = False;

			if ($data == '')
			{
				$data = $HTTP_RAW_POST_DATA;
			}
			$parser = xml_parser_create($GLOBALS['xmlrpc_defencoding']);

			$GLOBALS['_xh'][$parser] = array();
			$GLOBALS['_xh'][$parser]['st']     = '';
			$GLOBALS['_xh'][$parser]['cm']     = 0;
			$GLOBALS['_xh'][$parser]['isf']    = 0;
			$GLOBALS['_xh'][$parser]['params'] = array();
			$GLOBALS['_xh'][$parser]['method'] = '';

			// decompose incoming XML into request structure
			xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, true);
			xml_set_element_handler($parser, 'xmlrpc_se', 'xmlrpc_ee');
			xml_set_character_data_handler($parser, 'xmlrpc_cd');
			xml_set_default_handler($parser, 'xmlrpc_dh');
			if (!xml_parse($parser, $data, 1))
			{
				// return XML error as a faultCode
				$r = CreateObject('phpgwapi.xmlrpcresp','',
					$GLOBALS['xmlrpcerrxml'] + xml_get_error_code($parser),
					sprintf('XML error: %s at line %d',
					xml_error_string(xml_get_error_code($parser)),
					xml_get_current_line_number($parser))
				);
				xml_parser_free($parser);
			}
			else
			{
				xml_parser_free($parser);
				$m = CreateObject('phpgwapi.xmlrpcmsg',$GLOBALS['_xh'][$parser]['method']);
				// now add parameters in
				$plist = '';
				for($i=0; $i<sizeof($GLOBALS['_xh'][$parser]['params']); $i++)
				{
					//print "<!-- " . $GLOBALS['_xh'][$parser]['params'][$i]. "-->\n";
					$plist .= "$i - " . $GLOBALS['_xh'][$parser]['params'][$i]. " \n";
					$code = '$m->addParam(' . $GLOBALS['_xh'][$parser]['params'][$i] . ');';
					$code = ereg_replace(',,',",'',",$code);
					eval($code);
				}
				// uncomment this to really see what the server's getting!
				// xmlrpc_debugmsg($plist);
				// now to deal with the method
				$methName  = $GLOBALS['_xh'][$parser]['method'];
				$_methName = $GLOBALS['_xh'][$parser]['method'];
				$this->last_method = $methName;

				if (ereg("^system\.", $methName))
				{
					$dmap = $GLOBALS['_xmlrpcs_dmap'];
					$sysCall=1;
				}
				else
				{
					$dmap = $this->dmap;
					$sysCall=0;
				}

				if (!isset($dmap[$methName]['function']))
				{
					if($sysCall && $this->authed)
					{
						$r = CreateObject('phpgwapi.xmlrpcresp',
							'',
							$GLOBALS['xmlrpcerr']['unknown_method'],
							$GLOBALS['xmlrpcstr']['unknown_method'] . ': ' . $methName
						);
						return $r;
					}
					if ($this->authed)
					{
						/* phpgw mod - fetch the (bo) class methods to create the dmap */
						// This part is to update session action to match
						$this->method_requested = $methName;

						$method = $methName;
						$tmp = explode('.',$methName);
						$methName = $tmp[2];
						$service  = $tmp[1];
						$class    = $tmp[0];

						if (ereg('^service',$method))
						{
							$t = 'phpgwapi.' . $class . '.exec';
							$dmap = ExecMethod($t,array($service,'list_methods','xmlrpc'));
						}
						elseif($GLOBALS['phpgw']->acl->check('run',1,$class))
						{
							/* This only happens if they have app access.  If not, we will
							 * return a fault below.
							 */
							$listmeth = $class . '.' . $service . '.' . 'list_methods';
							$dmap = ExecMethod($listmeth,'xmlrpc');
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

						$this->dmap = $dmap;
						/* _debug_array($this->dmap);exit; */
					}
				}

				if (isset($dmap[$methName]['function']))
				{
					// dispatch if exists
					if (isset($dmap[$methName]['signature']))
					{
						$sr = $this->verifySignature($m, $dmap[$methName]['signature'] );
					}
					if ( (!isset($dmap[$methName]['signature'])) || $sr[0])
					{
						// if no signature or correct signature
						if ($sysCall)
						{
							$code = '$r=' . $dmap[$methName]['function'] . '($this, $m);';
							$code = ereg_replace(',,',",'',",$code);
							eval($code);
						}
						else
						{
							if (function_exists($dmap[$methName]['function']))
							{
								$code = '$r =' . $dmap[$methName]['function'] . '($m);';
								$code = ereg_replace(',,',",'',",$code);
								eval($code);
							}
							else
							{
								/* phpgw mod - finally, execute the function call and return the values */
								$params = $GLOBALS['_xh'][$parser]['params'][0];
								$code = '$p = '  . $params . ';';
								if (count($params) != 0)
								{
									eval($code);
									$params = $p->getval();
								}

								// _debug_array($params);
								$this->reqtoarray($params);
								// decode from utf-8 to our charset
								$this->req_array = $GLOBALS['phpgw']->translation->convert($this->req_array,'utf-8');
								//_debug_array($this->req_array);
								if (ereg('^service',$method))
								{
									$res = ExecMethod('phpgwapi.service.exec',array($service,$methName,$this->req_array));
								}
								else
								{
									list($s,$c,$m) = explode('.',$_methName);
									$res = ExecMethod($s . '.' . $c . '.' . $dmap[$methName]['function'],$this->req_array);
								}
								/* $res = ExecMethod($method,$params); */
								/* _debug_array($res);exit; */
								$this->resp_struct = array($this->build_resp($res,True));
								/*_debug_array($this->resp_struct); */
								@reset($this->resp_struct);
								$r = CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',$this->resp_struct,'struct'));
								/* _debug_array($r); */
							}
						}
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
						$r = False;	// send 401 header to force authorization
							/*CreateObject('phpgwapi.xmlrpcresp',
							CreateObject('phpgwapi.xmlrpcval',
								'UNAUTHORIZED',
								'string'
							)
						);*/
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
			}
			return $r;
		}

		function echoInput()
		{
			global $HTTP_RAW_POST_DATA;

			// a debugging routine: just echos back the input
			// packet as a string value

			$r = CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',"'Aha said I: '" . $HTTP_RAW_POST_DATA,'string'));
			//echo $r->serialize();

			$fp = fopen('/tmp/xmlrpc_debug.in','w');
			fputs($fp,$r->serialize);
			fputs($fp,$HTTP_RAW_POST_DATA);
			fclose($fp);
		}
	}
?>
