<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_info = array();
	$GLOBALS['phpgw_info']['flags'] = array(
		'noheader' => True,
		'nonavbar' => True,
		'disable_Template_class' => True,
		'currentapp' => 'notifywindow'
	);
	include('header.inc.php');
	$charSet = $GLOBALS['phpgw']->translation->charset();
?>
<html>
<head>
	<meta http-equiv="Refresh" content="300">
<?php
	print "<meta http-equiv=\"content-type\" content=\"text/html; charset=$charSet\" />";
?>
	<title>Notify Window</title>
</head>
<body bgcolor="<?php echo $GLOBALS['phpgw_info']['theme']['bg_color']; ?>" alink="blue" vlink="blue" link="blue">
<table>
	<tr><td><a href="<?php echo $GLOBALS['phpgw']->link('/notify.php'); ?>"><?php print lang('Check Now'); ?></a></td></tr>
<?php
	$GLOBALS['phpgw']->hooks->process('notifywindow');
?>
</table>
</body>
</html>
