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

	if ($submit || $AddVcard) {
		$phpgw_info["flags"] = array(
			"noheader" => True,
			"nonavbar" => True
		);
	}

	$phpgw_info["flags"]["currentapp"] = "addressbook";
	$phpgw_info["flags"]["enable_addressbook_class"] = True;
	include("../header.inc.php");

	$t = new Template($phpgw->common->get_tpl_dir("addressbook"));
	$t->set_file(array("add" => "add.tpl"));

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

	if ($AddVcard){
		Header("Location: " . $phpgw->link("/addressbook/vcardin.php"));
	} else if ($add_email) {
		list($fields["firstname"],$fields["lastname"]) = explode(" ", $name);
		$fields["email"] = $add_email;
		addressbook_form("","add.php","Add",$fields,'',$cat_id);
	} else if (! $submit && ! $add_email) {
		// Default
		addressbook_form("","add.php","Add","",$customfields,$cat_id);
	} elseif ($submit && $fields) {
		// This came from the view form, Copy entry
		$extrafields = array(
			"ophone"   => "ophone",
			"address2" => "address2",
			"address3" => "address3"
		);
		$qfields = $this->stock_contact_fields + $extrafields + $customfields;
		$addnew = unserialize(rawurldecode($fields));
		$addnew['note'] .= "\nCopied from ".$phpgw->accounts->id2name($addnew['owner']).", record #".$addnew['id'].".";
		$addnew['owner'] = $phpgw_info["user"]["account_id"];
		$addnew['id']    = '';

		if ($addnew['tid']) { addressbook_add_entry($addnew['owner'],$addnew,'','',$addnew['tid']); }
		else { addressbook_add_entry($addnew['owner'],$addnew); }

		$fields = addressbook_read_last_entry($qfields);
		$newid = $fields[0]['id'];
		Header("Location: "
			. $phpgw->link('/addressbook/edit.php',"&ab_id=$newid&order=$order&sort=$sort&filter=$filter&start=$start&query=$query&cat_id=$cat_id"));
	} else {
		if (! $bday_month && ! $bday_day && ! $bday_year) {
			$bday = "";
		} else {
			$bday = "$bday_month/$bday_day/$bday_year";
		}

		if ($url == "http://") {
			$url = "";
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
		$fields["title"]				= $title;
		$fields["tel_work"]				= $wphone;
		$fields["tel_home"]				= $hphone;
		$fields["tel_fax"]				= $fax;
		$fields["tel_pager"]			= $pager;
		$fields["tel_cell"]				= $mphone;
		$fields["tel_msg"]				= $msgphone;
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

		if ($access == True) {
			$fields["access"]           = 'private';
		} else {
			$fields["access"]           = 'public';
		}

		$fields["cat_id"]               = $ncat_id;

		addressbook_add_entry($phpgw_info["user"]["account_id"],$fields,$fields["access"],$fields["cat_id"]);
		$ab_id = addressbook_get_lastid();

		Header("Location: "
			. $phpgw->link("/addressbook/view.php","ab_id=$ab_id&order=$order&sort=$sort&filter=$filter&start=$start&cat_id=$cat_id"));
		$phpgw->common->phpgw_exit();
	}

	$t->set_var("lang_ok",lang("ok"));
	$t->set_var("lang_clear",lang("clear"));
	$t->set_var("lang_cancel",lang("cancel"));
	$t->set_var("cancel_url",$phpgw->link("/addressbook/index.php","sort=$sort&order=$order&filter=$filter&start=$start&cat_id=$cat_id"));
	$t->parse("out","add");
	$t->pparse("out","add");

	$phpgw->common->phpgw_footer();
?>
