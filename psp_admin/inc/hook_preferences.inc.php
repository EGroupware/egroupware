<?php
  /**************************************************************************\
  * eGroupWare                                                               *
  * http://www.egroupware.org                                                *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id: hook_preferences.inc.php,v 1.14 2005/07/23 15:52:48 ralfbecker Exp $ */
  {
	 $title = $appname;
	 $file = Array(
		'Preferences' => $GLOBALS['phpgw']->link('/preferences/preferences.php','appname='.$appname)
	 );
	 display_section($appname,$title,$file);
  }

?>
