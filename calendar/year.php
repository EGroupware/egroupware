<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	if (isset($friendly) && $friendly)
	{
  		$phpgw_flags = Array(
  			'currentapp'		=> 'calendar',
  			'enable_nextmatchs_class'	=> True,
  			'noheader'		=> True,
  			'nonavbar'		=> True,
  			'noappheader'	=> True,
  			'noappfooter'	=> True,
  			'nofooter'		=> True
  		);
	}
	else
	{
		$friendly = 0;
  		$phpgw_flags = Array(
  			'currentapp'		=> 'calendar',
  			'enable_nextmatchs_class'	=> True
  		);
	}

	$phpgw_info['flags'] = $phpgw_flags;
	include('../header.inc.php');

	$view = 'year';

	if ($friendly)
	{
		echo '<body bgcolor="'.$phpgw_info['theme']['bg_color'].'">';
	}
?>

<center>
<table border="0" cellspacing="3" cellpadding="4" cols=4>
 <tr>
  <?php
    if (!$friendly)
       echo '<td align="left"><a href="'.$phpgw->link('/calendar/year.php','year='.($year-1)).'">&lt;&lt;</a>';
  ?>
  </td>
  </td>
  <td align="center">
   <font face=\"".$phpgw_info["theme"][font]."\" size="+1"><?php echo $year; ?></font>
  </td>
  <?php
    if (! $friendly)
       echo '<td align="right"><a href="'.$phpgw->link('/calendar/year.php','year='.($year+1)).'">&gt;&gt;</a>';
  ?>
  </td>
 </tr>
 <tr valign="top">
<?php
  if(!$friendly) $link = 'day.php'; else $link = '';
  for($i=1;$i<13;$i++) {
    echo '<td valign="top">';
    if(!$friendly)
      echo $phpgw->calendar->mini_calendar($i,$i,$year,'day.php','none',False);
    else
      echo $phpgw->calendar->mini_calendar($i,$i,$year,'','none',False);
    echo '</td>';
    if($i % 3 == 0) echo '</tr><tr valign="top">';
  }
?>
 </tr>
</table>
</center>

<?php
	if (! $friendly)
	{
		echo '&nbsp;<a href="'.$phpgw->link('/calendar/year.php','friendly=1&year='.$year)
			.'" target="cal_printer_friendly" onMouseOver="window.status = '."'"
			.lang('Generate printer-friendly version')."'".'">['.lang('Printer Friendly').']</a>';
	}
	if(!isset($friendly) || $friendly == False)
	{
		$phpgw->common->phpgw_footer();
	}
	else
	{
		$phpgw->common->phpgw_exit();
	}
?>
