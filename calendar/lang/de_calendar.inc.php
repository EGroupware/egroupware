<?php

  function lang_calendar($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "today":		$s = "Heute";		break;
       case "this week":	$s = "Diese Woche";	break;
       case "this month":	$s = "Dieser Monat";	break;

       case "generate printer-friendly version":
	$s = "Drucker-freundliche Version erzeugen";	break;

       case "printer friendly":		$s = "Drucker-freundlich";	break;

       case "you have not entered a\\nbrief description":
	$s = "Sie haben keine a\\nKurzbeschreibung eingegeben";	break;

       case "you have not entered a\\nvalid time of day.":
	$s = "Sie haben keine a\\ng&uuml;ltige Tageszeit eingegeben.";	break;

       case "Are you sure\\nyou want to\\ndelete this entry ?":
	$s = "Sind Sie sicher,\\nda&szlig; Sie diesen\\nEintrag l&ouml;schen wollen ?";	break;

       case "participants":		$s = "Teilnehmer";	break;
       case "calendar - edit":	$s = "Kalender - Edit";	break;
       case "calendar - add":	$s = "Kalender - Add";	break;
       case "brief description":$s = "Kurzbeschreibung";break;
       case "full description":	$s = "vollst&auml;ndige Beschreibung";	break;
       case "duration":			$s = "Dauer";			break;
       case "minutes":			$s = "Minuten";			break;
       case "repeat type":		$s = "Wiederholungstyp";	break;
       case "none":				$s = "Keiner";		break;
       case "daily":			$s = "T&auml;glich";		break;
       case "weekly":			$s = "W&ouml;chentlich";	break;
       case "monthly (by day)":	$s = "Monatlich (nach Wochentag)";	break;
       case "monthly (by date)":$s = "Monatlich (nach Datum)";		break;
       case "yearly":			$s = "J&auml;hrlich";		break;
       case "repeat end date":	$s = "Enddatum";			break;
       case "use end date":		$s = "Enddatum benutzen";	break;
       case "repeat day":		$s = "Wiederholungstag";	break;
       case "(for weekly)":		$s = "(f&uuml;r w&ouml;chentlich)"; break;
       case "frequency":		$s = "H&auml;ufigkeit";		break;
       case "sun":				$s = "So";		break;
       case "mon":				$s = "Mo";		break;
       case "tue":				$s = "Di";		break;
       case "wed":				$s = "Mi";		break;
       case "thu":				$s = "Do";		break;
       case "fri":				$s = "Fr";		break;
       case "sat":				$s = "Sa";		break;
       case "su":				$s = "Su";		break;
       case "mo":				$s = "Mo";		break;
       case "tu":				$s = "Di";		break;
       case "we":				$s = "Mi";		break;
       case "th":				$s = "Do";		break;
       case "fr":				$s = "Fr";		break;
       case "sa":				$s = "Sa";		break;
       case "search results":	$s = "Suchergebnisse";			break;
       case "no matches found.":$s = "Keine Treffer gefunden.";		break;
       case "1 match found":	$s = "1 Treffer gefunden";		break;
       case "x matches found":	$s = "$m1 Treffer gefunden";		break;
       case "description":		$s = "Beschreibung";		break;
       case "repetition":		$s = "Repetition";		break;
       case "days repeated":	$s = "wiederholte Tage";		break;
       case "go!":				$s = "Go!";		break;
       case "year":				$s = "Jahr";		break;
       case "month":			$s = "Monat";			break;
       case "week":				$s = "Woche";		break;
       case "new entry":		$s = "Neuer Eintrag";		break;
       case "view this entry":	$s = "Diesen Eintrag anzeigen";		break;

       case "the following conflicts with the suggested time:<ul>x</ul>":
	$s = "Es gibt folgende &Uuml;berschneidungen mit dem angegebenen Termin:<ul>$m1</ul>";	break;

       case "your suggested time of <B> x - x </B> conflicts with the following existing calendar entries:";
        $s = "Der angegebene Zeitraum von <B> $m1 - $m2 </B> steht in Konflikt mit bestehenden Terminen:";	break;

       case "you must enter one or more search keywords":
	$s = "Sie m&uuml;ssen einen oder mehrere Suchbegriffe angeben";	break;

       case "are you sure\\nyou want to\\ndelete this entry ?\\n\\nthis will delete\\nthis entry for all users.":
         $s = "Sind Sie sicher, da&szlig; Sie diesen Eintrag l&ouml;schen wollen ?\\n\\nDies l&ouml;scht diesen Eintrag f&uuml;r alle Benutzer..";
         break;

       case "":		$s = "";	break;
       case "":		$s = "";	break;
       case "":		$s = "";	break;
       case "":		$s = "";	break;
       case "":		$s = "";	break;
       case "":		$s = "";	break;

       default: $s = "* ". $message;
    }
    return $s;
  }
?>