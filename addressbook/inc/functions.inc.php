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

  function form($format,$action,$title,$fields)
  {
      global $phpgw;

      $email	= $fields["email"];
      $firstname = $fields["firstname"];
      $lastname = $fields["lastname"];
      $hphone	= $fields["hphone"];
      $wphone	= $fields["wphone"];
      $fax	= $fields["fax"];
      $pager	= $fields["pager"];
      $mphone	= $fields["mphone"];
      $ophone	= $fields["ophone"];
      $street	= $fields["street"];
      $city	= $fields["city"];
      $state	= $fields["state"];
      $zip	= $fields["zip"];
      $bday	= $fields["bday"];
      $notes	= $fields["notes"];
      $access   = $fields["access"];
      $company  = $fields["company"];

    if ($format != "view") {
       $email 	= "<input name=\"email\" value=\"$email\">";
       $firstname = "<input name=\"firstname\" value=\"$firstname\">";
       $lastname = "<input name=\"lastname\" value=\"$lastname\">";
       $hphone	= "<input name=\"hphone\" value=\"$hphone\">";
       $wphone	= "<input name=\"wphone\" value=\"$wphone\">";
       $fax	= "<input name=\"fax\" value=\"$fax\">";
       $pager	= "<input name=\"pager\" value=\"$pager\">";
       $mphone	= "<input name=\"mphone\" value=\"$mphone\">";
       $ophone	= "<input name=\"ophone\" value=\"$ophone\">";
       $street	= "<input name=\"street\" value=\"$street\">";
       $city	= "<input name=\"city\" value=\"$city\">";
       $state	= "<input name=\"state\" value=\"$state\">";
       $zip	= "<input name=\"zip\" value=\"$zip\">";
       $company	= "<input name=\"company\" value=\"$company\">";

       if (strlen($bday) > 2) {
          list( $month, $day, $year ) = split( '/', $bday );
          $temp_month[$month] = "SELECTED";

          $bday ="<select name=bday_month>"
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
               . "<input maxlength=2 name=bday_day value=\"$day\" size=2>"
               . "<input maxlength=4 name=bday_year value=\"$year\" size=4>"
               . "<font face=\"$theme[font]\" size=\"-2\">(e.g. 1969)</font>";
    } else {
       $bday ="<select name=bday_month>"
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
            . "<input maxlength=2 name=bday_day size=2>"
            . "<input maxlength=4 name=bday_year size=4>"
            . "<font face=\"$theme[font]\" size=\"-2\">(e.g. 1969)</font>";
    }

    $notes	= "<TEXTAREA cols=\"60\" name=\"notes\" rows=\"4\">"
		. $notes . "</TEXTAREA>";
  } else {
     $notes	= "<form><TEXTAREA cols=\"60\" name=\"notes\" rows=\"4\">"
		. $notes . "</TEXTAREA></form>";
     if ($bday == "//")
        $bday = "";
  }

  if ($action) {
     echo "<FORM action=\"".$phpgw->link($action)."\" method=\"post\">\n";
  }

  ?>

<table width="75%" border="0" align="center">
  <tr>
    <td><font color="#000000" face="" size="-1"><?php echo lang_common("Last Name"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $lastname; ?>
    </font></td>
    <td><font color="#000000" face="" size="-1"><?php echo lang_common("First Name"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $firstname; ?>
    </font></td>
  </tr>
  <tr>
    <td>
     <font color="#000000" face="" size="-1"><?php echo lang_common("E-mail"); ?>:</font>
    </td>
    <td>
      <font size="-1">
      <?php echo $email; ?>
    </font></td>

    <td><font color="#000000" face="" size="-1"><?php echo lang_addressbook("Company Name"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $company; ?>
    </font></td>
    <td><font size="-1"></font></td>
  </tr>
  <tr>
    <td><font color="#000000" face="" size="-1"><?php echo lang_addressbook("Home Phone"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $hphone; ?>
    </font></td>
    <td><font color="#000000" face="" size="-1"><?php echo lang_addressbook("Fax"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $fax; ?>
    </font></td>
  </tr>
  <tr>
    <td><font color="#000000" face="" size="-1"><?php echo lang_addressbook("Work Phone"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $wphone; ?>
    </font></td>
    <td><font color="#000000" face="" size="-1"><?php echo lang_addressbook("Pager"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $pager; ?>
    </font></td>
  </tr>
  <tr>
    <td><font color="#000000" face="" size="-1"><?php echo lang_addressbook("Mobile"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $mphone; ?>
    </font></td>
    <td><font face="" size="-1" color="#000000"><?php echo lang_addressbook("Other number"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $ophone; ?>
    </font></td>
  </tr>
  <tr>
    <td><font face="" size="-1"><?php echo lang_addressbook("Street"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $street; ?>
    </font></td>
    <td><font face="" size="-1"><?php echo lang_addressbook("Birthday"); ?>:</font></td>
    <td>
      <font size="-1">
        <?php echo $bday; ?>
      </font> </td>
  </tr>
  <tr>
    <td><font face="" size="-1"><?php echo lang_addressbook("City"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $city; ?>
    </font></td>
    <td><font size="-1"></font></td>
    <td><font size="-1"></font></td>
  </tr>
  <tr>
    <td><font color="#000000" face="" size="-1"><?php echo lang_addressbook("State"); ?>:</font></td>
    <td>
      <font size="-1">
      <?php echo $state; ?>
    </font></td>
    <td><font size="-1"></font></td>
    <td><font size="-1"></font></td>
  </tr>
  <tr> 
    <td><font face="" size="-1"><?php echo lang_addressbook("ZIP Code"); ?>:</font></td>
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
	  echo "<td><font size=\"-1\">" . lang_common("Group access") . ":</font></td>"
	     . "<td colspan=\"3\"><font size=\"-1\">"
	     . $phpgw->accounts->convert_string_to_names($access);
       } else {
	  echo "<td><font size=\"-1\">" . lang_common("access") . ":</font></td>"
	     . "<td colspan=\"3\"><font size=\"-1\">"
	     . $access;
       }
    } else {
       ?>
    <td><font size="-1"><?php echo lang_common("Access"); ?>:</font></td>
    <td colspan="3">
      <font size="-1">

      <select name="access">
       <option value="private"<?php
        if ($access == "private") echo " selected"; ?>><?php echo lang_common("private"); ?></option>
       <option value="public"<?php
        if ($access == "public") echo " selected"; ?>><?php echo lang_common("Global Public"); ?></option>
       <option value="group"<?php
        if ($access != "public" && $access != "private" && $access != "")
           echo " selected";
        echo ">" . lang_common("Group Public") . "</option></select>";
    }
    ?>
    </tr>
    <?php
      if ($format != "view") {
         echo "<tr><td><font size=\"-1\">" . lang_common("Which groups")
	    . ":</font></td><td colspan=\"3\"><select name=\"n_groups[]\" "
	    . "multiple size=\"5\">";

        $user_groups = $phpgw->accounts->read_group_names($fields["owner"]);
        for ($i=0;$i<count($user_groups);$i++) {
            echo "<option value=\"" . $user_groups[$i][0] . "\"";
            if (ereg(",".$user_groups[$i][0].",",$access))
               echo " selected";

            echo ">" . $user_groups[$i][1] . "</option>\n";
        }
        echo "</select></font></td></tr>";
      }

    if ($format == "view")
       echo "<tr><td><font size=\"-1\">" . lang_common("Created by") . ":</font></td>"
	  . "<td colspan=\"3\"><font size=\"-1\">"
	  . grab_owner_name($fields[owner]);
   
  ?></font>
    </td>
  </tr>
  <tr>
    <td><font size="-1"><?php echo lang_addressbook("Notes"); ?>:
      
    </font></td>
    <td colspan="3">
      <font size="-1">
      <?php echo $notes; ?>
    </font></td>
  </tr>
</table>
<?php

}
