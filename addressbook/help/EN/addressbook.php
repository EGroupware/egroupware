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
Simply fill in the fields, and click OK.</li><p/></ul>
Access can be restricted to, private, group readable (that is the members of the
same groups as you are in, will be able to see the too, and globally readable, all
users to the system will be able to see the entry.<p/>
Users can only edit their own entries, regardless of readability settings.
<?php $phpgw->common->phpgw_footer(); ?>

