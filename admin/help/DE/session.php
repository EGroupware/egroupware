<?php
  /**************************************************************************\
  * phpGroupWare - User manual                                               *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */
	
	$phpgw_flags = Array(
		'currentapp'	=> 'manual',
		'admin_header'	=> True,
		'enable_utilities_class'	=> True
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../../../header.inc.php');
	$appname = 'admin';
?>
<img src="<?php echo $phpgw->common->image($appname,'navbar.gif'); ?>" border=0> 
<font face="<?php echo $phpgw_info['theme']['font']; ?>" size="2">
<p>
Diese Funktion ist normalerweise nur f&uuml;r den Administrator zugänglich. Administration aller Programme, Benutzer und Benutzergruppen und Sitzungs/Logging Kontrolle.
<ul>
<li><b>Session :</b>
<p><i>Sessions anzeigen:</i>
<br>Aktuelle Sitzungen, IP's, Login-Zeit, Inaktivit&auml;tszeit und die M&ouml;glichkeit sessions zu beenden (kill).
<p><i>Zugriffsaufzeichnungen (Access Log) anzeigen:</i>
<br>
      LoginId, IP, Login Time (Zeit), Logout Time (Zeit), Total time spent (gesamte 
      verbrachte Zeit). </ul>
<?php $phpgw->common->phpgw_footer(); ?>
