<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * Written by Mark Peters <skeeter@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
	/* $Id$ */

	$phpgw_flags = Array(
		'currentapp'		=> 'calendar',
		'enable_nextmatchs_class'	=> True,
		'admin_header'		=> True,
		'noheader'		=> True,
		'nonavbar'		=> True,
		'noappheader'		=> True,
		'noappfooter'		=> True,
		'parent_page'		=> '../admin/index.php'
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../header.inc.php');

	if(!isset($locale) || !$locale)
	{
		Header('Location: ' . $phpgw->link('/calendar/holiday_admin.php'));
	}
	$holidays = $phpgw->calendar->holidays->build_list_for_submission($locale);
	$phpgw->common->phpgw_header();
?>
	<body onLoad="document.submitform.submit()">
	<form action="http://www.phpgroupware.org/cal/accept_holiday.php" method="post" name="submitform">
<!--	<form action="<?php echo $phpgw->link('/calendar/accept_holiday.php'); ?>" method="post" name="submitform"> -->
<?php
	$c_holidays = count($holidays);
	echo '<input type="hidden" name="locale" value="'.$locale.'">'."\n";
	for($i=0;$i<$c_holidays;$i++)
	{
		echo '<input type="hidden" name="name[]" value="'.$holidays[$i]['name'].'">'."\n"
			. '<input type="hidden" name="day[]" value="'.$holidays[$i]['day'].'">'."\n"
			. '<input type="hidden" name="month[]" value="'.$holidays[$i]['month'].'">'."\n"
			. '<input type="hidden" name="occurence[]" value="'.$holidays[$i]['occurence'].'">'."\n"
			. '<input type="hidden" name="dow[]" value="'.$holidays[$i]['dow'].'">'."\n"
			. '<input type="hidden" name="observance[]" value="'.$holidays[$i]['observance_rule'].'">'."\n";
	}
?>
	</form>
	</body>
	</head>
