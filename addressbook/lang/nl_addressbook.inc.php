<?php

  function lang_addressbook($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "address book":     $s = "Adresboek";    break;
       case "last name":        $s = "Achteraam";       break;
       case "first name":       $s = "Voornaam";      break;
       case "e-mail":           $s = "E-Mail";          break;
       case "home phone":       $s = "Telefoon privé";      break;
       case "fax":              $s = "Fax";             break;
       case "work phone":       $s = "Telefoon werk";      break;
       case "pager":            $s = "Pieper";           break;
       case "mobile":           $s = "Telefoon Mobiel";          break;
       case "other number":     $s = "Ander nummer";    break;
       case "street":           $s = "Straat";          break;
       case "birthday":         $s = "Verjaardag";        break;
       case "city":             $s = "Stad";            break;
       case "state":            $s = "Provincie";           break;
       case "zip code":         $s = "Postcode";        break;
       case "notes":            $s = "Opmerkingen";           break;

       default: $s = "<b>*</b> ". $message;
    }
    return $s;
  }
