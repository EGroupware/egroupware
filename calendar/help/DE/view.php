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
?> <img src="<?php echo $phpgw->common->image('calendar','navbar.gif'); ?>" border="0"> 
<font face="<?php echo $phpgw_info['theme']['font']; ?>" size="2">
<p/>&nbsp;&nbsp;&nbsp; Ein durchsuchbares t&auml;gliches, w&ouml;chentliches, monatliches 
und j&auml;hrliches Kalender/Planer-Programm mit Alarm f&uuml;r wichtige Ereignisse.<br/>
Um entweder den St&uuml;ndlich/T&auml;glichen, w&ouml;chentlichen oder monatlichen 
Kalender zu sehen, klicken Sie auf das Betreffende Symbol in der linken oberen 
Ecke.<br/>
<ul>
  <li><b>Ansicht:</b><img src="<?php echo $phpgw->common->image('calendar','today.gif'); ?>">Tag 
    <img src="<?php echo $phpgw->common->image('calendar','week.gif'); ?>">Woche 
    <img src="<?php echo $phpgw->common->image('calendar','month.gif'); ?>">Monat 
    <img src="<?php echo $phpgw->common->image('calendar','year.gif'); ?>">Jahr
    <p/>&nbsp;&nbsp;&nbsp; <i>Tag:</i><br/>
    Der heutige Tag wird angezeigt, aufgeteilt in st&uuml;ndliche Bl&ouml;cke. 
    Anfangs und End-Zeit des Tages k&ouml;nnen in den Prefrences (Einstellungen, 
    Optionen) eingestellt werden.
    <p/>&nbsp;&nbsp;&nbsp; <i>Woche:</i><br/>
    Die aktuelle Woche wird angezeigt. Der Wochenanfangstag kann in den Prefrences 
    (Optionen) eingestellt werden.
    <p/>&nbsp;&nbsp;&nbsp; <i>Monat:</i><br/>
    Der vor eingestellter Eintrag ist der aktuelle Monat. Der vorherige oder k&uuml;nftige 
    <br>
    Monat sind leicht erreichbar mit nur einem klick.
    <p/>&nbsp;&nbsp;&nbsp;</li>
</ul>
</font> 
<?php $phpgw->common->phpgw_footer(); ?>
