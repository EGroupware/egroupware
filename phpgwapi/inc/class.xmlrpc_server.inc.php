<?php
	// by Edd Dumbill (C) 1999-2001
	// <edd@usefulinc.com>
	// $Id$
	
	// License is granted to use or modify this software ("XML-RPC for PHP")
	// for commercial or non-commercial use provided the copyright of the author
	// is preserved in any distributed or derivative work.
	
	// THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESSED OR
	// IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
	// OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
	// IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
	// INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
	// NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, 
	// DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
	// THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
	// (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
	// THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
	
	// XML RPC Server class
	// requires: xmlrpc.inc
	
	/* BEGIN server class */
	class xmlrpc_server
	{
		var $dmap = array();
		var $authed = False;

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
			$payload = "<?xml version=\"1.0\"?>\n" . $this->serializeDebug() . $r->serialize();
			Header("Content-type: text/xml\r\nContent-length: " . strlen($payload));
			print $payload;
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

		function build_array($res)
		{
			@reset($res);
			while(list($key,$val) = @each($res))
			{
				if(is_array($val))
				{
					$ele[$key] = CreateObject('phpgwapi.xmlrpcval',$this->build_array($val),'struct');
				}
				else
				{
					$ele[$key] = CreateObject('phpgwapi.xmlrpcval',$val,'string');
				}
			}
			return $ele;
		}

		function parseRequest($data='')
		{
			global $HTTP_RAW_POST_DATA;
	
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
				$r = CreateObject('phpgwapi.xmlrpcresp',0,
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
				$methName = $GLOBALS['_xh'][$parser]['method'];
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
				if(!isset($dmap[$methName]['function']))
				{
					if($this->authed)
					{
						/* phpgw mod - fetch the (bo) class methods to create the dmap */
						$method = $methName;
						$tmp = explode('.',$methName);
						$methName = $tmp[2];
						$listmeth = $tmp[0] . '.' . $tmp[1] . '.' . 'list_methods';
						$dmap = ExecMethod($listmeth,'xmlrpc');
						$this->dmap = $dmap;
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
							if(function_exists($dmap[$methName]['function']))
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
								eval($code);
								$params = $p->getval();
								/* _debug_array($params); */
								if(gettype($params) == 'array')
								{
									@reset($params);
									while(list($key,$val) = @each($params))
									{
										if(gettype($val) == 'array')
										{
											@reset($val);
											while(list($key1,$val1) = @each($val))
											{
												$tmp = '';
												if(get_class($val1) == 'xmlrpcval')
												{
													$tmp[$key1] = $val1->getval();
													/* echo '<br>Adding xmlrpc val1: ' . $tmp[$key1] . "\n"; */
												}
												else
												{
													/* echo '<br>Adding val1: ' . $val1 . "\n"; */
													$tmp[$key1] = $val1;
												}
											}
											$_params[$key] = $tmp;
										}
										else
										{
											/* echo '<br>Adding val: ' . $val . "\n"; */
											$_params[$key] = $val;
										}
									}
									$params = $_params;
								}
								/* _debug_array($params); */
								$res = ExecMethod($method,$params);
								/* _debug_array($res);exit; */
								
								$r = CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',$this->build_array($res),'struct'));
								/* _debug_array($r);exit; */
							}
						}
					}
					else
					{
						$r = CreateObject(
							'phpgwapi.xmlrpcresp',
							CreateObject('phpgwapi.xmlrpcval'),
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
						$r = CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval','UNAUTHORIZED','string'));
					}
					else
					{
						$r = CreateObject(
							'phpgwapi.xmlrpcresp',
							CreateObject('phpgwapi.xmlrpcval'),
							$GLOBALS['xmlrpcerr']['unknown_method'],
							$GLOBALS['xmlrpcstr']['unknown_method']
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
			echo $r->serialize();
		}
	}
?>
