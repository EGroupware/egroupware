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

	$phpgw_info['flags'] = array(
		'noheader' => True,
		'nonavbar' => True,
		'disable_Template_class' => True,
		'currentapp' => 'notifywindow'
	);
	include('header.inc.php');
?>
<html>
<head>
	<meta http-equiv="Refresh" content="300">
	<title>Notify Window</title>
	
	<script language="JavaScript">
	<!-- Activate Cloaking Device
	function CheckEmail()
	{
		window.opener.document.location.href="<?php echo $phpgw->link('/index.php','menuaction=email.uiindex.index'); ?>";
	}
	//-->
	</script>
</head>
<body bgcolor="<?php echo $phpgw_info['theme']['bg_color']; ?>" alink="blue" vlink="blue" link="blue">
<table>
	<tr><td><a href="<?php echo $phpgw->link('/notify.php'); ?>">Check Now</a></td></tr>
<?php
	$phpgw->common->hook('notifywindow',array('email'));
?>
</table>
</body>
</html>
