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
Hakutoiminnolla varustetu päivä-, viikko- ja kuukausikalenteri /
aikataulusovellus joka muistuttaa tärkeistä tapahtumista.<br/>
<ul><li><b>Muokkaa:Poista</b>&nbsp&nbsp<img src="<?php echo $phpgw->common->image('calendar','circle.gif'); ?>"><br/>
Muokataksesi tapahtumaa napsauta tätä kuvaketta.
Näytölle avautuu muokkauslomake.
Valitse muokkaa tai poista sivun alareunasta.</li><p/>
<b>Huomaa:</b>Voit muokata ja poistaa vain itse luomiasi tapahtumia.<p/></ul></font>
