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

		$dir_handle=opendir($phpgw_info["server"]["app_root"].$sep."import");
		$i=0; $myfilearray="";
		while ($file = readdir($dir_handle)) {
			//echo "<!-- ".is_file($phpgw_info["server"]["app_root"].$sep."import".$sep.$file)." -->";
			if ((substr($file, 0, 1) != ".") && is_file($phpgw_info["server"]["app_root"].$sep."import".$sep.$file) ) {
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
		$t->set_var("lang_cat",lang("Select Category"));
		$t->set_var("cancel_url",$phpgw->link("/addressbook/index.php",
			"sort=$sort&order=$order&filter=$filter&start=$start&query=$query&cat_id=$cat_id"));
		$t->set_var("navbar_bg",$phpgw_info["theme"]["navbar_bg"]);
		$t->set_var("navbar_text",$phpgw_info["theme"]["navbar_text"]);
		$t->set_var("import_text",lang("Import from LDIF, CSV, or VCard"));
		$t->set_var("action_url",$phpgw->link("/addressbook/import.php",
			"sort=$sort&order=$order&filter=$filter&start=$start&query=$query&cat_id=$cat_id"));
		$t->set_var("cat_id",cat_option($cat_id,True));
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
		include ($phpgw_info["server"]["app_root"].$sep."import".$sep.$conv_type);

		if ($private=="") { $private="public"; }
		$row=0;
		$buffer=array();
		$this = new import_conv;
		$buffer = $this->import_start_file($buffer,$basedn,$context);
		$fp=fopen($tsvfile,"r");
		if ($this->type == 'csv') {
			while ($data = fgetcsv($fp,8000,",")) {
				$num = count($data);
				$row++;
				if ($row == 1) {
					$header = $data;
				} else {
					$buffer = $this->import_start_record($buffer);
					for ($c=0; $c<$num; $c++ ) {
						//Send name/value pairs along with the buffer
						if ($this->import[$header[$c]]!="" && $data[$c]!="") {
							$buffer = $this->import_new_attrib($buffer, $this->import[$header[$c]],$data[$c]);
						}
					}
					$buffer = $this->import_end_record($buffer,$private);
				}
			}
		} elseif ($this->type == 'ldif') {
			while ($data = fgets($fp,8000)) {
				$url = "";
				list($name,$value,$extra) = split(':', $data);
				if (substr($name,0,2) == 'dn') {
					$buffer = $this->import_start_record($buffer);
				}
				
				$test = trim($value);
				if ($name && !empty($test) && $extra) {
					// Probable url string
					$url = $test;
					$value = $extra;
				} elseif ($name && empty($test) && $extra) {
					// Probable multiline encoding
					$newval = base64_decode(trim($extra));
					$value = $newval;
					echo $name.':'.$value;
				}
				
				if ($name && $value) {
					$test = split(',mail=',$value);
					if ($test[1]) {
						$name = "mail";
						$value = $test[1];
					}
					if ($url) {
						$name = "homeurl";
						$value = $url. ':' . $value;
					}
					//echo '<br>'.$j.': '.$name.' => '.$value;
					if ($this->import[$name] != "" && $value != "") {
						$buffer = $this->import_new_attrib($buffer, $this->import[$name],$value);
					}
				} else {
					$buffer = $this->import_end_record($buffer,$private);
				}
			}
		} else {
			while ($data = fgets($fp,8000)) {
				list($name,$value,$extra) = split(':', $data);
				if (strtolower(substr($name,0,5)) == 'begin') {
					$buffer = $this->import_start_record($buffer);
				}
				if (substr($value,0,5) == "http") {
					$value = $value . ":".$extra;
				}
				if ($name && $value) {
					reset($this->import);
					while ( list($fname,$fvalue) = each($this->import) ) {
						if ( strstr(strtolower($name), $this->import[$fname]) ) {
							$buffer = $this->import_new_attrib($buffer,$name,$value);
						}
					}
				} else {
					$buffer = $this->import_end_record($buffer);
				}
			}
		}

		fclose($fp);
		$buffer = $this->import_end_file($buffer,$private,$cat_id);

		if ($download == "") {
			if($conv_type=="Debug LDAP" || $conv_type=="Debug SQL" ) {
				header("Content-disposition: attachment; filename=\"conversion.txt\"");
				header("Content-type: application/octetstream");
				header("Content-length: ".strlen($buffer));
				header("Pragma: no-cache");
				header("Expires: 0");
				echo $buffer;
			} else {
				echo "<pre>$buffer</pre>";
				echo '<a href="'.$phpgw->link("/addressbook/index.php",
					"sort=$sort&order=$order&filter=$filter&start=$start&query=$query&cat_id=$cat_id")
					. '">'.lang("OK").'</a>';
				$phpgw->common->phpgw_footer();
			}
		} else {
			echo "<pre>$buffer</pre>";
			echo '<a href="'.$phpgw->link("/addressbook/index.php",
				"sort=$sort&order=$order&filter=$filter&start=$start&query=$query&cat_id=$cat_id")
				. '">'.lang("OK").'</a>';
			$phpgw->common->phpgw_footer();
		}
	}
?>
