<?php

  function lang_addressbook($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "address book":     $s = "Addressbuch";	break;
       case "last name":        $s = "Name";		break;
       case "first name":       $s = "Vorname";		break;
       case "e-mail":           $s = "E-Mail";		break;
       case "home phone":       $s = "Tel privat";	break;
       case "fax":              $s = "Fax";		break;
       case "work phone":       $s = "Tel dienstl.";	break;
       case "pager":            $s = "Pager";		break;
       case "mobile":           $s = "Mobil";		break;
       case "other number":     $s = "andere Nr.";	break;
       case "street":           $s = "Stra&szlig;e";	break;
       case "birthday":         $s = "Geburtstag";	break;
       case "city":             $s = "Stadt";		break;
       case "state":            $s = "Land";		break;
       case "zip code":         $s = "PLZ";		break;
       case "notes":            $s = "Notizen";		break;
       case "company name": 	$s = "Firma"; break;

       default: $s = "* " . $message;
    }
    return $s;
  }

?>