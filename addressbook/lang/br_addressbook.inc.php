<?php

  function lang_addressbook($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "address book":     $s = "Contatos";    break;
       case "last name":        $s = "Ultimo Nome";       break;
       case "first name":       $s = "Primeiro Nome";      break;
       case "e-mail":           $s = "E-Mail";          break;
       case "home phone":       $s = "Fone Residencial";      break;
       case "fax":              $s = "Fax";             break;
       case "work phone":       $s = "Fone Comercial";      break;
       case "pager":            $s = "Pager";           break;
       case "mobile":           $s = "Celular";          break;
       case "other number":     $s = "Outro Numero";    break;
       case "street":           $s = "Rua";          break;
       case "birthday":         $s = "Aniversario";        break;
       case "city":             $s = "Cidade";            break;
       case "state":            $s = "Estado";           break;
       case "zip code":         $s = "CEP";        break;
       case "notes":            $s = "Notas";           break;

       default: $s = "<b>*</b> ". $message;
    }
    return $s;
  }
