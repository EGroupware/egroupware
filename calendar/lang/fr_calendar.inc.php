<?php

  function lang_calendar($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "today":		$s = "Aujourd'hui";	break;
       case "this week":	$s = "Cette semaine";	break;
       case "this month":	$s = "Ce mois";	break;

       case "generate printer-friendly version":
	$s = "G&eacute;n&eacute;rer une version imprimable";	break;

       case "printer friendly":		$s = "Version imprimable";	break;

       case "you have not entered a\\nbrief description":
	$s = "Vous n'avez pas saisi\\de Description R&eacute;sum&eacute;";	break;

       case "you have not entered a\\nvalid time of day.":
	$s = "Vous n'avez pas saisi\\nune heure valide.";	break;

       case "are you sure\\nyou want to\\ndelete this entry ?":
	$s = "&ecirc;tes vous certain\\nde vouloir\\nsupprimer cette entr&eacute; ?";	break;

       case "participants":		$s = "Participants";	break;
       case "calendar - edit":	$s = "Calendrier - Edition";	break;
       case "calendar - add":	$s = "Calendrier - Ajout";	break;
       case "brief description":$s = "Description R&eacute;sum&eacute;";break;
       case "full description":	$s = "Description Compl&egrave;te";break;
       case "duration":			$s = "Dur&eacute;e";		break;
       case "minutes":			$s = "minutes";			break;
       case "repeat type":		$s = "Type de r&eacute;p&eacute;tition";		break;
       case "none":				$s = "Aucun";			break;
       case "daily":			$s = "Quotidien";			break;
       case "weekly":			$s = "Hebdomadaire";			break;
       case "monthly (by day)":	$s = "Mensuel (par jour)";break;
       case "monthly (by date)":$s = "Mensuel (par date)";break;
       case "yearly":			$s = "Annuel";	break;
       case "repeat end date":	$s = "Date de fin de r&eacute;p&eacute;tition";	break;
       case "use end date":		$s = "Utiliser la date de fin";	break;
       case "repeat day":		$s = "Jour de r&eacute;p&eacute;tition";		break;
       case "(for weekly)":		$s = "(pour hebdomadaire)";	break;
       case "frequency":		$s = "Fr&eacute;quence";		break;
       case "sun":				$s = "Dim";				break;
       case "mon":				$s = "Lun";				break;
       case "tue":				$s = "Mar";				break;
       case "wed":				$s = "Mer";				break;
       case "thu":				$s = "Jeu";				break;
       case "fri":				$s = "Ven";				break;
       case "sat":				$s = "Sam";				break;
       case "search results":	$s = "R&eacute;sultats de la recherche";	break;
       case "no matches found.":$s = "Aucune correspondance trouv&eacute;e.";break;
       case "1 match found":	$s = "1 r&eacute;sultat trouv&eacute;";	break;
       case "x matches found":	$s = "$m1 r&eacute;sultats trouv&eacute;";break;
       case "description":		$s = "Description";		break;
       case "repetition":		$s = "R&eacute;p&eacute;tition";		break;
       case "days repeated":	$s = "jours r&eacute;p&eacute;t&eacute;s";	break;
       case "go!":				$s = "Go!";				break;
       case "year":				$s = "Ann&eacute;e";			break;
       case "month":			$s = "Mois";			break;
       case "week":				$s = "Semaine";			break;
       case "new entry":		$s = "Nouvelle entr&eacute;e";		break;
       case "view this entry":	$s = "Voir cette entr&eacute;e";	break;

       case "the following conflicts with the suggested time:<ul>x</ul>":
	$s = "Les entr&eacute;es suivantes entrent en conflit avec l'heure propos&eacute;e :<ul>$m1</ul>";	break;

       case "your suggested time of <B> x - x </B> conflicts with the following existing calendar entries:":
	$s = "La plage horaire <B> $m1 - $m2 </B> que vous proposez entre en conflit avec les entr&eacute;es suivantes du calendrier :";	break;

       case "you must enter one or more search keywords":
	$s = "Vous devez entrer au moins un mot clef pour la recherche";	break;

       case "are you sure\\nyou want to\\ndelete this entry ?\\n\\nthis will delete\\nthis entry for all users.":
	$s = "&Eacute;tes vous certain\\nde vouloir\\nsupprimer cette entr&eacute;e ?\\n\\nCeci d&eacute;truira\\ncette entr&eacute;e pour tous les utilisateurs.";	break;

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
