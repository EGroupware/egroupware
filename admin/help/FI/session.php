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
T‰m‰ sovellus on yleens‰ vain j‰rjestelm‰n p‰‰k‰ytt‰j‰n k‰ytett‰viss‰.
Sovelluksella hallitaan kaikkia sovelluksia, k‰ytt‰ji‰, ryhmi‰ ja istuntojen
lokeja.
<ul>
<li><b>Istuntojen hallinta:</b>
<p><i>N‰yt‰ istunnot:</i>
<br>T‰m‰nhetkiset istunnot, IP-osoitteet, Kirjautumisaika, aika, jonka k‰ytt‰j‰
on ollut tekem‰tt‰ mit‰‰n ja mahdollisuus keskeytt‰‰ istunto.
<p><i>N‰yt‰ k‰yttˆloki:</i>
<br>Tunnus, IP-osoite, Sis‰‰nkirjautumisaika, Uloskirjautumisaika, Istunnon kesto.
</ul>
<?php $phpgw->common->phpgw_footer(); ?>
