<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * Written by Mark Peters <skeeter@phpgroupware.org>                        *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
	/* $Id$ */
	
	// Delete all records for a user
	if (floor($PHP_VERSION ) == 4)
	{
		global $phpgw, $account_id;
	}

	$phpgw->db->query('DELETE FROM phpgw_preferences WHERE preference_owner='.$account_id,__LINE__,__FILE__);
?>
