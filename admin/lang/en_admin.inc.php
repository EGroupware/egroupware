<?php

  function lang_admin($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "last x logins":	$s = "Last $m1 logins";		break;
       case "loginid":			$s = "LoginID";				break;
       case "ip":				$s = "IP";					break;
       case "total records":	$s = "Total records";		break;
       case "user accounts":	$s = "User accounts";		break;
       case "new group name":	$s = "New group name";		break;
       case "create group":		$s = "Create Group";		break;
       case "kill":				$s = "Kill";				break;
       case "idle":				$s = "idle";				break;
       case "login time":		$s = "Login Time";			break;
       case "anonymous user":	$s = "Anonymous user";		break;
       case "manager":			$s = "Manager";				break;
       case "account active":	$s = "Account active";		break;
       case "re-enter password": $s = "Re-enter password";	break;
       case "group name": 		$s = "Group Name";			break;
       case "display":			$s = "Display";				break;
       case "base url":			$s = "Base URL";			break;
       case "news file":		$s = "News File";			break;
       case "minutes between reloads":	$s = "Minutes between Reloads";		break;
       case "listings displayed":	$s = "Listings Displayed";		break;
       case "news type":		$s = "News Type";			break;
       case "user groups":		$s = "User groups";			break;
       case "headline sites":	$s = "Headline Sites";		break;
       case "network news":	$s = "Network News";		break;
       case "site":				$s = "Site";				break;
       case "view sessions":	$s = "View sessions";		break;
       case "view access log":	$s = "View Access Log";		break;
       case "active":			$s = "Active";				break;
       case "disabled":			$s = "Disabled";			break;
       case "last time read":	$s = "Last Time Read";		break;
       case "manager":			$s = "Manager";				break;
       case "permissions":		$s = "Permissions";			break;
       case "title":			$s = "Title";				break;
       case "enabled":			$s = "Enabled";				break;

       case "applications":		$s = "Applications";		break;
       case "installed applications":
	$s = "Installed applications";						break;



       case "add new application":
	$s = "Add new application";						break;

       case "application name":
	$s = "Application name";						break;

       case "application title":
	$s = "Application title";						break;

       case "edit application":
	$s = "Edit application";						break;

       case "you must enter an application name and title.":
	$s = "You must enter an application name and title.";	break;


       case "are you sure you want to delete this group ?":
	$s = "Are you sure you want to delete this group ?"; break;

       case "are you sure you want to kill this session ?":
	$s = "Are you sure you want to kill this session ?"; break;

       case "all records and account information will be lost!":
	$s = "All records and account information will be lost!";	break;

       case "are you sure you want to delete this account ?":
	$s = "Are you sure you want to delete this account ?";	break;

       case "are you sure you want to delete this news site ?":
	$s = "Are you sure you want to delete this news site ?";		break;

       case "percent of users that logged out":
	$s = "Percent of users that logged out";			break;

       case "list of current users":
	$s = "list of current users";						break;

       case "new password [ leave blank for no change ]":
	$s = "New password [ Leave blank for no change ]";	break;

       case "the two passwords are not the same":
	$s = "The two passwords are not the same";			break;

       case "the login and password can not be the same":
	$s = "The login and password can not be the same";	break;

       case "you must enter a password":	$s = "You must enter a password";		break;

       case "that loginid has already been taken":
	$s = "That loginid has already been taken";			break;

       case "you must enter a display":		$s = "You must enter a display";		break;
       case "you must enter a base url":	$s = "You must enter a base url";		break;
       case "you must enter a news url":	$s = "You must enter a news url";		break;

       case "you must enter the number of minutes between reload":
	$s = "You must enter the number of minutes between reload";		break;

       case "you must enter the number of listings display":
	$s = "You must enter the number of listings display";		break;

       case "you must select a file type":
	$s = "You must select a file type";					break;

       case "that site has already been entered":
	$s = "That site has already been entered";			break;

       case "select users for inclusion":
        $s = "Select users for inclusion";	break;

       case "sorry, the follow users are still a member of the group x":
        $s = "Sorry, the follow users are still a member of the group $m1";	break;

       case "they must be removed before you can continue":
        $s = "They must be removed before you can continue";	break;


       default: $s = "<b>*</b> ". $message;
    }
    return $s;
  }
?>
