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
<font face="<?php echo $phpgw_info['theme']['font']; ?>" size="2"><p/>
Tämä sovellus on yleensä vain järjestelmän pääkäyttäjän käytettävissä.
Sovelluksella hallitaan kaikkia sovelluksia, käyttäjiä, ryhmiä ja istuntojen
lokeja.
<ul>
<li><b>Käyttäjien hallinta:</b><p/>
<i>Käyttäjätunnukset:</i><br/>
Toiminnolla voit lisätä, muuttaa ja poistaa käyttäjätunnuksia sekä asettaa
ryhmät joihin tunnus kuuluu ja mihin sovelluksiin käyttäjällä on käyttöoikeus.<p/>
<i>Käyttäjäryhmät:</i><br/>
Toiminnolla voit lisätä, muuttaa ja poistaa käyttäjäryhmiä.<p/>
</ul></font>
<?php $phpgw->common->phpgw_footer(); ?>
