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
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../../../header.inc.php');
	$appname = 'admin';
?>
<img src="<?php echo $phpgw->common->image($appname,'navbar.gif'); ?>" border=0> 
<font face="<?php echo $phpgw_info['theme']['font']; ?>" size="2"><p/>&nbsp;&nbsp;
Diese Funktion ist normalerweise nur f&uuml;r den Administrator zugänglich. Administration aller Programme, Benutzer und Benutzergruppen und Sitzungs/Logging Kontrolle.
<ul>
<li><b>Benutzerkonten-Verwaltung:</b><p/>&nbsp;&nbsp;
<i>Benutzerkonten:</i><br/>
Erstellen, Edittieren und l&ouml;schen von Benutzerkonten. Setzen von Gruppenmidgliedschaft und der Zugriff auf Programme.<p/>&nbsp;&nbsp;
<i>Benutzergruppen:</i><br/>
Erstellen, Edittieren und l&ouml;schen von Benutzergruppen.<p/>&nbsp;&nbsp;
</ul></font>
<?php $phpgw->common->phpgw_footer(); ?>
