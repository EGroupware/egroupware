<?php

  function lang_admin($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "last x logins":		$s = "Ultimi $m1 login";	break;
       case "loginid":			$s = "Identificativo Login";	break;
       case "ip":			$s = "IP";			break;
       case "total records":		$s = "Totale registrazioni";	break;
       case "user accounts":		$s = "Account Utenti";		break;
       case "new group name":		$s = "Nuovo nome gruppo";	break;
       case "create group":		$s = "Crea Gruppo";		break;
       case "kill":			$s = "Termina";			break;
       case "idle":			$s = "inattivo";		break;
       case "login time":		$s = "Ora del Login";		break;
       case "anonymous user":		$s = "Utente anonimo";		break;
       case "account active":		$s = "Account inattivo";	break;
       case "re-enter password": 	$s = "Reinserisci la password"; break;
       case "group name": 		$s = "Nome Gruppo";		break;
       case "display":			$s = "Mostra";			break;
       case "base url":			$s = "URL di base";		break;
       case "news file":		$s = "File delle news";		break;
       case "minutes between reloads":	$s = "Minuti fra i caricamenti"; break;
       case "listings displayed":	$s = "Listings elencati";	break;
       case "news type":		$s = "Tipo di news";		break;
       case "user groups":		$s = "gruppi di utenti";	break;
       case "headline sites":		$s = "Siti con titoli";		break;
       case "site":			$s = "Sito";			break;
       case "view sessions":		$s = "Visualizza sessioni";	break;
       case "view access log":		$s = "Visualizza Log di accesso"; break;
       case "active":			$s = "Attivo";			break;
       case "disabled":			$s = "Disabilitato";		break;
       case "last time read":		$s = "Letto ultima volta";	break;
       case "manager":			$s = "Manager";			break;

       case "are you sure you want to delete this group ?":
	$s = "Sei sicuro di voler cancellare questo gruppo ?"; break;

       case "are you sure you want to kill this session ?":
	$s = "sei sicuro di voler eliminare questa sessione ?"; break;

       case "all records and account information will be lost!":
	$s = "Tutte le registrazioni e gli account andranno persi!";	break;

       case "are you sure you want to delete this account ?":
	$s = "Sei sicuro di voler cancellare questo account ?";	break;

       case "are you sure you want to delete this news site ?":
	$s = "Sei sicuro di voler cancellare questo sito di notizie ?";		break;

       case "percent of users that logged out":
	$s = "Percentuale di utenti che hanno terminato la connessione correttamente";	break;

       case "list of current users":
	$s = "lista degli utenti attivi adesso";				break;

       case "new password [ leave blank for no change ]":
	$s = "Nuova password [ Lascia vuoto se non vuoi cambiarla ]";		break;

       case "The two passwords are not the same":
	$s = "Tle due password non sono uguali";				break;

       case "the login and password can not be the same":
	$s = "Il nome utente e la passvord non possono essere uguali";		break;

       case "You must enter a password":	
	$s = "Devi inserire una password";					break;

       case "that loginid has already been taken":		
	$s = "Questo nome utente è già in uso";					break;

       case "you must enter a display":
	$s = "Devi inserire un display";					break;

       case "you must enter a base url":
	$s = "Devi inserire un URL di base";					break;

       case "you must enter a news url":
	$s = "Devi inserire un url con notizie";				break;

       case "you must enter the number of minutes between reload":
	$s = "Devi inserire il numero di minuti fra i caricamenti";		break;

       case "you must enter the number of listings display":
	$s = "Devi inserire il numero di display elencati";			break;

       case "you must select a file type":
	$s = "Devi selezionare un tipo di file";				break;

       case "that site has already been entered":
	$s = "Quel sito è già stato inserito";				break;

       default: $s = "* $message";
    }
    return $s;
  }
?>