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

  $abc = array('company'   => 'company',		  // AddressBook Columns and their descriptions
               'firstname' => 'first name',
               'lastname'  => 'last name',
               'email'     => 'email',
               'wphone'    => 'work phone',
               'hphone'    => 'home phone',
               'fax'       => 'fax',
               'pager'     => 'pager',
               'mphone'    => 'mobile phone',
               'ophone'    => 'other phone',
               'street'    => 'street',
               'city'      => 'city',
               'state'     => 'state',
               'zip'       => 'zip code',
               'bday'      => 'birthday',
               'url'       => 'URL'
              );

  function form($format,$action,$title,$fields)
  {
      global $phpgw, $phpgw_info;

      $email        = $fields["email"];
      $firstname    = $fields["firstname"];
      $lastname     = $fields["lastname"];
      $title        = $fields["title"];
      $hphone       = $fields["hphone"];
      $wphone	   = $fields["wphone"];
      $fax	      = $fields["fax"];
      $pager	    = $fields["pager"];
      $mphone	   = $fields["mphone"];
      $ophone	   = $fields["ophone"];
      $street	   = $fields["street"];
      $address2     = $fields["address2"];
      $city	     = $fields["city"];
      $state        = $fields["state"];
      $zip	      = $fields["zip"];
      $bday	     = $fields["bday"];
      $notes	    = $fields["notes"];
      $access       = $fields["access"];
      $ab_company   = $fields["company"];
      $company_id   = $fields["company_id"];
      $company_name = $fields["company_name"];
      $url          = $fields["url"];

    if ($format != "view") {
       $email 	= "<input name=\"email\" value=\"$email\">";
       $firstname = "<input name=\"firstname\" value=\"$firstname\">";
       $lastname = "<input name=\"lastname\" value=\"$lastname\">";
       $title = "<input name=\"title\" value=\"$title\">";
       $hphone	= "<input name=\"hphone\" value=\"$hphone\">";
       $wphone	= "<input name=\"wphone\" value=\"$wphone\">";
       $fax	= "<input name=\"fax\" value=\"$fax\">";
       $pager	= "<input name=\"pager\" value=\"$pager\">";
       $mphone	= "<input name=\"mphone\" value=\"$mphone\">";
       $ophone	= "<input name=\"ophone\" value=\"$ophone\">";
       $street	= "<input name=\"street\" value=\"$street\">";
       $address2  = "<input name=\"address2\" value=\"$address2\">";
       $city	= "<input name=\"city\" value=\"$city\">";
       $state	= "<input name=\"state\" value=\"$state\">";
       $zip	= "<input name=\"zip\" value=\"$zip\">";
       //$url	= "<input name=\"url\" value=\"$url\">";
              
       if($phpgw_info["apps"]["timetrack"]["enabled"]) {
         $company = '<select name="company">';
         $phpgw->db->query("select company_id,company_name from customers order by company_name");
         while ($phpgw->db->next_record()) {
           $ncust = $phpgw->db->f("company_id");
           $company = $company . '<option value="' . $ncust . '"';
           if ( $company_id == $ncust ) {
             $company = $company . " selected";
           }
             $company = $company . ">" . $phpgw->db->f("company_name") . "</option>";
           }
         $company = $company . "</select>";
       } else {
	$company = "<input name=\"company\" value=\"$ab_company\">";
       }

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
                      . "</select>"
;
          $bday_day   = '<input maxlength="2" name="bday_day" value="' . $day . '" size="2">'
;
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
                   . "</select>"
;
       $bday_day  = '<input name="bday_day" size="2" maxlength="2">'
;
       $bday_year = '<input name="bday_year" size="4" maxlength="4">'
;
    }

    $notes	 = '<TEXTAREA cols="60" name="notes" rows="4">'
 . $notes . '</TEXTAREA>';
  } else {
     $notes	= "<form><TEXTAREA cols=\"60\" name=\"notes\" rows=\"4\">"
		. $notes . "</TEXTAREA></form>";
     if ($bday == "//")
        $bday = "";
     if($phpgw_info["apps"]["timetrack"]["enabled"]) {
      $company = $company_name;
     } else {
      $company = $ab_company;
     }
  }

  if ($action) {
     echo "<FORM action=\"".$phpgw->link($action)."\" method=\"post\">\n";
  }

  // test:
  //echo "Time track app status = " . $phpgw_info["apps"]["timetrack"]["enabled"];

  ?>

