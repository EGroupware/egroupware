<?php

  function lang_pref($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "max matchs per page":
	$s = "Nombre max d'entit&eacute;s par page";		break;
       
       case "time zone offset":
	$s = "D&eacute;calage de la zone horaire";		break;
       
       case "this server is located in the x timezone":
	$s = "Ce serveur est situ&eacute dans la zone de temps $m1";	break;
       
       case "date format":	$s = "Format de date";			break;
       case "time format":	$s = "Format d'heure";			break;
       case "language":		$s = "Langage";			break;

       case "show text on navigation icons":
	$s = "Montrer le texte sur les icones de navigation";			break;
       
       case "show current users on navigation bar":
	$s = "Montrer les utlisteurs connect&eacute;s sur la barre de navigation";	break;
       
       case "show new messages on main screen":
	$s = "Montrer les nouveaux messages sur la page d'accueil";	break;
       
       case "email signature":
	$s = "Signature E-Mail";	break;
       
       case "show birthday reminders on main screen":
	$s = "Montrer les anniversaires sur la page d'accueil";	break;
       
       case "show high priority events on main screen":
	$s = "Montrer les &eacute;v&eacute;nements de haute priorit&eacute; sur la page d'accueil";	break;
       
       case "weekday starts on":
	$s = "Le premier jour de la semaine est";	break;
       
       case "work day starts on":
	$s = "La journ&eacute;e de travaille commence &agrave;";	break;
       
       case "work day ends on":
	$s = "La journ&eacute;e de travaille finit &agrave;";	break;
       
       case "select headline news sites":
	$s = "Choisissez les site d'Headlines";	break;
       
       case "change your password":
	$s = "Modifez votre mot de passe";		break;

       case "select different theme":
	$s = "Choisissez un theme diff&eacute;rent";		break;

       case "change your settings":
	$s = "Modifiez vos pr&eacute;f&eacute;rences";		break;

       case "enter your new password":
	$s = "Entrer votre mot de passe";		break;

       case "re-enter your password":
	$s = "Re-rentrer votre mot de passe";	break;

       case "the two passwords are not the same":
	$s = "Les deux mots de passes ne sont pas identiques";	break;

       case "you must enter a password":
	$s = "Vous devez entrer un mot de passe";	break;

       case "your current theme is: x":
	$s = "Votre theme courant est : <b>" . $m1 . "</b>";	break;

       case "please, select a new theme":
	$s = "Choisissez un nouveau theme";	break;

       case "note: this feature does *not* change your email password. this will need to be done manually.":
	$s = "Note: Ceci ne change pas le mot de passe de votre email. Ceci doit &ecirc;tre fait manuellement.";	break;


       default: $s = "* ". $message;
    }
    return $s;
  }


