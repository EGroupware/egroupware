<?php

  function lang_addressbook($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "last name":        $s = "Apellido";       break;
       case "first name":       $s = "Nombre";      break;
       case "E-Mail":           $s = "E-Mail";          break;
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

       default: $s = "<b>*</b> ". $message;
    }
    return $s;
  }
?>