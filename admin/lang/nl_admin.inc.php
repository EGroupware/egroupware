<?php

  function lang_admin($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "last x logins":	$s = "Laatste $m1 logins";		break;
       case "loginid":			$s = "LoginID";				break;
       case "ip":				$s = "IP-adres";					break;
       case "total records":	$s = "Totaal aantal records";		break;
       case "user accounts":	$s = "Gebruiker accounts";		break;
       case "new group name":	$s = "Nieuwe groepsnaam";		break;
       case "create group":		$s = "Maak Groep";		break;
       case "kill":				$s = "Verwijder";				break;
       case "idle":				$s = "niet actief";				break;
       case "login time":		$s = "Login tijd";			break;
       case "anonymous user":	$s = "Anonieme gebruiker";		break;
       case "manager":			$s = "Manager";				break;
       case "account active":	$s = "Account actief";		break;
       case "re-enter password": $s = "Voer wachtwoord opnieuw in";	break;
       case "group name": 		$s = "Groepsnaam";			break;
       case "display":			$s = "Weergave";				break;
       case "base url":			$s = "Basis-URL";			break;
       case "news file":		$s = "Nieuws-bestand";			break;
       case "minutes between reloads":	$s = "Minuten tussen herladen";		break;
       case "listings displayed":	$s = "Weergegeven lijsten";		break;
       case "news type":		$s = "Nieuwssoort";			break;
       case "user groups":		$s = "Gebruikersgroepen";			break;
       case "headline sites":	$s = "Headline Sites";		break;
       case "network news":	$s = "Netwerk nieuws";		break;
       case "site":				$s = "Site";				break;
       case "view sessions":	$s = "Bekijk sessies";		break;
       case "view access log":	$s = "Bekijk bezoek-logboek";		break;
       case "active":			$s = "Actief";				break;
       case "disabled":			$s = "Uitgeschakeld";			break;
       case "last time read":	$s = "Voor het laatst gelezen op";		break;
       case "manager":			$s = "Manager";		break;
       case "permissions":		$s = "Rechten";			break;

       case "are you sure you want to delete this group ?":
	$s = "Weet u zeker dat u deze groep wilt verwijderen ?"; break;

       case "are you sure you want to kill this session ?":
	$s = "Weet u zeker dat u deze sessie wilt beëindigen?"; break;

       case "all records and account information will be lost!":
	$s = "Alle records en account informatie zal verloren gaan!";	break;

       case "are you sure you want to delete this account ?":
	$s = "Weet u zeker dat u deze account wilt verwijderen ?";	break;

       case "are you sure you want to delete this news site ?":
	$s = "Weet u zeker dat u deze nieuws-site wilt verwijderen ?";		break;

      case "percent of users that logged out":
	$s = "Percentage gebruikers dat uitlogt";			break;

       case "list of current users":
	$s = "lijst van huidige gebruikers";						break;

       case "new password [ leave blank for no change ]":
	$s = "Nieuw wachtwoord [ Laat leeg om niet te wijzigen ]";	break;

       case "the two passwords are not the same":
	$s = "De twee wachtwoorden komen niet overeen";			break;

       case "the login and password can not be the same":
	$s = "De login en het wachtwoord mogen niet hetzelfde zijn";	break;

       case "you must enter a password":	$s = "U moet een wachtwoord invoeren";		break;

       case "that loginid has already been taken":
	$s = "Die gevraagde gebruikersnaam is reeds vergeven";			break;

       case "you must enter a display":		$s = "U moet een weergave opgeven";		break;
       case "you must enter a base url":	$s = "U moet een basis-url opgeven";		break;
       case "you must enter a news url":	$s = "U moet een nieuws-url opgeven";		break;

       case "you must enter the number of minutes between reload":
	$s = "U moet het aantal minuten tussen herladen opgeven";		break;

       case "you must enter the number of listings display":
	$s = "U moet het aantal weer te geven lijsten opgeven";		break;

       case "you must select a file type":
	$s = "U moet een bestandstype selecteren";					break;

       case "that site has already been entered":
	$s = "Die site is al ingevoerd";			break;

       case "select users for inclusion":
        $s = "Selecteer gebruikers";	break;

	case "sorry, the follow users are still a member of the group x":
        $s = "Sorry, de volgende gebruikers zijn nog lid van de groep $m1";	break;

       case "they must be removed before you can continue":
        $s = "Zij moeten verwijderd worden voor u verder kunt";	break;

       default: $s = "<b>*</b> ". $message;
    }
    return $s;
  }
?>