<table width="75%" border="0" align="center">
  <tr>
    <td><font color="#000000" face="" size="-1"><?php echo lang("Last Name"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $lastname; ?>
    </font></td>
    <td><font color="#000000" face="" size="-1"><?php echo lang("First Name"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $firstname; ?>
    </font></td>
  </tr>
  <tr>
    <td>
     <font color="#000000" face="" size="-1"><?php echo lang("Title"); ?>:</font>
    </td>
    <td>
      <font size="-1">
      <?php echo $title; ?>
    </font></td>
    <td>
     <font color="#000000" face="" size="-1"><?php echo lang("E-mail"); ?>:
    </td>
    <td>
      <font size="-1">
      <?php echo $email; ?>
    </td>
  </tr>

  <tr>
    <td>
     <font color="#000000" face="" size="-1"><?php echo lang("Company Name"); ?>:</font>
    </td>
    <td>
     <font size="-1"><?php echo $company; ?>
</font>
    </td>
    <td>
     <font color="#000000" face="" size="-1"><?php echo lang("URL"); ?>:</font>
    </td>
    <td>
     <input name="url" value="<?php
                                if (! ereg("^http://",$url)) {
                                   echo "http://";
                                }
                                echo $url;
                              ?>">
    </td>
    <td><font size="-1"></font></td>
  </tr>
  
  <tr>
    <td><font color="#000000" face="" size="-1"><?php echo lang("Home Phone"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $hphone; ?>
    </font></td>
    <td><font color="#000000" face="" size="-1"><?php echo lang("Fax"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $fax; ?>
    </font></td>
  </tr>
  <tr>
    <td><font color="#000000" face="" size="-1"><?php echo lang("Work Phone"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $wphone; ?>
    </font></td>
    <td><font color="#000000" face="" size="-1"><?php echo lang("Pager"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $pager; ?>
    </font></td>
  </tr>
  <tr>
    <td><font color="#000000" face="" size="-1"><?php echo lang("Mobile"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $mphone; ?>
    </font></td>
    <td><font face="" size="-1" color="#000000"><?php echo lang("Other number"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $ophone; ?>
    </font></td>
  </tr>
  <tr>
    <td><font face="" size="-1"><?php echo lang("Street"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $street; ?>
    </font></td>
    <td><font face="" size="-1"><?php echo lang("Birthday"); ?>:</font></td>
    <td>
     <?php 
       echo $phpgw->common->dateformatorder($bday_year,$bday_month,$bday_day)
          . '<font face="' . $theme["font"] . '" size="-2">(e.g. 1969)</font>';
     ?>
   </td>
  </tr>
  <tr>
    <td><font face="" size="-1"><?php echo lang("Line 2"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $address2; ?>
    </font></td>
    <td><font size="-1"></font></td>
    <td><font size="-1"></font></td>
  </tr>
  <tr>
    <td><font face="" size="-1"><?php echo lang("City"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $city; ?>
    </font></td>
    <td><font size="-1"></font></td>
    <td><font size="-1"></font></td>
  </tr>
  <tr>
    <td><font color="#000000" face="" size="-1"><?php echo lang("State"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $state; ?>
    </font></td>
    <td><font size="-1"></font></td>
    <td><font size="-1"></font></td>
  </tr>
  <tr> 
    <td><font face="" size="-1"><?php echo lang("ZIP Code"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $zip; ?>
    </font></td>
    <td><font size="-1"></font></td>
    <td><font size="-1"></font></td>
  </tr>
  <tr>
    <td colspan="4"><font size="-1"></font></td>
  </tr>
  <tr>

  <?php
    if ($format == "view") {
       if ($access != "private" && $access != "public") {
	  echo "<td><font size=\"-1\">" . lang("Group access") . ":</font></td>"
	     . "<td colspan=\"3\"><font size=\"-1\">"
	     . $phpgw->accounts->convert_string_to_names($access);
       } else {
	  echo "<td><font size=\"-1\">" . lang("access") . ":</font></td>"
	     . "<td colspan=\"3\"><font size=\"-1\">"
	     . $access;
       }
    } else {
       ?>
    <td><font size="-1"><?php echo lang("Access"); ?>:</font></td>
    <td colspan="3">
      <font size="-1">

      <select name="access">
       <option value="private"<?php
        if ($access == "private") echo " selected"; ?>><?php echo lang("private"); ?></option>
       <option value="public"<?php
        if ($access == "public") echo " selected"; ?>><?php echo lang("Global Public"); ?></option>
       <option value="group"<?php
        if ($access != "public" && $access != "private" && $access != "")
           echo " selected";
        echo ">" . lang("Group Public") . "</option></select>";
    }
    ?>
    </tr>
    <?php
      if ($format != "view") {
         echo "<tr><td><font size=\"-1\">" . lang("Which groups")
	    . ":</font></td><td colspan=\"3\"><select name=\"n_groups[]\" "
	    . "multiple size=\"5\">";

        $user_groups = $phpgw->accounts->read_group_names($fields["ab_owner"]);
        for ($i=0;$i<count($user_groups);$i++) {
            echo "<option value=\"" . $user_groups[$i][0] . "\"";
            if (ereg(",".$user_groups[$i][0].",",$access))
               echo " selected";

            echo ">" . $user_groups[$i][1] . "</option>\n";
        }
        echo "</select></font></td></tr>";
      }

    if ($format == "view")
       echo "<tr><td><font size=\"-1\">" . lang("Created by") . ":</font></td>"
	  . "<td colspan=\"3\"><font size=\"-1\">"
	  . grab_owner_name($fields["owner"]);
   
  ?></font>
    </td>
  </tr>
  <tr>
    <td><font size="-1"><?php echo lang("Notes"); ?>:
      
    </font></td>
    <td colspan="3">
      <font size="-1">
      <?php echo $notes; ?>
    </font></td>
  </tr>
</table>
<?php
}
?>
