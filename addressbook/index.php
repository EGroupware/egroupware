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

	$phpgw_info["flags"] = array("currentapp" =>
								"addressbook","enable_contacts_class" => True,
								"enable_nextmatchs_class" => True);
	include("../header.inc.php");

	#$t = new Template($phpgw_info["server"]["app_tpl"]);
	$t = new Template($phpgw->common->get_tpl_dir("addressbook"));
	$t->set_file(array( "addressbook_header"	=> "header.tpl",
						"column"				=> "column.tpl",
						"row"					=> "row.tpl",
						"addressbook_footer"	=> "footer.tpl" ));

	$this = CreateObject("phpgwapi.contacts");

	while ($column = each($this->stock_contact_fields)) {
		if (isset($phpgw_info["user"]["preferences"]["addressbook"][$column[1]]) &&
			$phpgw_info["user"]["preferences"]["addressbook"][$column[1]]) {
			$showcol = display_name($column[0]);
			$cols .= "  <td height=\"21\">\n";
			$cols .= '    <font size="-1" face="Arial, Helvetica, sans-serif">';
			$cols .= $phpgw->nextmatchs->show_sort_order($sort, $column[0],$order,"index.php",lang($showcol));
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

	// insert acl stuff here in lieu of old access perms
	// following sets up the filter for read, then restores the filter string for later checking
	if ($filter == "none") { $filter = ""; }
	$savefilter = $filter;
	if ($filter != "" ) { $filter = "access=$filter"; }
	
	$qfilter = $filter;
	$filter = $savefilter;
	
	if (!$columns_to_display ) {
		$columns_to_display = array("n_given","n_family","org_name");
		$noprefs=lang("Please set your preferences for this app");
	}
	$qcols = $columns_to_display + array("access");
  
	// read the entry list
	$entries = $this->read($start,$offset,$qcols,$query,$qfilter,$sort,$order);

	$search_filter = $phpgw->nextmatchs->show_tpl("index.php",$start, $this->total_records,"&order=$order&filter=$filter&sort=$sort&query=$query","75%", $phpgw_info["theme"]["th_bg"]);

	if ($this->total_records > $phpgw_info["user"]["preferences"]["common"]["maxmatchs"]) {
		$lang_showing=lang("showing x - x of x",($start + 1),($start + $phpgw_info["user"]["preferences"]["common"]["maxmatchs"]),$this->total_records);
	} else {
		$lang_showing=lang("showing x",$this->total_records);
	}

	// set basic vars and parse the header
	$t->set_var(font,$phpgw_info["theme"]["font"]);
	$t->set_var("lang_view",lang("View"));
	$t->set_var("lang_vcard",lang("VCard"));
	$t->set_var("lang_edit",lang("Edit"));
	
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
	$t->set_var("import_url",$phpgw->link("import.php"));
	$t->set_var("start",$start);
	$t->set_var("sort",$sort);
	$t->set_var("order",$order);
	$t->set_var("filter",$filter);
	$t->set_var("qfield",$qfield);
	$t->set_var("query",$query);
	$t->set_var("actionurl",$phpgw->link("add.php","sort=$sort&order=$order&filter=$filter&start=$start"));
	$t->set_var("start",$start);
	$t->set_var("filter",$filter);
	$t->set_var("cols",$cols);
	
	$t->pparse("out","addressbook_header");
	
	// Show the entries
	for ($i=0;$i<count($entries);$i++) { // each entry
		if ( ($entries[$i]["access"] == $filter) ||
			($entries[$i]["access"] == "," . $filter . ",") ||
			($filter == "") || ($filter == "none")) {
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
				} elseif ($column[0] == "email") {
					if ($phpgw_info["user"]["apps"]["email"]) {
						$ref='<a href="'.$phpgw->link($phpgw_info["server"]["webserver_url"] . "/email/compose.php","to=" . urlencode($coldata)).'" target="_new">';
					} else {
						//changed frmo a patch posted on sf, have not fully tested. Seek3r, Jan 30 2001
						// $ref='<a href="mailto:"'.$coldata.'">'.$coldata.'</a>';
						$ref='<a href="mailto:'.$coldata.'">';
					}
					$data=$coldata."</a>";
    	    	} else { // But these do not
					$ref=""; $data=$coldata;
				}
				$t->set_var(col_data,$ref.$data);
				$t->parse("columns","column",True);
			}
    
		$t->set_var(row_view_link,$phpgw->link("view.php","ab_id=$myid&start=$start&order=$order&filter="
			. "$filter&query=$query&sort=$sort"));
		$t->set_var(row_vcard_link,$phpgw->link("vcardout.php","ab_id=$myid&start=$start&order=$order&filter="
			. "$filter&query=$query&sort=$sort"));
		$t->set_var(row_edit_link,$phpgw->common->check_owner($myowner,"edit.php",lang("edit"),"ab_id="
			.$myid."&start=".$start."&sort=".$sort."&order=".$order."&query=".$query."&sort=".$sort));
		
		$t->parse("rows","row",True);
		$t->pparse("out","row");
		reset($columns_to_display); // If we don't reset it, our inside while won't loop
		}
	}

	$t->pparse("out","addressbook_footer");
	$phpgw->common->phpgw_footer();
?>
