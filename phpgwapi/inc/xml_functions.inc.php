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

	$xmlrpcI4       = 'i4';
	$xmlrpcInt      = 'int';
	$xmlrpcBoolean  = 'boolean';
	$xmlrpcDouble   = 'double';
	$xmlrpcString   = 'string';
	$xmlrpcDateTime = 'dateTime.iso8601';
	$xmlrpcBase64   = 'base64';
	$xmlrpcArray    = 'array';
	$xmlrpcStruct   = 'struct';

	$xmlrpcTypes = array(
		$xmlrpcI4       => 1,
		$xmlrpcInt      => 1,
		$xmlrpcBoolean  => 1,
		$xmlrpcString   => 1,
		$xmlrpcDouble   => 1,
		$xmlrpcDateTime => 1,
		$xmlrpcBase64   => 1,
		$xmlrpcArray    => 2,
		$xmlrpcStruct   => 3
	);

	$xmlEntities=array(
		'amp'  => '&',
		'quot' => '"',
		'lt'   => '<',
		'gt'   => '>',
		'apos' => "'"
	);

	$xmlrpcerr['unknown_method']     = 1;
	$xmlrpcstr['unknown_method']     = 'Unknown method';
	$xmlrpcerr['invalid_return']     = 2;
	$xmlrpcstr['invalid_return']     = 'Invalid return payload: enabling debugging to examine incoming payload';
	$xmlrpcerr['incorrect_params']   = 3;
	$xmlrpcstr['incorrect_params']   = 'Incorrect parameters passed to method';
	$xmlrpcerr['introspect_unknown'] = 4;
	$xmlrpcstr['introspect_unknown'] = "Can't introspect: method unknown";
	$xmlrpcerr['http_error']         = 5;
	$xmlrpcstr['http_error']         = "Didn't receive 200 OK from remote server.";

	$xmlrpc_defencoding = 'UTF-8';

	$xmlrpcName    = 'XML-RPC for PHP';
	$xmlrpcVersion = '1.0b9';

	// let user errors start at 800
	$xmlrpcerruser = 800; 
	// let XML parse errors start at 100
	$xmlrpcerrxml = 100;

	// formulate backslashes for escaping regexp
	$xmlrpc_backslash = chr(92) . chr(92);

	// used to store state during parsing
	// quick explanation of components:
	//   st - used to build up a string for evaluation
	//   ac - used to accumulate values
	//   qt - used to decide if quotes are needed for evaluation
	//   cm - used to denote struct or array (comma needed)
	//   isf - used to indicate a fault
	//   lv - used to indicate "looking for a value": implements
	//        the logic to allow values with no types to be strings
	//   params - used to store parameters in method calls
	//   method - used to store method name

	$_xh=array();

	function xmlrpc_entity_decode($string)
	{
		$top = split("&", $string);
		$op  = '';
		$i   = 0;
		while($i<sizeof($top))
		{
			if (ereg("^([#a-zA-Z0-9]+);", $top[$i], $regs))
			{
				$op .= ereg_replace("^[#a-zA-Z0-9]+;",
					xmlrpc_lookup_entity($regs[1]), $top[$i]);
			}
			else
			{
				if ($i == 0) 
				{
					$op = $top[$i];
				}
				else
				{
					$op .= '&' . $top[$i];
				}
			}
			$i++;
		}
		return $op;
	}

	function xmlrpc_lookup_entity($ent)
	{
		global $xmlEntities;

		if (isset($xmlEntities[strtolower($ent)]))
		{
			return $xmlEntities[strtolower($ent)];
		}
		if (ereg("^#([0-9]+)$", $ent, $regs))
		{
			return chr($regs[1]);
		}
		return '?';
	}

	function xmlrpc_se($parser, $name, $attrs)
	{
		global $_xh, $xmlrpcDateTime, $xmlrpcString;

		switch($name)
		{
			case 'STRUCT':
			case 'ARRAY':
				$_xh[$parser]['st'] .= 'array(';
				$_xh[$parser]['cm']++;
				// this last line turns quoting off
				// this means if we get an empty array we'll 
				// simply get a bit of whitespace in the eval
				$_xh[$parser]['qt']=0;
				break;
			case 'NAME':
				$_xh[$parser]['st'] .= "'";
				$_xh[$parser]['ac'] = '';
				break;
			case 'FAULT':
				$_xh[$parser]['isf'] = 1;
				break;
			case 'PARAM':
				$_xh[$parser]['st'] = '';
				break;
			case 'VALUE':
				$_xh[$parser]['st'] .= " CreateObject('phpgwapi.xmlrpcval',"; 
				$_xh[$parser]['vt']  = $xmlrpcString;
				$_xh[$parser]['ac']  = '';
				$_xh[$parser]['qt']  = 0;
				$_xh[$parser]['lv']  = 1;
				// look for a value: if this is still 1 by the
				// time we reach the first data segment then the type is string
				// by implication and we need to add in a quote
				break;
			case 'I4':
			case 'INT':
			case 'STRING':
			case 'BOOLEAN':
			case 'DOUBLE':
			case 'DATETIME.ISO8601':
			case 'BASE64':
				$_xh[$parser]['ac']=''; // reset the accumulator

				if ($name=='DATETIME.ISO8601' || $name=='STRING')
				{
					$_xh[$parser]['qt']=1;
					if ($name=='DATETIME.ISO8601')
					{
						$_xh[$parser]['vt']=$xmlrpcDateTime;
					}
				}
				elseif($name=='BASE64')
				{
					$_xh[$parser]['qt']=2;
				}
				else
				{
					// No quoting is required here -- but
					// at the end of the element we must check
					// for data format errors.
					$_xh[$parser]['qt']=0;
				}
				break;
			case 'MEMBER':
				$_xh[$parser]['ac']='';
				break;
			default:
				break;
		}

		if ($name!='VALUE')
		{
			$_xh[$parser]['lv']=0;
		}
	}

	function xmlrpc_ee($parser, $name)
	{
		global $_xh,$xmlrpcTypes,$xmlrpcString;

		switch($name)
		{
			case 'STRUCT':
			case 'ARRAY':
				if ($_xh[$parser]['cm'] && substr($_xh[$parser]['st'], -1) ==',')
				{
					$_xh[$parser]['st']=substr($_xh[$parser]['st'],0,-1);
				}
				$_xh[$parser]['st'].=")";	
				$_xh[$parser]['vt']=strtolower($name);
				$_xh[$parser]['cm']--;
				break;
			case 'NAME':
				$_xh[$parser]['st'].= $_xh[$parser]['ac'] . "' => ";
				break;
			case 'BOOLEAN':
				// special case here: we translate boolean 1 or 0 into PHP
				// constants true or false
				if ($_xh[$parser]['ac']=='1') 
				{
					$_xh[$parser]['ac']='True';
				}
				else
				{
					$_xh[$parser]['ac']='false';
				}
				$_xh[$parser]['vt']=strtolower($name);
				// Drop through intentionally.
			case 'I4':
			case 'INT':
			case 'STRING':
			case 'DOUBLE':
			case 'DATETIME.ISO8601':
			case 'BASE64':
				if ($_xh[$parser]['qt']==1)
				{
					// we use double quotes rather than single so backslashification works OK
					$_xh[$parser]['st'].="\"". $_xh[$parser]['ac'] . "\""; 
				}
				elseif ($_xh[$parser]['qt']==2)
				{
					$_xh[$parser]['st'].="base64_decode('". $_xh[$parser]['ac'] . "')"; 
				}
				else if ($name=='BOOLEAN')
				{
					$_xh[$parser]['st'].=$_xh[$parser]['ac'];
				}
				else
				{
					// we have an I4, INT or a DOUBLE
					// we must check that only 0123456789-.<space> are characters here
					if (!ereg("^\-?[0123456789 \t\.]+$", $_xh[$parser]['ac']))
					{
						// TODO: find a better way of throwing an error
						// than this!
						error_log("XML-RPC: non numeric value received in INT or DOUBLE");
						$_xh[$parser]['st'].="ERROR_NON_NUMERIC_FOUND";
					}
					else
					{
						// it's ok, add it on
						$_xh[$parser]['st'].=$_xh[$parser]['ac'];
					}
				}
				$_xh[$parser]['ac']=""; $_xh[$parser]['qt']=0;
				$_xh[$parser]['lv']=3; // indicate we've found a value
				break;
			case 'VALUE':
				// deal with a string value
				if (strlen($_xh[$parser]['ac'])>0 &&
					$_xh[$parser]['vt']==$xmlrpcString)
				{
					$_xh[$parser]['st'].="\"". $_xh[$parser]['ac'] . "\""; 
				}
				// This if() detects if no scalar was inside <VALUE></VALUE>
				// and pads an empty "".
				if($_xh[$parser]['st'][strlen($_xh[$parser]['st'])-1] == '(')
				{
					$_xh[$parser]['st'].= '""';
				}
				$_xh[$parser]['st'].=", '" . $_xh[$parser]['vt'] . "')";
				if ($_xh[$parser]['cm'])
				{
					$_xh[$parser]['st'].=",";
				}
				break;
			case 'MEMBER':
				$_xh[$parser]['ac']=""; $_xh[$parser]['qt']=0;
				 break;
			case 'DATA':
				$_xh[$parser]['ac']=""; $_xh[$parser]['qt']=0;
				break;
			case 'PARAM':
				$_xh[$parser]['params'][]=$_xh[$parser]['st'];
				break;
			case 'METHODNAME':
				$_xh[$parser]['method']=ereg_replace("^[\n\r\t ]+", "", $_xh[$parser]['ac']);
				break;
			case 'BOOLEAN':
				// special case here: we translate boolean 1 or 0 into PHP
				// constants true or false
				if ($_xh[$parser]['ac']=='1') 
				{
					$_xh[$parser]['ac']="True";
				}
				else
				{
					$_xh[$parser]['ac']="false";
				}
				$_xh[$parser]['vt']=strtolower($name);
				break;
			default:
				break;
		}
		// if it's a valid type name, set the type
		if (isset($xmlrpcTypes[strtolower($name)]))
		{
			$_xh[$parser]['vt']=strtolower($name);
		}
	}

	function xmlrpc_cd($parser, $data)
	{
		global $_xh, $xmlrpc_backslash;

		//if (ereg("^[\n\r \t]+$", $data)) return;
		// print "adding [${data}]\n";

		if ($_xh[$parser]['lv']!=3)
		{
			// "lookforvalue==3" means that we've found an entire value
			// and should discard any further character data
			if ($_xh[$parser]['lv']==1)
			{
				// if we've found text and we're just in a <value> then
				// turn quoting on, as this will be a string
				$_xh[$parser]['qt']=1; 
				// and say we've found a value
				$_xh[$parser]['lv']=2; 
			}
			if (isset($_xh[$parser]['qt']) && $_xh[$parser]['qt'])
			{
				// quoted string: replace characters that eval would
				// do special things with
				$_xh[$parser]['ac'].=str_replace('$', '\$',
					str_replace('"', '\"', 
					str_replace(chr(92),$xmlrpc_backslash, $data)));
			}
			else 
			{
				$_xh[$parser]['ac'].=$data;
			}
		}
	}

	function xmlrpc_dh($parser, $data)
	{
		global $_xh;
		if (substr($data, 0, 1) == "&" && substr($data, -1, 1) == ";")
		{
			if ($_xh[$parser]['lv']==1)
			{
				$_xh[$parser]['qt']=1; 
				$_xh[$parser]['lv']=2; 
			}
			$_xh[$parser]['ac'].=$data;
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
			if (function_exists("gmstrftime")) 
			{
				// gmstrftime doesn't exist in some versions
				// of PHP
				$t=gmstrftime("%Y%m%dT%H:%M:%S", $timet);
			}
			else
			{
				$t=strftime("%Y%m%dT%H:%M:%S", $timet-date("Z"));
			}
		}
		return $t;
	}

	function iso8601_decode($idate, $utc=0)
	{
		// return a timet in the localtime, or UTC
		$t=0;
		if (ereg("([0-9]{4})([0-9]{2})([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})",$idate, $regs))
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
	function xmlrpc_decode($xmlrpc_val)
	{
		$kind = @$xmlrpc_val->kindOf();

		if($kind == "scalar")
		{
			return $xmlrpc_val->scalarval();
		}
		elseif($kind == "array")
		{
			$size = $xmlrpc_val->arraysize();
			$arr = array();

			for($i = 0; $i < $size; $i++)
			{
				$arr[]=xmlrpc_decode($xmlrpc_val->arraymem($i));
			}
			return $arr; 
		}
		elseif($kind == "struct")
		{
			$xmlrpc_val->structreset();
			$arr = array();

			while(list($key,$value)=$xmlrpc_val->structeach())
			{
				$arr[$key] = xmlrpc_decode($value);
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
	function xmlrpc_encode($php_val)
	{
		global $xmlrpcInt;
		global $xmlrpcDouble;
		global $xmlrpcString;
		global $xmlrpcArray;
		global $xmlrpcStruct;

		$type = gettype($php_val);
		$xmlrpc_val = CreateObject('phpgwapi.xmlrpcval');

		switch($type)
		{
			case "array":
			case "object":
				$arr = array();
				while (list($k,$v) = each($php_val))
				{
					$arr[$k] = xmlrpc_encode($v);
				}
				$xmlrpc_val->addStruct($arr);
				break;
			case "integer":
				$xmlrpc_val->addScalar($php_val, $xmlrpcInt);
				break;
			case "double":
				$xmlrpc_val->addScalar($php_val, $xmlrpcDouble);
				break;
			case "string":
				$xmlrpc_val->addScalar($php_val, $xmlrpcString);
				break;
			// <G_Giunta_2001-02-29>
			// Add support for encoding/decoding of booleans, since they are supported in PHP
			case "boolean":
				$xmlrpc_val->addScalar($php_val, $xmlrpcBoolean);
				break;
			// </G_Giunta_2001-02-29>
			case "unknown type":
			default:
				$xmlrpc_val = false;
				break;
		}
		return $xmlrpc_val;
	}

	// listMethods: either a string, or nothing
	$_xmlrpcs_listMethods_sig = array(array($xmlrpcArray, $xmlrpcString), array($xmlrpcArray));
	$_xmlrpcs_listMethods_doc = 'This method lists all the methods that the XML-RPC server knows how to dispatch';
	function _xmlrpcs_listMethods($server, $m)
	{
		global $xmlrpcerr, $xmlrpcstr, $_xmlrpcs_dmap;

		$v     =  CreateObject('phpgwapi.xmlrpcval');
		$dmap  = $server->dmap;
		$outAr = array();
		for(reset($dmap); list($key, $val) = each($dmap); )
		{
			$outAr[] = CreateObject('phpgwapi.xmlrpcval',$key, 'string');
		}
		$dmap = $_xmlrpcs_dmap;
		for(reset($dmap); list($key, $val) = each($dmap); )
		{
			$outAr[] = CreateObject('phpgwapi.xmlrpcval',$key, 'string');
		}
		$v->addArray($outAr);
		return CreateObject('phpgwapi.xmlrpcresp',$v);
	}

	$_xmlrpcs_methodSignature_sig=array(array($xmlrpcArray, $xmlrpcString));
	$_xmlrpcs_methodSignature_doc='Returns an array of known signatures (an array of arrays) for the method name passed. If no signatures are known, returns a none-array (test for type != array to detect missing signature)';
	function _xmlrpcs_methodSignature($server, $m)
	{
		global $xmlrpcerr, $xmlrpcstr, $_xmlrpcs_dmap;

		$methName = $m->getParam(0);
		$methName = $methName->scalarval();
		if (ereg("^system\.", $methName))
		{
			$dmap = $_xmlrpcs_dmap;
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
			$r = CreateObject('phpgwapi.xmlrpcresp',0,$xmlrpcerr['introspect_unknown'],$xmlrpcstr['introspect_unknown']);
		}
		return $r;
	}

	$_xmlrpcs_methodHelp_sig = array(array($xmlrpcString, $xmlrpcString));
	$_xmlrpcs_methodHelp_doc = 'Returns help text if defined for the method passed, otherwise returns an empty string';
	function _xmlrpcs_methodHelp($server, $m)
	{
		global $xmlrpcerr, $xmlrpcstr, $_xmlrpcs_dmap;

		$methName = $m->getParam(0);
		$methName = $methName->scalarval();
		if (ereg("^system\.", $methName))
		{
			$dmap = $_xmlrpcs_dmap; $sysCall=1;
		}
		else
		{
			$dmap = $server->dmap; $sysCall=0;
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
			$r = CreateObject('phpgwapi.xmlrpcresp',0,$xmlrpcerr['introspect_unknown'],$xmlrpcstr['introspect_unknown']);
		}
		return $r;
	}

	$_xmlrpcs_dmap=array(
		'system.listMethods' => array(
			'function'  => '_xmlrpcs_listMethods',
			'signature' => $_xmlrpcs_listMethods_sig,
			'docstring' => $_xmlrpcs_listMethods_doc
		),
		'system.methodHelp' => array(
			'function'  => '_xmlrpcs_methodHelp',
			'signature' => $_xmlrpcs_methodHelp_sig,
			'docstring' => $_xmlrpcs_methodHelp_doc
		),
		'system.methodSignature' => array(
			'function'  => '_xmlrpcs_methodSignature',
			'signature' => $_xmlrpcs_methodSignature_sig,
			'docstring' => $_xmlrpcs_methodSignature_doc
		)
	);

	$_xmlrpc_debuginfo = '';
	function xmlrpc_debugmsg($m)
	{
		global $_xmlrpc_debuginfo;
		$_xmlrpc_debuginfo = $_xmlrpc_debuginfo . $m . "\n";
	}

?>
