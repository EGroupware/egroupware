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
		$phpgw_info["flags"] = array("noheader" => False, "nonavbar" => False);
	} else {
		$phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);
	}

	$phpgw_info["flags"]["enable_addressbook_class"] = True;
	$phpgw_info["flags"]["currentapp"] = "addressbook";
	include("../header.inc.php");
	
	if (! $ab_id) {
		Header("Location: " . $phpgw->link("index.php"));
		$phpgw->common->phpgw_exit();
	}

	$this = CreateObject("phpgwapi.contacts");

	if ($filter != "private")
		//$filtermethod = " or ab_access='public' " . $phpgw->accounts->sql_search("ab_access");
	
		$fields = addressbook_read_entry($ab_id,$this->stock_contact_fields);
		
		$rights = $phpgw->acl->get_rights($fields[0]["owner"],$phpgw_info["flags"]["currentapp"]);
		if ( ($rights & PHPGW_ACL_READ) || ($fields[0]["owner"] == $phpgw_info["user"]["account_id"]) ) {
	
			$email        = $fields[0]["d_email"];
			$fullname     = $fields[0]["fn"];
			$prefix       = $fields[0]["n_prefix"];
			$firstname    = $fields[0]["n_given"];
			$middle       = $fields[0]["n_middle"];
			$lastname     = $fields[0]["n_family"];
			$suffix       = $fields[0]["n_suffix"];
			$title        = $fields[0]["title"];
			$hphone       = $fields[0]["a_tel"];
			$wphone       = $fields[0]["b_tel"];
			$fax          = $fields[0]["c_tel"];
			$pager        = $fields[0]["pager"];
			$mphone       = $fields[0]["mphone"];
			$ophone       = $fields[0]["ophone"];
			$street       = $fields[0]["adr_street"];
			$address2     = $fields[0]["address2"];
			$city         = $fields[0]["adr_locality"];
			$state        = $fields[0]["adr_region"];
			$zip          = $fields[0]["adr_postalcode"];
			$country      = $fields[0]["adr_countryname"];
			$company      = $fields[0]["org_name"];
			$dept         = $fields[0]["org_unit"];
			$bday         = $fields[0]["bday"];
			$notes        = $fields[0]["notes"];
			$access       = $fields[0]["access"];
			$url          = $fields[0]["url"];

			if(!$nolname && !$nofname) {
				/* First name and last must be in the vcard. */
				if($lastname == "") {
					/* Run away here. */
					Header("Location: " . $phpgw->link("vcardout.php","nolname=1&ab_id=$ab_id&start=$start&order=$order&filter=" . "$filter&query=$query&sort=$sort"));
				}
				if($firstname == "" ) {
					Header("Location: " . $phpgw->link("vcardout.php","nofname=1&ab_id=$ab_id&start=$start&order=$order&filter=" . "$filter&query=$query&sort=$sort"));
				}

				header("Content-type: text/X-VCARD");
				$fn = explode("@",$email);
				$filename = sprintf("%s.vcf", $fn[0]);

				header("Content-Disposition: attachment; filename=$filename");

				printf("BEGIN:VCARD\r\n");
				printf("N:%s;%s\r\n", $lastname, $firstname);
				if (!$fullname) { printf("FN:%s %s\r\n", $firstname, $lastname); }
				else            { printf("FN:%s\r\n", $fullname); }

				/* This stuff is optional. */
				if($title != "") /* Title */
					printf("TITLE:%s\r\n",$title);
				if($email != "") /* E-mail */
					printf("EMAIL;INTERNET:%s\r\n", $email);
				if($hphone != "") /* Home Phone */
					printf("TEL;HOME:%s\r\n", $hphone);
				if($wphone != "") /* Work Phone */
					printf("TEL;WORK:%s\r\n", $wphone);
				if($mphone != "") /* Mobile Phone */
					printf("TEL;CELL:%s\r\n", $mphone);
				if($fax != "") /* Fax Number */
					printf("TEL;FAX:%s\r\n", $fax);
				if($pager != "") /* Pager Number */
					printf("TEL;PAGER:%s\r\n", $pager);
				//if($ophone != "") /* Other Phone */
				//$NOTES .= "Other Phone: " .  $ophone;
				/* The address one is pretty icky. Send it if ANY of the fields are present. */
				if($address2 != "" || /* Street Line 1 */
					$street != "" || /* Street Line 2 */
					$city != "" || /* City */
					$state != "" || /* State */
					$zip != "")     /* Zip */
					printf("ADR:;%s;%s;%s;%s;%s;%s\r\n", $address2,
					$street,$city,$state,$zip,$country);

				if($bday != "" && $bday != "//") /* Birthday */
					printf("BDAY:%s\r\n", $bday); /* This is not the right format. */
				if($company != "") /* Company Name (Really isn't company_name?) */
					printf("ORG:%s %s\r\n", $company, $dept);
				if($notes != "") /* Notes */
					$NOTES .= $notes;

				if($NOTES != "") /* All of the notes. */
					printf("NOTE:%s\r\n", $NOTES);
				/* End of Stuff. */
				printf("VERSION:2.1\r\n");
				printf("END:VCARD\r\n");
			} /* !nolname && !nofname */
	} else { /* acl check failed */
		Header("Location: " . $phpgw->link("vcardout.php","nofname=1&ab_id=$ab_id&start=$start&order=$order&filter=" . "$filter&query=$query&sort=$sort"));
	}

	if($nofname) {
		echo "<BR><BR><CENTER>";
		echo lang("This person's first name was not in the address book.") ."<BR>";
		echo lang("Vcards require a first name entry.") . "<BR><BR>";
		echo "<a href=" . $phpgw->link("index.php","order=$order&start=$start&filter=$filter&query=$query&sort=$sort") . ">OK</a>";
		echo "</CENTER>";
	}

	if($nolname) {
		echo "<BR><BR><CENTER>";
		echo lang("This person's last name was not in the address book.") . "<BR>";
		echo lang("Vcards require a last name entry.") . "<BR><BR>";
		echo "<a href=" . $phpgw->link("index.php","order=$order&start=$start&filter=$filter&query=$query&sort=$sort") . ">OK</a>";
		echo "</CENTER>";
	}

	if($nolname || $nofname)
		$phpgw->common->phpgw_footer();
	/* End of php. */
?>
