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
		Header("Location: " . $phpgw->link('/addressbook/index.php',"cd=16&order=$order&sort=$sort&filter=$filter&start=$start&query=$query"));
		$phpgw->common->phpgw_exit();
	}

	$this = CreateObject("phpgwapi.contacts");

	// Read in user custom fields, if any
	$phpgw->preferences->read_repository();
	$customfields = array();
	while (list($col,$descr) = @each($phpgw_info["user"]["preferences"]["addressbook"])) {
		if ( substr($col,0,6) == 'extra_' ) {
			$field = ereg_replace('extra_','',$col);
			$field = ereg_replace(' ','_',$field);
			$customfields[$field] = ucfirst($field);
		}
	}

	if (!$submit) {
		// merge in extra fields
		$extrafields = array(
			"ophone" => "ophone",
			"address2" => "address2",
			"address3" => "address3"
		);
		if ($rights & PHPGW_ACL_EDIT) {
			$qfields = $this->stock_contact_fields + $extrafields + $customfields;
			$fields = addressbook_read_entry($ab_id,$qfields);
			addressbook_form("","edit.php","Edit",$fields[0],$customfields);
		} else {
			Header("Location: " . $phpgw->link('/addressbook/index.php',"cd=16&order=$order&sort=$sort&filter=$filter&start=$start&query=$query"));
			$phpgw->common->phpgw_exit();
		}
	} else {
		if ($url == "http://") {
			$url = "";
		}
		if (! $bday_month && ! $bday_day && ! $bday_year) {
			$bday = "";
		} else {
			$bday = "$bday_month/$bday_day/$bday_year";
		}
	
		$fields["org_name"]				= $company;
		$fields["org_unit"]				= $department;
		$fields["n_given"]				= $firstname;
		$fields["n_family"]				= $lastname;
		$fields["n_middle"]				= $middle;
		$fields["n_prefix"]				= $prefix;
		$fields["n_suffix"]				= $suffix;
		if ($prefix) { $pspc = " "; }
		if ($middle) { $mspc = " "; } else { $nspc = " "; }
		if ($suffix) { $sspc = " "; }
		$fields["fn"]					= $prefix.$pspc.$firstname.$nspc.$mspc.$middle.$mspc.$lastname.$sspc.$suffix;
		$fields["email"]				= $email;
		$fields["email_type"]			= $email_type;
		$fields["email_home"]			= $hemail;
		$fields["email_home_type"]		= $hemail_type;

		$fields["title"]				= $title;
		$fields["tel_work"]				= $wphone;
		$fields["tel_home"]				= $hphone;
		$fields["tel_fax"]				= $fax;
		$fields["tel_pager"]			= $pager;
		$fields["tel_cell"]				= $mphone;
		$fields["tel_msg"]				= $msgphone;
		$fields["tel_prefer"]           = $tel_prefer;

		$fields["adr_one_street"]		= $bstreet;
		$fields["adr_one_locality"]		= $bcity;
		$fields["adr_one_region"]		= $bstate;
		$fields["adr_one_postalcode"]	= $bzip;
		$fields["adr_one_countryname"]	= $bcountry;

		reset($this->adr_types);
		$typed = '';
		while (list($type,$val) = each($this->adr_types)) {
			$ftype = 'one_'.$type;
			eval("if (\$\$ftype=='on'\) { \$typed \.= \$type\.';'; }");
		}	
		$fields["adr_one_type"]     = substr($typed,0,-1);

		$fields["address2"]				= $address2;
		$fields["address3"]				= $address3;

		$fields["adr_two_street"]		= $hstreet;
		$fields["adr_two_locality"]		= $hcity;
		$fields["adr_two_region"]		= $hstate;
		$fields["adr_two_postalcode"]	= $hzip;
		$fields["adr_two_countryname"]	= $hcountry;

		reset($this->adr_types);
		$typed = '';
		while (list($type,$val) = each($this->adr_types)) {
			$ftype = 'two_'.$type;
			eval("if \(\$\$ftype=='on'\) { \$typed \.= \$type\.';'; }");
		}
		$fields["adr_two_type"]         = substr($typed,0,-1);

		reset($customfields);
		while (list($name,$val) = each($customfields)) {
			$cust = '';
			eval("if (\$name\) { \$cust \.= \$\$name; }");
			if ($cust) { $fields[$name] = $cust; }
		}

		$fields["ophone"]               = $ophone;
		$fields["tz"]					= $timezone;
		$fields["bday"]					= $bday;
		$fields["url"]					= $url;
		$fields["pubkey"]				= $pubkey;
		$fields["note"]					= $notes;
		$fields["label"]                = $label;

		$userid = $phpgw_info["user"]["account_id"];

		addressbook_update_entry($ab_id,$userid,$fields);

		Header("Location: " . $phpgw->link("/addressbook/view.php","ab_id=$ab_id&order=$order&sort=$sort&filter=$filter&start=$start"));
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
	$t->set_var("cancel_link",'<form method="POST" action="'.$phpgw->link("/addressbook/index.php","sort=$sort&order=$order&filter=$filter&start=$start") . '">');
	$t->set_var("delete_link",'<form method="POST" action="'.$phpgw->link("/addressbook/delete.php","ab_id=$ab_id") . '">');
	
	$t->parse("out","edit");
	$t->pparse("out","edit");
	
	$phpgw->common->phpgw_footer();
?>
