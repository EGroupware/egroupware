<?php
	/**************************************************************************\
	* eGroupWare - Resources                                                   *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	* --------------------------------------------                             *
	\**************************************************************************/

	/* $Id: */

	class so_acl
	{
		function get_rights($location)
		{
			return $GLOBALS['egw']->acl->get_all_rights($location,'news_admin');
		}

		function remove_location($location)
		{
			$GLOBALS['egw']->acl->delete_repository('news_admin',$location,false);
		}

		function get_permissions($user, $inc_groups)
		{
			return $GLOBALS['egw']->acl->get_all_location_rights($user,'sitemgr',$inc_groups);
		}
	}
