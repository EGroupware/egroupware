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
A searchable daily,weekly,monthly calendar/scheduling application with alerts for high priority events.<br/>
<ul><li><b>Adding an entry:</b> <img src="<?php echo $phpgw->common->image('calendar','new.gif'); ?>"><br/>
To add a new entry for yourself other members of your group click on the small icon as shown above.
A form page will be presented, where you can input the relevant fields.</li><p/>
<table width="80%">
<td bgcolor="#ccddeb" width="50%" valign="top">
<font face="<?php echo $font ?>" size="2">
Brief Description:<br/>
Full Description:<br/>
Date:<br/>
Time:<br/>
Duration:<br/>
Priority:<br/>
Acccess:</td>
<td bgcolor="#ccddeb" width="50%" valign="top">
<font face="<?php echo $font; ?>" size="2">
Group selection:<br/>
Participants;<br/>
Repeat type:<br/>
Repeat end date:<br/>
Frequency:</td></table>
Simply fill in the fields, and click Submit.</ul><br/>
<b>Note:</b> Access can be set as with other applications in this suite, Private,Group Readable,Globally
Readable.<p/></font>
