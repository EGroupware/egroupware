<?php

  function lang_pref($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "max matchs per page":
	$s = "Maximale Treffer pro Seite";		break;
       
       case "time zone offset":
	$s = "Zeitzonen Differenz";			break;
       
       case "this server is located in the x timezone":
	$s = "Dieser Server befindet sich in der ZeitZone \"" . $m1. "\"";	break;
       
       case "date format":	$s = "Datumsformat";			break;
       case "time format":	$s = "Zeitformat";			break;
       case "language":		$s = "Sprache";				break;

       case "default sorting order":	$s = "Standard-Sortierung";	break;
       case "default application":		$s = "Standard-Anwendung";	break;

       case "show text on navigation icons":
	$s = "Text zu Icons in der Navigations-Leiste anzeigen";	break;
       
       case "show current users on navigation bar":
	$s = "Anzahl gegenw&auml;rtiger User in der Navigationsleiste anzeigen";	break;
       
       case "show new messages on main screen":
	$s = "Nach dem Login neue Nachrichten anzeigen";	break;
       
       case "email signature":
	$s = "E-Mail Signatur";	break;
       
       case "show birthday reminders on main screen":
	$s = "Nach dem Login Geburtstags-Mahner anzeigen";	break;
       
       case "show high priority events on main screen":
	$s = "Nach dem Login Ereignisse mit hoher Priorit&auml;t anzeigen";	break;
       
       case "weekday starts on":
	$s = "Arbeitswoche beginnt am";	break;
       
       case "work day starts on":
	$s = "Arbeitstag beginnt um";	break;
       
       case "work day ends on":
	$s = "Arbeitstag endet um";	break;
       
       case "select headline news sites":
	$s = "Sites f&uuml;r Schlagzeilen ausw&auml;hlen";	break;
       
       case "change your password":
	$s = "Passwort &auml;ndern";		break;

       case "select different theme":
	$s = "anderes Schema w&auml;hlen";		break;

       case "change your settings":
	$s = "Einstellungen &auml;ndern";		break;

       case "change your profile":
	$s = "Profil &auml;ndern";		break;

       case "enter your new password":
	$s = "Neues Passwort eingeben";		break;

       case "re-enter your password":
	$s = "Neues Passwort wiederholen";	break;

       case "the two passwords are not the same":
	$s = "Die Eingaben stimmen nicht &uuml;berein";	break;

       case "you must enter a password":
	$s = "Sie m&uuml;ssen ein Passwort angeben";	break;

       case "your current theme is: x":
	$s = "Ihr gegenw&auml;rtiges Schema ist: <b>" . $m1 ."</b>";	break;

       case "please, select a new theme":
	$s = "W&auml;hlen Sie bitte ein neues Schema";	break;

       case "note: this feature does *not* change your email password. this will need to be done manually.":
	$s = "Anmerkung: Ihr EMail Passwort wird durch diese Funktion *NICHT* angepasst. Dies m&uuml;ssen Sie manuell tun.";	break;

       case "monitor newsgroups":
	$s = "Newsgroups &uuml;berwachen";	break;


       default: $s = "* ". $message;
    }
    return $s;
  }


