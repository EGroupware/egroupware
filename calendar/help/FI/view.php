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
?>
<img src="<?php echo $phpgw->common->image('calendar','navbar.gif'); ?>" border="0">
<font face="<?php echo $phpgw_info['theme']['font']; ?>" size="2"><p/>
Hakutoiminnolla varustetu p‰iv‰-, viikko- ja kuukausikalenteri /
aikataulusovellus joka muistuttaa t‰rkeist‰ tapahtumista.<br/>
Esitetty n‰kym‰ vaihtuu p‰iv‰-, viikko- ja kuukausin‰kymien v‰lill‰
vasemmassa yl‰reunassa n‰kyvist‰ kuvakkeista.<br/>
<ul><li><b>N‰kym‰:</b><img src="<?php echo $phpgw->common->image('calendar','today.gif'); ?>">P‰iv‰ <img src="<?php echo $phpgw->common->image('calendar','week.gif'); ?>">Viikko <img src="<?php echo $phpgw->common->image('calendar','month.gif'); ?>">Kuukausi <img src="<?php echo $phpgw->common->image('calendar','year.gif'); ?>">Vuosi<br/>
<i>P‰iv‰:</i><br/>
Valittu p‰iv‰ n‰ytet‰‰n k‰ytt‰j‰n valitseman kokoisiin lohkoihin
jaoteltuna. Jaottelutarkkuus sek‰ p‰iv‰n alku ja loppu muutetaan Kalenterin
asetuksista.<br/>
<i>Viikko:</i><br/>
Valittu viikko. Viikon alkamisp‰iv‰ muutetaan Kalenterin
asetuksista.<br/>
<i>Kuukausi:</i><br/>
Valittu kuukausi sek‰ edellinen ja seuraava kuukausi mini-kalentereina.
Voit siirty‰ p‰iv‰n‰kym‰‰n haluamaasi p‰iv‰‰ napsauttamalla.<p/></li></ul></font>
