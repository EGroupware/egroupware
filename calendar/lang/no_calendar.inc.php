<?php

  function lang_calendar($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "today":		$s = "I dag";	break;
       case "this week":	$s = "Denne uken";	break;
       case "this month":	$s = "Denne måneden";	break;

       case "generate printer-friendly version":
	$s = "Generer printer-vennlig versjon";	break;

       case "printer friendly":		$s = "Printer Vennlig";	break;

       case "you have not entered a\\nbrief description":
	$s = "Du har ikke skrevet inn en\\nkort beskrivelse";	break;

       case "you have not entered a\\nvalid time of day.":
	$s = "Du har ikke skrevet inn en\\ngyldig tid.";	break;

       case "Are you sure\\nyou want to\\ndelete this entry ?":
	$s = "Er du sikker på at\\ndu vil\\nslette denne?";	break;

       case "participants":		$s = "Deltakere";	break;
       case "calendar - edit":	$s = "Kalender - Edit";	break;
       case "calendar - add":	$s = "Kalender - Tilføy";	break;
       case "brief description":$s = "Kort beskrivelse";break;
       case "full description":	$s = "Full beskrivelse";break;
       case "duration":			$s = "Varighet";		break;
       case "minutes":			$s = "minutter";			break;
       case "repeat type":		$s = "Gjenta type";		break;
       case "none":				$s = "Ingen";			break;
       case "daily":			$s = "Daglig";			break;
       case "weekly":			$s = "Ukentlig";			break;
       case "monthly (by day)":	$s = "Månedlig (etter dag)";break;
       case "monthly (by date)":$s = "Månedlig (etter dato)";break;
       case "yearly":			$s = "Årlig";	break;
       case "repeat end date":	$s = "Gjennta sluttdato";	break;
       case "use end date":		$s = "Bruk sluttdato";	break;
       case "repeat day":		$s = "Gjenta dag";		break;
       case "(for weekly)":		$s = "(for Ukentlig)";	break;
       case "frequency":		$s = "Hvor ofte";		break;
       case "sun":				$s = "Søn";				break;
       case "mon":				$s = "Man";				break;
       case "tue":				$s = "Tir";				break;
       case "wed":				$s = "Ons";				break;
       case "thu":				$s = "Tor";				break;
       case "fri":				$s = "Fre";				break;
       case "sat":				$s = "Lør";				break;
       case "search results":	$s = "Søk resultater";	break;
       case "no matches found.":$s = "Ingen match funnet.";break;
       case "1 match found":	$s = "1 match funnet";	break;
       case "x matches found":	$s = "$m1 match funnet";break;
       case "description":		$s = "Beskrivelse";		break;
       case "repetition":		$s = "Gjenntakelse";		break;
       case "days repeated":	$s = "dager gjentatt";	break;
       case "go!":				$s = "Go!";				break;
       case "year":				$s = "År";			break;
       case "month":			$s = "Måned";			break;
       case "week":				$s = "Uke";			break;
       case "new entry":		$s = "Ny Entry";		break;
       case "view this entry":	$s = "Vis denne entry";	break;

       case "the following conflicts with the suggested time:<ul>x</ul>":
	$s = "De følgende konflikter ved den foreslåtte tidene:<ul>$m1</ul>";	break;

       case "Your suggested time of <B> x - x </B> conflicts with the following existing calendar entries:":
	$s = "Din foreslåtte tid av <B> $m1 - $m2 </B> er i konflikt med de følgende kalender entries:";	break;

       case "you must enter one or more search keywords":
	$s = "Du må skrive inn ett eller flere søkeord";	break;

       case "are you sure\\nyou want to\\ndelete this entry ?\\n\\nthis will delete\\nthis entry for all users.":		$s = "Er du sikker på at\\ndu vil\\nslette denne entry ?\\n\\nDette vil slette\\ndenne entry for alle brukere.";	break;

       default: $s = "* $message";
    }
    return $s;
  } 
?>