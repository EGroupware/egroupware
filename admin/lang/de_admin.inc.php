<?php

  function lang_admin($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "last x logins":		$s = "Letze $m1 Logins";	break;
       case "loginid":			$s = "LoginID";			break;
       case "ip":			$s = "IP";			break;
       case "total records":		$s = "Anzahl Datens&auml;tze insgesamt";	break;
       case "user accounts":		$s = "Benutzerkonten";		break;
       case "new group name":		$s = "Neuer Gruppenname";	break;
       case "create group":		$s = "Erstelle Gruppe";		break;
       case "kill":			$s = "Kill";			break;
       case "idle":			$s = "idle";			break;
       case "login time":		$s = "Login Zeit";		break;
       case "anonymous user":		$s = "Anonymer User";		break;
       case "manager":			$s = "Manager";			break;
       case "account active":		$s = "Konto aktiv";		break;
       case "re-enter password":	$s = "Passwort wiederholen";	break;
       case "group name": 		$s = "Gruppenname";		break;
       case "display":			$s = "Bezeichnung";		break;
       case "base url":			$s = "Basis URL";		break;
       case "news file":		$s = "News File";		break;
       case "minutes between reloads":	$s = "Minuten zwischen Reloads"; break;
       case "listings displayed":	$s = "Zeilen maximal";		break;
       case "news type":		$s = "Nachrichtentyp";		break;
       case "user groups":		$s = "Benutzergruppen";		break;
       case "headline sites":		$s = "Sites f&uuml;r Schlagzeilen";	break;
       case "network news":		$s = "Network News";		break;
       case "site":			$s = "Site";			break;
       case "view sessions":		$s = "Sitzungen anzeigen";	break;
       case "view access log":		$s = "Access Log anzeigen";	break;
       case "active":			$s = "Aktiv";			break;
       case "disabled":			$s = "Deaktiviert";		break;
       case "last time read":		$s = "Zuletzt gelesen";		break;
       case "permissions":		$s = "Zugriffsrechte";		break;
       case "title":			$s = "Titel";			break;
       case "enabled":			$s = "Verf&uuml;gbar";		break;
       case "applications":		$s = "Anwendungen";		break;
       case "installed applications":	$s = "Installierte Anwendungen";	break;
       case "add new application":	$s = "Neue Anwendung hinzuf&uuml;gen";	break;
       case "application name":		$s = "Name der Anwendung";	break;
       case "application title":	$s = "Titel der Anwendung";	break;
       case "edit application":		$s = "Anwendung editieren";	break;

       case "you must enter an application name and title.":
	$s = "Sie m&uuml;ssen der Anwendung einen Namen und einen Titel geben.";	break;

       case "are you sure you want to delete this group ?":
	$s = "Sind Sie sicher, da&szlig; Sie diese Gruppe l&ouml;schen wollen ?"; break;

       case "are you sure you want to kill this session ?":
	$s = "Sind Sie sicher, da&szlig; Sie diese Session killen wollen ?"; break;

       case "all records and account information will be lost!":
	$s = "Alle Datens&auml;tze und Account Informationen sind dann verloren!";	break;

       case "are you sure you want to delete this account ?":
	$s = "Sind Sie sicher, da&szlig; Sie diesen Account l&ouml;schen wollen ?";	break;

       case "are you sure you want to delete this news site ?":
	$s = "Sind Sie sicher, diese News Site l&ouml;schen zu wollen ?";		break;

       case "percent of users that logged out":
	$s = "Prozent der User, die sich korrekt ausgelogged haben";			break;

       case "list of current users":
	$s = "Liste der gegenw&auml;rtigen User";						break;

       case "new password [ leave blank for no change ]":
	$s = "Neues Passwort [ Lassen Sie das Feld leer, wenn es nicht ge&auml;ndert werden soll ]";	break;

       case "the two passwords are not the same":
	$s = "Die Passworte stimmen nicht &uuml;berein";			break;

       case "the login and password can not be the same":
	$s = "Login und Passwort d&uuml;rfen nicht identisch sein";	break;

       case "you must enter a password":	$s = "Sie m&uuml;ssen ein Passwort eingeben";		break;

       case "that loginid has already been taken":
	$s = "Diese LoginID ist bereits vergeben";			break;

       case "you must enter a display":		$s = "Sie m&uuml;ssen einen Namen f&uuml;r die Site eingeben";		break;
       case "you must enter a base url":	$s = "Sie m&uuml;ssen eine Basis URL angeben";		break;
       case "you must enter a news url":	$s = "Sie m&uuml;ssen eine News URL angeben";		break;

       case "you must enter the number of minutes between reload":
	$s = "Sie m&uuml;ssen eine Anzahl von Minuten zwischen Reloads angeben";		break;

       case "you must enter the number of listings display":
	$s = "Sie m&uuml;ssen die Anzahl der anzuzeigenden Zeilen angeben";		break;

       case "you must select a file type":
	$s = "Sie m&uuml;ssen einen Filetyp ausw&auml;hlen";					break;

       case "that site has already been entered":
	$s = "Diese Site wurde bereits eingegeben";			break;

       case "select users for inclusion":
        $s = "W&auml;hlen Sie Benutzer f&uuml;r diese Gruppe aus";	break;

       case "sorry, the follow users are still a member of the group x":
        $s = "Sorry, die folgenden Benutzer sind noch Mitglied der Gruppe $m1";	break;

       case "they must be removed before you can continue":
        $s = "Sie m&uuml;ssen zuvor aus dieser entfernt werden";	break;

       default: $s = "* ". $message;
    }
    return $s;
  }
?>