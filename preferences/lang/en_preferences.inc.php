<?php

  function lang_pref($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "max matchs per page":
	$s = "Max matches per page";		break;
       
       case "time zone offset":
	$s = "Time zone offset";		break;
       
       case "this server is located in the x timezone":
	$s = "This server is located in the " . $m1 . " timezone";	break;
       
       case "date format":	$s = "Date format";			break;
       case "time format":	$s = "Time format";			break;
       case "language":		$s = "Language";			break;

       case "default sorting order":	$s = "Default sorting order";	break;
       case "default application":		$s = "Default application";	break;

       case "show text on navigation icons":
	$s = "Show text on navigation icons";			break;
       
       case "show current users on navigation bar":
	$s = "Show current users on navigation bar";	break;
       
       case "show new messages on main screen":
	$s = "Show new messages on main screen";	break;
       
       case "email signature":
	$s = "E-Mail signature";	break;
       
       case "show birthday reminders on main screen":
	$s = "Show birthday reminiders on main screen";	break;
       
       case "show high priority events on main screen":
	$s = "Show high priority events on main screen";	break;
       
       case "weekday starts on":
	$s = "Weekday starts on";	break;
       
       case "work day starts on":
	$s = "Work day starts on";	break;
       
       case "work day ends on":
	$s = "Work day ends on";	break;
       
       case "select headline news sites":
	$s = "Select Headline News sites";	break;
       
       case "change your password":
	$s = "Change your Password";		break;

       case "select different theme":
	$s = "Select different Theme";		break;

       case "change your settings":
	$s = "Change your Settings";		break;

       case "change your profile":
	$s = "Change your profile";		break;

       case "enter your new password":
	$s = "Enter your new password";		break;

       case "re-enter your password":
	$s = "Re-Enter your password";	break;

       case "the two passwords are not the same":
	$s = "The two passwords are not the same";	break;

       case "you must enter a password":
	$s = "You must enter a password";	break;

       case "your current theme is: x":
	$s = "Your current theme is: <b>" . $m1 . "</b>";	break;

       case "please, select a new theme":
	$s = "Please, select a new theme";	break;

       case "note: this feature does *not* change your email password. this will need to be done manually.":
	$s = "Note: This feature does *not* change your email password. This will need to be done manually.";	break;

       case "monitor newsgroups":
	$s = "Monitor Newsgroups";	break;


       default: $s = "<b>*</b> ". $message;
    }
    return $s;
  }


