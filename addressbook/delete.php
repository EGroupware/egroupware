<?php
/**************************************************************************\
* phpGroupWare - addressbook                                               *
* http://www.phpgroupware.org                                              *
* Written by Joseph Engo <jengo@phpgroupware.org>                          *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

	if ($confirm) {
		$phpgw_info["flags"] = array(
			"noheader" => True,
			"nonavbar" => True
		);
	}

	$phpgw_info["flags"]["currentapp"] = "addressbook";
	$phpgw_info["flags"]["enable_contacts_class"] = True;
	include("../header.inc.php");
  
	if (! $ab_id) {
		@Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/addressbook/"));
	}

	$this = CreateObject("phpgwapi.contacts");
	$fields = $this->read_single_entry($ab_id,array("owner" => "owner"));
	//$record_owner = $fields[0]["owner"];

	$t = new Template($phpgw->common->get_tpl_dir("addressbook"));
	$t->set_file(array("delete" => "delete.tpl"));

	if ($confirm != "true") {
		$t->set_var(lang_sure,lang("Are you sure you want to delete this entry ?"));
		$t->set_var(no_link,$phpgw->link("view.php","&ab_id=$ab_id&order=$order&sort=$sort&filter=$filter&start=$start&query=$query"));
		$t->set_var(lang_no,lang("NO"));
		$t->set_var(yes_link,$phpgw->link("delete.php","ab_id=$ab_id&confirm=true&order=$order&sort=$sort&filter=$filter&start=$start&query=$query"));
		$t->set_var(lang_yes,lang("YES"));
		$t->pparse("out","delete");

		$phpgw->common->phpgw_footer(); 
	} else {
		$this->account_id=$phpgw_info["user"]["account_id"];
		$this->delete($ab_id);
		@Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"]. "/addressbook/","cd=16&order=$order&sort=$sort&filter=$filter&start=$start&query=$query"));
	}
?>
