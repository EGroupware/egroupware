<?php
	/**************************************************************************\
	* phpGroupWare - account administration                                    *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	class soaccounts
	{

		function soaccounts()
		{
		}

		function account_total($query)
		{
			if ($query)
			{
				$querymethod = " AND (account_firstname LIKE '%$query%' OR account_lastname LIKE "
					. "'%$query%' OR account_lid LIKE '%$query%') ";
			}

			$GLOBALS['phpgw']->db->query("SELECT COUNT(*) FROM phpgw_accounts WHERE account_type='u'".$querymethod,__LINE__,__FILE__);
			$GLOBALS['phpgw']->db->next_record();

			return $GLOBALS['phpgw']->db->f(0);
		}
	}
?>
