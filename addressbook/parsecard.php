<?php

/**************************************************************************\
* phpGroupWare - addressbook                                               *
* http://www.phpgroupware.org						     *
* Written by Joseph Engo <jengo@phpgroupware.org>			     *
* --------------------------------------------			     *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*	  option) any later version. 					     *
\**************************************************************************/

/* $Id$ */

	$phpgw_info["flags"] = array("currentapp" => "addressbook",
								"enable_contact_class" => True,
								"noheader" => True, "nonavbar" => True);
	include("../header.inc.php");

	//if($access == "group")
	//	$access = $n_groups;
	//echo $access . "<BR>";

	parsevcard($filename,$access);
	// Delete the temp file.
	unlink($filename);
	unlink($filename . ".info");
	Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/addressbook/", "cd=14"));
?>
