<?php

  function lang_addressbook($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "address book":     $s = "Carnet d'adresse";    break;
       case "last name":        $s = "Nom";       break;
       case "first name":       $s = "Pr&eacute;nom";      break;
       case "e-mail":           $s = "E-Mail";          break;
       case "home phone":       $s = "T&eacute;l&eacute;phone Personnel";      break;
       case "fax":              $s = "Fax";             break;
       case "work phone":       $s = "T&eacute;l&eacute;phone Professionnel";      break;
       case "pager":            $s = "Pager";           break;
       case "mobile":           $s = "T&eacute;l&eacute;phone Portable";          break;
       case "other number":     $s = "Autre num&eacute;ro";    break;
       case "street":           $s = "Rue";          break;
       case "birthday":         $s = "Anniversaire";        break;
       case "city":             $s = "Ville";            break;
    case "state":            $s = "Etat";           break;//doesn't exist in france !!!
       case "zip code":         $s = "Code Postal";        break;
       case "notes":            $s = "Notes";           break;

       default: $s = "* ". $message;
    }
    return $s;
  }
