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

 	$extrafields = array(
		"ophone"   => "ophone",
		"address2" => "address2",
		"address3" => "address3"
	);
	$qfields = $this->stock_contact_fields + $extrafields;

	$fieldlist = addressbook_read_entry($ab_id,$qfields);
	$fields = $fieldlist[0];

	$email        = $fields["email"];
	$emailtype    = $fields["email_type"]; if (!$emailtype) { $emailtype = 'INTERNET'; }
	$hemail       = $fields["email_home"]; if (!$hemail) { $hemail = 'none'; }
	$hemailtype   = $fields["email_home_type"]; if (!$hemailtype) { $hemailtype = 'INTERNET'; }
	$fullname     = $fields["fn"];
	$prefix       = $fields["n_prefix"];
	$firstname    = $fields["n_given"];
	$middle       = $fields["n_middle"];
	$lastname     = $fields["n_family"];
	$suffix       = $fields["n_suffix"];
	$title        = $fields["title"];
	$aphone       = $fields["tel_work"];
	$bphone       = $fields["tel_home"];
	$afax         = $fields["tel_fax"];
	$apager       = $fields["tel_pager"];
	$amphone      = $fields["tel_cell"];
	$aisdnphone   = $fields["tel_isdn"];
	$acarphone    = $fields["tel_car"];
	$avidphone    = $fields["tel_video"];
	$amsgphone    = $fields["tel_msg"];
	$abbsphone    = $fields["tel_bbs"];
	$amodem       = $fields["tel_modem"];
	$preferred    = $fields["tel_prefer"];
	$aophone      = $fields["ophone"];

	// Setup array for display of preferred phone number below
	while (list($name,$val) = each($this->tel_types)) {
		if ($name == $preferred) {
			$pref[$name] .= ';PREF';
		}
	}

	$aophone      = $fields["ophone"];
	$astreet      = $fields["adr_one_street"];
	$address2     = $fields["address2"];
	$acity        = $fields["adr_one_locality"];
	$astate       = $fields["adr_one_region"];
	$azip         = $fields["adr_one_postalcode"];
	$acountry     = $fields["adr_one_countryname"];
	$atype        = $fields["adr_one_type"]; if (!empty($atype)) { $atype = ';'.$atype; }
	$label        = $fields["label"];

	$bstreet      = $fields["adr_two_street"];
	$bcity        = $fields["adr_two_locality"];
	$bstate       = $fields["adr_two_region"];
	$bzip         = $fields["adr_two_postalcode"];
	$bcountry     = $fields["adr_two_countryname"];
	$btype        = $fields["adr_two_type"]; if (!empty($btype)) { $btype = ';'.$btype; }

	$company      = $fields["org_name"];
	$dept         = $fields["org_unit"];
	$bday         = $fields["bday"];
	$notes        = ereg_replace("\r\n","=0A",$fields["note"]);
	$access       = $fields["access"];
	$url          = $fields["url"];

	if(!$nolname && !$nofname) {
		/* First name and last must be in the vcard. */
		if($lastname == "") {
			/* Run away here. */
			Header("Location: " . $phpgw->link("/addressbook/vcardout.php","nolname=1&ab_id=$ab_id&start=$start&order=$order&filter=" . "$filter&query=$query&sort=$sort"));
		}
		if($firstname == "" ) {
			Header("Location: " . $phpgw->link("/addressbook/vcardout.php","nofname=1&ab_id=$ab_id&start=$start&order=$order&filter=" . "$filter&query=$query&sort=$sort"));
		}

		header("Content-type: text/x-vcard");
		$fn = explode("@",$email);
		$filename = sprintf("%s.vcf", $fn[0]);

		header("Content-Disposition: attachment; filename=$filename");

		printf("BEGIN:VCARD\r\n");
		printf("X-PHPGROUPWARE-FILE-AS:phpGroupWare.org\r\n");
		printf("N:%s;%s\r\n", $lastname, $firstname);
		if (!$fullname) { printf("FN:%s %s\r\n", $firstname, $lastname); }
		else            { printf("FN:%s\r\n", $fullname); }

		
		if($title != "") /* Title */
			printf("TITLE:%s\r\n",$title);

		// 'A' grouping - work stuff
		if($email != "") /* E-mail */
			printf("A.EMAIL;%s:%s\r\n", $emailtype,$email);

		if($aphone != "")     printf("A.TEL%s;WORK:%s\r\n",  $pref['work'],  $aphone);
		if($amphone != "")    printf("A.TEL%s;CELL:%s\r\n",  $pref['cell'],  $amphone);
		if($afax != "")       printf("A.TEL%s;FAX:%s\r\n",   $pref['fax'],   $afax);
		if($apager != "")     printf("A.TEL%s;PAGER:%s\r\n", $pref['pager'], $apager);
		if($amsgphone != "")  printf("A.TEL%s;MSG:%s\r\n",   $pref['msg'],   $amsgphone);
		if($acarphone != "")  printf("A.TEL%s;CAR:%s\r\n",   $pref['car'],   $acarphone);
		if($abbs != "")       printf("A.TEL%s;BBS:%s\r\n",   $pref['fax'],   $afax);
		if($amodem != "")     printf("A.TEL%s;MODEM:%s\r\n", $pref['modem'], $amodem);
		if($aisdnphone != "") printf("A.TEL%s;ISDN:%s\r\n",  $pref['isdn'],  $aisdnphone);
		if($avidphone != "")  printf("A.TEL%s;VIDEO:%s\r\n", $pref['video'], $avidphone);

		if($ophone != "") $NOTES .= "Other Phone: " .  $ophone . "\r\n";

		if($astreet != "" || /* Business Street Line 1 */
			$address2 != "" || /* Business Street Line 2 */
			$acity != "" || /* Business City */
			$astate != "" || /* Business State */
			$azip != "") {    /* Business Zip */
			printf("A.ADR%s;WORK:;%s;%s;%s;%s;%s;%s\r\n", $atype,$address2,
				$astreet,$acity,$astate,$azip,$acountry);
		}
		if ($label) {
			printf("LABEL;WORK;QUOTED-PRINTABLE:%s\r\n",$label);
		} else {
			if ($address2 && $astreet && $acity && $astate && $azip && $acountry) {
				printf("LABEL;WORK;QUOTED-PRINTABLE:%s=0A%s=0A%s,%s  %s=0A%s\r\n",$address2,$astreet,$acity,$astate,$azip,$acountry);
			}
		}
		// end 'A' grouping

		// 'B' Grouping - home stuff
		if($hemail != "") /* Home E-mail */
			printf("B.EMAIL;%s:%s\r\n", $hemailtype,$hemail);
		if($bphone != "") /* Home Phone */
			printf("B.TEL%s;HOME:%s\r\n", $pref['home'],$bphone);

		if(	$bstreet != "" || /* Home Street */
			$bcity != "" || /* Home City */
			$bstate != "" || /* Home State */
			$bzip != "") {    /* Home Zip */
			printf("B.ADR%s;HOME:;;%s;%s;%s;%s;%s\r\n", $btype,$bstreet,
				$bcity,$bstate,$bzip,$bcountry);
		}
		if ($bstreet && $bcity && $bstate && $bzip && $bcountry) {
			printf("LABEL;HOME;QUOTED-PRINTABLE:%s=0A%s,%s  %s=0A%s\r\n",$bstreet,$bcity,$bstate,$bzip,$bcountry);
		}

		if ($url) {
			printf("URL:%s\r\n",$url);
		}
		// end 'B' grouping

		if($bday != "" && $bday != "//") /* Birthday */
			printf("BDAY:%s\r\n", $bday); /* This is not the right format. */
		if($company != "") /* Company Name (Really isn't company_name?) */
			printf("ORG:%s %s\r\n", $company, $dept);
		if($notes != "") /* Notes */
			$NOTES .= $notes;

		if($NOTES != "") /* All of the notes. */
			printf("NOTE;QUOTED-PRINTABLE:%s\r\n", $NOTES);
		/* End of Stuff. */
		printf("VERSION:2.1\r\n");
		printf("END:VCARD\r\n");
	} /* !nolname && !nofname */

	if($nofname) {
		echo "<BR><BR><CENTER>";
		echo lang("This person's first name was not in the address book.") ."<BR>";
		echo lang("Vcards require a first name entry.") . "<BR><BR>";
		echo "<a href=" . $phpgw->link("/addressbook/index.php","order=$order&start=$start&filter=$filter&query=$query&sort=$sort") . ">OK</a>";
		echo "</CENTER>";
	}

	if($nolname) {
		echo "<BR><BR><CENTER>";
		echo lang("This person's last name was not in the address book.") . "<BR>";
		echo lang("Vcards require a last name entry.") . "<BR><BR>";
		echo "<a href=" . $phpgw->link("/addressbook/index.php","order=$order&start=$start&filter=$filter&query=$query&sort=$sort") . ">OK</a>";
		echo "</CENTER>";
	}

	if($nolname || $nofname)
		$phpgw->common->phpgw_footer();
	/* End of php. */
?>
