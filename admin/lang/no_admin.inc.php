<?php

  function lang_admin($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "last x logins":			$s = "Siste $m1 logins";	break;
       case "loginid":				$s = "LoginID";			break;
       case "ip":					$s = "IP";				break;
       case "total records":			$s = "Total historie";		break;
       case "user accounts":			$s = "Bruker accounts";		break;
       case "new group name":			$s = "Nytt gruppe navn";		break;
       case "create group":			$s = "Lag Gruppe";		break;
       case "kill":				$s = "Avslutt";				break;
       case "idle":				$s = "idle";				break;
       case "login time":			$s = "Login Tid";			break;
       case "anonymous user":			$s = "Anonym bruker";		break;
       case "account active":			$s = "Account aktiv";		break;
       case "re-enter password": 		$s = "Skriv inn passord igjen";	break;
       case "group name": 			$s = "Gruppe Navn";			break;
       case "display":				$s = "Vis";				break;
       case "base url":				$s = "Basis URL";			break;
       case "news file":			$s = "Nyhets Fil";			break;
       case "minutes between reloads":	$s = "Minutter mellom Reloads";		break;
       case "listings displayed":		$s = "Lister vist";		break;
       case "news type":			$s = "Nyhets Type";			break;
       case "user groups":			$s = "Bruker Grupper";			break;
       case "headline sites":			$s = "Headline Siter";		break;
       case "site":				$s = "Site";				break;
       case "view sessions":			$s = "Vis sessions";		break;
       case "view access log":		$s = "Vis Access Log";		break;
       case "active":				$s = "Aktiv";				break;
       case "disabled":				$s = "Deaktivert";			break;
       case "last time read":			$s = "Lest siste gang";		break;
       case "manager":			$s = "Manager";		break;

       case "are you sure you want to delete this group ?":
	$s = "Er du sikker på du vil slette denne gruppen?"; break;

       case "are you sure you want to kill this session ?":
	$s = "Er du sikker på at du vil avslutte denne session?"; break;

       case "all records and account information will be lost!":
	$s = "All historie og brukerinformasjon vil gå tapt!";	break;

       case "are you sure you want to delete this account ?":
	$s = "Er du sikker på at du vil slette denne account?";	break;

       case "are you sure you want to delete this news site ?":
	$s = "Er du sikker på at du vil slette denne nyhets siten?";		break;

       case "percent of users that logged out":
	$s = "Prosent av brukere som logget ut";			break;

       case "list of current users":
	$s = "liste over brukere";						break;

       case "new password [ leave blank for no change ]":
	$s = "Nytt passord [ Ingenting hvis ingen forandring ]";	break;

       case "The two passwords are not the same":
	$s = "Passordene er ikke de sammme";			break;

       case "the login and password can not be the same":
	$s = "Loging og passord kan ikke være det samme";	break;

       case "You must enter a password":	$s = "Du må skrive inn et passord";		break;

       case "that loginid has already been taken":
	$s = "Den loginID er opptatt";			break;

       case "you must enter a display":		$s = "Du må skrive inn et display";		break;
       case "you must enter a base url":	$s = "Du må skrive inn en base url";		break;
       case "you must enter a news url":	$s = "Du må skrive inn en nyhets url";		break;

       case "you must enter the number of minutes between reload":
	$s = "Du må skrive inn antallet minutter mellom reload";		break;

       case "you must enter the number of listings display":
	$s = "Du må skrive inn antallet visninger";		break;

       case "you must select a file type":
	$s = "Du må velge en filtype";					break;

       case "that site has already been entered":
	$s = "Den siten har allerede blitt brukt";			break;

       default: $s = "* $message";
    }
    return $s;
  } 
?>