<?php

  function lang_pref($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "max matchs per page":
	$s = "Max items per pagina";		break;
       
       case "time zone offset":
	$s = "Tijdzone verschil";		break;
       
       case "this server is located in the x timezone":
	$s = "Deze server bevind zich in de " . $m1 . " tijdzone";	break;
       
       case "date format":	$s = "Datum formaat";			break;
       case "time format":	$s = "Tijd formaat";			break;
       case "language":		$s = "Taal";			break;

       case "show text on navigation icons":
	$s = "Geef tekst weer op de navigatie iconen";			break;
       
       case "show current users on navigation bar":
	$s = "Geef het aantal huidige gebruikers weer op de navigatiebalk";	break;
       
       case "show new messages on main screen":
	$s = "Geef het aantal nieuwe berichten weer op het hoofdscherm";	break;
       
       case "email signature":
	$s = "E-Mail handtekening";	break;
       
       case "show birthday reminders on main screen":
	$s = "Geef verjaardags herinneringen op het hoofdscherm weer";	break;
       
       case "show high priority events on main screen":
	$s = "Geef evenementen met hoge prioriteit op het hoofdscherm weer";	break;
       
       case "weekday starts on":
	$s = "Eerste werkdag";	break;
       
       case "work day starts on":
	$s = "Werkdag begint om";	break;
       
       case "work day ends on":
	$s = "Werkdag eindigt om";	break;
       
       case "select headline news sites":
	$s = "Selecteer Headline Nieuws sites";	break;
       
       case "change your password":
	$s = "Verander uw wachtwoord";		break;

       case "select different theme":
	$s = "Selecteer een ander thema";		break;

       case "change your settings":
	$s = "Verander uw instellingen";		break;

       case "enter your new password":
	$s = "Verander uw wachtwoord";		break;

       case "re-enter your password":
	$s = "Voer uw wachtwoord nogmaals in";	break;

       case "the two passwords are not the same":
	$s = "TDe twee wachtwoorden komen niet overeen";	break;

       case "you must enter a password":
	$s = "U moet een wachtwoord opgeven";	break;

       case "your current theme is: x":
	$s = "Uw huidige thema is: <b>" . $m1 . "</b>";	break;

       case "please, select a new theme":
	$s = "Selecteer een nieuw thema";	break;

       case "note: this feature does *not* change your email password. this will need to be done manually.":
	$s = "Noot: Deze optie veranderd *niet* uw email-wachtwoord. Dit moet handmatig gebeuren.";	break;


       default: $s = "<b>*</b> ". $message;
    }
    return $s;
  }


