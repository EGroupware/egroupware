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
  $phpgw_info["flags"]["enable_addressbook_class"] = True;
  include("../header.inc.php");

  $sep = $phpgw_info["server"]["dir_separator"];

  # Construct a default basedn and context for Contacts if using LDAP
  $tmpbasedn = split(",",$phpgw_info["server"]["ldap_context"]);
  array_shift($tmpbasedn);
  for ($i=0;$i<count($tmpbasedn);$i++) {
    if($i==0) {
      $fakebasedn = $tmpbasedn[$i];
    } else {
      $fakebasedn = $fakebasedn.",".$tmpbasedn[$i];
    }
  }
  $fakecontext = "ou=Contacts,".$fakebasedn;

  if (!$convert) {
    $t = new Template($phpgw_info["server"]["app_tpl"]);
    $t->set_file(array("import" => "import.tpl"));

    $dir_handle=opendir($phpgw_info["server"]["app_root"].$sep."conv");
    $i=0; $myfilearray="";
    while ($file = readdir($dir_handle)) {
      #echo "<!-- ".is_file($phpgw_info["server"]["app_root"].$sep."conv".$sep.$file)." -->";
      if ((substr($file, 0, 1) != ".") && is_file($phpgw_info["server"]["app_root"].$sep."conv".$sep.$file) ) {
        $myfilearray[$i] = $file;
        $i++;
      }
    }
    closedir($dir_handle);
    sort($myfilearray);
    for ($i=0;$i<count($myfilearray);$i++) {
      $conv .= '<OPTION VALUE="'.$myfilearray[$i].'">'.$myfilearray[$i].'</OPTION>';
    }
    
    $t->set_var("lang_cancel",lang("Cancel"));
    $t->set_var("cancel_url",$phpgw->link("index.php"));
    $t->set_var("navbar_bg",$phpgw_info["theme"]["navbar_bg"]);
    $t->set_var("navbar_text",$phpgw_info["theme"]["navbar_text"]);
    $t->set_var("import_text",lang("Import from Outlook or LDIF"));
    $t->set_var("action_url",$phpgw->link("import.php"));
    $t->set_var("tsvfilename","");
    $t->set_var("conv",$conv);
    $t->set_var("debug",lang("Debug output in browser"));
    $t->set_var("fakebasedn",$fakebasedn);
    $t->set_var("fakecontext",$fakecontext);
    $t->set_var("download",lang("Submit"));

    #$t->parse("out","import");
    $t->pparse("out","import");

    $phpgw->common->phpgw_footer();

  } else {
    include ($phpgw_info["server"]["app_root"].$sep."conv".$sep.$conv_type);

    if ($private=="") { $private="public"; }
    $row=0;
    $buffer="";
    $o = new outlook_conv;
    $buffer = $o->outlook_start_file($buffer,$basedn,$context);
    $fp=fopen($tsvfile,"r");
    while ($data = fgetcsv($fp,8000,",")) {
      $num = count($data);
      $row++;
      if ($row == 1) {
        $header = $data;
      } else {
        $buffer = $o->outlook_start_record($buffer);
        for ($c=0; $c<$num; $c++ ) {
          //Send name/value pairs along with the buffer
          if ($o->outlook[$header[$c]]!="" && $data[$c]!="") {
            $buffer = $o->outlook_new_attrib($buffer, $o->outlook[$header[$c]],$data[$c]);
          }
        }
        $buffer = $o->outlook_end_record($buffer,$private);
      }
    }
    fclose($fp);

    $buffer = $o->outlook_end_file($buffer);
    if ($download == "") {
      if($conv_type=="Debug LDAP" || $conv_type=="Debug SQL" ) {
        header("Content-disposition: attachment; filename=\"conversion.txt\"");
        header("Content-type: application/octetstream");
        header("Pragma: no-cache");
        header("Expires: 0");
        echo $buffer;
      } else {
        echo "<pre>$buffer</pre>";
	echo '<a href="'.$phpgw->link("index.php").'">'.lang("OK").'</a>';
        $phpgw->common->phpgw_footer();
      }
    } else {
      echo "<pre>$buffer</pre>";
      echo '<a href="'.$phpgw->link("index.php").'">'.lang("OK").'</a>';
      $phpgw->common->phpgw_footer();
    }
  }
?>
