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
		'currentapp'	=> 'manual'
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../../../header.inc.php');
?>
<img src="<?php echo $phpgw->common->image('calendar','navbar.gif'); ?>" border="0">
<font face="<?php echo $phpgw_info['theme']['font']; ?>" size="2"><p/>&nbsp;&nbsp; Ein 
durchsuchbares t&auml;gliches, w&ouml;chentliches, monatliches und j&auml;hrliches 
Kalender/Planer-Programm mit Alarm f&uuml;r wichtige Ereignisse.<br/>
<ul><li><b>Bearbeiten:L&ouml;schen</b>&nbsp&nbsp<img src="<?php echo $phpgw->common->image('calendar','circle.gif'); ?>"><br/>
Um eine Eintragung zu bearbeiten oder zu l&ouml;schen, klicken Sie auf das kleine Symbol wie oben gezeigt.
W&auml;hlen Sie bearbeiten oder löschen aus.<br/>
    Wenn Sie Bearbeiten w&auml;hlen wird ihnen eine Formular seite angezeigt, 
    worauf Sie die gewünschten Felder bearbeiten können. </li><p/>&nbsp;&nbsp;
<b>Wichtig:</b>Sie k&ouml;nnen nur Eintragungen die Sie selber gemacht haben bearbeiten oder l&ouml;schen.<p/>&nbsp;&nbsp;</ul></font>
<?php $phpgw->common->phpgw_footer(); ?>
