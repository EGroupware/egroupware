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

	$phpgw_info["flags"] = array(
		"currentapp" => "addressbook",
		"enable_contacts_class" => True,
		"enable_nextmatchs_class" => True
	);

	include("../header.inc.php");

	$t = new Template($phpgw->common->get_tpl_dir("addressbook"));
	$t->set_file(array(
		"addressbook_header"	=> "header.tpl",
		"column"				=> "column.tpl",
		"row"					=> "row.tpl",
		"addressbook_footer"	=> "footer.tpl" ));

	$this = CreateObject("phpgwapi.contacts");
 	$extrafields = array(
		"pager"    => "pager",
		"mphone"   => "mphone",
		"ophone"   => "ophone",
		"address2" => "address2",
	);
	$qfields = $this->stock_contact_fields + $extrafields;

	// create column list and the top row of the table based on user prefs
	while ($column = each($qfields)) {
		if (isset($phpgw_info["user"]["preferences"]["addressbook"][$column[1]]) &&
			$phpgw_info["user"]["preferences"]["addressbook"][$column[1]]) {
			$showcol = display_name($column[0]);
			$cols .= "  <td height=\"21\">\n";
			$cols .= '    <font size="-1" face="Arial, Helvetica, sans-serif">';
			$cols .= $phpgw->nextmatchs->show_sort_order($sort, $column[0],$order,"/addressbook/index.php",$showcol);
			$cols .= "</font>\n  </td>";
			$cols .= "\n";

			// To be used when displaying the rows
			$columns_to_display[$column[0]] = True;
		}
	}

	if (! $start)
		$start = 0;

	if($phpgw_info["user"]["preferences"]["common"]["maxmatchs"] && $phpgw_info["user"]["preferences"]["common"]["maxmatchs"] > 0) {
		$offset = $phpgw_info["user"]["preferences"]["common"]["maxmatchs"];
	} else {
		$offset = 30;
	}

	// Set filter to display entries where tid is blank,
	//   else they may be accounts, etc.
	$savefilter = $filter;
	if ($filter == "none") {
		$filter  = 'tid=';
	} elseif($filter == "private") {
		$filter  = 'owner='.$phpgw_info["user"]["account_id"].',tid=';
	} else {
		$filter .= ',tid=';
	}

	// Check if prefs were set, if not, create some defaults
	if (!$columns_to_display ) {
		$columns_to_display = array(
			"n_given"  => "n_given",
			"n_family" => "n_family",
			"org_name" => "org_name"
		);
		// No prefs,. so cols above may have been set to "" or a bunch of <td></td>
		$cols="";
		while ($column = each($columns_to_display)) {
			$showcol = display_name($column[0]);
			$cols .= "  <td height=\"21\">\n";
			$cols .= '    <font size="-1" face="Arial, Helvetica, sans-serif">';
			$cols .= $phpgw->nextmatchs->show_sort_order($sort, $column[0],$order,"/addressbook/index.php",$showcol);
			$cols .= "</font>\n  </td>";
			$cols .= "\n";
		}
		$noprefs=lang("Please set your preferences for this app");
	}
	$qcols = $columns_to_display;
 
	// read the entry list
	if (!$userid) { $userid = $phpgw_info["user"]["account_id"]; }
	$entries = addressbook_read_entries($start,$offset,$qcols,$query,$filter,$sort,$order,$userid);
	// now that the query is done, reset filter, since nextmatchs grabs it globally
	$filter=$savefilter;

	$search_filter = $phpgw->nextmatchs->show_tpl("/addressbook/index.php",$start, $this->total_records,"&order=$order&filter=$filter&sort=$sort&query=$query","75%", $phpgw_info["theme"]["th_bg"]);

	if ($this->total_records > $phpgw_info["user"]["preferences"]["common"]["maxmatchs"]) {
		if ($start + $phpgw_info["user"]["preferences"]["common"]["maxmatchs"] > $this->total_records) {
			$end = $this->total_records;
		} else {
			$end = $start + $phpgw_info["user"]["preferences"]["common"]["maxmatchs"];
		}
		$lang_showing=lang("showing x - x of x",($start + 1),$end,$this->total_records);
	} else {
		$lang_showing=lang("showing x",$this->total_records);
	}

	// set basic vars and parse the header
	$t->set_var(font,$phpgw_info["theme"]["font"]);
	$t->set_var("lang_view",lang("View"));
	$t->set_var("lang_vcard",lang("VCard"));
	$t->set_var("lang_edit",lang("Edit"));
	$t->set_var("lang_owner",lang("Owner"));
	
	$t->set_var(searchreturn,$noprefs . " " . $searchreturn);
	$t->set_var(lang_showing,$lang_showing);
	$t->set_var(search_filter,$search_filter);
	$t->set_var("lang_addressbook",lang("Address book"));
	$t->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);
	$t->set_var("th_font",$phpgw_info["theme"]["font"]);
	$t->set_var("th_text",$phpgw_info["theme"]["th_text"]);
	$t->set_var("lang_add",lang("Add"));
	$t->set_var("lang_addvcard",lang("AddVCard"));
	$t->set_var("lang_import",lang("Import File"));
	$t->set_var("import_url",$phpgw->link("/addressbook/import.php"));
	$t->set_var("start",$start);
	$t->set_var("sort",$sort);
	$t->set_var("order",$order);
	$t->set_var("filter",$filter);
	$t->set_var("qfield",$qfield);
	$t->set_var("query",$query);
	$t->set_var("actionurl",$phpgw->link("/addressbook/add.php","sort=$sort&order=$order&filter=$filter&start=$start"));
	$t->set_var("start",$start);
	$t->set_var("filter",$filter);
	$t->set_var("cols",$cols);
	
	$t->pparse("out","addressbook_header");

	// Show the entries
	for ($i=0;$i<count($entries);$i++) { // each entry
		$t->set_var(columns,"");
		$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
		$t->set_var(row_tr_color,$tr_color);
		$myid    = $entries[$i]["id"];
		$myowner = $entries[$i]["owner"];

		while ($column = each($columns_to_display)) { // each entry column
			$ref=$data="";
			$coldata = $entries[$i][$column[0]];
			// Some fields require special formatting.       
			if ($column[0] == "url") {
				$ref='<a href="'.$coldata.'" target="_new">';
				$data=$coldata.'</a>';
			} elseif ($column[0] == "d_email") {
				if ($phpgw_info["user"]["apps"]["email"]) {
					$ref='<a href="'.$phpgw->link("/email/compose.php","to=" . urlencode($coldata)).'" target="_new">';
				} else {
					$ref='<a href="mailto:'.$coldata.'">';
				}
				$data=$coldata."</a>";
			} else { // But these do not
				$ref=""; $data=$coldata;
			}
			$t->set_var(col_data,$ref.$data);
			$t->parse("columns","column",True);
		}
    
		$t->set_var(row_view_link,$phpgw->link("/addressbook/view.php","ab_id=$myid&start=$start&order=$order&filter="
			. "$filter&query=$query&sort=$sort"));
		$t->set_var(row_vcard_link,$phpgw->link("/addressbook/vcardout.php","ab_id=$myid&start=$start&order=$order&filter="
			. "$filter&query=$query&sort=$sort"));
		$t->set_var(row_edit_link,$phpgw->common->check_owner($myowner,"/addressbook/edit.php",lang("edit"),"ab_id="
			.$myid."&start=".$start."&sort=".$sort."&order=".$order."&query=".$query."&sort=".$sort));
		$t->set_var(row_owner,$phpgw->accounts->id2name($myowner));
		
		$t->parse("rows","row",True);
		$t->pparse("out","row");
		reset($columns_to_display); // If we don't reset it, our inside while won't loop
	}

	$t->pparse("out","addressbook_footer");
	$phpgw->common->phpgw_footer();
?>
