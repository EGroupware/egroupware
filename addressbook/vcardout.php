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
  }else{
     $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);
  }

  $phpgw_info["flags"]["currentapp"] = "addressbook";
  include("../header.inc.php");

  if (! $ab_id) {
    Header("Location: " . $phpgw->link("index.php"));
    exit;
  }

  if ($filter != "private")
     $filtermethod = " or ab_access='public' " . $phpgw->accounts->sql_search("ab_access");

  if($phpgw_info["apps"]["timetrack"]["enabled"]) {
   $phpgw->db->query("SELECT * FROM addressbook as a, customers as c WHERE a.ab_company_id = c.company_id "
		     . "AND ab_id=$ab_id AND (ab_owner='"
	             . $phpgw_info["user"]["account_id"] . "' $filtermethod)");
  } else {
   $phpgw->db->query("SELECT * FROM addressbook "
                     . "WHERE ab_id=$ab_id AND (ab_owner='"
                     . $phpgw_info["user"]["account_id"] . "' $filtermethod)");
  }
  $phpgw->db->next_record();


if(!$nolname && !$nofname)
{

	/* First name and last must be in the vcard. */
	if($phpgw->db->f("ab_lastname") == "")
	{
		/* Run away here. */
	        Header("Location: " . $phpgw->link("vcardout.php","nolname=1&ab_id=$ab_id&start=$start&order=$order&filter=" . "$filter&query=$query&sort=$sort"));
	}
	if($phpgw->db->f("ab_firstname") =="" )
	{
	        Header("Location: " . $phpgw->link("vcardout.php","nofname=1&ab_id=$ab_id&start=$start&order=$order&filter=" . "$filter&query=$query&sort=$sort"));
	}

	header("Content-type: text/X-VCARD");
	$fn = explode("@",$phpgw->db->f("ab_email"));
	$filename = sprintf("%s.vcf", $fn[0]);


	header("Content-Disposition: attachment; filename=$filename");

	printf("BEGIN:VCARD\r\n");
	printf("N:%s;%s\r\n", $phpgw->db->f("ab_lastname"), $phpgw->db->f("ab_firstname"));
	printf("FN:%s %s\r\n", $phpgw->db->f("ab_firstname"), $phpgw->db->f("ab_lastname"));

	/* This stuff is optional. */
	if($phpgw->db->f("ab_title") != "") /* Title */
		printf("TITLE:%s\r\n",$phpgw->db->f("ab_title"));
	if($phpgw->db->f("ab_email") != "") /* E-mail */
		printf("EMAIL;INTERNET:%s\r\n", $phpgw->db->f("ab_email"));
	if($phpgw->db->f("ab_hphone") != "") /* Home Phone */
		printf("TEL;HOME:%s\r\n", $phpgw->db->f("ab_hphone"));
	if($phpgw->db->f("ab_wphone") != "") /* Work Phone */
		printf("TEL;WORK:%s\r\n", $phpgw->db->f("ab_wphone"));
	if($phpgw->db->f("ab_mphone") != "") /* Mobile Phone */
		printf("TEL;CELL:%s\r\n", $phpgw->db->f("ab_mphone"));
	if($phpgw->db->f("ab_fax") != "") /* Fax Number */
		printf("TEL;FAX:%s\r\n", $phpgw->db->f("ab_fax"));
	if($phpgw->db->f("ab_pager") != "") /* Pager Number */
		printf("TEL;PAGER:%s\r\n", $phpgw->db->f("ab_pager"));
//	if($pgpgw->db->f("ab_ophone") != "") /* Other Phone */
//		$NOTES .= "Other Phone: " .  $phpgw->db->f("ab_ophone");
	/* The address one is pretty icky. Send it if ANY of the fields are present. */
	if($phpgw->db->f("ab_address2") != "" || /* Street Line 1 */
		   $phpgw->db->f("ab_street") != "" || /* Street Line 2 */
		   $phpgw->db->f("ab_city") != "" || /* City */
		   $phpgw->db->f("ab_state") != "" || /* State */
		   $phpgw->db->f("ab_zip") != "")     /* Zip */
// Warning Ugly U.S. centric assumption made here.....
		printf("ADR:;%s;%s;%s;%s;%s;%s\r\n", $phpgw->db->f("ab_address2"),
			$phpgw->db->f("ab_street"),$phpgw->db->f("ab_city"),
			$phpgw->db->f("ab_state"),$phpgw->db->f("ab_zip"),
			"United States"
		);
	if($phpgw->db->f("ab_bday") != "" && $phpgw->db->f("ab_bday") != "//") /* Birthday */
		printf("BDAY:%s\r\n", $phpgw->db->f("ab_bday")); /* This is not the right format. */
	if($phpgw->db->f("ab_company") != "") /* Company Name (Really isn't company_name?) */
		printf("ORG:%s\r\n", $phpgw->db->f("ab_company"));
	if($phpgw->db->f("ab_notes") != "") /* Notes */
		$NOTES .= $phpgw->db->f("ab_notes");

	if($NOTES != "") /* All of the notes. */
		printf("NOTE:%s\r\n", $NOTES);
	/* End of Stuff. */
	printf("VERSION:2.1\r\n");
	printf("END:VCARD\r\n");
} /* !nolname && !nofname */

if($nofname)
{
	echo "<BR><BR><CENTER>";
	echo lang("This person's first name was not in the address book.") ."<BR>";
	echo lang("Vcards require a first name entry.") . "<BR><BR>";
	echo "<a href=" . $phpgw->link("index.php","order=$order&start=$start&filter=$filter&query=$query&sort=$sort") . ">OK</a>";
	echo "</CENTER>";
}

if($nolname)
{
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
