<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	$oProc->query ("INSERT INTO phpgw_vfs (owner_id, createdby_id, modifiedby_id, created, modified, size, mime_type, deleteable, comment, app, directory, name, link_directory, link_name) VALUES (1,0,0,NULL,'1970-01-01',NULL,NULL,'Directory','Y',NULL,NULL,'/','', NULL, NULL)");
	$oProc->query ("INSERT INTO phpgw_vfs (owner_id, createdby_id, modifiedby_id, created, modified, size, mime_type, deleteable, comment, app, directory, name, link_directory, link_name) VALUES (2,0,0,NULL,'1970-01-01',NULL,NULL,'Directory','Y',NULL,NULL,'/','home', NULL, NULL)");

?>
