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
<font face="<?php echo $phpgw_info['theme']['font']; ?>" size="2"><p/>
T‰m‰ sovellus on yleens‰ vain j‰rjestelm‰n p‰‰k‰ytt‰j‰n k‰ytett‰viss‰.
Sovelluksella hallitaan kaikkia sovelluksia, k‰ytt‰ji‰, ryhmi‰ ja istuntojen
lokeja.
<ul><li><b>Uutisryhm‰t:</b><br/>
Uutisryhmien p‰ivitys k‰sin.</li><p/>
<li><b>Palvelimen tiedot:</b><br/>
T‰ll‰ hetkell‰ palvelimen phpinfo.</li><p/>
</ul></font>
<?php $phpgw->common->phpgw_footer(); ?>
