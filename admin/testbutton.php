<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  	/* $Id$ */
  	
  	$phpgw_info["flags"] = array(
  		"currentapp" => "admin"
  	);
  	
  	include("../header.inc.php");

	$button = CreateObject('phpgwapi.graphics');
	
	print "<form>";
	print "<input type=\"image\" src=\"/phpgroupware/phpgwapi/templates/default/images/".
			$button->createButton("Lars is the best ;)")."\" border=\"0\" name=\"text\">";
	print "</form>";
	
	$phpgw->common->phpgw_footer();
?>
