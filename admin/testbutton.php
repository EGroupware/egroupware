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

	$button->parseHTTPPostVars();
	$button->createInputButton(lang('save'), 'save');

	if (isset($submit)) print "is worked $submit<br>";

	print "<form method=post>";
	print $button->createInputButton("Lars is the best ;)",'submit');
	print "<br>the same as ascii<br>";
	print $button->createInputButton("Lars is the best ;)",'submit','ascii');
	print "</form>";
	
	$phpgw->common->phpgw_footer();
?>
