<?php
  /**************************************************************************\
  * phpGroupWare - addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@mail.com>                                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */
  
  // NOTE: This entire file needs to be rewritten.  There is a great deal of code not being used
  //       anymore. This should also be converted to templates while where at it (jengo)

/*  $abc = array('company'   => 'company',	// AddressBook Columns and their descriptions
               'firstname' => 'first name',
               'lastname'  => 'last name',
               'email'     => 'email',
               'wphone'    => 'work phone',
               'hphone'    => 'home phone',
               'fax'       => 'fax',
               'pager'     => 'pager',
	       'title'     => 'title',
               'mphone'    => 'mobile phone',
               'ophone'    => 'other phone',
               'street'    => 'street',
               'city'      => 'city',
               'state'     => 'state',
               'zip'       => 'zip code',
               'bday'      => 'birthday',
               'url'       => 'URL'
              );
*/
  function form($format,$action,$title,$fields) { // used for add/edit
    global $phpgw, $phpgw_info;
     
    $t = new Template($phpgw_info["server"]["app_tpl"]);
    $t->set_file(array( "form"	=> "form.tpl"));

    $email        = $fields["D.EMAIL"];
    $firstname    = $fields["N_Given"];
    $lastname     = $fields["N_Family"];
    $title        = $fields["TITLE"];
    $hphone       = $fields["A_TEL"];
    $wphone       = $fields["B_TEL"];
    $fax          = $fields["C_TEL"];
    $pager        = $fields["pager"];
    $mphone       = $fields["mphone"];
    $ophone       = $fields["ophone"];
    $street       = $fields["ADR_Street"];
    $address2     = $fields["address2"];
    $city         = $fields["ADR_Locality"];
    $state        = $fields["ADR_Region"];
    $zip          = $fields["ADR_PostalCode"];
    $country      = $fields["ADR_Country"];
    $bday         = $fields["bday"];
    $notes        = $fields["notes"];
    $company      = $fields["ORG_Name"];
    $url          = $fields["url"];

    if ($format != "view") {
      $email 	 = "<input name=\"email\" value=\"$email\">";
      $firstname = "<input name=\"firstname\" value=\"$firstname\">";
      $lastname  = "<input name=\"lastname\" value=\"$lastname\">";
      $title     = "<input name=\"title\" value=\"$title\">";
      $hphone	 = "<input name=\"hphone\" value=\"$hphone\">";
      $wphone	 = "<input name=\"wphone\" value=\"$wphone\">";
      $fax	 = "<input name=\"fax\" value=\"$fax\">";
      $pager	 = "<input name=\"pager\" value=\"$pager\">";
      $mphone	 = "<input name=\"mphone\" value=\"$mphone\">";
      $ophone	 = "<input name=\"ophone\" value=\"$ophone\">";
      $street	 = "<input name=\"street\" value=\"$street\">";
      $address2  = "<input name=\"address2\" value=\"$address2\">";
      $city	 = "<input name=\"city\" value=\"$city\">";
      $state	 = "<input name=\"state\" value=\"$state\">";
      $zip	 = "<input name=\"zip\" value=\"$zip\">";
      $country   = "<input name=\"country\" value=\"$country\">";

/*
      if($phpgw_info["apps"]["timetrack"]["enabled"]) {
        $company  = '<select name="company">';
	if (!$company) {
          $company .= '<option value="0" SELECTED>'. lang("none").'</option>';
        } else {
          $company .= '<option value="0">'. lang("none").'</option>';
        }
        $phpgw->db->query("select company_id,company_name from customers order by company_name");
        while ($phpgw->db->next_record()) {
          $ncust = $phpgw->db->f("company_id");
          $company .= '<option value="' . $ncust . '"';
          if ( $company_id == $ncust ) {
            $company .= " selected";
          }
          $company .= ">" . $phpgw->db->f("company_name") . "</option>";
        }
        $company .=  "</select>";
      } else { */
        $company = "<input name=\"company\" value=\"$company\">";
/*    } */

      if (strlen($bday) > 2) {
        list( $month, $day, $year ) = split( '/', $bday );
        $temp_month[$month] = "SELECTED";

        $bday_month = "<select name=bday_month>"
                    . "<option value=\"\" $temp_month[0]> </option>"
                    . "<option value=1 $temp_month[1]>January</option>" 
                    . "<option value=2 $temp_month[2]>February</option>"
                    . "<option value=3 $temp_month[3]>March</option>"
                    . "<option value=4 $temp_month[4]>April</option>"
                    . "<option value=5 $temp_month[5]>May</option>"
                    . "<option value=6 $temp_month[6]>June</option>" 
                    . "<option value=7 $temp_month[7]>July</option>"
                    . "<option value=8 $temp_month[8]>August</option>"
                    . "<option value=9 $temp_month[9]>September</option>"
                    . "<option value=10 $temp_month[10]>October</option>"
                    . "<option value=11 $temp_month[11]>November</option>"
                    . "<option value=12 $temp_month[12]>December</option>"
                    . "</select>";
        $bday_day   = '<input maxlength="2" name="bday_day" value="' . $day . '" size="2">';
        $bday_year  = '<input maxlength="4" name="bday_year" value="' . $year . '" size="4">';
      } else {
        $bday_month = "<select name=bday_month>"
                    . "<option value=\"\" SELECTED> </option>"
                    . "<option value=1>January</option>" 
                    . "<option value=2>February</option>"
                    . "<option value=3>March</option>"
                    . "<option value=4>April</option>"
                    . "<option value=5>May</option>"
                    . "<option value=6>June</option>" 
                    . "<option value=7>July</option>"
                    . "<option value=8>August</option>"
                    . "<option value=9>September</option>"
                    . "<option value=10>October</option>"
                    . "<option value=11>November</option>"
                    . "<option value=12>December</option>"
                    . "</select>";
        $bday_day  = '<input name="bday_day" size="2" maxlength="2">';
        $bday_year = '<input name="bday_year" size="4" maxlength="4">';
      }

    $email_type = "<select name=email_type>";
    while ($type = each($this->email_types)) {
       $email_type .= "<option value=\"\" $type[0]> </option>";
    }
    $email_type .= "</select>";
    
    $notes	 = '<TEXTAREA cols="60" name="notes" rows="4">' . $notes . '</TEXTAREA>';
    } else {
      $notes	= "<form><TEXTAREA cols=\"60\" name=\"notes\" rows=\"4\">"
		. $notes . "</TEXTAREA></form>";
      if ($bday == "//")
        $bday = "";

/*
      if($phpgw_info["apps"]["timetrack"]["enabled"]) {
        $company = $company_name;
      } else { */
        $company = $company;
/*    } */
    }

  if ($action) {
     echo "<FORM action=\"".$phpgw->link($action)."\" method=\"post\">\n";
  }

  // test:
  //echo "Time track app status = " . $phpgw_info["apps"]["timetrack"]["enabled"];

    if (! ereg("^http://",$url)) {
      $url = "http://". $url;
    } 

    $birthday = $phpgw->common->dateformatorder($bday_year,$bday_month,$bday_day)
          . '<font face="'.$theme["font"].'" size="-2">(e.g. 1969)</font>';

    if ($format == "view") {
       if ($access != "private" && $access != "public") {
	  $access_link .= '<td><font size="-1">'.lang("Group access").':</font></td>'
	     . '<td colspan="3"><font size="-1">'
	     . $phpgw->accounts->convert_string_to_names($access);
       } else {
	  $access_link .=  '<td><font size="-1">'.lang("Access").':</font></td>'
	     . '<td colspan="3"><font size="-1">'
	     . $access;
       }
    } else {
          $access_link .= '<td><font size="-1">'.lang("Access").':</font></td>
    <td colspan="3">
      <font size="-1">
      <select name="access">
       <option value="private"';

        if ($access == "private") $access_link .= ' selected>'.lang("private").'</option>';
        else $access_link .= '>'.lang("private").'</option>';

	$access_link .= '<option value="public"
	';

        if ($access == "public")
          $access_link .= ' selected>'.lang("Global Public").'</option>';
        else $access_link .= '>'.lang("Global Public").'</option>';
        
        $access_link .= '<option value="group"
        ';

        if ($access != "public" && $access != "private" && $access != "")
          $access_link .= ' selected>'.lang("Group Public").'</option></select>';
        else
          $access_link .= '>'.lang("Group Public").'</option></select>';

        $access_link .= '</tr>
        ';
    }

      if ($format != "view") {
         $access_link .= '<tr><td><font size="-1">' . lang("Which groups")
	    . ':</font></td><td colspan="3"><select name="n_groups[]" '
	    . 'multiple size="5">';

        $user_groups = $phpgw->accounts->read_group_names($fields["owner"]);
        for ($i=0;$i<count($user_groups);$i++) {
            $access_link .= '<option value="'.$user_groups[$i][0].'"';
            if (ereg(",".$user_groups[$i][0].",",$access))
               $access_link .= ' selected';

            $access_link .= '>'.$user_groups[$i][1].'</option>
	    ';
        }
        $access_link .= '</select></font></td></tr>';
	$t->set_var("lang_access",lang("access"));
      } else {
        $access_link = '';
	$t->set_var("lang_access",'');
      }

    if ($format == "view")
       $create .= '<tr><td><font size="-1">'.lang("Created by").':</font></td>'
	  . '<td colspan="3"><font size="-1">'
	  . grab_owner_name($fields["owner"]);
    else
       $create = '';
  
    $t->set_var("lang_lastname",lang("Last Name"));
    $t->set_var("lastname",$lastname);
    $t->set_var("lang_firstname",lang("First Name"));
    $t->set_var("firstname",$firstname);
    $t->set_var("lang_company",lang("Company Name"));
    $t->set_var("company",$company);
    $t->set_var("lang_title",lang("Title"));
    $t->set_var("title",$title);
    $t->set_var("lang_email",lang("Email"));
    $t->set_var("email",$email);
    $t->set_var("lang_email_type",lang("Email Type"));
    $t->set_var("email_type",$email_type);
    $t->set_var("lang_url",lang("URL"));
    $t->set_var("url",$url);
    $t->set_var("lang_hphone",lang("Home Phone"));
    $t->set_var("hphone",$hphone);
    $t->set_var("lang_fax",lang("fax"));
    $t->set_var("fax",$fax);
    $t->set_var("lang_wphone",lang("Work Phone"));
    $t->set_var("wphone",$wphone);
    $t->set_var("lang_pager",lang("Pager"));
    $t->set_var("pager",$pager);
    $t->set_var("lang_mphone",lang("Mobile"));
    $t->set_var("mphone",$mphone);
    $t->set_var("lang_ophone",lang("Other Number"));
    $t->set_var("ophone",$ophone);
    $t->set_var("lang_street",lang("Street"));
    $t->set_var("street",$street);
    $t->set_var("lang_birthday",lang("Birthday"));
    $t->set_var("birthday",$birthday);
    $t->set_var("lang_address2",lang("Line 2"));
    $t->set_var("address2",$address2);
    $t->set_var("lang_city",lang("city"));
    $t->set_var("city",$city);
    $t->set_var("lang_state",lang("state"));
    $t->set_var("state",$state);
    $t->set_var("lang_zip",lang("Zip Code"));
    $t->set_var("zip",$zip);
    $t->set_var("lang_country",lang("Country"));
    $t->set_var("country",$country);
    $t->set_var("access_link",$access_link);
    $t->set_var("create",$create);
    $t->set_var("lang_notes",lang("notes"));
    $t->set_var("notes",$notes);

    $t->parse("out","form");
    $t->pparse("out","form");
  } //end form function

?>
