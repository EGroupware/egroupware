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
	$font = $phpgw_info['theme']['font'];
?>
<img src="<?php echo $phpgw->common->image('calendar','navbar.gif'); ?>" border="0">
<font face="<?php echo $font; ?>" size="2"><p/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Ein durchsuchbares 
t&auml;gliches, w&ouml;chentliches, monatliches und j&auml;hrliches Kalender/Planer-Programm 
mit Alarm f&uuml;r wichtige Ereignisse.<br/>
Um entweder den St&uuml;ndlich/T&auml;glichen, w&ouml;chentlichen oder monatlichen 
Kalender zu sehen, klicken Sie auf das Betreffende Symbol in der linken oberen 
Ecke.<br/>
<ul><li><b>Ansicht:</b><img src="<?php echo $phpgw->common->image('calendar','today.gif'); ?>">Tag <img src="<?php echo $phpgw->common->image('calendar','week.gif'); ?>">Woche <img src="<?php echo $phpgw->common->image('calendar','month.gif'); ?>">Monat <img src="<?php echo $phpgw->common->image('calendar','year.gif'); ?>">Jahr<br/>
<i>Tag:</i><br/>
    Der heutige Tag wird angezeigt, aufgeteilt in st&uuml;ndliche Bl&ouml;cke. 
    Anfangs und End-Zeit des Tages können in den Prefrences (Einstellungen, Optionen) 
    eingestellt werden.<br/>
<i>Woche:</i><br/>
Die aktuelle Woche wird angezeigt. Der Wochenanfangstag kann in den Prefrences (Optionen) eingestellt werden.<br/>
<i>Monat:</i><br/>
    Der vor eingestellter Eintrag ist der aktuelle Monat. Der vorherige oder künftige 
    Monat sind leicht erreichbar mit nur einem klick.<br/>
<i>Jahr:</i><br/>
    Das laufende Jahr wird angezeigt. Es werden die kleinen monatlichen Kalender 
    ansichten benutzt.</li>
  <p/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<li><b>Eine Eintragung hinzuf&uuml;gen:</b> <img src="<?php echo $phpgw->common->image('calendar','new.gif'); ?>"><br/>
    Um eine Eintragung für sich selber oder für Mitglieder Ihrer Gruppe hinzuzufügen 
    klicken Sie auf das kleine (+) Symbol wie oben gezeigt. Eine Formularseite 
    wird angezeigt, auf der Sie dann die relevanten Felder ausf&uuml;llen k&ouml;nnen. 
    <table width="80%">
<td bgcolor="#ccddeb" width="50%" valign="top">
<font face="<?php echo $font; ?>" size="2">
Titel:<br/>
Vollst&auml;ndige Beschreibung:<br/>
Datum:<br/>
Zeit:<br/>
Dauer:<br/>
Priorit&auml;t:<br/>
Zugriff:</td>
<td bgcolor="#ccddeb" width="50%" valign="top">
<font face="<?php echo $font; ?>" size="2">
Gruppen Auswahl:<br/>
Teilnehmer;<br/>
Wiederholungstyp:<br/>
Wiederholungs-Enddatum:<br/>
Intervall:</td></table>
F&uuml;llen Sie die Felder aus und klicken Sie absenden.<br/>
    <b>Wichtig:</b> Der Zugriff kann, wie mit anderen Programmen dieses Pakets, 
    auf privat, Gruppen und global eingestellt werden.</li>
  <p/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
  <li><b>Bearbeiten:L&ouml;schen</b>&nbsp&nbsp<img src="<?php echo $phpgw->common->image('calendar','circle.gif'); ?>"><br/>
Um eine Eintragung zu bearbeiten oder zu l&ouml;schen, klicken Sie auf das kleine Symbol wie oben gezeigt.
W&auml;hlen Sie bearbeiten oder löschen aus.<br/>
    Wenn Sie Bearbeiten w&auml;hlen wird ihnen eine Formular seite angezeigt, 
    worauf Sie die gewünschten Felder bearbeiten können. <br/>
<b>Wichtig:</b>Sie können nur Eintragungen die Sie selber gemacht haben bearbeiten oder löschen.</li><p/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</ul></font>
<?php $phpgw->common->phpgw_footer(); ?>
