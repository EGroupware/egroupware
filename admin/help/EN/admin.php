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
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../../../header.inc.php');
	$appname = 'admin';
?>
<img src="<?php echo $phpgw->common->image($appname,'navbar.gif'); ?>" border=0> 
<font face="<?php echo $phpgw_info['theme']['font']; ?>" size="2"><p/>
This function is usually only available to the systems administrator of the system.
Administration of all applications, user and group accounts, session logging and control.
<ul>
<li><b>Account management:</b><br/>
<i>User accounts:</i><br/>
Create, edit and delete users accounts. Set membership of groups, and access to applications.<br/
><i>User groups:</i><br/>
Create, edit and delete groups.<p/>
<li><b>Session management:</b><br/>
<i>View sessions:</i><br/>
Current sessions, IP, Login Time, Idle Time, and gives option to kill session.<br/>
<i>View Access Log:</i><br/>
LoginId, IP, Login Time, Logout Time, Total time spent.</li><p/>
<li><b>Headline sites:</b><br/>
Administer headline sites as seen by users in the headlines application.<br/>
<i>Edit:</i> Options for the headline sites:<br/>
Display,BaseURL, NewsFile,Minutes between reloads,Listing Displayed,News Type.<br/>
<i>Delete:</i>Remove an existing headling site, clicking on delete will give
you a checking page to be sure you do want to delete.<br/>
<i>View:</i>Displays set options as in edit.<br/>
<i>Add:</i>Form for adding new headline site, options as in edit.</li><p/>
<li><b>Network news:</b><br/>
Manual update for news groups.</li><p/>
<li><b>Server information:</b><br/>
At present phpinfo of the server that is running.</li><p/>
</ul></font>
