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

	$GLOBALS['xmlrpcerr']['unknown_method']     = 1;
	$GLOBALS['xmlrpcstr']['unknown_method']     = 'Unknown method';
	$GLOBALS['xmlrpcerr']['invalid_return']     = 2;
	$GLOBALS['xmlrpcstr']['invalid_return']     = 'Invalid return payload: enabling debugging to examine incoming payload';
	$GLOBALS['xmlrpcerr']['incorrect_params']   = 3;
	$GLOBALS['xmlrpcstr']['incorrect_params']   = 'Incorrect parameters passed to method';
	$GLOBALS['xmlrpcerr']['introspect_unknown'] = 4;
	$GLOBALS['xmlrpcstr']['introspect_unknown'] = "Can't introspect: method unknown";
	$GLOBALS['xmlrpcerr']['http_error']         = 5;
	$GLOBALS['xmlrpcstr']['http_error']         = "Didn't receive 200 OK from remote server.";

	$GLOBALS['xmlrpc_defencoding'] = 'UTF-8';

	$GLOBALS['xmlrpcName']    = 'XML-RPC for PHP';
	$GLOBALS['xmlrpcVersion'] = '1.0b9';

	// let user errors start at 800
	$GLOBALS['xmlrpcerruser'] = 800; 
	// let XML parse errors start at 100
	$GLOBALS['xmlrpcerrxml'] = 100;

	// formulate backslashes for escaping regexp
	$GLOBALS['xmlrpc_backslash'] = chr(92) . chr(92);

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

	$GLOBALS['_xh']=array();

	function xmlrpc_entity_decode($string)
	{
		$top = split('&', $string);
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
		if (isset($GLOBALS['xmlEntities'][strtolower($ent)]))
		{
			return $GLOBALS['xmlEntities'][strtolower($ent)];
		}
		if (ereg("^#([0-9]+)$", $ent, $regs))
		{
			return chr($regs[1]);
		}
		return '?';
	}

	function xmlrpc_se($parser, $name, $attrs)
	{
		switch($name)
		{
			case 'STRUCT':
			case 'ARRAY':
				$GLOBALS['_xh'][$parser]['st'] .= 'array(';
				$GLOBALS['_xh'][$parser]['cm']++;
				// this last line turns quoting off
				// this means if we get an empty array we'll 
				// simply get a bit of whitespace in the eval
				$GLOBALS['_xh'][$parser]['qt']=0;
				break;
			case 'NAME':
				$GLOBALS['_xh'][$parser]['st'] .= "'";
				$GLOBALS['_xh'][$parser]['ac'] = '';
				break;
			case 'FAULT':
				$GLOBALS['_xh'][$parser]['isf'] = 1;
				break;
			case 'PARAM':
				$GLOBALS['_xh'][$parser]['st'] = '';
				break;
			case 'VALUE':
				$GLOBALS['_xh'][$parser]['st'] .= " CreateObject('phpgwapi.xmlrpcval',"; 
				$GLOBALS['_xh'][$parser]['vt']  = xmlrpcString;
				$GLOBALS['_xh'][$parser]['ac']  = '';
				$GLOBALS['_xh'][$parser]['qt']  = 0;
				$GLOBALS['_xh'][$parser]['lv']  = 1;
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
				$GLOBALS['_xh'][$parser]['ac']=''; // reset the accumulator

				if ($name=='DATETIME.ISO8601' || $name=='STRING')
				{
					$GLOBALS['_xh'][$parser]['qt']=1;
					if ($name=='DATETIME.ISO8601')
					{
						$GLOBALS['_xh'][$parser]['vt']=xmlrpcDateTime;
					}
				}
				elseif($name=='BASE64')
				{
					$GLOBALS['_xh'][$parser]['qt']=2;
				}
				else
				{
					// No quoting is required here -- but
					// at the end of the element we must check
					// for data format errors.
					$GLOBALS['_xh'][$parser]['qt']=0;
				}
				break;
			case 'MEMBER':
				$GLOBALS['_xh'][$parser]['ac']='';
				break;
			default:
				break;
		}

		if ($name!='VALUE')
		{
			$GLOBALS['_xh'][$parser]['lv']=0;
		}
	}

	function xmlrpc_ee($parser, $name)
	{
		switch($name)
		{
			case 'STRUCT':
			case 'ARRAY':
				if ($GLOBALS['_xh'][$parser]['cm'] && substr($GLOBALS['_xh'][$parser]['st'], -1) ==',')
				{
					$GLOBALS['_xh'][$parser]['st']=substr($GLOBALS['_xh'][$parser]['st'],0,-1);
				}
				$GLOBALS['_xh'][$parser]['st'].=')';
				$GLOBALS['_xh'][$parser]['vt']=strtolower($name);
				$GLOBALS['_xh'][$parser]['cm']--;
				break;
			case 'NAME':
				$GLOBALS['_xh'][$parser]['st'].= $GLOBALS['_xh'][$parser]['ac'] . "' => ";
				break;
			case 'BOOLEAN':
				// special case here: we translate boolean 1 or 0 into PHP
				// constants true or false
				if ($GLOBALS['_xh'][$parser]['ac']=='1') 
				{
					$GLOBALS['_xh'][$parser]['ac']='True';
				}
				else
				{
					$GLOBALS['_xh'][$parser]['ac']='false';
				}
				$GLOBALS['_xh'][$parser]['vt']=strtolower($name);
				// Drop through intentionally.
			case 'I4':
			case 'INT':
			case 'STRING':
			case 'DOUBLE':
			case 'DATETIME.ISO8601':
			case 'BASE64':
				if ($GLOBALS['_xh'][$parser]['qt']==1)
				{
					// we use double quotes rather than single so backslashification works OK
					$GLOBALS['_xh'][$parser]['st'].='"'. $GLOBALS['_xh'][$parser]['ac'] . '"'; 
				}
				elseif ($GLOBALS['_xh'][$parser]['qt']==2)
				{
					$GLOBALS['_xh'][$parser]['st'].="base64_decode('". $GLOBALS['_xh'][$parser]['ac'] . "')"; 
				}
				else if ($name=='BOOLEAN')
				{
					$GLOBALS['_xh'][$parser]['st'].=$GLOBALS['_xh'][$parser]['ac'];
				}
				else
				{
					// we have an I4, INT or a DOUBLE
					// we must check that only 0123456789-.<space> are characters here
					if (!ereg("^\-?[0123456789 \t\.]+$", $GLOBALS['_xh'][$parser]['ac']))
					{
						// TODO: find a better way of throwing an error
						// than this!
						error_log('XML-RPC: non numeric value received in INT or DOUBLE');
						$GLOBALS['_xh'][$parser]['st'].='ERROR_NON_NUMERIC_FOUND';
					}
					else
					{
						// it's ok, add it on
						$GLOBALS['_xh'][$parser]['st'].=$GLOBALS['_xh'][$parser]['ac'];
					}
				}
				$GLOBALS['_xh'][$parser]['ac']=""; $GLOBALS['_xh'][$parser]['qt']=0;
				$GLOBALS['_xh'][$parser]['lv']=3; // indicate we've found a value
				break;
			case 'VALUE':
				// deal with a string value
				if (strlen($GLOBALS['_xh'][$parser]['ac'])>0 &&
					$GLOBALS['_xh'][$parser]['vt']==xmlrpcString)
				{
					$GLOBALS['_xh'][$parser]['st'].='"'. $GLOBALS['_xh'][$parser]['ac'] . '"'; 
				}
				// This if() detects if no scalar was inside <VALUE></VALUE>
				// and pads an empty "".
				if($GLOBALS['_xh'][$parser]['st'][strlen($GLOBALS['_xh'][$parser]['st'])-1] == '(')
				{
					$GLOBALS['_xh'][$parser]['st'].= '""';
				}
				$GLOBALS['_xh'][$parser]['st'].=", '" . $GLOBALS['_xh'][$parser]['vt'] . "')";
				if ($GLOBALS['_xh'][$parser]['cm'])
				{
					$GLOBALS['_xh'][$parser]['st'].=",";
				}
				break;
			case 'MEMBER':
				$GLOBALS['_xh'][$parser]['ac']="";
				$GLOBALS['_xh'][$parser]['qt']=0;
				break;
			case 'DATA':
				$GLOBALS['_xh'][$parser]['ac']="";
				$GLOBALS['_xh'][$parser]['qt']=0;
				break;
			case 'PARAM':
				$GLOBALS['_xh'][$parser]['params'][]=$GLOBALS['_xh'][$parser]['st'];
				break;
			case 'METHODNAME':
				$GLOBALS['_xh'][$parser]['method']=ereg_replace("^[\n\r\t ]+", "", $GLOBALS['_xh'][$parser]['ac']);
				break;
			case 'BOOLEAN':
				// special case here: we translate boolean 1 or 0 into PHP
				// constants true or false
				if ($GLOBALS['_xh'][$parser]['ac']=='1') 
				{
					$GLOBALS['_xh'][$parser]['ac']='True';
				}
				else
				{
					$GLOBALS['_xh'][$parser]['ac']='false';
				}
				$GLOBALS['_xh'][$parser]['vt']=strtolower($name);
				break;
			default:
				break;
		}
		// if it's a valid type name, set the type
		if (isset($GLOBALS['xmlrpcTypes'][strtolower($name)]))
		{
			$GLOBALS['_xh'][$parser]['vt']=strtolower($name);
		}
	}

	function xmlrpc_cd($parser, $data)
	{
		//if (ereg("^[\n\r \t]+$", $data)) return;
		// print "adding [${data}]\n";

		if ($GLOBALS['_xh'][$parser]['lv']!=3)
		{
			// "lookforvalue==3" means that we've found an entire value
			// and should discard any further character data
			if ($GLOBALS['_xh'][$parser]['lv']==1)
			{
				// if we've found text and we're just in a <value> then
				// turn quoting on, as this will be a string
				$GLOBALS['_xh'][$parser]['qt']=1; 
				// and say we've found a value
				$GLOBALS['_xh'][$parser]['lv']=2; 
			}
			if (isset($GLOBALS['_xh'][$parser]['qt']) && $GLOBALS['_xh'][$parser]['qt'])
			{
				// quoted string: replace characters that eval would
				// do special things with
				$GLOBALS['_xh'][$parser]['ac'].=str_replace('$', '\$',
					str_replace('"', '\"', 
					str_replace(chr(92),$GLOBALS['xmlrpc_backslash'], $data)));
			}
			else 
			{
				$GLOBALS['_xh'][$parser]['ac'].=$data;
			}
		}
	}

	function xmlrpc_dh($parser, $data)
	{
		if (substr($data, 0, 1) == '&' && substr($data, -1, 1) == ';')
		{
			if ($GLOBALS['_xh'][$parser]['lv']==1)
			{
				$GLOBALS['_xh'][$parser]['qt']=1; 
				$GLOBALS['_xh'][$parser]['lv']=2; 
			}
			$GLOBALS['_xh'][$parser]['ac'].=$data;
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
				$xmlrpc_val->addScalar($php_val, xmlrpcInt);
				break;
			case "double":
				$xmlrpc_val->addScalar($php_val, xmlrpcDouble);
				break;
			case "string":
				$xmlrpc_val->addScalar($php_val, xmlrpcString);
				break;
			// <G_Giunta_2001-02-29>
			// Add support for encoding/decoding of booleans, since they are supported in PHP
			case "boolean":
				$xmlrpc_val->addScalar($php_val, xmlrpcBoolean);
				break;
			// </G_Giunta_2001-02-29>
			case "unknown type":
			default:
				$xmlrpc_val = false;
				break;
		}
		return $xmlrpc_val;
	}

	$GLOBALS['_xmlrpc_debuginfo'] = '';
	function xmlrpc_debugmsg($m)
	{
		$GLOBALS['_xmlrpc_debuginfo'] .= $m . "\n";
	}

?>
