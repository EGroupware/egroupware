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
Esitetty näkymä vaihtuu päivä-, viikko- ja kuukausinäkymien välillä
vasemmassa yläreunassa näkyvistä kuvakkeista.<br/>
<ul><li><b>Näkymä:</b><img src="<?php echo $phpgw->common->image('calendar','today.gif'); ?>">Päivä <img src="<?php echo $phpgw->common->image('calendar','week.gif'); ?>">Viikko <img src="<?php echo $phpgw->common->image('calendar','month.gif'); ?>">Kuukausi <img src="<?php echo $phpgw->common->image('calendar','year.gif'); ?>">Vuosi<br/>
<i>Päivä:</i><br/>
Valittu päivä näytetään käyttäjän valitseman kokoisiin lohkoihin
jaoteltuna. Jaottelutarkkuus sekä päivän alku ja loppu muutetaan Kalenterin
asetuksista.<br/>
<i>Viikko:</i><br/>
Valittu viikko. Viikon alkamispäivä muutetaan Kalenterin
asetuksista.<br/>
<i>Kuukausi:</i><br/>
Valittu kuukausi sekä edellinen ja seuraava kuukausi mini-kalentereina.
Voit siirtyä päivänäkymään haluamaasi päivää napsauttamalla.<br/>
<i>Vuosi:</i><br/>
Valittu vuosi kuukausien mini-kalentereina.</li><p/>
<li><b>Tapahtuman lisääminen:</b> <img src="<?php echo $phpgw->common->image('calendar','new.gif'); ?>"><br/>
Lisätäksesi uuden tapahtuman itsellesi / ryhmällesi, napsauta yllä olevan
näköistä kuvaketta. Näytölle avautuu lomake, johon voit syöttää tarvittavat
tiedot.
<table width="80%">
<td bgcolor="#ccddeb" width="50%" valign="top">
<font face="<?php echo $font; ?>" size="2">
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
Täytä vain kentät ja napsauta Lähetä.<br/>
<b>Huomaa:</b> Kuten muidenkin sovellusten kohdalla, voit antaa tiedon
käyttöoikeudeksi Yksityinen, Oma ryhmä tai Kaikki.</li><p/>
<li><b>Muokkaa:Poista</b>&nbsp&nbsp<img src="<?php echo $phpgw->common->image('calendar','circle.gif'); ?>"><br/>
Muokataksesi tapahtumaa napsauta tätä kuvaketta.
Näytölle avautuu muokkauslomake.
Valitse muokkaa tai poista sivun alareunasta.<br/>
<b>Huomaa:</b>Voit muokata ja poistaa vain itse luomiasi tapahtumia.</li><p/></ul></font>
<?php $phpgw->common->phpgw_footer(); ?>
