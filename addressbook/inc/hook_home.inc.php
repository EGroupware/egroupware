<?php
  /**************************************************************************\
  * phpGroupWare - E-Mail                                                    *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	$d1 = strtolower(substr(PHPGW_APP_INC,0,3));
	if($d1 == 'htt' || $d1 == 'ftp' )
	{
		echo "Failed attempt to break in via an old Security Hole!<br>\n";
		$GLOBALS['phpgw']->common->phpgw_exit();
	}
	unset($d1);

	if ($GLOBALS['phpgw_info']['user']['apps']['addressbook']
		&& $GLOBALS['phpgw_info']['user']['preferences']['addressbook']['mainscreen_showbirthdays'])
	{
		echo "\n<!-- Birthday info -->\n";

		$c = CreateObject('phpgwapi.contacts');
		$qfields = array(
			'n_given'  => 'n_given',
			'n_family' => 'n_family',
			'bday'     => 'bday'
		);
		$now = time() - ((60 * 60) * intval($GLOBALS['phpgw_info']['user']['preferences']['common']['tz_offset']));
		$today = $GLOBALS['phpgw']->common->show_date($now,'n/d/');

		$bdays = $c->read(0,15,$qfields,$today,'tid=n','','',$GLOBALS['phpgw_info']['user']['account_id']);

		while(list($key,$val) = @each($bdays))
		{
			$tmp = '<a href="'
				. $GLOBALS['phpgw']->link('/addressbook/view.php','ab_id=' .  $val['id']) . '">'
				. $val['n_given'] . ' ' . $val['n_family'] . '</a>';
			echo '<tr><td align="left">' . lang("Today is x's birthday!", $tmp) . '</td></tr>' . "\n";
		}

		$tomorrow = $GLOBALS['phpgw']->common->show_date($now + 86400,'n/d/');

		$bdays = $c->read(0,15,$qfields,$tomorrow,'tid=n','','',$GLOBALS['phpgw_info']['user']['account_id']);

		while(list($key,$val) = @each($bdays))
		{
			$tmp = '<a href="'
				. $GLOBALS['phpgw']->link('/addressbook/view.php','ab_id=' .  $val['id']) . '">'
				. $val['n_given'] . ' ' . $val["n_family"] . '</a>';
			echo '<tr><td align="left">' . lang("Tomorrow is x's birthday.", $tmp) . '</td></tr>' . "\n";
		}
		echo "\n<!-- Birthday info -->\n";
	}
?>
