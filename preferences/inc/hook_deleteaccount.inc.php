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
	$table_locks = Array('phpgw_preferences');

	$GLOBALS['phpgw']->db->lock($table_locks);
	$GLOBALS['phpgw']->db->query('DELETE FROM phpgw_preferences WHERE preference_owner='.$GLOBALS['HTTP_POST_VARS']['account_id'],__LINE__,__FILE__);
	$GLOBALS['phpgw']->db->unlock();


?>
