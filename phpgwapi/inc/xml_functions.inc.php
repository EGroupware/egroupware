<?php
	// by Edd Dumbill (C) 1999-2001
	// <edd@usefulinc.com>
	// xmlrpc.inc,v 1.18 2001/07/06 18:23:57 edmundd

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

	/* $Id$ */

	if (!function_exists('xml_parser_create'))
	{
		// Win 32 fix. From: "Leo West" <lwest@imaginet.fr>
		if($WINDIR)
		{
			dl('php3_xml.dll');
		}
		else
		{
			dl('xml.so');
		}
	}

	$GLOBALS['xmlrpc_valid_parents'] = array(
		'BOOLEAN' => array('VALUE'),
		'I4' => array('VALUE'),
		'INT' => array('VALUE'),
		'STRING' => array('VALUE'),
		'DOUBLE' => array('VALUE'),
		'DATETIME.ISO8601' => array('VALUE'),
		'BASE64' => array('VALUE'),
		'ARRAY' => array('VALUE'),
		'STRUCT' => array('VALUE'),
		'PARAM' => array('PARAMS'),
		'METHODNAME' => array('METHODCALL'),
		'PARAMS' => array('METHODCALL', 'METHODRESPONSE'),
		'MEMBER' => array('STRUCT'),
		'NAME' => array('MEMBER'),
		'DATA' => array('ARRAY'),
		'FAULT' => array('METHODRESPONSE'),
		'VALUE' => array('MEMBER', 'DATA', 'PARAM', 'FAULT'),
	);

	define('xmlrpcI4','i4');
	define('xmlrpcInt','int');
	define('xmlrpcBoolean','boolean');
	define('xmlrpcDouble','double');
	define('xmlrpcString','string');
	define('xmlrpcDateTime','dateTime.iso8601');
	define('xmlrpcBase64','base64');
	define('xmlrpcArray','array');
	define('xmlrpcStruct','struct');

	$GLOBALS['xmlrpcTypes'] = array(
		xmlrpcI4       => 1,
		xmlrpcInt      => 1,
		xmlrpcBoolean  => 1,
		xmlrpcString   => 1,
		xmlrpcDouble   => 1,
		xmlrpcDateTime => 1,
		xmlrpcBase64   => 1,
		xmlrpcArray    => 2,
		xmlrpcStruct   => 3
	);

	$GLOBALS['xmlEntities']=array(
		'amp'  => '&',
		'quot' => '"',
		'lt'   => '<',
		'gt'   => '>',
		'apos' => "'"
	);

	$GLOBALS['xmlrpcerr']['unknown_method']=1;
	$GLOBALS['xmlrpcstr']['unknown_method']='Unknown method';
	$GLOBALS['xmlrpcerr']['invalid_return']=2;
	$GLOBALS['xmlrpcstr']['invalid_return']='Invalid return payload: enable debugging to examine incoming payload';
	$GLOBALS['xmlrpcerr']['incorrect_params']=3;
	$GLOBALS['xmlrpcstr']['incorrect_params']='Incorrect parameters passed to method';
	$GLOBALS['xmlrpcerr']['introspect_unknown']=4;
	$GLOBALS['xmlrpcstr']['introspect_unknown']="Can't introspect: method unknown";
	$GLOBALS['xmlrpcerr']['http_error']=5;
	$GLOBALS['xmlrpcstr']['http_error']="Didn't receive 200 OK from remote server.";
	$GLOBALS['xmlrpcerr']['no_data']=6;
	$GLOBALS['xmlrpcstr']['no_data']='No data received from server.';
	$GLOBALS['xmlrpcerr']['no_ssl']=7;
	$GLOBALS['xmlrpcstr']['no_ssl']='No SSL support compiled in.';
	$GLOBALS['xmlrpcerr']['curl_fail']=8;
	$GLOBALS['xmlrpcstr']['curl_fail']='CURL error';
	$GLOBALS['xmlrpcerr']['multicall_notstruct'] = 9;
	$GLOBALS['xmlrpcstr']['multicall_notstruct'] = 'system.multicall expected struct';
	$GLOBALS['xmlrpcerr']['multicall_nomethod']  = 10;
	$GLOBALS['xmlrpcstr']['multicall_nomethod']  = 'missing methodName';
	$GLOBALS['xmlrpcerr']['multicall_notstring'] = 11;
	$GLOBALS['xmlrpcstr']['multicall_notstring'] = 'methodName is not a string';
	$GLOBALS['xmlrpcerr']['multicall_recursion'] = 12;
	$GLOBALS['xmlrpcstr']['multicall_recursion'] = 'recursive system.multicall forbidden';
	$GLOBALS['xmlrpcerr']['multicall_noparams']  = 13;
	$GLOBALS['xmlrpcstr']['multicall_noparams']  = 'missing params';
	$GLOBALS['xmlrpcerr']['multicall_notarray']  = 14;
	$GLOBALS['xmlrpcstr']['multicall_notarray']  = 'params is not an array';

	$GLOBALS['xmlrpcerr']['invalid_request']=15;
	$GLOBALS['xmlrpcstr']['invalid_request']='Invalid request payload';
	$GLOBALS['xmlrpcerr']['no_curl']=16;
	$GLOBALS['xmlrpcstr']['no_curl']='No CURL support compiled in.';
	$GLOBALS['xmlrpcerr']['server_error']=17;
	$GLOBALS['xmlrpcstr']['server_error']='Internal server error';

	$GLOBALS['xmlrpcerr']['no_access']          = 18;
	$GLOBALS['xmlrpcstr']['no_access']          = 'Access denied';
	$GLOBALS['xmlrpcerr']['not_existent']       = 19;
	$GLOBALS['xmlrpcstr']['not_existent']       = 'Entry does not (longer) exist!';

	$GLOBALS['xmlrpcerr']['cannot_decompress']=103;
	$GLOBALS['xmlrpcstr']['cannot_decompress']='Received from server compressed HTTP and cannot decompress';
	$GLOBALS['xmlrpcerr']['decompress_fail']=104;
	$GLOBALS['xmlrpcstr']['decompress_fail']='Received from server invalid compressed HTTP';
	$GLOBALS['xmlrpcerr']['dechunk_fail']=105;
	$GLOBALS['xmlrpcstr']['dechunk_fail']='Received from server invalid chunked HTTP';
	$GLOBALS['xmlrpcerr']['server_cannot_decompress']=106;
	$GLOBALS['xmlrpcstr']['server_cannot_decompress']='Received from client compressed HTTP request and cannot decompress';
	$GLOBALS['xmlrpcerr']['server_decompress_fail']=107;
	$GLOBALS['xmlrpcstr']['server_decompress_fail']='Received from client invalid compressed HTTP request';

	$GLOBALS['xmlrpc_defencoding'] = 'UTF-8';
	$GLOBALS['xmlrpc_internalencoding']='ISO-8859-1';

	$GLOBALS['xmlrpcName']    = 'XML-RPC for PHP';
	$GLOBALS['xmlrpcVersion'] = '2.0';

	// let user errors start at 800
	$GLOBALS['xmlrpcerruser'] = 800;
	// let XML parse errors start at 100
	$GLOBALS['xmlrpcerrxml'] = 100;

	// formulate backslashes for escaping regexp
	$GLOBALS['xmlrpc_backslash'] = chr(92) . chr(92);

	/*!
	@function xmlrpcfault
	@abstract Error reporting for XML-RPC
	@discussion Author: jengo <br>
	Returns XML-RPC fault and stops this execution of the application. <br>
	Syntax: void xmlrpcfault(string) <br>
	Example1: xmlrpcfault('Session could not be verifed'); <br>
	@param $string Error message to be returned.
	*/
	function xmlrpcfault($string)
	{
		$r = CreateObject('phpgwapi.xmlrpcresp',
			CreateObject('phpgwapi.xmlrpcval'),
			$GLOBALS['xmlrpcerr']['unknown_method'],
			$string
		);
		$payload = '<?xml version="1.0"?>' . "\n" . $r->serialize();
		Header('Content-type: text/xml');
		Header('Content-length: ' . bytes($payload));
		print $payload;
		$GLOBALS['egw']->common->phpgw_exit(False);
	}

	// used to store state during parsing
	// quick explanation of components:
	//   ac - used to accumulate values
	//   isf - used to indicate a fault
	//   lv - used to indicate "looking for a value": implements
	//        the logic to allow values with no types to be strings
	//   params - used to store parameters in method calls
	//   method - used to store method name
	//   stack - array with genealogy of xml elements names:
	//           used to validate nesting of xmlrpc elements
	$GLOBALS['_xh'] = null;

	/**
	* To help correct communication of non-ascii chars inside strings, regardless
	* of the charset used when sending requests, parsing them, sending responses
	* and parsing responses, convert all non-ascii chars present in the message
	* into their equivalent 'charset entity'. Charset entities enumerated this way
	* are independent of the charset encoding used to transmit them, and all XML
	* parsers are bound to understand them.
	* 
	* @author Eugene Pivnev
	*/
	function xmlrpc_encode_entities($data)
	{
		return htmlspecialchars($data,ENT_QUOTES,$GLOBALS['egw']->translation->system_charset ? 
			$GLOBALS['egw']->translation->system_charset : 'latin1');
	}

	if (!function_exists('htmlspecialchars_decode'))	// php < 5.1
	{
	    function htmlspecialchars_decode($text,$quote_style=ENT_COMPAT)
	    {
	        return strtr($text, array_flip(get_html_translation_table(HTML_SPECIALCHARS,$quote_style)));
	    }
	}

	function xmlrpc_entity_decode($string)
	{
		return htmlspecialchars_decode($data,ENT_QUOTES);
	}

	function xmlrpc_lookup_entity($ent)
	{
		if (isset($GLOBALS['xmlEntities'][strtolower($ent)]))
		{
			return $GLOBALS['xmlEntities'][strtolower($ent)];
		}
		if (preg_match('/'."^#([0-9]+)$".'/', $ent, $regs))
		{
			return chr($regs[1]);
		}
		return '?';
	}

	function xmlrpc_se($parser, $name, $attrs)
	{
		// if invalid xmlrpc already detected, skip all processing
		if ($GLOBALS['_xh'][$parser]['isf'] < 2)
		{
			// check for correct element nesting
			// top level element can only be of 2 types
			if (count($GLOBALS['_xh'][$parser]['stack']) == 0)
			{
				if ($name != 'METHODRESPONSE' && $name != 'METHODCALL')
				{
					$GLOBALS['_xh'][$parser]['isf'] = 2;
					$GLOBALS['_xh'][$parser]['isf_reason'] = 'missing top level xmlrpc element';
					return;
				}
			}
			else
			{
				// not top level element: see if parent is OK
				if (!in_array($GLOBALS['_xh'][$parser]['stack'][0], $GLOBALS['xmlrpc_valid_parents'][$name]))
				{
					$GLOBALS['_xh'][$parser]['isf'] = 2;
					$GLOBALS['_xh'][$parser]['isf_reason'] = "xmlrpc element $name cannot be child of {$GLOBALS['_xh'][$parser]['stack'][0]}";
					return;
				}
			}

			switch($name)
			{
				case 'STRUCT':
				case 'ARRAY':
					// create an empty array to hold child values, and push it onto appropriate stack
					$cur_val = array();
					$cur_val['values'] = array();
					$cur_val['type'] = $name;
					@array_unshift($GLOBALS['_xh'][$parser]['valuestack'], $cur_val);
					break;
				case 'DATA':
				case 'METHODCALL':
				case 'METHODRESPONSE':
				case 'PARAMS':
					// valid elements that add little to processing
					break;
				case 'METHODNAME':
				case 'NAME':
					$GLOBALS['_xh'][$parser]['ac']='';
					break;
				case 'FAULT':
					$GLOBALS['_xh'][$parser]['isf']=1;
					break;
				case 'VALUE':
					$GLOBALS['_xh'][$parser]['vt']='value'; // indicator: no value found yet
					$GLOBALS['_xh'][$parser]['ac']='';
					$GLOBALS['_xh'][$parser]['lv']=1;
					break;
				case 'I4':
				case 'INT':
				case 'STRING':
				case 'BOOLEAN':
				case 'DOUBLE':
				case 'DATETIME.ISO8601':
				case 'BASE64':
					if ($GLOBALS['_xh'][$parser]['vt']!='value')
					{
						//two data elements inside a value: an error occurred!
						$GLOBALS['_xh'][$parser]['isf'] = 2;
						$GLOBALS['_xh'][$parser]['isf_reason'] = "$name element following a {$GLOBALS['_xh'][$parser]['vt']} element inside a single value";
						return;
					}

					$GLOBALS['_xh'][$parser]['ac']=''; // reset the accumulator
					break;
				case 'MEMBER':
					$GLOBALS['_xh'][$parser]['valuestack'][0]['name']=''; // set member name to null, in case we do not find in the xml later on
					//$GLOBALS['_xh'][$parser]['ac']='';
					// Drop trough intentionally
				case 'PARAM':
					// clear value, so we can check later if no value will passed for this param/member
					$GLOBALS['_xh'][$parser]['value']=null;
					break;
				default:
					/// INVALID ELEMENT: RAISE ISF so that it is later recognized!!!
					$GLOBALS['_xh'][$parser]['isf'] = 2;
					$GLOBALS['_xh'][$parser]['isf_reason'] = "found not-xmlrpc xml element $name";
					break;
			}

			// Save current element name to stack, to validate nesting
			@array_unshift($GLOBALS['_xh'][$parser]['stack'], $name);

			if($name!='VALUE')
			{
				$GLOBALS['_xh'][$parser]['lv']=0;
			}
		}
	}

	function xmlrpc_ee($parser, $name)
	{
		if ($GLOBALS['_xh'][$parser]['isf'] < 2)
		{
			// push this element name from stack
			// NB: if XML validates, correct opening/closing is guaranteed and
			// we do not have to check for $name == $curr_elem.
			// we also checked for proper nesting at start of elements...
			$curr_elem = array_shift($GLOBALS['_xh'][$parser]['stack']);

			switch($name)
			{
				case 'STRUCT':
				case 'ARRAY':
					// fetch out of stack array of values, and promote it to current value
					$curr_val = array_shift($GLOBALS['_xh'][$parser]['valuestack']);
					$GLOBALS['_xh'][$parser]['value'] = $curr_val['values'];

					$GLOBALS['_xh'][$parser]['vt']=strtolower($name);
					break;
				case 'NAME':
					$GLOBALS['_xh'][$parser]['valuestack'][0]['name'] = $GLOBALS['_xh'][$parser]['ac'];
					break;
				case 'BOOLEAN':
				case 'I4':
				case 'INT':
				case 'STRING':
				case 'DOUBLE':
				case 'DATETIME.ISO8601':
				case 'BASE64':
					$GLOBALS['_xh'][$parser]['vt']=strtolower($name);
					if ($name=='STRING')
					{
						$GLOBALS['_xh'][$parser]['value']=$GLOBALS['_xh'][$parser]['ac'];
					}
					elseif ($name=='DATETIME.ISO8601')
					{
						$GLOBALS['_xh'][$parser]['vt'] = xmlrpcDateTime;
						$GLOBALS['_xh'][$parser]['value']=$GLOBALS['_xh'][$parser]['ac'];
					}
					elseif ($name=='BASE64')
					{
						///@todo check for failure of base64 decoding / catch warnings
						$GLOBALS['_xh'][$parser]['value'] = base64_decode($GLOBALS['_xh'][$parser]['ac']);
					}
					elseif ($name=='BOOLEAN')
					{
						// special case here: we translate boolean 1 or 0 into PHP
							// constants true or false
							// NB: this simple checks helps a lot sanitizing input, ie no
							// security problems around here
							if ($GLOBALS['_xh'][$parser]['ac']=='1')
							{
								$GLOBALS['_xh'][$parser]['value']=true;
							}
							else
							{
								// log if receiveing something strange, even though we set the value to false anyway
								if ($GLOBALS['_xh'][$parser]['ac']!='0')
								error_log('XML-RPC: invalid value received in BOOLEAN: '.$GLOBALS['_xh'][$parser]['ac']);
								$GLOBALS['_xh'][$parser]['value']=false;
							}
					}
					elseif ($name=='DOUBLE')
					{
						// we have a DOUBLE
						// we must check that only 0123456789-.<space> are characters here
						if (!ereg("^[+-]?[eE0123456789 \\t\\.]+$", $GLOBALS['_xh'][$parser]['ac']))
						{
							// TODO: find a better way of throwing an error
							// than this!
							error_log('XML-RPC: non numeric value received in DOUBLE: '.$GLOBALS['_xh'][$parser]['ac']);
							$GLOBALS['_xh'][$parser]['value']='ERROR_NON_NUMERIC_FOUND';
						}
						else
						{
							// it's ok, add it on
							$GLOBALS['_xh'][$parser]['value']=(double)$GLOBALS['_xh'][$parser]['ac'];
						}
					}
					else
					{
						// we have an I4/INT
						// we must check that only 0123456789-<space> are characters here
						if (!ereg("^[+-]?[0123456789 \\t]+$", $GLOBALS['_xh'][$parser]['ac']))
						{
							// TODO: find a better way of throwing an error
							// than this!
							error_log('XML-RPC: non numeric value received in INT: '.$GLOBALS['_xh'][$parser]['ac']);
							$GLOBALS['_xh'][$parser]['value']='ERROR_NON_NUMERIC_FOUND';
						}
						else
						{
							// it's ok, add it on
							$GLOBALS['_xh'][$parser]['value'] = (int)$GLOBALS['_xh'][$parser]['ac'];
						}
					}
					$GLOBALS['_xh'][$parser]['ac']=''; // is this necessary?
					$GLOBALS['_xh'][$parser]['lv']=3; // indicate we've found a value
					break;
				case 'VALUE':
					// This if() detects if no scalar was inside <VALUE></VALUE>
					if ($GLOBALS['_xh'][$parser]['vt'] == 'value')
					{
						$GLOBALS['_xh'][$parser]['value'] = $GLOBALS['_xh'][$parser]['ac'];
						$GLOBALS['_xh'][$parser]['vt'] = xmlrpcString;
					}

					// build the xmlrpc val out of the data received, and substitute it
					$temp =& CreateObject('phpgwapi.xmlrpcval',$GLOBALS['_xh'][$parser]['value'], $GLOBALS['_xh'][$parser]['vt']);
					// check if we are inside an array or struct:
					// if value just built is inside an array, let's move it into array on the stack
					if (count($GLOBALS['_xh'][$parser]['valuestack']) && $GLOBALS['_xh'][$parser]['valuestack'][0]['type']=='ARRAY')
					{
						$GLOBALS['_xh'][$parser]['valuestack'][0]['values'][] = $temp;
					}
					else
					{
						$GLOBALS['_xh'][$parser]['value'] = $temp;
					}
					break;
				case 'MEMBER':
					$GLOBALS['_xh'][$parser]['ac']=''; // is this necessary?
					// add to array in the stack the last element built,
					// unless no VALUE was found
					if ($GLOBALS['_xh'][$parser]['value'])
					$GLOBALS['_xh'][$parser]['valuestack'][0]['values'][$GLOBALS['_xh'][$parser]['valuestack'][0]['name']] = $GLOBALS['_xh'][$parser]['value'];
					else
					error_log('XML-RPC: missing VALUE inside STRUCT in received xml');
					break;
				case 'DATA':
					$GLOBALS['_xh'][$parser]['ac']=''; // is this necessary?
					break;
				case 'PARAM':
					// add to array of params the current value,
					// unless no VALUE was found
					if ($GLOBALS['_xh'][$parser]['value'])
					$GLOBALS['_xh'][$parser]['params'][]=$GLOBALS['_xh'][$parser]['value'];
					else
					error_log('XML-RPC: missing VALUE inside PARAM in received xml');
					break;
				case 'METHODNAME':
					$GLOBALS['_xh'][$parser]['method']=preg_replace('/'."^[\n\r\t ]+".'/', '', $GLOBALS['_xh'][$parser]['ac']);
					break;
				case 'PARAMS':
				case 'FAULT':
				case 'METHODCALL':
				case 'METHORESPONSE':
					break;
				default:
					// End of INVALID ELEMENT!
					// shall we add an assert here for unreachable code???
					break;
			}
		}
	}

	function xmlrpc_cd($parser, $data)
	{
		//if(preg_match('/'."^[\n\r \t]+$".'/', $data)) return;
		// print "adding [${data}]\n";

		// skip processing if xml fault already detected
		if ($GLOBALS['_xh'][$parser]['isf'] < 2)
		{
			if($GLOBALS['_xh'][$parser]['lv']!=3)
			{
				// "lookforvalue==3" means that we've found an entire value
				// and should discard any further character data
				if($GLOBALS['_xh'][$parser]['lv']==1)
				{
					// if we've found text and we're just in a <value> then
					// say we've found a value
					$GLOBALS['_xh'][$parser]['lv']=2;
				}
				if(!@isset($GLOBALS['_xh'][$parser]['ac']))
				{
					$GLOBALS['_xh'][$parser]['ac'] = '';
				}
				$GLOBALS['_xh'][$parser]['ac'].=$data;
			}
		}
	}

	function xmlrpc_dh($parser, $data)
	{
		// skip processing if xml fault already detected
		if ($GLOBALS['_xh'][$parser]['isf'] < 2)
		{
			if(substr($data, 0, 1) == '&' && substr($data, -1, 1) == ';')
			{
				if($GLOBALS['_xh'][$parser]['lv']==1)
				{
					$GLOBALS['_xh'][$parser]['lv']=2;
				}
				$GLOBALS['_xh'][$parser]['ac'].=$data;
			}
		}
	}

	// date helpers
	function iso8601_encode($timet, $utc=0)
	{
		// return an ISO8601 encoded string
		// really, timezones ought to be supported
		// but the XML-RPC spec says:
		//
		// "Don't assume a timezone. It should be specified by the server in its
		// documentation what assumptions it makes about timezones."
		//
		// these routines always assume localtime unless
		// $utc is set to 1, in which case UTC is assumed
		// and an adjustment for locale is made when encoding
		if (!$utc)
		{
			$t=strftime("%Y%m%dT%H:%M:%S", $timet);
		}
		else
		{
			if(function_exists('gmstrftime'))
			{
				// gmstrftime doesn't exist in some versions
				// of PHP
				$t = gmstrftime("%Y%m%dT%H:%M:%S", $timet);
			}
			else
			{
				$t = strftime('%Y%m%dT%H:%M:%S', $timet-date('Z'));
			}
		}
		return $t;
	}

	function iso8601_decode($idate, $utc=0)
	{
		// return a time in the localtime, or UTC
		$t = 0;
		if (preg_match('/'."([0-9]{4})([0-9]{2})([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})".'/',$idate, $regs))
		{
			if ($utc)
			{
				$t=gmmktime($regs[4], $regs[5], $regs[6], $regs[2], $regs[3], $regs[1]);
			}
			else
			{
				$t=mktime($regs[4], $regs[5], $regs[6], $regs[2], $regs[3], $regs[1]);
			}
		}
		return $t;
	}

	/****************************************************************
	* xmlrpc_decode takes a message in PHP xmlrpc object format and *
	* tranlates it into native PHP types.                           *
	*                                                               *
	* author: Dan Libby (dan@libby.com)                             *
	****************************************************************/
	function phpgw_xmlrpc_decode($xmlrpc_val)
	{
		$kind = @$xmlrpc_val->kindOf();

		if($kind == 'scalar')
		{
			return $xmlrpc_val->scalarval();
		}
		elseif($kind == 'array')
		{
			$size = $xmlrpc_val->arraysize();
			$arr = array();

			for($i = 0; $i < $size; $i++)
			{
				$arr[] = phpgw_xmlrpc_decode($xmlrpc_val->arraymem($i));
			}
			return $arr;
		}
		elseif($kind == 'struct')
		{
			$xmlrpc_val->structreset();
			$arr = array();

			while(list($key,$value)=$xmlrpc_val->structeach())
			{
				$arr[$key] = phpgw_xmlrpc_decode($value);
			}
			return $arr;
		}
	}

	/****************************************************************
	* xmlrpc_encode takes native php types and encodes them into    *
	* xmlrpc PHP object format.                                     *
	* BUG: All sequential arrays are turned into structs.  I don't  *
	* know of a good way to determine if an array is sequential     *
	* only.                                                         *
	*                                                               *
	* feature creep -- could support more types via optional type   *
	* argument.                                                     *
	*                                                               *
	* author: Dan Libby (dan@libby.com)                             *
	****************************************************************/
	function phpgw_xmlrpc_encode($php_val)
	{
		$type = gettype($php_val);
		$xmlrpc_val = CreateObject('phpgwapi.xmlrpcval');

		switch($type)
		{
			case 'array':
			case 'object':
				$arr = array();
				while(list($k,$v) = each($php_val))
				{
					$arr[$k] = phpgw_xmlrpc_encode($v);
				}
				$xmlrpc_val->addStruct($arr);
				break;
			case 'integer':
				$xmlrpc_val->addScalar($php_val, xmlrpcInt);
				break;
			case 'double':
				$xmlrpc_val->addScalar($php_val, xmlrpcDouble);
				break;
			case 'string':
				$xmlrpc_val->addScalar($php_val, xmlrpcString);
				break;
			// <G_Giunta_2001-02-29>
			// Add support for encoding/decoding of booleans, since they are supported in PHP
			case 'boolean':
				$xmlrpc_val->addScalar($php_val, xmlrpcBoolean);
				break;
			// </G_Giunta_2001-02-29>
			case 'unknown type':
			default:
				$xmlrpc_val = False;
				break;
		}
		return $xmlrpc_val;
	}

	/* The following functions are the system functions for login, logout, etc.
	 * They are added to the server map at the end of this file.
	 */

	$GLOBALS['_xmlrpcs_listMethods_sig'] = array(array(xmlrpcArray, xmlrpcString), array(xmlrpcArray));
	$GLOBALS['_xmlrpcs_listMethods_doc'] = 'This method lists all the methods that the XML-RPC server knows how to dispatch';
	function _xmlrpcs_listMethods($server, $m)
	{
		$v     =  CreateObject('phpgwapi.xmlrpcval');
		$dmap  = $server->dmap;
		$outAr = array();
		for(reset($dmap); list($key, $val) = each($dmap); )
		{
			$outAr[] = CreateObject('phpgwapi.xmlrpcval',$key, 'string');
		}
		$dmap = $GLOBALS['_xmlrpcs_dmap'];
		for(reset($dmap); list($key, $val) = each($dmap); )
		{
			$outAr[] = CreateObject('phpgwapi.xmlrpcval',$key, 'string');
		}
		$v->addArray($outAr);
		return CreateObject('phpgwapi.xmlrpcresp',$v);
	}

	$GLOBALS['_xmlrpcs_methodSignature_sig']=array(array(xmlrpcArray, xmlrpcString));
	$GLOBALS['_xmlrpcs_methodSignature_doc']='Returns an array of known signatures (an array of arrays) for the method name passed. If no signatures are known, returns a none-array (test for type != array to detect missing signature)';
	function _xmlrpcs_methodSignature($server, $m)
	{
		$methName = $m->getParam(0);
		$methName = $methName->scalarval();
		if (preg_match('/'."^system\.".'/', $methName))
		{
			$dmap = $GLOBALS['_xmlrpcs_dmap'];
			$sysCall = 1;
		}
		else
		{
			$dmap = $server->dmap;
			$sysCall = 0;
		}
		//	print "<!-- ${methName} -->\n";
		if (isset($dmap[$methName]))
		{
			if ($dmap[$methName]['signature'])
			{
				$sigs = array();
				$thesigs=$dmap[$methName]['signature'];
				for($i=0; $i<sizeof($thesigs); $i++)
				{
					$cursig = array();
					$inSig  = $thesigs[$i];
					for($j=0; $j<sizeof($inSig); $j++)
					{
						$cursig[] = CreateObject('phpgwapi.xmlrpcval',$inSig[$j], 'string');
					}
					$sigs[] = CreateObject('phpgwapi.xmlrpcval',$cursig, 'array');
				}
				$r = CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',$sigs, 'array'));
			}
			else
			{
				$r = CreateObject('phpgwapi.xmlrpcresp', CreateObject('phpgwapi.xmlrpcval','undef', 'string'));
			}
		}
		else
		{
			$r = CreateObject('phpgwapi.xmlrpcresp',0,$GLOBALS['xmlrpcerr']['introspect_unknown'],$GLOBALS['xmlrpcstr']['introspect_unknown']);
		}
		return $r;
	}

	$GLOBALS['_xmlrpcs_methodHelp_sig'] = array(array(xmlrpcString, xmlrpcString));
	$GLOBALS['_xmlrpcs_methodHelp_doc'] = 'Returns help text if defined for the method passed, otherwise returns an empty string';
	function _xmlrpcs_methodHelp($server, $m)
	{
		$methName = $m->getParam(0);
		$methName = $methName->scalarval();
		if (preg_match('/'."^system\.".'/', $methName))
		{
			$dmap = $GLOBALS['_xmlrpcs_dmap'];
			$sysCall=1;
		}
		else
		{
			$dmap = $server->dmap;
			$sysCall=0;
		}
		//	print "<!-- ${methName} -->\n";
		if (isset($dmap[$methName]))
		{
			if ($dmap[$methName]['docstring'])
			{
				$r = CreateObject('phpgwapi.xmlrpcresp', CreateObject('phpgwapi.xmlrpcval',$dmap[$methName]['docstring']),'string');
			}
			else
			{
				$r = CreateObject('phpgwapi.xmlrpcresp', CreateObject('phpgwapi.xmlrpcval'), 'string');
			}
		}
		else
		{
			$r = CreateObject('phpgwapi.xmlrpcresp',0,$GLOBALS['xmlrpcerr']['introspect_unknown'],$GLOBALS['xmlrpcstr']['introspect_unknown']);
		}
		return $r;
	}

	$GLOBALS['_xmlrpcs_login_sig'] = array(array(xmlrpcStruct,xmlrpcStruct));
	$GLOBALS['_xmlrpcs_login_doc'] = 'eGroupWare client or server login via XML-RPC';
	function _xmlrpcs_login($server,$m)
	{
		$rdata = $m->getParam(0);
		$data = $rdata->scalarval();

		if($data['server_name'])
		{
			$server_name = $data['server_name']->scalarval();
		}
		if($data['domain'])
		{
			$domain = $data['domain']->scalarval();
		}
		$username = $data['username']->scalarval();
		$password = $data['password']->scalarval();

		if($server_name)
		{
			list($sessionid,$kp3) = $GLOBALS['egw']->session->create_server($username.'@'.$server_name,$password,"text");
		}
		else
		{
			if($domain)
			{
				$user = $username.'@'.$domain;
			}
			else
			{
				$user = $username;
			}
			$GLOBALS['login'] = $user;

			$sessionid = $GLOBALS['egw']->session->create($user,$password,"text");
			$kp3 = $GLOBALS['egw']->session->kp3;
			$domain = $GLOBALS['egw']->session->account_domain;
		}

		if($sessionid && $kp3)
		{
			$rtrn['domain'] = CreateObject('phpgwapi.xmlrpcval',$domain,'string');
			$rtrn['sessionid'] = CreateObject('phpgwapi.xmlrpcval',$sessionid,'string');
			$rtrn['kp3'] = CreateObject('phpgwapi.xmlrpcval',$kp3,'string');
		}
		else
		{
			$rtrn['GOAWAY'] = CreateObject('phpgwapi.xmlrpcval','XOXO','string');
		}
		return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',$rtrn,'struct'));
	}

	$GLOBALS['_xmlrpcs_logout_sig'] = array(array(xmlrpcStruct,xmlrpcStruct));
	$GLOBALS['_xmlrpcs_logout_doc'] = 'eGroupWare client or server logout via XML-RPC';
	function _xmlrpcs_logout($server,$m)
	{
		$rdata = $m->getParam(0);
		$data = $rdata->scalarval();

		$sessionid = $data['sessionid']->scalarval();
		$kp3       = $data['kp3']->scalarval();

		$later = $GLOBALS['egw']->session->destroy($sessionid,$kp3);

		if ($later)
		{
			$rtrn['GOODBYE'] = CreateObject('phpgwapi.xmlrpcval','XOXO','string');
		}
		else
		{
			/* This never happens, yet */
			$rtrn['OOPS'] = CreateObject('phpgwapi.xmlrpcval','WHAT?','string');
		}
		return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',$rtrn,'struct'));
	}

	$GLOBALS['_xmlrpcs_phpgw_api_version_sig'] = array(array(xmlrpcString));
	$GLOBALS['_xmlrpcs_phpgw_api_version_doc'] = 'Returns the eGroupWare API version';
	function _xmlrpcs_phpgw_api_version($server,$m)
	{
		$version = $GLOBALS['egw_info']['server']['versions']['phpgwapi'];

		return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',$version,'string'));
	}

	/*
	$GLOBALS['_xmlrpcs_listApps_sig'] = array(array(xmlrpcStruct,xmlrpcString));
	$GLOBALS['_xmlrpcs_listApps_doc'] = 'Returns a list of installed phpgw apps';
	function _xmlrpcs_listApps($server,$m)
	{
		$m->getParam(0);
		$GLOBALS['egw']->db->query("SELECT * FROM egw_applications WHERE app_enabled<3",__LINE__,__FILE__);
		if($GLOBALS['egw']->db->num_rows())
		{
			while($GLOBALS['egw']->db->next_record())
			{
				$name   = $GLOBALS['egw']->db->f('app_name');
				$title  = $GLOBALS['egw']->db->f('app_title');
				$status = $GLOBALS['egw']->db->f('app_enabled');
				$version= $GLOBALS['egw']->db->f('app_version');
				$apps[$name] = CreateObject('phpgwapi.xmlrpcval',
					array(
						'title'  => CreateObject('phpgwapi.xmlrpcval',$title,'string'),
						'name'   => CreateObject('phpgwapi.xmlrpcval',$name,'string'),
						'status' => CreateObject('phpgwapi.xmlrpcval',$status,'string'),
						'version'=> CreateObject('phpgwapi.xmlrpcval',$version,'string')
					),
					'struct'
				);
			}
		}
		return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',$apps, 'struct'));
	}
	*/

	$GLOBALS['_xmlrpcs_egw_time_sig'] = array(array(xmlrpcStruct));
	$GLOBALS['_xmlrpcs_egw_time_doc'] = 'returns system-time and -timezone and if loged in user-time and timezone';
	function _xmlrpcs_time($server,$m)
	{
		$return = array(
			'system' => $GLOBALS['server']->date2iso8601(time()),
			'system_tz_offset' => (int) date('Z'),
		);
		if ($GLOBALS['server']->authed)
		{
			$tz_offset_s = 3600 * (int) $GLOBALS['egw_info']['user']['preferences']['common']['tz_offset'];
			$return += array(
				'user' => $GLOBALS['server']->date2iso8601(time()+$tz_offset_s),
				'user_tz_offset' => (int) date('Z') + $tz_offset_s,
			);
		}
		return CreateObject(
			'phpgwapi.xmlrpcresp',
			$GLOBALS['server']->build_resp($return,true)
		);
	}

	/* Add the system functions to the server map */
	$GLOBALS['_xmlrpcs_dmap'] = array(
		'system.listMethods' => array(
			'function'  => '_xmlrpcs_listMethods',
			'signature' => $GLOBALS['_xmlrpcs_listMethods_sig'],
			'docstring' => $GLOBALS['_xmlrpcs_listMethods_doc']
		),
		'system.methodHelp' => array(
			'function'  => '_xmlrpcs_methodHelp',
			'signature' => $GLOBALS['_xmlrpcs_methodHelp_sig'],
			'docstring' => $GLOBALS['_xmlrpcs_methodHelp_doc']
		),
		'system.methodSignature' => array(
			'function'  => '_xmlrpcs_methodSignature',
			'signature' => $GLOBALS['_xmlrpcs_methodSignature_sig'],
			'docstring' => $GLOBALS['_xmlrpcs_methodSignature_doc']
		),
		'system.login'  => array(
			'function'  => '_xmlrpcs_login',
			'signature' => $GLOBALS['_xmlrpcs_login_sig'],
			'docstring' => $GLOBALS['_xmlrpcs_login_doc']
		),
		'system.logout'  => array(
			'function'  => '_xmlrpcs_logout',
			'signature' => $GLOBALS['_xmlrpcs_logout_sig'],
			'docstring' => $GLOBALS['_xmlrpcs_logout_doc']
		),
		'system.phpgw_api_version' => array(
			'function'  => '_xmlrpcs_phpgw_api_version',
			'signature' => $GLOBALS['_xmlrpcs_phpgw_api_version_sig'],
			'docstring' => $GLOBALS['_xmlrpcs_phpgw_api_version_doc']
		),
		/*
		'system.listApps' => array(
			'function'  => '_xmlrpcs_listApps',
			'signature' => $GLOBALS['_xmlrpcs_listApps_sig'],
			'docstring' => $GLOBALS['_xmlrpcs_listApps_doc']
		),
		*/
		'system.time'  => array(
			'function'  => '_xmlrpcs_time',
			'signature' => $GLOBALS['_xmlrpcs_egw_time_sig'],
			'docstring' => $GLOBALS['_xmlrpcs_egw_time_doc']
		)
	);

	$GLOBALS['_xmlrpc_debuginfo'] = '';
	function xmlrpc_debugmsg($m)
	{
		$GLOBALS['_xmlrpc_debuginfo'] .= $m . "\n";
	}
?>
