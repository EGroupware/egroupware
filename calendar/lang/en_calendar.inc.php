<?php

  function lang_calendar($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "today":		$s = "Today";	break;
       case "this week":	$s = "This week";	break;
       case "this month":	$s = "This month";	break;

       case "generate printer-friendly version":
	$s = "Generate printer-friendly version";	break;

       case "printer friendly":		$s = "Printer Friendly";	break;

       case "you have not entered a\\nbrief description":
	$s = "You have not entered a\\nBrief Description";	break;

       case "you have not entered a\\nvalid time of day.":
	$s = "You have not entered a\\nvalid time of day.";	break;

       case "are you sure\\nyou want to\\ndelete this entry ?":
	$s = "Are you sure\\nyou want to\\ndelete this entry ?";	break;

       case "participants":		$s = "Participants";	break;
       case "calendar - edit":	$s = "Calendar - Edit";	break;
       case "calendar - add":	$s = "Calendar - Add";	break;
       case "brief description":$s = "Brief Description";break;
       case "full description":	$s = "Full Description";break;
       case "duration":			$s = "Duration";		break;
       case "minutes":			$s = "minutes";			break;
       case "repeat type":		$s = "Repeat type";		break;
       case "none":				$s = "None";			break;
       case "daily":			$s = "Daily";			break;
       case "weekly":			$s = "weekly";			break;
       case "monthly (by day)":	$s = "Monthly (by day)";break;
       case "monthly (by date)":$s = "Monthly (by date)";break;
       case "yearly":			$s = "Yearly";	break;
       case "repeat end date":	$s = "Repeat End date";	break;
       case "use end date":		$s = "Use End date";	break;
       case "repeat day":		$s = "Repeat day";		break;
       case "(for weekly)":		$s = "(for Weekly)";	break;
       case "frequency":		$s = "Frequency";		break;
       case "sun":				$s = "Sun";				break;
       case "mon":				$s = "Mon";				break;
       case "tue":				$s = "Tue";				break;
       case "wed":				$s = "Wed";				break;
       case "thu":				$s = "Thu";				break;
       case "fri":				$s = "Fri";				break;
       case "sat":				$s = "Sat";				break;
       case "su":				$s = "Su";				break;
       case "mo":				$s = "M";				break;
       case "tu":				$s = "T";				break;
       case "we":				$s = "W";				break;
       case "th":				$s = "T";				break;
       case "fr":				$s = "F";				break;
       case "sa":				$s = "Sa";				break;
       case "search results":	$s = "Search Results";	break;
       case "no matches found.":$s = "No matches found.";break;
       case "1 match found":	$s = "1 match found";	break;
       case "x matches found":	$s = "$m1 matches found";break;
       case "description":		$s = "Description";		break;
       case "repetition":		$s = "Repetition";		break;
       case "days repeated":	$s = "days repeated";	break;
       case "go!":				$s = "Go!";				break;
       case "year":				$s = "Year";			break;
       case "month":			$s = "Month";			break;
       case "week":				$s = "Week";			break;
       case "new entry":		$s = "New Entry";		break;
       case "view this entry":	$s = "View this entry";	break;

       case "the following conflicts with the suggested time:<ul>x</ul>":
	$s = "The following conflicts with the suggested time:<ul>$m1</ul>";	break;

       case "your suggested time of <B> x - x </B> conflicts with the following existing calendar entries:":
	$s = "Your suggested time of <B> $m1 - $m2 </B> conflicts with the following existing calendar entries:";	break;

       case "you must enter one or more search keywords":
	$s = "You must enter one or more search keywords";	break;

       case "are you sure\\nyou want to\\ndelete this entry ?\\n\\nthis will delete\\nthis entry for all users.":		$s = "Are you sure\\nyou want to\\ndelete this entry ?\\n\\nThis will delete\\nthis entry for all users.";	break;

       case "":		$s = "";	break;
       case "":		$s = "";	break;
       case "":		$s = "";	break;
       case "":		$s = "";	break;
       case "":		$s = "";	break;
       case "":		$s = "";	break;
       default: $s = "<b>*</b> ". $message;
    }
    return $s;
  }
?>
