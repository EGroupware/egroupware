<?php

  function lang_admin($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "last x logins":	$s = "Les $m1 derniers logins";		break;
       case "loginid":			$s = "LoginID";				break;
       case "ip":				$s = "IP";					break;
       case "total records":	$s = "Nombre total d'enregistrements";		break;
       case "user accounts":	$s = "Comptes utilisateurs";		break;
       case "new group name":	$s = "Nouveau nom de groupe";		break;
       case "create group":		$s = "Cr&eacute;er un groupe";		break;
       case "kill":				$s = "Tuer";				break;
       case "idle":				$s = "inactivit&eacute;";				break;
       case "login time":		$s = "Heure d'entr&eacute;e";			break;
       case "anonymous user":	$s = "Utilisateur anonyme";		break;
       case "account active":	$s = "Compte actif";		break;
       case "re-enter password": $s = "Re-saisissez le mot de passe";	break;
       case "group name": 		$s = "Nom de groupe";			break;
       case "display":			$s = "Afficher";				break;
       case "base url":			$s = "URL de base";			break;
       case "news file":		$s = "Fichier de News";			break;
       case "minutes between reloads":	$s = "Minutes entre chaque recharge";		break;
       case "listings displayed":	$s = "Affichage en listing";		break;
       case "news type":		$s = "Type de News";			break;
       case "user groups":		$s = "Groupes d'utilisateurs";			break;
       case "headline sites":	$s = "Sites d'Headline";		break;
       case "site":				$s = "Site";				break;
       case "view sessions":	$s = "Voir les sessions";		break;
       case "view access log":	$s = "Voir les log d'acc&egrave;s";		break;
       case "active":			$s = "Actif";				break;
       case "disabled":			$s = "D&eacute;sactiv&eacute;";			break;
       case "last time read":	$s = "Heure de derni&egrave;re lecture";		break;
       case "manager":			$s = "Manager";		break;

       case "are you sure you want to delete this group ?":
	$s = "Voulez-vous vraiment supprimer ce groupe ?"; break;

       case "are you sure you want to kill this session ?":
	$s = "Voulez-vous vraiment tuer cette session ?"; break;

       case "all records and account information will be lost!":
	$s = "Tous les enregistrements et le compte vont &ecirc;tre perdus !";	break;

       case "are you sure you want to delete this account ?":
	$s = "Voulez-vous vraiment supprimer ce compte ?";	break;

       case "are you sure you want to delete this news site ?":
	$s = "Voulez-vous vraiment supprimer ce site de news ?";		break;

       case "percent of users that logged out":
	$s = "Pourcentage d'utilisateurs qui se sont d&eacute;logu&eacute;s";			break;

       case "list of current users":
	$s = "liste des utilisateurs connect&eacute;s en ce moment";						break;

       case "new password [ leave blank for no change ]":
	$s = "Nouveau mot de passe [ Laissez vide pour ne rien changer ]";	break;

       case "the two passwords are not the same":
	$s = "Les deux mots de passe ne sont pas identiques";			break;

       case "the login and password can not be the same":
	$s = "Le login et le mot de passe ne peuvent pas &ecirc;tre identiques";	break;

       case "you must enter a password":	$s = "Vous devez entrer un mot de passe";		break;

       case "that loginid has already been taken":
	$s = "Ce login est d&eacute;j&agrave; utlis&eacute;";			break;

       case "you must enter a display":		$s = "Vous devez entrer un affichage";		break;
       case "you must enter a base url":	$s = "Vous devez entrer une URL de base";		break;
       case "you must enter a news url":	$s = "Vous devez entrer une URL de news";		break;

       case "you must enter the number of minutes between reload":
	$s = "Vous devez entrer un le nombre de minutes entre chaque recharge";		break;

       case "you must enter the number of listings display":
	$s = "Vous devez entrer le nombre d'entit&eacute;s affich&eacute; dans chaque listing";		break;

       case "you must select a file type":
	$s = "Vous devez entrer un type de fichier";					break;

       case "that site has already been entered":
	$s = "Ce site a d&eacute;j&agrave; &eacute;t&eacute; entr&eacute;";			break;

       default: $s = "* ". $message;
    }
    return $s;
  }
?>