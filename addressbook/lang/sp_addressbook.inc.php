<?php

  function lang_addressbook($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "address book":     $s = "Libreta de direcciones";    break;
       case "last name":        $s = "Apellido";       break;
       case "first name":       $s = "Nombre";      break;
       case "e-mail":           $s = "E-Mail";          break;
       case "home phone":       $s = "Tel.Particular";      break;
       case "fax":              $s = "Fax";             break;
       case "work phone":       $s = "Tel.Trabajo";      break;
       case "pager":            $s = "Pager";           break;
       case "mobile":           $s = "Tel.Celular";          break;
       case "other number":     $s = "Otro Numero";    break;
       case "street":           $s = "Calle";          break;
       case "birthday":         $s = "Cumpleaños";        break;
       case "city":             $s = "Ciudad";            break;
       case "state":            $s = "Estado";           break;
       case "zip code":         $s = "Codigo Postal";        break;
       case "notes":            $s = "Notas";           break;
       case "company name":    $s = "Compania";  break;

       default: $s = "<b>*</b> ". $message;
    }
    return $s;
  }
?>