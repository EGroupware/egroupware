<?
  /**************************************************************************\
  * update_mysql_perms.php                                                   *
  * Written by Peter "tooley" Tebault <peter@tebault.org>                    *
  * quick and dirty converter for mysql accounts.permissions field upgrade   *
  * caused by CVS changes around 31-July-2000                                *
  * written as a bit of:                                                     *
  *                                                                          *
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

/*
// tooley: and i quote from irc...
<Seek3r> admin' => '1', 
<Seek3r> email'  => '2', 
<Seek3r> pop_mail'=> '3',       
<Seek3r> calendar'  => '4', 
<Seek3r> addressbook' => '5',   
<Seek3r> todo'      => '6', 
<Seek3r> tts'                   => '7', 
<Seek3r> bookmarks' => '8', 
<Seek3r> anonymous'     => '9', 
<Seek3r> filemanager' => '10',
<Seek3r> headlines'     => '11', 
<Seek3r> nntp'  => '12', 
<Seek3r> chat'  => '13',
<Seek3r> ftp'   => '14'
*/

// CHANGE THIS LINE IF NECESSARY
include("../inc/config.inc.php");

// DON'T EDIT BELOW THIS LINE
$host = $phpgw_info["server"]["db_host"];
$db = $phpgw_info["server"]["db_name"];
$user = $phpgw_info["server"]["db_user"];
$pass = $phpgw_info["server"]["db_pass"];

$conn = mysql_connect($host,$user,$pass);
$result = mysql_db_query($db,"SELECT con,permissions,firstname,lastname FROM accounts",$conn);

echo "Updating permissions...<br><br>\n";
echo mysql_num_rows($result) . " users to process:<br>\n";

while ($row = mysql_fetch_array($result)) {
  $perm = $row[1];
	$tok = strtok($perm,":");
  if(isset($newperm)) unset($newperm);
	while($tok) {
		switch($tok) {
			case "1":
				$newperm .= "admin:";
				break;
			case "2":
				$newperm .= "email:";
				break;
			case "3":
				$newperm .= "pop_mail:";
				break;
			case "4":
				$newperm .= "calendar:";
				break;
			case "5":
				$newperm .= "addressbook:";
				break;
			case "6":
				$newperm .= "todo:";
				break;
			case "7":
				$newperm .= "tts:";
				break;
			case "8":
				$newperm .= "bookmarks:";
				break;
			case "9":
				$newperm .= "anonymous:";
				break;
			case "10":
				$newperm .= "filemanager:";
				break;
			case "11":
				$newperm .= "headlines:";
				break;
			case "12":
				$newperm .= "nntp:";
				break;
			case "13":
				$newperm .= "chat:";
				break;
			case "14":
				$newperm .= "ftp:";
				break;
			default:
				$newperm .= $tok.":";
        $slip++;
				break;
		}
		$tok = strtok(":");
	}
  $thiscon = $row[0];
  echo ("Updating $row[3], $row[2] ($newperm)...");
	if(
    mysql_db_query($db,"UPDATE accounts SET permissions='$newperm' WHERE con='$thiscon'",$conn)) { 
      echo "Success.<br>\n";
      $success++;
  } else {
    echo "Fail: " . mysql_error($conn);
    $fail++;
  }
}

echo "Processed $success records successfully";
if($fail > 0) { 
  echo ", failed $fail records (see errors above).<br>\n";
} else {
  echo ".<br>\n";
}
if($slip > 0) {
  echo "<br>Warning: $slip permission entries didn't parse as old style permissions.   Perhaps you already converted?  (No harm done, we just stored their permissions as they had been.)<br>\n";
}
