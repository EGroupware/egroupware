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

	$phpgw_info["flags"]["currentapp"] = "addressbook";
	$phpgw_info["flags"]["enable_contacts_class"] = True;
	include("../header.inc.php");

	$t = new Template($phpgw->common->get_tpl_dir("addressbook"));
	$t->set_file(array( "edit"	=> "edit.tpl"));

	if (! $ab_id) {
		Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"]. "/addressbook/","cd=16&order=$order&sort=$sort&filter=$filter&start=$start&query=$query"));
		$phpgw->common->phpgw_exit();
	}

	$this = CreateObject("phpgwapi.contacts");

	if (!$submit) {
		// not checking acl here, only on submit - that ok?
		// merge in extra fields
		$extrafields = array(
			"pager" => "pager",
			"mphone" => "mphone",
			"ophone" => "ophone",
			"address2" => "address2",
		);
		$qfields = $this->stock_contact_fields + $extrafields;
		$fields = addressbook_read_entry($ab_id,$qfields);
		addressbook_form("","edit.php","Edit",$fields[0]);
	} else {
		if ($url == "http://") {
			$url = "";
		}
		if (! $bday_month && ! $bday_day && ! $bday_year) {
			$bday = "";
		} else {
			$bday = "$bday_month/$bday_day/$bday_year";
		}
	
		$fields["org_name"]			= $company;
		$fields["org_unit"]			= $department;
		$fields["n_given"]			= $firstname;
		$fields["n_family"]			= $lastname;
		$fields["n_middle"]			= $middle;
		$fields["n_prefix"]			= $prefix;
		$fields["n_suffix"]			= $suffix;
		if ($prefix) { $pspc = " "; }
		if ($middle) { $mspc = " "; }
		if ($suffix) { $sspc = " "; }
		$fields["fn"]				= $prefix.$pspc.$firstname.$mspc.$middle.$mspc.$lastname.$sspc.$suffix;
		$fields["d_email"]			= $email;
		$fields["d_emailtype"]		= $email_type;
		$fields["title"]			= $title;
		$fields["a_tel"]			= $wphone;
		$fields["b_tel"]			= $hphone;
		$fields["c_tel"]			= $fax;
		$fields["pager"]			= $pager;
		$fields["mphone"]			= $mphone;
		$fields["ophone"]			= $ophone;
		$fields["adr_street"]		= $street;
		$fields["address2"]			= $address2;
		$fields["adr_locality"]		= $city;
		$fields["adr_region"]		= $state;
		$fields["adr_postalcode"]	= $zip;
		$fields["adr_countryname"]	= $country;
		$fields["tz"]				= $timezone;
		$fields["bday"]				= $bday;
		$fields["url"]				= $url;
		$fields["note"]				= $notes;

		// this is now in functions.inc.php and will handle acl soon
		//if (!$userid) { 
			$userid = $phpgw_info["user"]["account_id"];
		//}
		addressbook_update_entry($ab_id,$userid,$fields);

		Header("Location: " . $phpgw->link("/addressbook/view.php","&ab_id=$ab_id&order=$order&sort=$sort&filter=$filter&start=$start"));
		$phpgw->common->phpgw_exit();
	}

	$t->set_var("ab_id",$ab_id);
	$t->set_var("sort",$sort);
	$t->set_var("order",$order);
	$t->set_var("filter",$filter);
	$t->set_var("start",$start);
	$t->set_var("lang_ok",lang("ok"));
	$t->set_var("lang_clear",lang("clear"));
	$t->set_var("lang_cancel",lang("cancel"));
	$t->set_var("lang_delete",lang("delete"));
	$t->set_var("lang_submit",lang("submit"));
	$t->set_var("cancel_link",'<form action="'.$phpgw->link("/addressbook/index.php","sort=$sort&order=$order&filter=$filter&start=$start") . '">');
	$t->set_var("delete_link",'<form action="'.$phpgw->link("/addressbook/delete.php","ab_id=$ab_id") . '">');
	
	$t->parse("out","edit");
	$t->pparse("out","edit");
	
	$phpgw->common->phpgw_footer();
?>
