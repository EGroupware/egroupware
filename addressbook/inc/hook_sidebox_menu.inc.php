<?php
  /**************************************************************************\
  * phpGroupWare - Calendar's Sidebox-Menu for idots-template                *
  * http://www.phpgroupware.org                                              *
  * Written by Pim Snel <pim@lingewoud.nl>                                   *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */
{

 /*
	This hookfile is for generating an app-specific side menu used in the idots
	template set.

	$menu_title speaks for itself
	$file is the array with link to app functions

	display_sidebox can be called as much as you like
 */

	$menu_title = $GLOBALS['phpgw_info']['apps'][$appname]['title'] . ' '. lang('Menu');
	$file = Array(
		'Add'=>$GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uiaddressbook.add'),
		'_NewLine_', // give a newline
		'import contacts'=>$GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uiXport.import')
	);
	display_sidebox($appname,$menu_title,$file);

	if ($GLOBALS['phpgw_info']['user']['apps']['preferences'])
	{
		$menu_title = lang('Preferences');
		$file = Array(
			'Addressbook preferences'=>$GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uiaddressbook.preferences'),
			'Grant Access'=>$GLOBALS['phpgw']->link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app=addressbook'),
			'Edit Categories' =>$GLOBALS['phpgw']->link('/index.php','menuaction=preferences.uicategories.index&cats_app=addressbook&cats_level=True&global_cats=True')
		);
		display_sidebox($appname,$menu_title,$file);
	}

	if ($GLOBALS['phpgw_info']['user']['apps']['admin'])
	{
		$menu_title = lang('Administration');
		$file = Array(
			'Configuration'=>$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiconfig.index&appname=addressbook'),
			'Custom Fields'=>$GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uifields.index'),
			'Global Categories' =>$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.index&appname=addressbook')
		);
		display_sidebox($appname,$menu_title,$file);
	}
}
?>
