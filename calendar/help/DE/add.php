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
?> <img src="<?php echo $phpgw->common->image('calendar','navbar.gif'); ?>" border="0"> 
<font face="<?php echo $font; ?>" size="2">
<p/>&nbsp;&nbsp;&nbsp;&nbsp; Ein durchsuchbares t&auml;gliches, w&ouml;chentliches, monatliches 
und j&auml;hrliches Kalender/Planer-Programm mit Alarm f&uuml;r wichtige Ereignisse.<br/>
<ul>
  <li><b>Einen Eintrag hinzuf&uuml;gen:</b> <img src="<?php echo $phpgw->common->image('calendar','new.gif'); ?>"><br/>
    Um eine Eintragung f&uuml;r sich selber oder f&uuml;r ihre Gruppe hinzuzuf&uuml;gen 
    klicken Sie auf das kleine Symbol (+) wie das oben. Eine Formularseite wird 
    angezeigt, auf der Sie die wichtigen Felder ausf&uuml;llen k&ouml;nnen.</li>
  <p/>&nbsp;&nbsp;&nbsp;&nbsp; 
  <table width="80%">
    <td bgcolor="#ccddeb" width="50%" valign="top"> <font face="<?php echo $font ?>" size="2"> 
      Titel:<br/>
      Vollst&auml;ndige Beschreibung:<br/>
      Datum:<br/>
      Zeit:<br/>
      Dauer:<br/>
      Priorit&auml;t:<br/>
      Zugriff:</td>
    <td bgcolor="#ccddeb" width="50%" valign="top"> <font face="<?php echo $font; ?>" size="2"> 
      Gruppen Auswahl:<br/>
      Teilnehmer;<br/>
      Wiederholungstyp:<br/>
      Wiederholungs-Enddatum:<br/> Intervall:<font size="2" face="<?php echo $font; ?>">&nbsp;</font>:</td>
  </table>
  F&uuml;llen Sie die Felder aus und klicken Sie absenden. 
</ul>
<br/>
<b>Wichtig:</b> Der Zugriff kann, wie mit anderen Programmen dieses Paketes, auf 
Privat, Gruppen lesbar oder global eingestellt werden. 
<p/>&nbsp;&nbsp;&nbsp;&nbsp;</font> 
<?php $phpgw->common->phpgw_footer(); ?>
