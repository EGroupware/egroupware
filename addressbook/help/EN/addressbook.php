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
<img src="<?php echo $phpgw->common->image('addressbook','navbar.gif'); ?>" border="0">
<font face="<?php echo $font ?>" size="2"><p/>
A searchable address book for keeping contact information of business 
associates or friends and family.
<ul><li><b>Add:</b><br/>
Click on the add button, a form page will be presented with the following fields:
<table width="80%">
<td bgcolor="#ccddeb" width="50%" valign="top">
<font face="<?php echo $font; ?>" size="2">
Last name:<br/>
E-mail:<br/>
Home phone:<br/>
Work phone:<br/>
Mobile:<br/>
Street:<br/>
City:<br/>
State:<br/>
ZIP code:<br/>
Access:<br/>
Group settings:<br/>
Notes:</td>
<td bgcolor="#ccddeb" width="50%" valign="top">
<font face="<?php echo $font; ?>" size="2">
First name:<br/>
Company name:<br/>
Fax:<br/>
Pager:<br/>
Other number:<br/>
Birthday:</td></table>
...and many others.  
Simply fill in the fields, and click OK.</li><p/></ul>
Access can be restricted to private, overriding acl preferences settings.
From preferences, you can grant access to users to the be able to view, 
edit, and even delete your entries.<p/>
