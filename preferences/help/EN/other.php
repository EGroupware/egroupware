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
		'enable_utilities_class'	=> True
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../../../header.inc.php');
?>
<img src="<?php echo $phpgw->common->image('preferences','navbar.gif'); ?>" border=0> 
<font face="<?php echo $phpgw_info['theme']['font']; ?>" size="2"><p/>
The place to make changes to your personal piece of groupware.<p/>
<ul>
<li><b>Change your Password:</b><br/>
Used for changing your login password to the system.
You will be presented with two text boxes, enter your new password, 
then click on the change button.</li><p/>
<li><b>Select different Theme:</b><br/>
To change the look of the pages you see, within the system. Displayed is
your current theme (for new users set to default). Simply click on the
theme of your choice. Your pages will change immediately.<p/>
<li><b>Change your profile:</b><br/>
Here you can set a few details about yourself, for public viewing by the
rest of the users of the system. Title,Phone number, Comments, Picture upload.</li><p/>
<li><b>Monitor Newsgroups:</b><br/>
Easily choose which news groups you want to set for reading.</li>
