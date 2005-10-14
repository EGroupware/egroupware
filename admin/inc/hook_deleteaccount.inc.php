<?php
	/**************************************************************************\
	* eGroupWare                                                               *
	* http://www.egroupware.org                                                *
	* Written by Mark Peters <skeeter@phpgroupware.org>                        *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	if((int)$GLOBALS['hook_values']['account_id'] > 0)
	{
		$GLOBALS['egw']->accounts->delete((int)$GLOBALS['hook_values']['account_id']);
		$GLOBALS['egw']->db->lock(Array('phpgw_acl'));
		$GLOBALS['egw']->db->query("DELETE FROM phpgw_acl WHERE acl_location='" . (int)$GLOBALS['hook_values']['account_id']
			. "' OR acl_account=".(int)$GLOBALS['hook_values']['account_id'],__LINE__,__FILE__);
		$GLOBALS['egw']->db->unlock();
	}
?>
