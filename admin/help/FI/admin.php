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
T‰m‰ sovellus on yleens‰ vain j‰rjestelm‰n p‰‰k‰ytt‰j‰n k‰ytett‰viss‰.
Sovelluksella hallitaan kaikkia sovelluksia, k‰ytt‰ji‰, ryhmi‰ ja istuntojen
lokeja.
<ul>
<li><b>K‰ytt‰jien hallinta:</b><br/>
<i>User accounts:</i><br/>
Toiminnolla voit lis‰t‰, muuttaa ja poistaa k‰ytt‰j‰tunnuksia sek‰ asettaa
ryhm‰t joihin tunnus kuuluu ja mihin sovelluksiin k‰ytt‰j‰ll‰ on k‰yttˆoikeus.<br/
><i>K‰ytt‰j‰ryhm‰t:</i><br/>
Toiminnolla voit lis‰t‰, muuttaa ja poistaa k‰ytt‰j‰ryhmi‰.<p/>
<li><b>Istuntojen hallinta:</b>
<p><i>N‰yt‰ istunnot:</i>
<br>T‰m‰nhetkiset istunnot, IP-osoitteet, Kirjautumisaika, aika, jonka k‰ytt‰j‰
on ollut tekem‰tt‰ mit‰‰n ja mahdollisuus keskeytt‰‰ istunto.
<p><i>N‰yt‰ k‰yttˆloki:</i>
<br>Tunnus, IP-osoite, Sis‰‰nkirjautumisaika, Uloskirjautumisaika, Istunnon kesto.<p/>
<li><b>Uutisotsikko -palvelut:</b><br/>
Hallinnoi palvelinten listaa, jolta Uutisotsikot -sovelluksen k‰ytt‰j‰t voivat
valita haluamansa palvelimen.<br/>
<i>Muokkaa:</i> Palvelimen asetukset:<br/>
N‰yttˆ,Pohja-URL, Tiedosto, Latausten v‰li, N‰ytetty lista, Uutisten tyyppi.<br/>
<i>Poista:</i>Poistaa palvelimen varmistettuaan ensin k‰ytt‰j‰lt‰,
ett‰ h‰n todella haluaa poistaa sen.<br/>
<i>N‰yt‰:</i>N‰ytt‰‰ asetukset.<br/>
<i>Lis‰‰:</i>Uuden palvelimen lis‰‰mist‰ varten, kuten muokkaustila.</li><p/>
<li><b>Uutisryhm‰t:</b><br/>
Uutisryhmien p‰ivitys k‰sin.</li><p/>
<li><b>Palvelimen tiedot:</b><br/>
T‰ll‰ hetkell‰ palvelimen phpinfo.</li><p/>
</ul></font>
<?php $phpgw->common->phpgw_footer(); ?>
