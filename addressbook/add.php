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
		$phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);
	}
	
	$phpgw_info["flags"]["currentapp"] = "addressbook";
	$phpgw_info["flags"]["enable_addressbook_class"] = True;
	include("../header.inc.php");
	
	#$t = new Template($phpgw_info["server"]["app_tpl"]);
	$t = new Template($phpgw->common->get_tpl_dir("addressbook"));
	$t->set_file(array("add" => "add.tpl"));
	
	$this = CreateObject("phpgwapi.contacts");
	
	if ($AddVcard){
		Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] .
			"/addressbook/vcardin.php"));
	} else if ($add_email) {
		list($fields["firstname"],$fields["lastname"]) = explode(" ", $name);
		$fields["email"] = $add_email;
		form("","add.php","Add",$fields);
	} else if (! $submit && ! $add_email) {
		form("","add.php","Add","","","");
	} else {
		if (! $bday_month && ! $bday_day && ! $bday_year) {
			$bday = "";
		} else {
			$bday = "$bday_month/$bday_day/$bday_year";
		}
		if ($access != "private" && $access != "public") {
			$access = $phpgw->accounts->array_to_string($access,$n_groups);
		}
		if ($url == "http://") {
			$url = "";
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
		$fields["adr_Countryname"]	= $country;
		$fields["tz"]				= $timezone;
		$fields["bday"]				= $bday;
		$fields["url"]				= $url;
		$fields["notes"]			= $notes;
		$fields["access"]			= $access;
	
		$this->add($phpgw_info["user"]["account_id"],$fields);
		
		Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"]."/addressbook/","cd=14"));
	}

	$t->set_var("lang_ok",lang("ok"));
	$t->set_var("lang_clear",lang("clear"));
	$t->set_var("lang_cancel",lang("cancel"));
	$t->set_var("cancel_url",$phpgw->link("index.php?sort=$sort&order=$order&filter=$filter&start=$start"));
	$t->parse("out","add");
	$t->pparse("out","add");
	
	$phpgw->common->phpgw_footer();
?>
