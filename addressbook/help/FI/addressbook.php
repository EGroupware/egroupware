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
<font face="<?php echo $font ?>" size="2"><p/>
Hakutoiminnolla varustettu osoitekirja yhteystietojen tallentamiseen.
<ul><li><b>Lisää:</b><br/>
Napsauta lisää -painiketta ja näytölle avautuu lomake, jossa on seuraavat kentät:
<table width="80%">
<td bgcolor="#ccddeb" width="50%" valign="top">
<font face="<?php echo $font; ?>" size="2">
Sukunimi:<br/>
E-Mail:<br/>
Kotinumero:<br/>
Työnumero:<br/>
Matkapuhelin:<br/>
Katuosoite:<br/>
Kaupunki:<br/>
Osavaltio:<br/>
Postinumero:<br/>
Käyttöoikeus:<br/>
Ryhmän asetukset:<br/>
Muuta:</td>
<td bgcolor="#ccddeb" width="50%" valign="top">
<font face="<?php echo $font; ?>" size="2">
Etunimi:<br/>
Yritys:<br/>
Fax:<br/>
Hakulaite:<br/>
Muu numero:<br/>
Syntymäpäivä:</td></table>
...ja paljon muita.
Täytä tiedot kenttiin ja napsauta OK.</li><p/></ul>
Tiedon käyttöoikeus voidaan rajoittaa yksityiseksi, mikä ohittaa ACL:n
asetuksen. Osoitekirjan asetuksista voit antaa muille käyttäjille
oikeuden selata, muokata tai jopa poistaa tallentamiasi tietoja.<p/>
