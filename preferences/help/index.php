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
	$GLOBALS['phpgw_info']['flags'] = $phpgw_flags;
	include('../../header.inc.php');
	$appname = 'preferences';
	include(PHPGW_SERVER_ROOT.'/'.$appname.'/setup/setup.inc.php');
?>
<img src="<?php echo $GLOBALS['phpgw']->common->image($appname,'navbar'); ?>" border="0"><p/>
<font face="<?php echo $GLOBALS['phpgw_info']['theme']['font']; ?>" size="2">
Version: <b><?php echo $setup_info[$appname]['version']; ?></b><p/>
</font>
<?php $GLOBALS['phpgw']->common->phpgw_footer(); ?>
