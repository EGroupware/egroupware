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

	if($_POST['account_id'])
	{
		$GLOBALS['phpgw']->accounts->delete($_POST['account_id']);
		$GLOBALS['phpgw']->db->lock(Array('phpgw_acl'));
		$GLOBALS['phpgw']->db->query("DELETE FROM phpgw_acl WHERE acl_location='" . $_POST['account_id']
			. "' OR acl_account=".$_POST['account_id'],__LINE__,__FILE__);
		$GLOBALS['phpgw']->db->unlock();
	}
?>
