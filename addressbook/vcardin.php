<?php
/**************************************************************************\
* phpGroupWare - E-Mail                                                    *
* http://www.phpgroupware.org                                              *
* This file written by Joseph Engo <jengo@phpgroupware.org>                *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

	if ($action == "Load Vcard") {
		$phpgw_info["flags"] = array(
			"noheader" => True, "nonavbar" => True,
			"currentapp" => "addressbook",
			"enable_contacts_class" => True
		);
		include("../header.inc.php");
	} else {
		$phpgw_info["flags"] = array(
			"currentapp" => "addressbook",
			"enable_contacts_class" => True
		);
		include("../header.inc.php");
		echo '<body bgcolor="' . $phpgw_info["theme"]["bg_color"] . '">';
	}
  
	// Some of the methods where borrowed from
	// Squirrelmail <Luke Ehresman> http://www.squirrelmail.org
	// (only the uploaddir naming anymore)
	$sep = SEP;

	$uploaddir = $phpgw_info["server"]["temp_dir"] . $sep;

	if ($action == "Load Vcard") {
		if($uploadedfile == "none" || $uploadedfile == "") {
			Header("Location: " . $phpgw->link("/addressbook/vcardin.php","action=GetFile"));
		} else {
			srand((double)microtime()*1000000);
			$random_number = rand(100000000,999999999);
			$newfilename = md5("$uploadedfile, $uploadedfile_name, "
						. time() . getenv("REMOTE_ADDR") . $random_number );

			copy($uploadedfile, $uploaddir . $newfilename);
			$ftp = fopen($uploaddir . $newfilename . ".info","w");
			fputs($ftp,"$uploadedfile_type\n$uploadedfile_name\n");
			fclose($ftp);

			$filename = $uploaddir . $newfilename;

			$contacts = CreateObject("phpgwapi.contacts");
			$vcard = CreateObject("phpgwapi.vcard");
			$myimport = $vcard->import;
			$buffer = array();

			$fp=fopen($filename,"r");
			while ($data = fgets($fp,8000)) {
				list($name,$value,$extra) = split(':', $data);
				if (substr($value,0,5) == "http") {
					$value = $value . ":".$extra;
				}
				if ($name && $value) {
					reset($vcard->import);
					while ( list($fname,$fvalue) = each($vcard->import) ) {
						if ( strstr(strtolower($name), $vcard->import[$fname]) ) {
							$value = trim($value);
							$value = ereg_replace("=0D=0A","\n",$value);
							$buffer += array($name => $value);
						}
					}
				}
			}
			fclose($fp);

			$entry = $vcard->in($buffer);
			$contacts->add($phpgw_info["user"]["account_id"],$entry);

			// Delete the temp file.
			unlink($filename);
			unlink($filename . ".info");
			Header("Location: " . $phpgw->link("/addressbook/", "cd=14"));
		}
	}

	if ($action == "GetFile"){
		echo "<B><CENTER>You must select a vcard. (*.vcf)</B></CENTER><BR><BR>";
	}

	$t = new Template(PHPGW_APP_TPL);
	$t->set_file(array("vcardin" => "vcardin.tpl"));

	$vcard_header  = "<p>&nbsp;<b>" . lang("Address book - VCard in") . "</b><hr><p>";

	$t->set_var(vcard_header,$vcard_header);
	$t->set_var(action_url,$phpgw->link("/addressbook/vcardin.php"));
	$t->set_var(lang_access,lang("Access"));
	$t->set_var(lang_groups,lang("Which groups"));
	$t->set_var(access_option,$access_option);

	$t->set_var(group_option,$group_option);

	$t->pparse("out","vcardin");

	if ($action != "Load Vcard")
		$phpgw->common->phpgw_footer();
?>
