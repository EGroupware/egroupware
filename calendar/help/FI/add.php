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
<font face="<?php echo $font; ?>" size="2"><p/>
Hakutoiminnolla varustetu päivä-, viikko- ja kuukausikalenteri /
aikataulusovellus joka muistuttaa tärkeistä tapahtumista.<br/>
<ul><li><b>Tapahtuman lisääminen:</b> <img src="<?php echo $phpgw->common->image('calendar','new.gif'); ?>"><br/>
Lisätäksesi uuden tapahtuman itsellesi / ryhmällesi, napsauta yllä olevan
näköistä kuvaketta. Näytölle avautuu lomake, johon voit syöttää tarvittavat
tiedot.</li><p/>
<table width="80%">
<td bgcolor="#ccddeb" width="50%" valign="top">
<font face="<?php echo $font ?>" size="2">
Lyhyt kuvaus:<br/>
Kuvaus:<br/>
Päivä:<br/>
Aika:<br/>
Kesto:<br/>
Tärkeys:<br/>
Näkyvyys:</td>
<td bgcolor="#ccddeb" width="50%" valign="top">
<font face="<?php echo $font; ?>" size="2">
Ryhmä:<br/>
Osallistujat:<br/>
Toistuvuus:<br/>
Viimeisen kerran:<br/>
Jakso:</td></table>
Täytä vain kentät ja napsauta Lähetä.</ul><br/>
<b>Huomaa:</b>
Kuten muidenkin sovellusten kohdalla, voit antaa tiedon
käyttöoikeudeksi Yksityinen, Oma ryhmä tai Kaikki.<p/></font>
<?php $phpgw->common->phpgw_footer(); ?>
