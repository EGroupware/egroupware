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

	$sep = $phpgw->common->filesystem_separator();

	$uploaddir = $phpgw_info["server"]["temp_dir"] . $sep . $phpgw_info["user"]["sessionid"] . $sep;

	if ($action == "Load Vcard") {
		if($uploadedfile == "none" || $uploadedfile == "") {
			Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/addressbook/vcardin.php","action=GetFile"));
		} else {
			srand((double)microtime()*1000000);
			$random_number = rand(100000000,999999999);
			$newfilename = md5("$uploadedfile, $uploadedfile_name, " . $phpgw_info["user"]["sessionid"]
						. time() . getenv("REMOTE_ADDR") . $random_number );

			copy($uploadedfile, $uploaddir . $newfilename);
			$ftp = fopen($uploaddir . $newfilename . ".info","w");
			fputs($ftp,"$uploadedfile_type\n$uploadedfile_name\n");
			fclose($ftp);

			// This has to be non-interactive in case of a multi-entry vcard.
			$filename = $uploaddir . $newfilename;
			$n_groups = $phpgw->accounts->array_to_string($access,$n_groups);
      
			if($access == "group")
				$access = $n_groups;
			//echo $access . "<BR>";

			parsevcard($filename,$access);
			// Delete the temp file.
			unlink($filename);
			unlink($filename . ".info");
			Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/addressbook/", "cd=14"));
		}
	}

	if (! file_exists($phpgw_info["server"]["temp_dir"] . $sep . $phpgw_info["user"]["sessionid"]))
		mkdir($phpgw_info["server"]["temp_dir"] . $sep . $phpgw_info["user"]["sessionid"],0700);

	if ($action == "GetFile"){
		echo "<B><CENTER>You must select a vcard. (*.vcf)</B></CENTER><BR><BR>";
	}


	$t = new Template($phpgw->common->get_tpl_dir("addressbook"));
	$t->set_file(array("vcardin" => "vcardin.tpl"));
	
	$vcard_header  = "<p>&nbsp;<b>" . lang("Address book - VCard in") . "</b><hr><p>";

	$t->set_var(vcard_header,$vcard_header);
	$t->set_var(action_url,$phpgw->link("vcardin.php"));
	$t->set_var(lang_access,lang("Access"));
	$t->set_var(lang_groups,lang("Which groups"));

	$access_option = "<option value=\"private\"";
	if($access == "private")
		$access_option .= "selected";
    $access_option .= ">" . lang("private");
	$access_option .= "</option>\n";
	$access_option .= "<option value=\"public\"\n";
	if($access == "public")
		$access_option .=  "selected";
    $access_option .= ">" . lang("Global Public");
    $access_option .= "</option>\n";
    $access_option .= "<option value=\"group\"";
	if($access != "private" && $access != "public" && $access != "")
		$access_option .= "selected";
    $access_option .= ">" . lang("Group Public"); 
    $access_option .= "</option>\n";
	
	$t->set_var(access_option,$access_option);
	
    //$user_groups = $phpgw->accounts->read_group_names($fields["ab_owner"]);
    for ($i=0;$i<count($user_groups);$i++) {
    	$group_option = "<option value=\"" . $user_groups[$i][0] . "\"";
        if (ereg(",".$user_groups[$i][0].",",$access)) {
        	$group_option .= " selected";
            $group_option .= ">" . $user_groups[$i][1];
			$group_option .= "</option>\n";
		}
	}

	$t->set_var(group_option,$group_option);
	
	$t->pparse("out","vcardin");

	if ($action != "Load Vcard")
		$phpgw->common->phpgw_footer();
?>
