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
<img src="<?php echo $phpgw->common->image('addressbook','navbar.gif'); ?>" border="0">
<font face="<?php echo $font ?>" size="2"><p/>&nbsp;&nbsp;&nbsp;&nbsp; Ein durchsuchbares 
Adressbuch um Kontakt-Informationen von Gesch&auml;ftspartnern, Freunden und der 
Familie aufzubewahren. 
<ul>
  <li><b>Hinzuf&uuml;gen:</b><br/>
    Klicken Sie auf den Hinzuf&uuml;gen Knopf, es wird eine Formularseite mit 
    den folgenden Feldern angezeigt: 
    <table width="80%">
<td bgcolor="#ccddeb" width="50%" valign="top">
<font face="<?php echo $font; ?>" size="2">
Nachnahme:<br/>
E-mail:<br/>
Telefon Privat:<br/>
Telefon Gesch&auml;ftlich:<br/>
Mobiltelefon:<br/>
Strasse:<br/>
Stadt:<br/>
Land:<br/>
Postleitzahl:<br/>
Zugriff:<br/>
Gruppen-Einstellungen:<br/>
Notizen:</td>
        <td bgcolor="#ccddeb" width="50%" valign="top"> <font face="<?php echo $font; ?>" size="2"> 
          Vorname:<br/>
          Firmenname:<br/>
Fax:<br/>
Pager:<br/>
Andere Nummer:<br/>
Geburtstag:</td></table>
...und viele andere mehr.  
Einfach die Felder ausfüllen und OK klicken.</li><p/>&nbsp;&nbsp;&nbsp;&nbsp;</ul>
Der Zugriff kann auf Privat beschr&auml;nkt werden, was die Prefrences (Optionen) 
Einstellungen überschreibt. In den Prefrences kann eingestellt werden, welche 
Benutzer welchen Zugriff auf ihr Adressbuch haben - anschauen, bearbeiten oder 
sogar l&ouml;schen. 
<p/>&nbsp;&nbsp;&nbsp;&nbsp;
<?php $phpgw->common->phpgw_footer(); ?>

