<?php 
  /**************************************************************************\
  * phpGroupWare - holidaycalc                                               *
  * http://www.phpgroupware.org                                              *
  * Based on Yoshihiro Kamimura <your@itheart.com>                           *
  *          http://www.itheart.com                                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	if (empty($phpgw_info['user']['preferences']['common']['country']))
	{
		$rule = 'US';
	}
	else
	{
		$rule = $phpgw_info['user']['preferences']['common']['country'];
	}

	include(PHPGW_INCLUDE_ROOT.'/calendar/inc/class.holidaycalc_'.$rule.'.inc.php');
?>
