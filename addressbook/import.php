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

	$phpgw_info["flags"]["currentapp"] = "addressbook";
	$phpgw_info["flags"]["enable_contacts_class"] = True;
	include("../header.inc.php");

	//$sep = $phpgw_info["server"]["dir_separator"];
	$sep = SEP;

	// Construct a default basedn for Contacts if using LDAP
	$tmpbasedn = split(",",$phpgw_info["server"]["ldap_context"]);
	array_shift($tmpbasedn);
	for ($i=0;$i<count($tmpbasedn);$i++) {
		if($i==0) {
			$basedn = $tmpbasedn[$i];
		} else {
			$basedn = $basedn.",".$tmpbasedn[$i];
		}
	}
	$context = $phpgw_info["server"]["ldap_contact_context"];

	if (!$convert) {
		$t = new Template($phpgw_info["server"]["app_tpl"]);
		$t->set_file(array("import" => "import.tpl"));

		$dir_handle=opendir($phpgw_info["server"]["app_root"].$sep."conv");
		$i=0; $myfilearray="";
		while ($file = readdir($dir_handle)) {
			//echo "<!-- ".is_file($phpgw_info["server"]["app_root"].$sep."conv".$sep.$file)." -->";
			if ((substr($file, 0, 1) != ".") && is_file($phpgw_info["server"]["app_root"].$sep."conv".$sep.$file) ) {
				$myfilearray[$i] = $file;
				$i++;
			}
		}
		closedir($dir_handle);
		sort($myfilearray);
		for ($i=0;$i<count($myfilearray);$i++) {
			$fname = ereg_replace('_',' ',$myfilearray[$i]);
			$conv .= '<OPTION VALUE="'.$myfilearray[$i].'">'.$fname.'</OPTION>';
		}

		$t->set_var("lang_cancel",lang("Cancel"));
		$t->set_var("cancel_url",$phpgw->link("/addressbook/index.php"));
		$t->set_var("navbar_bg",$phpgw_info["theme"]["navbar_bg"]);
		$t->set_var("navbar_text",$phpgw_info["theme"]["navbar_text"]);
		$t->set_var("import_text",lang("Import from Outlook (CSV) or Netscape (LDIF)"));
		$t->set_var("action_url",$phpgw->link("/addressbook/import.php"));
		$t->set_var("tsvfilename","");
		$t->set_var("conv",$conv);
		$t->set_var("debug",lang("Debug output in browser"));
		$t->set_var("filetype",lang("LDIF"));
		$t->set_var("basedn",$basedn);
		$t->set_var("context",$context);
		$t->set_var("download",lang("Submit"));

		$t->pparse("out","import");
		$phpgw->common->phpgw_footer();
	} else {
		include ($phpgw_info["server"]["app_root"].$sep."conv".$sep.$conv_type);

		if ($private=="") { $private="public"; }
		$row=0;
		$buffer=array();
		$o = new import_conv;
		$buffer = $o->import_start_file($buffer,$basedn,$context);
		$fp=fopen($tsvfile,"r");
		if ($o->type != 'ldif') {
			while ($data = fgetcsv($fp,8000,",")) {
				$num = count($data);
				$row++;
				if ($row == 1) {
					$header = $data;
				} else {
					$buffer = $o->import_start_record($buffer);
					for ($c=0; $c<$num; $c++ ) {
						//Send name/value pairs along with the buffer
						if ($o->import[$header[$c]]!="" && $data[$c]!="") {
							$buffer = $o->import_new_attrib($buffer, $o->import[$header[$c]],$data[$c]);
						}
					}
					$buffer = $o->import_end_record($buffer,$private);
				}
			}
		} else {
			while ($data = fgets($fp,8000)) {
				list($name,$value,$url) = split(':', $data);
				if (substr($name,0,2) == 'dn') {
					$buffer = $o->import_start_record($buffer);
				}
				if ($name && $value) {
					$test = split(',mail=',$value);
					if ($test[1]) {
						$name = "mail";
						$value = $test[1];
					}
					if ($url) {
						$name = "homeurl";
						$value = $value . ':' . $url;
					}
					//echo '<br>'.$j.': '.$name.' => '.$value;
					if ($o->import[$name] != "" && $value != "") {
						$buffer = $o->import_new_attrib($buffer, $o->import[$name],$value);
					}
				} else {
					$buffer = $o->import_end_record($buffer,$private);
				}
			}
		}

		fclose($fp);

		$buffer = $o->import_end_file($buffer);
		if ($download == "") {
			if($conv_type=="Debug LDAP" || $conv_type=="Debug SQL" ) {
				header("Content-disposition: attachment; filename=\"conversion.txt\"");
				header("Content-type: application/octetstream");
				header("Pragma: no-cache");
				header("Expires: 0");
				echo $buffer;
			} else {
				echo "<pre>$buffer</pre>";
				echo '<a href="'.$phpgw->link("/addressbook/index.php").'">'.lang("OK").'</a>';
				$phpgw->common->phpgw_footer();
			}
		} else {
			echo "<pre>$buffer</pre>";
			echo '<a href="'.$phpgw->link("/addressbook/index.php").'">'.lang("OK").'</a>';
			$phpgw->common->phpgw_footer();
		}
	}
?>
