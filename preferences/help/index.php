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
	include('../../header.inc.php');
?>
<img src="<?php echo $phpgw->common->image('preferences','navbar.gif'); ?>" border="0">
<font face="<?php echo $phpgw_info['theme']['font']; ?>" size="2"><p/>
<?php include(PHPGW_SERVER_ROOT.'/preferences/version.inc.php'); ?>
Version: <b><?php echo $phpgw_info['server']['versions']['preferences']; ?></b>
</font>
<?php $phpgw->common->phpgw_footer(); ?>
