<?php
  /**************************************************************************\
  * phpGroupWare - Calendar Holidays                                         *
  * http://www.phpgroupware.org                                              *
  * Written by Mark Peters <skeeter@phpgroupware.org>                        *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	if (floor(phpversion()) == 4) {
		global $phpgw, $phpgw_info, $treemenu;
	}

	$treemenu .= '..<img src="'.$phpgw->common->image($appname,'navbar.gif').'" border="0" alt="'.ucwords($appname).'">'.ucwords($appname).'|'.$phpgw->link('/'.$appname.'/help/'.$appname.'.php')."\n";
// Modify the $treemenu variables from here down.....
?>
