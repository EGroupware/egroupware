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
	$table_locks = Array('phpgw_et_notes');
	$db2 = $GLOBALS['phpgw']->db;
	$db2->lock($table_locks);

	$new_owner = intval(get_var('new_owner',Array('POST')));
	$account_id = intval(get_var('account_id',Array('POST')));
	if($new_owner==0)
	{
		$db2->query('DELETE FROM phpgw_et_notes WHERE note_owner='.$account_id,__LINE__,__FILE__);
	}
	else
	{
		$db2->query('UPDATE phpgw_et_notes SET note_owner='.$new_owner
			. ' WHERE note_owner='.$account_id,__LINE__,__FILE__);
	}
	$db2->unlock();
?>
