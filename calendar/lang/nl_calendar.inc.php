<?php

  function lang_calendar($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "today":		$s = "Vandaag";	break;
       case "this week":	$s = "Deze week";	break;
       case "this month":	$s = "Deze maand";	break;

       case "generate printer-friendly version":
	$s = "Genereer een printer-vriendelijke versie";	break;

       case "printer friendly":		$s = "Printer-vriendelijk";	break;

       case "you have not entered a\\nbrief description":
	$s = "U hebt geen korte \\nomschrijving ingevoerd";	break;

       case "you have not entered a\\nvalid time of day.":
	$s = "U hebt geen geldig \\ntijdstip ingevoerd.";	break;

       case "are you sure\\nyou want to\\ndelete this entry ?":
	$s = "Weet u zeker dat u \\ndeze afspraak wilt verwideren?";	break;

       case "participants":		$s = "Deelnemers";	break;
       case "calendar - edit":	$s = "Kalendar - Bewerken";	break;
       case "calendar - add":	$s = "Kalendar - Toevoegen";	break;
       case "brief description":$s = "Korte omschrijving";break;
       case "full description":	$s = "Volledige omschrijving";break;
       case "duration":			$s = "Duur";		break;
       case "minutes":			$s = "minuten";			break;
       case "repeat type":		$s = "Terugkeer-patroon";		break;
       case "none":				$s = "Geen";			break;
       case "daily":			$s = "Dagelijks";			break;
       case "weekly":			$s = "Wekelijks";			break;
       case "monthly (by day)":	$s = "Maandelijks (op dag)";break;
       case "monthly (by date)":$s = "Maandelijks (op datum)";break;
       case "yearly":			$s = "Jaarlijks";	break;
       case "repeat end date":	$s = "Einddatum terugkeerpatroon";	break;
       case "use end date":		$s = "Gebruik einddatum";	break;
       case "repeat day":		$s = "Herhaal dag";		break;
       case "(for weekly)":		$s = "(voor wekelijks)";	break;
       case "frequency":		$s = "Frequentie";		break;
       case "sun":				$s = "Zo";				break;
       case "mon":				$s = "Ma";				break;
       case "tue":				$s = "Di";				break;
       case "wed":				$s = "Wo";				break;
       case "thu":				$s = "Do";				break;
       case "fri":				$s = "Vr";				break;
       case "sat":				$s = "Za";				break;
       case "su":				$s = "Zo";				break;
       case "m":				$s = "Ma";				break;
       case "t":				$s = "Di";				break;
       case "w":				$s = "Wo";				break;
       case "t":				$s = "Do";				break;
       case "f":				$s = "Vr";				break;
       case "sa":				$s = "Za";				break;
        case "search results":	$s = "Zoek resultaten";	break;
       case "no matches found.":$s = "Geen item gevonden.";break;
       case "1 match found":	$s = "1 item gevonden";	break;
       case "x matches found":	$s = "$m1 items gevonden";break;
       case "description":		$s = "Omschrijving";		break;
       case "repetition":		$s = "Terugkeerpatroon";		break;
       case "days repeated":	$s = "dagen herhaald";	break;
       case "go!":				$s = "Doen!";				break;
       case "year":				$s = "Jaar";			break;
       case "month":			$s = "Maand";			break;
       case "week":				$s = "Week";			break;
       case "new entry":		$s = "Nieuwe afspraak";		break;
       case "view this entry":	$s = "Bekijk deze afspraak";	break;

       case "the following conflicts with the suggested time:<ul>x</ul>":
	$s = "Het volgende levert een conflict op met de aangegeven tijd:<ul>$m1</ul>";	break;

       case "your suggested time of <B> x - x </B> conflicts with the following existing calendar entries:":
	$s = "De door u opgegeven tijd <B> $m1 - $m2 </B> levert een confilct op met de volgende afspraken:";	break;

       case "you must enter one or more search keywords":
	$s = "U moet een of meer trefwoorden opgeven om op te zoeken";	break;

       case "are you sure\\nyou want to\\ndelete this entry ?\\n\\nthis will delete\\nthis entry for all users.":	
     $s = "Weet u zeker dat \\nu deze afspraak \\nwilt verwijderen?\\n \\nDit zal deze \\nafspraak voor alle \\ngebruikers verwijderen.";	break;

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