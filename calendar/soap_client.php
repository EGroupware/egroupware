<?php
/**************************************************************************\
* phpGroupWare - calendar                                                  *
* http://www.phpgroupware.org                                              *
* Written by Mark A Peters <skeeter@phpgroupware.org>                      *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

	/* $Id$ */

	$phpgw_info['flags'] = array(
		'disable_template_class' => True,
//		'login'                  => True,
		'currentapp'             => 'calendar',
		'noheader'               => True,
		'nofooter'               => True);

	include('../header.inc.php');
	include('../soap/vars.php');

	$method_params = Array();
	$server['calendar.bocalendar.read_entry'] = array(
		'soapaction' => "urn:soapinterop",
		'endpoint' => "http://devel/phpgroupware/calendar/soap_server.php?sessionid=c849d2572fe94cbccdf67c5a33ef7d15&kp3=dc6d2b287cce75e8794fec51ee78c3cb&domain=default",
//		'endpoint' => "http://devel/phpgroupware/calendar/soap_server.php",
		'methodNamespace' => "http://soapinterop.org",
		'soapactionNeedsMethod' => 0,
		'name' => 'phpGW calendar - read_entry'
	);

	$server['calendar.bocalendar.store_to_cache'] = array(
		'soapaction' => "urn:soapinterop",
		'endpoint' => "http://devel/phpgroupware/calendar/soap_server.php?sessionid=c849d2572fe94cbccdf67c5a33ef7d15&kp3=dc6d2b287cce75e8794fec51ee78c3cb&domain=default",
//		'endpoint' => "http://devel/phpgroupware/calendar/soap_server.php",
		'methodNamespace' => "http://soapinterop.org",
		'soapactionNeedsMethod' => 0,
		'name' => 'phpGW calendar - store_to_cache'
	);


	$method_params['calendar.bocalendar.read_entry']['id'] = 85;

	$method_params['calendar.bocalendar.store_to_cache']['syear'] = 2001;
	$method_params['calendar.bocalendar.store_to_cache']['smonth'] = 7;
	$method_params['calendar.bocalendar.store_to_cache']['sday'] = 9;
	$method_params['calendar.bocalendar.store_to_cache']['eyear'] = 2001;
	$method_params['calendar.bocalendar.store_to_cache']['emonth'] = 7;
	$method_params['calendar.bocalendar.store_to_cache']['eday'] = 10;

//	$method = 'calendar.bocalendar.read_entry';
	$method = 'calendar.bocalendar.store_to_cache';

	print "<b>METHOD: ".$method."</b><br>";
	$soap_message = CreateObject('phpgwapi.soapmsg',$method,$method_params[$method],$server[$method]['methodNamespace']);
	print_r($soap_message);
	$soap = CreateObject('phpgwapi.soap_client',$server[$method]['endpoint']);
	if($return = $soap->send($soap_message,$server[$method]['soapaction'])){
		// check for valid response
		if(get_class($return) == 'soapval'){
			print 'Correctly decoded server\'s response<br>';
			// fault?
			if(eregi('fault',$return->name)){
				$status = 'failed';
			} else {
				$status = 'passed';
			}
		} else {
			print 'Client could not decode server\'s response<br>';
		}
	} else {
		print 'Was unable to send or receive.';
	}
	
	//$soap->incoming_payload .= "\n\n<!-- SOAPx4 CLIENT DEBUG\n$client->debug_str\n\nRETURN VAL DEBUG: $return->debug_str-->";
	print '<strong>Request:</strong><br><xmp>'.$soap->outgoing_payload.'</xmp><br>';
	print '<strong>Response:</strong><br><xmp>'.$soap->incoming_payload.'</xmp>';
//	print_r($return);
?>
