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

	if ($submit || ! $ab_id) {
		$phpgw_info["flags"] = array(
			"noheader" => True,
			"nonavbar" => True
		);
	}

	$phpgw_info["flags"] = array(
		"currentapp" => "addressbook",
		"enable_contacts_class" => True,
		"enable_nextmatchs_class" => True);

	include("../header.inc.php");

	$t = new Template($phpgw->common->get_tpl_dir("addressbook"));
	$t->set_file(array( "view"	=> "view.tpl"));

	$this = CreateObject("phpgwapi.contacts");

	if (! $ab_id) {
		Header("Location: " . $phpgw->link("/addressbook/index.php"));
	}

	while ($column = each($this->stock_contact_fields)) {
		if (isset($phpgw_info["user"]["preferences"]["addressbook"][$column[0]]) &&
			$phpgw_info["user"]["preferences"]["addressbook"][$column[0]]) {
			$columns_to_display[$column[0]] = True;
			$colname[$column[0]] = $column[1];
		}
	}

	// No prefs?
	if (!$columns_to_display ) {
		$columns_to_display = array(
			"n_given"  => "n_given",
			"n_family" => "n_family",
			"org_name" => "org_name"
		);
		while ($column = each($columns_to_display)) {
			$colname[$column[0]] = $column[1];
		}
		$noprefs=  " - " . lang("Please set your preferences for this app");
	}

	// merge in extra fields
 	$extrafields = array(
		"ophone"   => "ophone",
		"address2" => "address2",
		"address3" => "address3"
	);
	$qfields = $this->stock_contact_fields + $extrafields;

	$fields  = addressbook_read_entry($ab_id,$qfields);

	$record_owner  = $fields[0]["owner"];

	$view_header  = "<p>&nbsp;<b>" . lang("Address book - view") . $noprefs . "</b><hr><p>";
	$view_header .= '<table border="0" cellspacing="2" cellpadding="2" width="80%" align="center">';

	reset($columns_to_display);
	while ($column = each($columns_to_display)) { // each entry column
		$columns_html .= "<tr><td><b>" . display_name($colname[$column[0]]) . "</b>:</td>";
		$ref=$data="";
		$coldata = $fields[0][$column[0]];
		// Some fields require special formatting.       
		if ($column[0] == "url") {
			$ref='<a href="'.$coldata.'" target="_new">';
			$data=$coldata.'</a>';
		} elseif (($column[0] == "email") || ($column[0] == "email_home")) {
			if ($phpgw_info["user"]["apps"]["email"]) {
			$ref='<a href="'.$phpgw->link("/email/compose.php","to=" . urlencode($coldata)).'" target="_new">';
			} else {
				$ref='<a href="mailto:'.$coldata.'">';
			}
			$data=$coldata."</a>";
		} else { // But these do not
			$ref=""; $data=$coldata;
		}
		$columns_html .= "<td>" . $ref . $data . "</td>";
	}

	$columns_html .= '<tr><td colspan="4">&nbsp;</td></tr>'
		. '<tr><td><b>' . lang("Record owner") . '</b></td><td>'
		. $phpgw->common->grab_owner_name($record_owner) . '</td><td><b>' 
		. $access_link . '</b></td><td></table>';

	$sfields = rawurlencode(serialize($fields[0]));

	if ($rights & PHPGW_ACL_EDIT) {
		$editlink = '<form method="POST" action="'.$phpgw->link("/addressbook/edit.php","ab_id=$ab_id&start=$start&sort=$sort&order=$order"
			. "&query=$query&sort=$sort").'">';
	} else {
		$editlink = '';
	}

	$copylink  = '<form method="POST" action="'.$phpgw->link("/addressbook/add.php","order=$order&start=$start&filter=$filter&query=$query&sort=$sort").'">';
	$vcardlink = '<form method="POST" action="'.$phpgw->link("/addressbook/vcardout.php","ab_id=$ab_id&order=$order&start=$start&filter=$filter&query=$query&sort=$sort").'">';
	$donelink  = '<form method="POST" action="'.$phpgw->link("/addressbook/index.php","order=$order&start=$start&filter=$filter&query=$query&sort=$sort").'">';

	$t->set_var("access_link",$access_link);
	$t->set_var("ab_id",$ab_id);
	$t->set_var("sort",$sort);
	$t->set_var("order",$order);
	$t->set_var("filter",$filter);
	$t->set_var("start",$start);
	$t->set_var("view_header",$view_header);
	$t->set_var("cols",$columns_html);
	$t->set_var("lang_ok",lang("ok"));
	$t->set_var("lang_done",lang("done"));
	$t->set_var("lang_edit",lang("edit"));
	$t->set_var("lang_copy",lang("copy"));
	$t->set_var("copy_fields",$sfields);
	$t->set_var("lang_submit",lang("submit"));
	$t->set_var("lang_vcard",lang("vcard"));
	$t->set_var("done_link",$donelink);
	$t->set_var("edit_link",$editlink);
	$t->set_var("copy_link",$copylink);
	$t->set_var("vcard_link",$vcardlink);

	$t->parse("out","view");
	$t->pparse("out","view");

	$phpgw->common->phpgw_footer();
?>
