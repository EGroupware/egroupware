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
<font face="<?php echo $phpgw_info['theme']['font']; ?>" size="2"><p/>&nbsp;&nbsp;&nbsp; 
Diese Funktion ist normalerweise nur f&uuml;r den Administrator zugänglich. Administration 
aller Programme, Benutzer und Benutzergruppen und Sitzung's/Logging Kontrolle. 
<ul><li><b>Netzwerk News:</b><br/>
Manuelle Aktualisierung f&uuml;r Newsgruppen.</li><p/>&nbsp;&nbsp;&nbsp;
<li><b>Server Information:</b><br/>
    Zeigt die phpinfo(); des Server's an.</li>
  <p/>&nbsp;&nbsp;&nbsp;
</ul></font>
<?php $phpgw->common->phpgw_footer(); ?>
