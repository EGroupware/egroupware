<?php

	$soapTypes = array(
		'i4'           => 1,
		'int'          => 1,
		'boolean'      => 1,
		'string'       => 1,
		'double'       => 1,
		'float'        => 1,
		'dateTime'     => 1,
		'timeInstant'  => 1,
		'dateTime'     => 1,
		'base64Binary' => 1,
		'base64'       => 1,
		'array'        => 2,
		'Array'        => 2,
		'SOAPStruct'   => 3,
		'ur-type'      => 2
	);

	while(list($key,$val) = each($soapTypes))
	{
		$soapKeys[] = $val;
	}

	$typemap = array(
		'http://soapinterop.org/xsd'                => array('SOAPStruct'),
		'http://schemas.xmlsoap.org/soap/encoding/' => array('base64'),
		'http://www.w3.org/1999/XMLSchema'          => $soapKeys
	);

	$namespaces = array(
		'http://schemas.xmlsoap.org/soap/envelope/' => 'SOAP-ENV',
		'http://www.w3.org/1999/XMLSchema-instance' => 'xsi',
		'http://www.w3.org/1999/XMLSchema'          => 'xsd',
		'http://schemas.xmlsoap.org/soap/encoding/' => 'SOAP-ENC',
		'http://soapinterop.org/xsd'                => 'si'
	);

	$xmlEntities = array(
		'quot' => '"',
		'amp'  => '&',
		'lt'   => '<',
		'gt'   => '>',
		'apos' => "'"
	);

	$soap_defencoding = 'UTF-8';
?>
