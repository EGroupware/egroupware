<?php
  /**************************************************************************\
  * phpGroupWare xmlrpc server                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Dan Kuykendall <dan@kuykendall.org>                           *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	// a PHP version
	// of the state-number server
	// send me an integer and i'll sell you a state

	$stateNames = array(
		'Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California',
		'Colorado', 'Columbia', 'Connecticut', 'Delaware', 'Florida',
		'Georgia', 'Hawaii', 'Idaho', 'Illinois', 'Indiana', 'Iowa', 'Kansas',
		'Kentucky', 'Louisiana', 'Maine', 'Maryland', 'Massachusetts', 'Michigan',
		'Minnesota', 'Mississippi', 'Missouri', 'Montana', 'Nebraska', 'Nevada',
		'New Hampshire', 'New Jersey', 'New Mexico', 'New York', 'North Carolina',
		'North Dakota', 'Ohio', 'Oklahoma', 'Oregon', 'Pennsylvania', 'Rhode Island',
		'South Carolina', 'South Dakota', 'Tennessee', 'Texas', 'Utah', 'Vermont',
		'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming'
	);

	$findstate_sig = array(array(xmlrpcString, xmlrpcInt));

	$findstate_doc = 'When passed an integer between 1 and 51 returns the
name of a US state, where the integer is the index of that state name
in an alphabetic order.';

	function findstate($m)
	{
		$err='';
		// get the first param
		$sno=$m->getParam(0);
		// if it's there and the correct type

		if (isset($sno) && ($sno->scalartyp()=='int'))
		{
			// extract the value of the state number
			$snv=$sno->scalarval();
			// look it up in our array (zero-based)
			if (isset($GLOBALS['stateNames'][$snv-1]))
			{
				$sname=$GLOBALS['stateNames'][$snv-1];
			}
			else
			{
				// not, there so complain
				$err="I don't have a state for the index '" . $snv . "'";
			}
		}
		else
		{
			// parameter mismatch, complain
			$err='One integer parameter required';
		}

		// if we generated an error, create an error return response
		if ($err)
		{
			return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval'), $GLOBALS['xmlrpcerruser'], $err);
		}
		else
		{
			// otherwise, we create the right response
			// with the state name
			return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',$sname));
		}
	}

	$addtwo_sig=array(array(xmlrpcInt, xmlrpcInt, xmlrpcInt));
	$addtwo_doc='Add two integers together and return the result';

	function addtwo($m)
	{
		$s=$m->getParam(0);
		$t=$m->getParam(1);
		return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',$s->scalarval()+$t->scalarval(),'int'));
	}

	$addtwodouble_sig=array(array(xmlrpcDouble, xmlrpcDouble, xmlrpcDouble));
	$addtwodouble_doc='Add two doubles together and return the result';

	function addtwodouble($m)
	{
		$s=$m->getParam(0);
		$t=$m->getParam(1);
		return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',$s->scalarval()+$t->scalarval(),'double'));
	}

	$stringecho_sig=array(array(xmlrpcString, xmlrpcString));
	$stringecho_doc='Accepts a string parameter, returns the string.';

	function stringecho($m)
	{
		// just sends back a string 
		$s=$m->getParam(0);
		return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',$s->scalarval()));
	}

	$echoback_sig=array(array(xmlrpcString, xmlrpcString));
	$echoback_doc='Accepts a string parameter, returns the entire incoming payload';

	function echoback($m)
	{
		// just sends back a string with what i got
		// send to me, just escaped, that's all
		//
		// $m is an incoming message
		$s="I got the following message:\n" . $m->serialize();
		return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',$s));
	}

	$echosixtyfour_sig=array(array(xmlrpcString, xmlrpcBase64));
	$echosixtyfour_doc='Accepts a base64 parameter and returns it decoded as a string';

	function echosixtyfour($m)
	{
		// accepts an encoded value, but sends it back
		// as a normal string. this is to test base64 encoding
		// is working as expected
		$incoming=$m->getParam(0);
		return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',$incoming->scalarval(), 'string'));
	}

	$bitflipper_sig=array(array(xmlrpcArray, xmlrpcArray));
	$bitflipper_doc='Accepts an array of booleans, and returns them inverted';

	function bitflipper($m)
	{
		$v  = $m->getParam(0);
		$sz = $v->arraysize();
		$rv = CreateObject('phpgwapi.xmlrpcval',array(), xmlrpcArray);

		for($j=0; $j<$sz; $j++)
		{
			$b = $v->arraymem($j);
			if ($b->scalarval())
			{	
				$rv->addScalar(False, 'boolean');
			}
			else
			{	
				$rv->addScalar(True, 'boolean');
			}
		}
		return CreateObject('phpgwapi.xmlrpcresp',$rv);
	}

	// Sorting demo
	//
	// send me an array of structs thus:
	//
	// Dave 35
	// Edd  45
	// Fred 23
	// Barney 37
	//
	// and I'll return it to you in sorted order

	function agesorter_compare($a, $b)
	{
		// don't even ask me _why_ these come padded with
		// hyphens, I couldn't tell you :p
		$a=ereg_replace('-', '', $a);
		$b=ereg_replace('-', '', $b);

		if ($GLOBALS['agesorter_arr'][$a]==$agesorter[$b])
		{
			return 0;
		}
		return ($GLOBALS['agesorter_arr'][$a] > $GLOBALS['agesorter_arr'][$b]) ? -1 : 1;
	}

	$agesorter_sig=array(array(xmlrpcArray, xmlrpcArray));
	$agesorter_doc='Send this method an array of [string, int] structs, eg:
<PRE>
 Dave   35
 Edd    45
 Fred   23
 Barney 37
</PRE>
And the array will be returned with the entries sorted by their numbers.
';

	function agesorter($m)
	{
		global $s;

		xmlrpc_debugmsg("Entering 'agesorter'");
		// get the parameter
		$sno = $m->getParam(0);
		// error string for [if|when] things go wrong
		$err = '';
		// create the output value
		$v = CreateObject('phpgwapi.xmlrpcval');
		$agar = array();

		if (isset($sno) && $sno->kindOf()=='array')
		{
			$max = $sno->arraysize(); 
			// TODO: create debug method to print can work once more
			// print "<!-- found $max array elements -->\n";
			for($i=0; $i<$max; $i++)
			{
				$rec = $sno->arraymem($i);
				if ($rec->kindOf()!='struct')
				{
					$err = 'Found non-struct in array at element ' . $i;
					break;
				}
				// extract name and age from struct
				$n = $rec->structmem('name');
				$a = $rec->structmem('age');
				// $n and $a are xmlrpcvals, 
				// so get the scalarval from them
				$agar[$n->scalarval()] = $a->scalarval();
			}

			$GLOBALS['agesorter_arr'] = $agar; 
			// hack, must make global as uksort() won't
			// allow us to pass any other auxilliary information
			uksort($GLOBALS['agesorter_arr'], agesorter_compare);
			$outAr = array();
			while (list($key,$val) = each($GLOBALS['agesorter_arr']))
			{
				// recreate each struct element
				$outAr[] = CreateObject('phpgwapi.xmlrpcval',array(
					'name' => CreateObject('phpgwapi.xmlrpcval',$key),
					'age'  => CreateObject('phpgwapi.xmlrpcval',$val, 'int')
					),
					'struct'
				);
			}
			// add this array to the output value
			$v->addArray($outAr);
		}
		else
		{
			$err = 'Must be one parameter, an array of structs';
		}

		if ($err)
		{
			return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval'), $GLOBALS['xmlrpcerruser'], $err);
		}
		else
		{
			return CreateObject('phpgwapi.xmlrpcresp',$v);
		}
	}

	// signature and instructions, place these in the dispatch map

	$mail_send_sig = array(
		array(
			xmlrpcBoolean, xmlrpcString, xmlrpcString,
			xmlrpcString, xmlrpcString, xmlrpcString,
			xmlrpcString, xmlrpcString
		)
	);

	$mail_send_doc = 'mail.send(recipient, subject, text, sender, cc, bcc, mimetype)
<BR>recipient, cc, and bcc are strings, comma-separated lists of email addresses, as described above.
<BR>subject is a string, the subject of the message.
<BR>sender is a string, it\'s the email address of the person sending the message. This string can not be
a comma-separated list, it must contain a single email address only.
text is a string, it contains the body of the message.
<BR>mimetype, a string, is a standard MIME type, for example, text/plain.
';

	// WARNING; this functionality depends on the sendmail -t option
	// it may not work with Windows machines properly; particularly
	// the Bcc option.  Sneak on your friends at your own risk!
	function mail_send($m)
	{
		$err = '';

		$mTo   = $m->getParam(0);
		$mSub  = $m->getParam(1);
		$mBody = $m->getParam(2);
		$mFrom = $m->getParam(3);
		$mCc   = $m->getParam(4);
		$mBcc  = $m->getParam(5);
		$mMime = $m->getParam(6);
	
		if ($mTo->scalarval() == '')
		{
			$err = "Error, no 'To' field specified";
		}
		if ($mFrom->scalarval() == '')
		{
			$err = "Error, no 'From' field specified";
		}
		$msghdr  = 'From: ' . $mFrom->scalarval() . "\n";
		$msghdr .= 'To: '. $mTo->scalarval() . "\n";

		if ($mCc->scalarval()!='')
		{
			$msghdr .= 'Cc: ' . $mCc->scalarval(). "\n";
		}
		if ($mBcc->scalarval()!='')
		{
			$msghdr .= 'Bcc: ' . $mBcc->scalarval(). "\n";
		}
		if ($mMime->scalarval()!='')
		{
			$msghdr .= 'Content-type: ' . $mMime->scalarval() . "\n";
		}

		$msghdr .= 'X-Mailer: XML-RPC for PHP mailer 1.0';

		if ($err == '')
		{
			if (!mail('', $mSub->scalarval(), $mBody->scalarval(), $msghdr))
			{
				$err = 'Error, could not send the mail.';
			}
		}

		if ($err)
		{
			return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval'), $GLOBALS['xmlrpcerruser'], $err);
		}
		else
		{
			return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval','true', xmlrpcBoolean));
		}
	}

	$v1_arrayOfStructs_sig = array(array(xmlrpcInt, xmlrpcArray));
	$v1_arrayOfStructs_doc = 'This handler takes a single parameter, an array of structs, each of which contains at least three elements named moe, larry and curly, all <i4>s. Your handler must add all the struct elements named curly and return the result.';

	function v1_arrayOfStructs($m)
	{
		$sno = $m->getParam(0);
		$numcurly = 0;
		for($i=0; $i<$sno->arraysize(); $i++)
		{
			$str = $sno->arraymem($i);
			$str->structreset();
			while(list($key,$val) = $str->structeach())
			{
				if ($key == 'curly')
				{
					$numcurly += $val->scalarval();
				}
			}
		}
		return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',$numcurly, 'int'));
	}

	$v1_easyStruct_sig = array(array(xmlrpcInt, xmlrpcStruct));
	$v1_easyStruct_doc = 'This handler takes a single parameter, a struct, containing at least three elements named moe, larry and curly, all &lt;i4&gt;s. Your handler must add the three numbers and return the result.';

	function v1_easyStruct($m)
	{
		$sno   = $m->getParam(0);
		$moe   = $sno->structmem('moe');
		$larry = $sno->structmem('larry');
		$curly = $sno->structmem('curly');
		$num   = $moe->scalarval() + $larry->scalarval() + $curly->scalarval();
		return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',$num, 'int'));
	}

	$v1_echoStruct_sig=array(array(xmlrpcStruct, xmlrpcStruct));
	$v1_echoStruct_doc='This handler takes a single parameter, a struct. Your handler must return the struct.';

	function v1_echoStruct($m)
	{
		$sno=$m->getParam(0);
		return CreateObject('phpgwapi.xmlrpcresp',$sno);
	}

	$v1_manyTypes_sig = array(
		array(
			xmlrpcArray, xmlrpcInt, xmlrpcBoolean,
			xmlrpcString, xmlrpcDouble, xmlrpcDateTime,
			xmlrpcBase64
		)
	);
	$v1_manyTypes_doc = 'This handler takes six parameters, and returns an array containing all the parameters.';

	function v1_manyTypes($m)
	{
		return CreateObject('phpgwapi.xmlrpcresp',
			CreateObject('phpgwapi.xmlrpcval',array(
				$m->getParam(0),
				$m->getParam(1),
				$m->getParam(2),
				$m->getParam(3),
				$m->getParam(4),
				$m->getParam(5)
			),
			'array'
		));
	}

	$v1_moderateSizeArrayCheck_sig = array(array(xmlrpcString, xmlrpcArray));
	$v1_moderateSizeArrayCheck_doc = 'This handler takes a single parameter, which is an array containing between 100 and 200 elements. Each of the items is a string, your handler must return a string containing the concatenated text of the first and last elements.';

	function v1_moderateSizeArrayCheck($m)
	{
		$ar    = $m->getParam(0);
		$sz    = $ar->arraysize();
		$first = $ar->arraymem(0);
		$last  = $ar->arraymem($sz-1);
		return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',$first->scalarval() . $last->scalarval(), "string"));
	}

	$v1_simpleStructReturn_sig = array(array(xmlrpcStruct, xmlrpcInt));
	$v1_simpleStructReturn_doc = 'This handler takes one parameter, and returns a struct containing three elements, times10, times100 and times1000, the result of multiplying the number by 10, 100 and 1000.';

	function v1_simpleStructReturn($m)
	{
		$sno=$m->getParam(0);
		$v=$sno->scalarval();
		return CreateObject('phpgwapi.xmlrpcresp',
			CreateObject('phpgwapi.xmlrpcval',array(
				'times10'   => CreateObject('phpgwapi.xmlrpcval',$v*10, 'int'),
				'times100'  => CreateObject('phpgwapi.xmlrpcval',$v*100, 'int'),
				'times1000' => CreateObject('phpgwapi.xmlrpcval',$v*1000, 'int')
			),
			'struct'
		));
	}

	$v1_nestedStruct_sig = array(array(xmlrpcInt, xmlrpcStruct));
	$v1_nestedStruct_doc = 'This handler takes a single parameter, a struct, that models a daily calendar. At the top level, there is one struct for each year. Each year is broken down into months, and months into days. Most of the days are empty in the struct you receive, but the entry for April 1, 2000 contains a least three elements named moe, larry and curly, all &lt;i4&gt;s. Your handler must add the three numbers and return the result.';

	function v1_nestedStruct($m)
	{
		$sno=$m->getParam(0);
		$twoK=$sno->structmem('2000');
		$april=$twoK->structmem('04');
		$fools=$april->structmem('01');
		$curly=$fools->structmem('curly');
		$larry=$fools->structmem('larry');
		$moe=$fools->structmem('moe');
		return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',
			$curly->scalarval() + $larry->scalarval() + $moe->scalarval(), 'int'
		));
	}

	$v1_countTheEntities_sig=array(array(xmlrpcStruct, xmlrpcString));
	$v1_countTheEntities_doc='This handler takes a single parameter, a string, that contains any number of predefined entities, namely &lt;, &gt;, &amp; \' and ".<BR>Your handler must return a struct that contains five fields, all numbers:  ctLeftAngleBrackets, ctRightAngleBrackets, ctAmpersands, ctApostrophes, ctQuotes.';

	function v1_countTheEntities($m)
	{
		$sno=$m->getParam(0);
		$str=$sno->scalarval();
		$gt=0; $lt=0; $ap=0; $qu=0; $amp=0;
		for($i=0; $i<strlen($str); $i++)
		{
			$c=substr($str, $i, 1);
			switch($c)
			{
				case '>':
					$gt++;
					break;
				case '<':
					$lt++;
					break;
				case '"':
					$qu++;
					break;
				case "'":
					$ap++;
					break;
				case '&':
					$amp++;
					break;
				default:
					break;
			}
		}
		return CreateObject('phpgwapi.xmlrpcresp',
			CreateObject('phpgwapi.xmlrpcval',array(
				'ctLeftAngleBrackets'  => CreateObject('phpgwapi.xmlrpcval',$lt, 'int'),
				'ctRightAngleBrackets' => CreateObject('phpgwapi.xmlrpcval',$gt, 'int'),
				'ctAmpersands'         => CreateObject('phpgwapi.xmlrpcval',$amp, 'int'),
				'ctApostrophes'        => CreateObject('phpgwapi.xmlrpcval',$ap, 'int'),
				'ctQuotes'             => CreateObject('phpgwapi.xmlrpcval',$qu, 'int')
			),
   		     'struct'
		));
	}

	// trivial interop tests
	// http://www.xmlrpc.com/stories/storyReader$1636

	$i_echoString_sig=array(array(xmlrpcString, xmlrpcString));
	$i_echoString_doc='Echoes string.';

	$i_echoStringArray_sig=array(array(xmlrpcArray, xmlrpcArray));
	$i_echoStringArray_doc='Echoes string array.';

	$i_echoInteger_sig=array(array(xmlrpcInt, xmlrpcInt));
	$i_echoInteger_doc='Echoes integer.';

	$i_echoIntegerArray_sig=array(array(xmlrpcArray, xmlrpcArray));
	$i_echoIntegerArray_doc='Echoes integer array.';

	$i_echoFloat_sig=array(array(xmlrpcDouble, xmlrpcDouble));
	$i_echoFloat_doc='Echoes float.';

	$i_echoFloatArray_sig=array(array(xmlrpcArray, xmlrpcArray));
	$i_echoFloatArray_doc='Echoes float array.';

	$i_echoStruct_sig=array(array(xmlrpcStruct, xmlrpcStruct));
	$i_echoStruct_doc='Echoes struct.';

	$i_echoStructArray_sig=array(array(xmlrpcArray, xmlrpcArray));
	$i_echoStructArray_doc='Echoes struct array.';

	$i_echoValue_doc='Echoes any value back.';

	$i_echoBase64_sig=array(array(xmlrpcBase64, xmlrpcBase64));
	$i_echoBase64_doc='Echoes base64.';

	$i_echoDate_sig=array(array(xmlrpcDateTime, xmlrpcDateTime));
	$i_echoDate_doc='Echoes dateTime.';

	function i_echoParam($m)
	{
		$s = $m->getParam(0);
		return CreateObject('phpgwapi.xmlrpcresp',$s);
	}

	function i_echoString($m)
	{
		return i_echoParam($m);
	}
	function i_echoInteger($m)
	{
		return i_echoParam($m);
	}
	function i_echoFloat($m)
	{
		return i_echoParam($m);
	}
	function i_echoStruct($m)
	{
		return i_echoParam($m);
	}
	function i_echoStringArray($m)
	{
		return i_echoParam($m);
	}
	function i_echoIntegerArray($m)
	{
		return i_echoParam($m);
	}
	function i_echoFloatArray($m)
	{
		return i_echoParam($m);
	}
	function i_echoStructArray($m)
	{
		return i_echoParam($m);
	}
	function i_echoValue($m)
	{
		return i_echoParam($m);
	}
	function i_echoBase64($m)
	{
		return i_echoParam($m);
	}
	function i_echoDate($m)
	{
		return i_echoParam($m);
	}

	$i_whichToolkit_doc = 'Returns a struct containing the following strings:  toolkitDocsUrl, toolkitName, toolkitVersion, toolkitOperatingSystem.';

	function i_whichToolkit($m)
	{
		$ret = array(
			'toolkitDocsUrl' => 'http://xmlrpc.usefulinc.com/php.html',
			'toolkitName'    => $GLOBALS['xmlrpcName'],
			'toolkitVersion' => $GLOBALS['xmlrpcVersion'],
			'toolkitOperatingSystem' => $GLOBALS['SERVER_SOFTWARE']
		);
		return CreateObject('phpgwapi.xmlrpcresp',xmlrpc_encode($ret));
	}

	$server->add_to_map('examples.getStateName',            'findstate',$findstate_sig,$findstate_doc);
	$server->add_to_map('examples.sortByAge',               'agesorter',$agesorter_sig,$agesorter_doc);
	$server->add_to_map('examples.addtwo',                  'addtwo',$addtwo_sig,$addtwo_doc);
	$server->add_to_map('examples.addtwodouble',            'addtwodouble',$addtwodouble_sig,$addtwodouble_doc);
	$server->add_to_map('examples.stringecho',              'stringecho',$stringecho_sig,$stringecho_doc);
	$server->add_to_map('examples.echo',                    'echoback',$echoback_sig,$echoback_doc);
	$server->add_to_map('examples.decode64',                'echosixtyfour',$echosixtyfour_sig,$echosixtyfour_doc);
	$server->add_to_map('examples.invertBooleans',          'bitflipper',$bitflipper_sig,$bitflipper_doc);
	$server->add_to_map('mail.send',                        'mail_send',$mail_send_sig,$mail_send_doc);
	$server->add_to_map('validator1.arrayOfStructsTest',    'v1_arrayOfStructs',$v1_arrayOfStructs_sig,$v1_arrayOfStructs_doc);
	$server->add_to_map('validator1.easyStructTest',        'v1_easyStruct',$v1_easyStruct_sig,$v1_easyStruct_doc);
	$server->add_to_map('validator1.echoStructTest',        'v1_echoStruct',$v1_echoStruct_sig,$v1_echoStruct_doc);
	$server->add_to_map('validator1.manyTypesTest',         'v1_manyTypes',$v1_manyTypes_sig,$v1_manyTypes_doc);
	$server->add_to_map('validator1.moderateSizeArrayCheck','v1_moderateSizeArrayCheck',$v1_moderateSizeArrayCheck_sig,$v1_moderateSizeArrayCheck_doc);
	$server->add_to_map('validator1.simpleStructReturnTest','v1_simpleStructReturn',$v1_simpleStructReturn_sig,$v1_simpleStructReturn_doc);
	$server->add_to_map('validator1.nestedStructTest',      'v1_nestedStruct',$v1_nestedStruct_sig,$v1_nestedStruct_doc);
	$server->add_to_map('validator1.countTheEntities',      'v1_countTheEntities',$v1_countTheEntities_sig,$v1_countTheEntities_doc);
	$server->add_to_map('interopEchoTests.echoString',      'i_echoString',$i_echoString_sig,$i_echoString_doc);
	$server->add_to_map('interopEchoTests.echoStringArray', 'i_echoStringArray',$i_echoStringArray_sig,$i_echoStringArray_doc);
	$server->add_to_map('interopEchoTests.echoInteger',     'i_echoInteger',$i_echoInteger_sig,$i_echoInteger_doc);
	$server->add_to_map('interopEchoTests.echoIntegerArray','i_echoIntegerArray',$i_echoIntegerArray_sig,$i_echoIntegerArray_doc);
	$server->add_to_map('interopEchoTests.echoFloat',       'i_echoFloat',$i_echoFloat_sig,$i_echoFloat_doc);
	$server->add_to_map('interopEchoTests.echoFloatArray',  'i_echoFloatArray',$i_echoFloatArray_sig,$i_echoFloatArray_doc);
	$server->add_to_map('interopEchoTests.echoStruct',      'i_echoStruct',$i_echoStruct_sig,$i_echoStruct_doc);
	$server->add_to_map('interopEchoTests.echoStructArray', 'i_echoStructArray',$i_echoStructArray_sig,$i_echoStructArray_doc);
	$server->add_to_map('interopEchoTests.echoValue',       'i_echoValue','',$i_echoValue_doc);
	$server->add_to_map('interopEchoTests.echoBase64',      'i_echoBase64',$i_echoBase64_sig,$i_echoBase64_doc);
	$server->add_to_map('interopEchoTests.echoDate',        'i_echoDate',$i_echoDate_sig,$i_echoDate_doc);
	$server->add_to_map('interopEchoTests.whichToolkit',    'i_whichToolkit','',$i_whichToolkit_doc);

	// that should do all we need!
?>
