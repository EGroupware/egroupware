<?php

  /**************************************************************************\
  * phpGroupWare - addressbook                                               *
  * http://www.phpgroupware.org						     *
  * Written by Joseph Engo <jengo@phpgroupware.org>			     *
  * --------------------------------------------			     *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *	  option) any later version. 					     *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_info["flags"] = array("currentapp" => "addressbook", "enable_addressbook_class" => True, "noheader" => True, "nonavbar" => True);
  include("../header.inc.php");


// parse a vcard and fill the address book with it.
function parsevcard($filename,$access)
{
      global $phpgw;
      global $phpgw_info;

	$vcard = fopen($filename, "r");
	if (!$vcard) // Make sure we have a file to read.
	{
		fclose($vcard);
		return FALSE;
	}


        // Keep runnig through this to support vcards
	// with multiple entries.
        while (!feof($vcard))
	{
	  if(!empty($varray))
            unset($varray);

	  // Make sure our file is a vcard.
	  // I should deal with empty line at the
	  // begining of the file. Those will fail here.
	  $vline = fgets($vcard,20);
	  $vline = strtolower($vline);
	  if(strcmp("begin:vcard", substr($vline, 0, strlen("begin:vcard")) ) != 0)
	  {	
		fclose($vcard);
		return FALSE;
	  }

	  // Write the vcard into an array.
	  // You can have multiple vcards in one file.
	  // I only deal with halve of that. :)
	  // It will only return values from the 1st vcard.
	  $varray[0] = "begin";
	  $varray[1] = "vcard";
	  $i=2;
	  while(!feof($vcard) && strcmp("end:vcard", strtolower(substr($vline, 0, strlen("end:vcard"))) ) !=0 )
	  {
		$vline = fgets($vcard,4096);
		// Check for folded lines and escaped colons '\:'
		$la = explode(":", $vline);


//if (ereg("\:",$vline))
//{
//	// Oh, no....  Horrible disaster....
//	// Yell.
//	echo "<PRE><FLAILING ARMS></PRE><BR>";
//	echo "DANGER WILL ROBINSON!!!!!!!!!!<BR>";
//	echo "This just broke.  Really.<BR>";
//	echo "<PRE></FLAILING ARMS></PRE><BR>";
//}


		// DANGER Will Robinson!!!!!!!
		// I don't check for escaped colons here.
		//  '\:'  These would cause horrible disaster..
		// Fix this situation.  Check if the last character
		// of the line is \  If it is, you've found one.
		if (count($la) > 1)
		{
			$varray[$i] = strtolower($la[0]);
			$i++;

			for($j=1;$j<=count($la);$j++)
			{
				$varray[$i] .= $la[$j];
			}
			$i++;
		}
		else // This is the continuation of a folded line.
		{
			$varray[$i-1] .= $la[0];
		}
	  }

	  fillab($varray,$access); // Add this entry to the addressbook before
			 // moving on to the next one.

	} // while(!feof($vcard))

	fclose($vcard);
	return TRUE;
}


function fillab($varray,$access)
{
      global $phpgw;
      global $phpgw_info;

	$i=0;
	while($i < count($varray)) // incremented by 2
	{
		$k = explode(";",$varray[$i]); // Key
		$v = explode(";",$varray[$i+1]); // Values
		for($h=0;$h<count($k);$h++)
		{
			switch($k[$h])
			{
				case "fn":
					$formattedname = $v[0];
					break;
				case "n":
					$lastname = $v[0];
					$firstname = $v[1];
					break;
				case "bday":
					$bday = $v[0];
					break;
				case "adr": // This one is real ugly. :(
					$street = $v[2];
					$address2 = $v[1] . " " . $v[0];
					$city = $v[3];
					$state = $v[4];
					$zip = $v[5];
					$country = $v[6];
					break;
				case "tel": // Check to see if there another phone entry.
					if(!ereg("home",$varray[$i]) &&
					   !ereg("work",$varray[$i]) &&
					   !ereg("fax",$varray[$i])  &&
					   !ereg("cell",$varray[$i]) &&
					   !ereg("pager",$varray[$i]) &&
					   !ereg("bbs",$varray[$i])  &&
					   !ereg("modem",$varray[$i]) &&
					   !ereg("car",$varray[$i])  &&
					   !ereg("isdn",$varray[$i]) &&
					   !ereg("video",$varray[$i])   )
					{ // There isn't a seperate home entry.
					  // Use this number.
						$hphone = $v[0];
					}
					break;
				case "home":
					$hphone = $v[0];
					break;
				case "work":
					$wphone = $v[0];
					break;
				case "fax":
					$fax = $v[0];
					break;
				case "pager":
					$pager = $v[0];
					break;
				case "cell":
					$mphone = $v[0];
					break;
				case "pref":
					$notes .= "Preferred phone number is ";
					$notes .= $v[0] . "\n";
					break;
				case "msg":
					$notes .= "Messaging service on number "; 
					$notes .= $v[0] . "\n";
					break;
				case "bbs":
					$notes .= "BBS phone number ";
					$notes .= $v[0] . "\n";
					break;
				case "modem":
					$notes .= "Modem phone number ";
					$notes .= $v[0] . "\n";
					break;
				case "car":
					$notes .= "Car phone number ";
					$notes .= $v[0] . "\n";
					break;
				case "isdn":
					$notes .= "ISDN number ";
					$notes .= $v[0] . "\n";
					break;
				case "video":
					$notes .= "Video phone number ";
					$notes .= $v[0] . "\n";
					break;
				case "email":
					if(!ereg("internet",$varray[$i]))
					{
						$email = $v[0];
					}
					break;
				case "internet":
					$email = $v[0];
					break;
				case "title":
					$title = $v[0];
					break;
				case "org":
					$company = $v[0];
					if(count($v) > 1)
					{
						$notes .= $v[0] . "\n";
						for($j=1;$j<count($v);$j++)
						{
							$notes .= $v[$j] . "\n";
						}
					}
					break;
				default: // Throw most other things into notes.
					break;
			} // switch
		} // for
		$i++;
	} // All of the values that are getting filled are.

//echo "Formatted name: " . $formattedname . "<BR>";
//echo "First Name: " . $firstname . "<BR>";
//echo "Last Name: " . $lastname . "<BR>";
//echo "Home Phone: " .$hphone . "<BR>";
//echo "Cell Phone: " . $mphone . "<BR>";
//echo "Work Phone: " . $wphone . "<BR>";
//echo "Fax: " . $fax . "<BR>";
//echo "Email: " . $email . "<BR>";
//echo "Organization: " . $company . "<BR>";
//echo "Address:<BR>";
//echo $address2 . "<BR>" . $street ."<BR>";
//echo $city . " " . $state . " " . $zip . "<BR>";
//echo "Notes: " . $notes . "<BR>";


     if($phpgw_info["apps"]["timetrack"]["enabled"]) {
       $sql = "insert into addressbook (ab_owner,ab_access,ab_firstname,ab_lastname,ab_title,ab_email,"
        . "ab_hphone,ab_wphone,ab_fax,ab_pager,ab_mphone,ab_ophone,ab_street,ab_address2,ab_city,"
        . "ab_state,ab_zip,ab_bday,"
          . "ab_notes,ab_company_id) values ('" . $phpgw_info["user"]["account_id"] . "','$access','"
          . addslashes($firstname). "','"
          . addslashes($lastname) . "','"
          . addslashes($title)  . "','"
          . addslashes($email)  . "','"
          . addslashes($hphone) . "','"
          . addslashes($wphone) . "','"
          . addslashes($fax)    . "','"
          . addslashes($pager)  . "','"
          . addslashes($mphone) . "','"
          . addslashes($ophone) . "','"
          . addslashes($street) . "','"
          . addslashes($address2) . "','"
          . addslashes($city)   . "','"
          . addslashes($state)  . "','"
          . addslashes($zip)    . "','"
          . addslashes($bday)   . "','"
          . addslashes($notes)  . "','"
          . addslashes($company). "')";
     } else {
       $sql = "insert into addressbook (ab_owner,ab_access,ab_firstname,ab_lastname,ab_title,ab_email,"
        . "ab_hphone,ab_wphone,ab_fax,ab_pager,ab_mphone,ab_ophone,ab_street,ab_address2,ab_city,"
        . "ab_state,ab_zip,ab_bday,"
          . "ab_notes,ab_company) values ('" . $phpgw_info["user"]["account_id"] . "','$access','"
          . addslashes($firstname). "','"
          . addslashes($lastname) . "','"
          . addslashes($title)  . "','"
          . addslashes($email)  . "','"
          . addslashes($hphone) . "','"
          . addslashes($wphone) . "','"
          . addslashes($fax)    . "','"
          . addslashes($pager)  . "','"
          . addslashes($mphone) . "','"
          . addslashes($ophone) . "','"
          . addslashes($street) . "','"
          . addslashes($address2) . "','"
          . addslashes($city)   . "','"
          . addslashes($state)  . "','"
          . addslashes($zip)    . "','"
          . addslashes($bday)   . "','"
          . addslashes($notes)  . "','"
          . addslashes($company). "')";
     }
     $phpgw->db->query($sql);
}

if($access == "group")
  $access = $n_groups;
//echo $access . "<BR>";

parsevcard($filename,$access);
// Delete the temp file.
unlink($filename);
unlink($filename . ".info");
Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/addressbook/",
            "cd=14"));

// End of php.
?>
