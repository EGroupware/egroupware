<?php
	$server->add_to_map(
		'hello',
		array('string'),
		array('string')
	);
	function hello($serverid)
	{
		global $phpgw_info;
		return CreateObject('soap.soapval','return','string',$phpgw_info['server']['site_title']);
	}

	$server->add_to_map(
		"echoString",
		array("string"),
		array("string")
	);
	function echoString($inputString)
	{
		return CreateObject('soap.soapval',"return","string",$inputString);
	}

	$server->add_to_map(
		"echoStringArray",
		array("array"),
		array("array")
	);
	function echoStringArray($inputStringArray)
	{
		return $inputStringArray;
	}

	$server->add_to_map(
		"echoInteger",
		array("int"),
		array("int")
	);
	function echoInteger($inputInteger)
	{
		return $inputInteger;
	}

	$server->add_to_map(
		"echoIntegerArray",
		array("array"),
		array("array")
	);
	function echoIntegerArray($inputIntegerArray)
	{
		return $inputIntegerArray;
	}

	$server->add_to_map(
		"echoFloat",
		array("float"),
		array("float")
	);
	function echoFloat($inputFloat)
	{
		return $inputFloat;
	}

	$server->add_to_map(
		"echoFloatArray",
		array("array"),
		array("array")
	);
	function echoFloatArray($inputFloatArray)
	{
		return $inputFloatArray;
	}

	$server->add_to_map(
		"echoStruct",
		array("SOAPStruct"),
		array("SOAPStruct")
	);
	function echoStruct($inputStruct)
	{
		return $inputStruct;
	}

	$server->add_to_map(
		"echoStructArray",
		array("array"),
		array("array")
	);
	function echoStructArray($inputStructArray)
	{
		return $inputStructArray;
	}

	$server->add_to_map(
		"echoVoid",
		array(),
		array()
	);
	function echoVoid()
	{
	}

	$server->add_to_map(
		"echoBase64",
		array("base64"),
		array("base64")
	);
	function echoBase64($b_encoded)
	{
		return base64_encode(base64_decode($b_encoded));
	}

	$server->add_to_map(
		"echoDate",
		array("timeInstant"),
		array("timeInstant")
	);
	function echoDate($timeInstant)
	{
		return $timeInstant;
	}
?>
