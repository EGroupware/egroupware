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
A searchable daily,weekly,monthly calendar/scheduling application with alerts for high priority events.<br/>
<ul><li><b>Edit:Delete</b>&nbsp&nbsp<img src="<?php echo $phpgw->common->image('calendar','circle.gif'); ?>"><br/>
To edit an entry group click on the small icon as shown above.
A form page will be presented, where you can edit the relevant fields.
Chose edit or delete from the bottom of the page.</li><p/>
<b>Note:</b>You can only make changes or delete those calendar entries created by you.<p/></ul></font>
<?php $phpgw->common->phpgw_footer(); ?>
