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

	$phpgw_info['flags'] = array(
		'noheader'              => True,
		'nonavbar'              => True,
		'currentapp'            => 'addressbook',
		// is this really needed ?
		'enable_contacts_class' => True
	);

	include('../header.inc.php');

	$this = CreateObject('phpgwapi.contacts');

	// First, make sure they have permission to this entry
	$check = addressbook_read_entry($ab_id,array('owner' => 'owner'));

	if ( !$this->check_perms($this->grants[$check[0]['owner']],PHPGW_ACL_EDIT) && ($check[0]['owner'] != $phpgw_info['user']['account_id']) )
	{
		Header("Location: "
			. $phpgw->link('/addressbook/index.php',"cd=16&order=$order&sort=$sort&filter=$filter&start=$start&query=$query&cat_id=$cat_id"));
		$phpgw->common->phpgw_exit();
	}

	$t = new Template($phpgw->common->get_tpl_dir("addressbook"));
	$t->set_file(array("edit"	=> "edit.tpl"));

	if (! $ab_id) {
		Header("Location: "
			. $phpgw->link('/addressbook/index.php',"cd=16&order=$order&sort=$sort&filter=$filter&start=$start&query=$query&cat_id=$cat_id"));
		$phpgw->common->phpgw_exit();
	}

	if (! $submit)
	{
		$phpgw->common->phpgw_header();
		echo parse_navbar();
	}

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

		$qfields = $this->stock_contact_fields + $extrafields + $customfields;
		$fields = addressbook_read_entry($ab_id,$qfields);
		addressbook_form("","edit.php","Edit",$fields[0],$customfields);
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

		if ($access == True || $access == "private") {
			$fields["access"]           = 'private';
		} else {
			$fields["access"]           = 'public';
		}

		$fields["cat_id"]               = $cat_id;

		if (($this->grants[$check[0]['owner']] & PHPGW_ACL_EDIT) && $check[0]['owner'] != $phpgw_info['user']['account_id'])
		{
			$userid = $check[0]['owner'];
		}
		else
		{
			$userid = $phpgw_info["user"]["account_id"];
		}

		addressbook_update_entry($ab_id,$userid,$fields,$fields['access'],$fields["cat_id"]);

		Header("Location: "
			. $phpgw->link("/addressbook/view.php","ab_id=$ab_id&order=$order&sort=$sort&filter=$filter&start=$start&query=$query&cat_id=$cat_id"));
		$phpgw->common->phpgw_exit();
	}

	$t->set_var("ab_id",$ab_id);
	$t->set_var("sort",$sort);
	$t->set_var("order",$order);
	$t->set_var("filter",$filter);
	$t->set_var("query",$query);
	$t->set_var("start",$start);
	$t->set_var("cat_id",$cat_id);
	$t->set_var("lang_ok",lang("ok"));
	$t->set_var("lang_clear",lang("clear"));
	$t->set_var("lang_cancel",lang("cancel"));
	$t->set_var("lang_submit",lang("submit"));
	$t->set_var("cancel_link",'<form method="POST" action="' . $phpgw->link("/addressbook/index.php") . '">');

	if (($this->grants[$check[0]['owner']] & PHPGW_ACL_DELETE) || $check[0]['owner'] == $phpgw_info['user']['account_id'])
	{
		$t->set_var('delete_link','<form method="POST" action="'.$phpgw->link("/addressbook/delete.php") . '">');
		$t->set_var('delete_button','<input type="submit" name="delete" value="' . lang('Delete') . '">');
	}

	$t->pfp("out","edit");
	
	$phpgw->common->phpgw_footer();
?>
