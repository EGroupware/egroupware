<?php

  function lang_calendar($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "today":		$s = "Oggi";		break;
       case "this week":	$s = "Questa settimana"; break;
       case "this month":	$s = "Questo mese";	break;

       case "generate printer-friendly version":
	$s = "Genera versione per Stampante";	break;

       case "printer friendly":		$s = "Per Stampa";	break;

       case "you have not entered a\\nbrief description":
	$s = "Non hai inserito una a\\nBreve Descrizione";	break;

       case "you have not entered a\\nvalid time of day.":
	$s = "Hai inserito un a\\norario non valido.";		break;

       case "Are you sure\\nyou want to\\ndelete this entry ?":
	$s = "sei sicuro\\ndi voler cancellare\\nquesta nota ?";	break;

       case "participants":		$s = "Partecipanti";		break;
       case "calendar - edit":		$s = "Calendario - Modifica";	break;
       case "calendar - add":		$s = "Calendario - Aggiungi";	break;
       case "brief description":	$s = "Breve Descrizione";	break;
       case "full description":		$s = "Descrizione Completa";	break;
       case "duration":			$s = "Durata";			break;
       case "minutes":			$s = "minuti";			break;
       case "repeat type":		$s = "Tipo di ripetizione";	break;
       case "none":			$s = "Nessuna";			break;
       case "daily":			$s = "giornaliera";		break;
       case "weekly":			$s = "settimanale";		break;
       case "monthly (by day)":		$s = "mensile (per giorno)";	break;
       case "monthly (by date)":	$s = "mensile (per data)";	break;
       case "yearly":			$s = "Annuale";			break;
       case "repeat end date":		$s = "Data fine ripetizioni";	break;
       case "use end date":		$s = "Usa data finale";		break;
       case "repeat day":		$s = "Giorno ripetizione";	break;
       case "(for weekly)":		$s = "(per settimanale)";	break;
       case "frequency":		$s = "Frequeza";		break;
       case "sun":			$s = "Dom";			break;
       case "mon":			$s = "Lun";			break;
       case "tue":			$s = "Mar";			break;
       case "wed":			$s = "Mer";			break;
       case "thu":			$s = "Giov";			break;
       case "fri":			$s = "Ven";			break;
       case "sat":			$s = "Sab";			break;
       case "search results":		$s = "Risultati ricerca";	break;
       case "no matches found.":	$s = "Nessuna occorrenza trovata.";	break;
       case "1 match found":		$s = "Trovata 1 occorrenza";	break;
       case "x matches found":		$s = "$m1 occorrenze trovate";	break;
       case "description":		$s = "Descrizione";		break;
       case "repetition":		$s = "Ripetizione";		break;
       case "days repeated":		$s = "giorni ripetizione";	break;
       case "go!":			$s = "Vai!";			break;
       case "year":			$s = "Anno";			break;
       case "month":			$s = "Mese";			break;
       case "week":			$s = "Settimana";		break;
       case "new entry":		$s = "Nuovo appuntamento";	break;
       case "view this entry":		$s = "Visualizza questo appuntamento";	break;

       case "the following conflicts with the suggested time:<ul>x</ul>":
	$s = "Il seguente è in conflitto con l'orario suggerito:<ul>$m1</ul>";	break;

       case "Your suggested time of <B> x - x </B> conflicts with the following existing calendar entries:":
	$s = "L'orario da te suggerito: <B> $m1 - $m2 </B> è in conflitto con i seguenti appuntamenti in calendario:"; break;

       case "you must enter one or more search keywords":
	$s = "Devi specificare una o più parole chiave per la ricerca";	break;

       case "are you sure\\nyou want to\\ndelete this entry ?\\n\\nthis will delete\\nthis entry for all users.":
	$s = "Sei sicuro\\ndi voler\\ncancellare questo appuntamento ?\\n\\nQuesta azione lo cancellerà per\\ntutti gli utenti."; break;

       default: $s = "* $message";
    }
    return $s;
  }

?>