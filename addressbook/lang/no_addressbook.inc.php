<?php

  function lang_addressbook($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "address book":     $s = "Addresse Bok";    break;
       case "last name":        $s = "Etternavn";       break;
       case "first name":       $s = "Fornavn";    	  break;
       case "E-Mail":           $s = "E-Post";          break;
       case "home phone":       $s = "Hjemme telefon";  break;
       case "fax":              $s = "Telefaks";        break;
       case "work phone":       $s = "Arbeids telefon"; break;
       case "pager":            $s = "Personsøker";     break;
       case "mobile":           $s = "Mobil";           break;
       case "other number":     $s = "Annet Nummer";    break;
       case "street":           $s = "Gate";            break;
       case "birthday":         $s = "Fødselsdag";      break;
       case "city":             $s = "By";              break;
       case "state":            $s = "Stat";            break;
       case "zip code":         $s = "Postnummer";      break;
       case "notes":            $s = "Annet";           break;

       default: $s = "* $message";
    }
    return $s;
  }