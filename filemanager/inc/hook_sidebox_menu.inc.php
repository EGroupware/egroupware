<?php
  /**************************************************************************\
  * eGroupWare - Calendar's Sidebox-Menu for idots-template                  *
  * http://www.egroupware.org                                                *
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
	array('','text'=>'Filemanager Preferences','link'=>$GLOBALS['phpgw']->link('/preferences/preferences.php','appname=filemanager')),
	);
	display_sidebox($appname,$menu_title,$file);

}
?>
