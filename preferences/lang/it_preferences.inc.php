<?php

  function lang_pref($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "max matchs per page":
	$s = "Numero massimo di risultati per pagina";			break;

       case "time zone offset":
	$s = "Differenza di fuso orario";				break;

       case "this server is located in the x timezone":
	$s = "Questo server è situato nel fuso orario $m1";		break;

       case "date format":	$s = "Formato data";			break;
       case "time format":	$s = "Formato orario";			break;
       case "language":		$s = "Lingua";				break;

       case "show text on navigation icons":
	$s = "Mostra il testo nella barra di navigazione";		break;

       case "show current users on navigation bar":
	$s = "Mostra gli utenti collegati nella barra di navigazioner";	break;

       case "show new messages on main screen":
	$s = "Mostra i nuovi messaggi sullo schremo principale";	break;

       case "email signature":
	$s = "Firma dell'E-Mail";					break;

       case "show birthday reminders on main screen":
	$s = "Mostra sullo schermo i promemoria dei compleanni";	break;

       case "show high priority events on main screen":
	$s = "Mostra sullo schermo gli eventi a priorità alta";		break;

       case "weekday starts on":
	$s = "La settiman inizia il";					break;

       case "work day starts on":
	$s = "La giornata lavorativa inizia alle";			break;

       case "work day ends on":
	$s = "La giornata lavorativa finisce alle";			break;

       case "select headline news sites":
	$s = "Seleziona i siti da cui prendere le notizie";		break;

       case "change your password":
	$s = "Cambia la password";					break;

       case "select different theme":
	$s = "Seleziona un tema differente";				break;

       case "change your settings":
	$s = "Cambia le preferenze";					break;

       case "enter your new password":
	$s = "Inserisci la nuova password";				break;

       case "re-enter your password":
	$s = "Reinserisci la password";					break;

       case "the two passwords are not the same":
	$s = "Le due password non sono uguali";				break;

       case "you must enter a password":
	$s = "Devi inserire una password";				break;

       case "your current theme is: x":
	$s = "il tuo tema attuale è: " . $m1;				break;

       case "please, select a new theme":
	$s = "per favore, seleziona un tema";				break;

       case "note: this feature does *not* change your email password. this will need to be done manually.":
	$s = "Nota: Questa funzione *non* cambia la tua password di posta elettronica. Deve essere fatto manualmente.";	break;

       default: $s = "* $message";
    }
    return $s;
  }
?>