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
		'enable_utilities_class'	=> True
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../../../header.inc.php');
?> <img src="<?php echo $phpgw->common->image('preferences','navbar.gif'); ?>" border=0> 
<font face="<?php echo $phpgw_info['theme']['font']; ?>" size="2"> 
<p/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Der Ort um &Auml;nderungen 
an Ihrer persönlichen Groupware zu machen. 
<p/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 
<ul>
  <li><b>Ihre Einstellungen &auml;ndern:</b><br/>
    <i>Maximale Treffer pro Seite:</i><br/>
    W&auml;hlen Sie wie viele Treffer auf Ihrem Bildschirm auf einmal angezeigt 
    werden, standard ist 15. 
    <p/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Zeige Text bei den Navigations-Symbolen:</i><br/>
    Text unter den Symbolen oben am Bildschirm anzeigen. 
    <p/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Zeitzonen Differenz:</i><br/>
    Stellen Sie Ihre Zeitzone ein, Standard ist die Zentral Europ&auml;ische Standard 
    Zeit. 
    <p/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Datumsformat:</i><br/>
    W&auml;hlen Sie die Anordnung von Tag/Monat/Jahr, Standard ist Monat/Tag/Jahr. 
    <p/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Zeitformat:</i><br/>
    W&auml;hlen Sie zwischen am/pm=12 Stunden oder 24 Stunden Einstellung. 
    <p/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Sprache:</i><br/>
    W&auml;hlen Sie Ihre Sprache. W&auml;hlbar sind Deutsch, Spanisch, Norwegisch, 
    Italienisch, Franz&ouml;sisch und die Standardeinstellung Englisch. 
    <p/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Zeige Geburtstagserrinnerungen auf dem Begr&uuml;ssungsbildschirm::</i><br/>
    Diese Option aktiviert die Errinnerung f&uuml;r Geburtstage als ein Alarm. 
    Wenn Sie sich an einem Tag an dem ein Geburtstag eingetragen ist in das System 
    einloggen, wird eine Alarm-Botschaft auf dem Startbildschirm angezeigt. Geburtstagseinstellungen 
    werden im Adressbuch gemacht. 
    <p/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Zeige Ereignisse mit hoher Priorit&auml;t auf dem Hauptbildschirm:</i><br/>
    Die Auswahl dieser Option aktiviert Erinnerungen f&uuml;r wichtige Ereignisse 
    in der TO-DO LiST oder dem Ticketing System. 
    <p/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Die Woche Startet am:</i><br/>
    W&auml;hlen Sie den Tag an dem die Woche beginnt.. 
    <p/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Der Arbeitstag beginnt um:</i><br/>
    W&auml;hlen Sie die Anfangszeit ihres Arbeitstages. Das hat einfluss auf den 
    Tagesanfang im Kalender wenn die Tagesansicht angezeigt wird. 
    <p/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Ende eines Arbeitstags:</i><br/>
     W&auml;hlen Sie die Endzeit ihres Arbeitstags. Das &auml;ndert die letzte 
    Stunde die im Kalender in der Tagesansicht angezeigt wird. 
    <p/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>W&auml;hlen Sie News Seiten:</i><br/>
    Klicken Sie on so viele Schlagzeilen Seiten wie Sie w&uuml;nschen, diese werden 
    dann f&uuml;r Sie angezeigt wenn sie Die Headlines Funktion benutzen. Die 
    Systemadministratoren bestimmen welche voreingestellt sind, sie sind also 
    Ihre Ansprechpartner wenn Sie welche wollen die nicht dort sind. ;) </li>
</ul>
</font> 
<?php $phpgw->common->phpgw_footer(); ?>
