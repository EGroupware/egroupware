<?php
  /**************************************************************************\
  * phpGroupWare - Addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	if(!isset($owner)) { $owner = 0; } 

	$grants = $phpgw->acl->get_grants('addressbook');
  
	if(!isset($owner) || !$owner) {
		$owner = $phpgw_info['user']['account_id'];
		$rights = PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE + 16;
	} else {
		if($grants[$owner]) {
			$rights = $grants[$owner];
			if (!($rights & PHPGW_ACL_READ)) {
				$owner = $phpgw_info['user']['account_id'];
				$rights = PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE + 16;
			}
		}
	}

?>
