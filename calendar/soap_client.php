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

	$soap_temp_kp3 = $kp3;
	$soap_temp_domain = $domain;

	$phpgw_info['flags'] = array(
		'disable_template_class' => True,
//		'login'                  => True,
		'currentapp'             => 'calendar',
		'noappheader'            => True,
		'noappfooter'            => True
	);

	include('../header.inc.php');
	include('../soap/vars.php');

	$dest_server = basename(
	$server['calendar.bocalendar.read_entry'] = array(
		'soapaction' => "urn:soapinterop",
		'endpoint' => $phpgw->link('/calendar/soap_server.php'),
//		'endpoint' => "http://devel/phpgroupware/calendar/soap_server.php?sessionid=$sessionid&kp3=$soap_temp_kp3&domain=$soap_temp_domain",
//		'endpoint' => "http://devel/phpgroupware/calendar/soap_server.php",
		'methodNamespace' => "http://soapinterop.org",
		'soapactionNeedsMethod' => 0,
		'name' => 'phpGW calendar - read_entry'
	);

	$server['calendar.bocalendar.store_to_cache'] = array(
		'soapaction' => "urn:soapinterop",
		'endpoint' => "http://devel/phpgroupware/calendar/soap_server.php?sessionid=$sessionid&kp3=$soap_temp_kp3&domain=$soap_temp_domain",
//		'endpoint' => "http://devel/phpgroupware/calendar/soap_server.php",
		'methodNamespace' => "http://soapinterop.org",
		'soapactionNeedsMethod' => 0,
		'name' => 'phpGW calendar - store_to_cache'
	);

//	$method_params['calendar.bocalendar.read_entry']['id'] = 85;

//	$method_params['calendar.bocalendar.store_to_cache']['syear'] = 2001;
//	$method_params['calendar.bocalendar.store_to_cache']['smonth'] = 7;
//	$method_params['calendar.bocalendar.store_to_cache']['sday'] = 9;
//	$method_params['calendar.bocalendar.store_to_cache']['eyear'] = 2001;
//	$method_params['calendar.bocalendar.store_to_cache']['emonth'] = 7;
//	$method_params['calendar.bocalendar.store_to_cache']['eday'] = 10;

//	$method = 'calendar.bocalendar.read_entry';
//	$method = 'calendar.bocalendar.store_to_cache';

	$sb = CreateObject('phpgwapi.sbox');
?>

<form action="<?php echo $phpgw->link('/calendar/soap_client.php'); ?>" method='post'>
<table>
<tr>
<td><input type="radio" name="method" value="calendar.bocalendar.read_entry">Select Entry</td>
<td><input name="method_params[calendar.bocalendar.read_entry][id]" size="2" VALUE="" maxlength="2"></td>
</tr>
<tr>
<td><input type="radio" name="method" value="calendar.bocalendar.store_to_cache">Select by Date</td>
<?php
$now = time() - ((60 * 60) * (intval($phpgw_info['user']['preferences']['common']['tz_offset'])));

echo '<td>Start : '.
	$sb->getYears('method_params[calendar.bocalendar.store_to_cache][syear]',intval($phpgw->common->show_date($now,'Y')),intval($phpgw->common->show_date($now,'Y'))).
	$sb->getMonthText('method_params[calendar.bocalendar.store_to_cache][smonth]',intval($phpgw->common->show_date($now,'n'))).
	$sb->getDays('method_params[calendar.bocalendar.store_to_cache][sday]',intval($phpgw->common->show_date($now,'d')))."</td>";
echo '<tr><td></td><td>End : '.
	$sb->getYears('method_params[calendar.bocalendar.store_to_cache][eyear]',intval($phpgw->common->show_date($now,'Y')),intval($phpgw->common->show_date($now,'Y'))).
	$sb->getMonthText('method_params[calendar.bocalendar.store_to_cache][emonth]',intval($phpgw->common->show_date($now,'n'))).
	$sb->getDays('method_params[calendar.bocalendar.store_to_cache][eday]',intval($phpgw->common->show_date($now,'d')));

echo "</td></table><br>\n".'<input type="submit" name="submit" value="'.lang('Submit').'"></form>';

	if($method && $submit)
	{
		settype($method_params['calendar.bocalendar.read_entry']['id'],'integer');
		settype($method_params['calendar.bocalendar.store_to_cache']['syear'],'integer');
		settype($method_params['calendar.bocalendar.store_to_cache']['smonth'],'integer');
		settype($method_params['calendar.bocalendar.store_to_cache']['sday'],'integer');
		settype($method_params['calendar.bocalendar.store_to_cache']['eyear'],'integer');
		settype($method_params['calendar.bocalendar.store_to_cache']['emonth'],'integer');
		settype($method_params['calendar.bocalendar.store_to_cache']['eday'],'integer');
		print "<b>METHOD: ".$method."</b><br>";
		$soap_message = CreateObject('phpgwapi.soapmsg',$method,$method_params[$method],$server[$method]['methodNamespace']);
//		print_r($soap_message);
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
	}
?>
