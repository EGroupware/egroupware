<?php

  function lang_addressbook($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "address book":     $s = "Address Book";    break;
       case "last name":        $s = "Last Name";       break;
       case "first name":       $s = "First Name";      break;
       case "e-mail":           $s = "E-Mail";          break;
       case "home phone":       $s = "Home Phone";      break;
       case "fax":              $s = "Fax";             break;
       case "work phone":       $s = "Work Phone";      break;
       case "pager":            $s = "Pager";           break;
       case "mobile":           $s = "Mobile";          break;
       case "other number":     $s = "Other Number";    break;
       case "street":           $s = "Street";          break;
       case "birthday":         $s = "Birthday";        break;
       case "city":             $s = "City";            break;
       case "state":            $s = "State";           break;
       case "zip code":         $s = "ZIP Code";        break;
       case "notes":            $s = "Notes";           break;
       case "company name":		$s = "company name";	break;

       default: $s = "<b>*</b> ". $message;
    }
    return $s;
  }
