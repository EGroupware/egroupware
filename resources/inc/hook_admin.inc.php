<?php
  /**************************************************************************\
  * eGroupWare                                                               *
  * http://www.egroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  /* $Id$ */

//$file['Site Configuration'] = $GLOBALS['phpgw']->link('/index.php', array(menuaction => 'myapp.uiobj.admin_method'); 
//display_section('myapp',$file); 

	{
		$file = Array
		(
			'Global Categories'	=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.index&appname=' . $appname),
			'Configure Access Permissions' => $GLOBALS['phpgw']->link('/index.php','menuaction=resources.ui_acl.acllist'),
// 			'eGW SMF bridge' => $GLOBALS['phpgw']->link('/index.php','menuaction=resources.ui_smfbridge.egw2smf')
			//'Configure RSS exports' => $GLOBALS['phpgw']->link('/index.php','menuaction=news_admin.uiexport.exportlist'),
		);
		display_section($appname,$appname,$file);
	}
?>
