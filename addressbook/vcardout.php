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

	if ($nolname || $nofname) {
		$phpgw_info["flags"] = array(
			"noheader" => False,
			"nonavbar" => False
		);
	} else {
		$phpgw_info["flags"] = array(
			"noheader" => True,
			"nonavbar" => True
		);
	}

	$phpgw_info["flags"]["enable_contacts_class"] = True;
	$phpgw_info["flags"]["currentapp"] = "addressbook";
	include("../header.inc.php");

	if (! $ab_id) {
		Header("Location: " . $phpgw->link("/addressbook/index.php"));
		$phpgw->common->phpgw_exit();
	}

	$this = CreateObject("phpgwapi.contacts");

 	$extrafields = array("address2" => "address2");
	$qfields = $this->stock_contact_fields + $extrafields;

	$fieldlist = addressbook_read_entry($ab_id,$qfields);
	$fields = $fieldlist[0];

	$emailtype    = $fields["email_type"]; if (!$emailtype) { $fields["email_type"] = 'INTERNET'; }
	$hemailtype   = $fields["email_home_type"]; if (!$hemailtype) { $fields["email_home_type"] = 'INTERNET'; }
	$firstname    = $fields["n_given"];
	$lastname     = $fields["n_family"];

	if(!$nolname && !$nofname) {
		/* First name and last must be in the vcard. */
		if($lastname == "") {
			/* Run away here. */
			Header("Location: " . $phpgw->link("/addressbook/vcardout.php",
				"nolname=1&ab_id=$ab_id&start=$start&order=$order&filter=$filter&query=$query&sort=$sort&cat_id=$cat_id"));
		}
		if($firstname == "" ) {
			Header("Location: " . $phpgw->link("/addressbook/vcardout.php",
				"nofname=1&ab_id=$ab_id&start=$start&order=$order&filter=$filter&query=$query&sort=$sort&cat_id=$cat_id"));
		}

		header("Content-type: text/x-vcard");
		$fn = explode("@",$email);
		$filename = sprintf("%s.vcf", $fn[0]);

		header("Content-Disposition: attachment; filename=$filename");

		// create vcard object
		$vcard = CreateObject("phpgwapi.vcard");
		// set translation variable
		$myexport = $vcard->export;
		// check that each $fields exists in the export array and
		// set a new array to equal the translation and original value
		while( list($name,$value) = each($fields) ) {
			if ($myexport[$name] && ($value != "") ) {
				//echo '<br>'.$name."=".$fields[$name]."\n";
				$buffer[$myexport[$name]] = $value;
			}
		}
		// create a vcard from this translated array
	    $entry = $vcard->out($buffer);
		// print it
		echo $entry;
		$phpgw->common->exit;
	} /* !nolname && !nofname */

	if($nofname) {
		echo "<BR><BR><CENTER>";
		echo lang("This person's first name was not in the address book.") ."<BR>";
		echo lang("Vcards require a first name entry.") . "<BR><BR>";
		echo "<a href=" . $phpgw->link("/addressbook/index.php",
			"order=$order&start=$start&filter=$filter&query=$query&sort=$sort&cat_id=$cat_id") . ">OK</a>";
		echo "</CENTER>";
	}

	if($nolname) {
		echo "<BR><BR><CENTER>";
		echo lang("This person's last name was not in the address book.") . "<BR>";
		echo lang("Vcards require a last name entry.") . "<BR><BR>";
		echo "<a href=" . $phpgw->link("/addressbook/index.php",
			"order=$order&start=$start&filter=$filter&query=$query&sort=$sort&cat_id=$cat_id") . ">OK</a>";
		echo "</CENTER>";
	}

	if($nolname || $nofname)
		$phpgw->common->phpgw_footer();
	/* End of php. */
?>
