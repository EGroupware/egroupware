<?php

  function lang_admin($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "last x logins":  $s = "Ultimos $m1 logins";    break;
       case "loginid":      $s = "LoginID";        break;
       case "ip":        $s = "IP";          break;
       case "total records":  $s = "Total de registros";    break;
       case "user accounts":  $s = "Cuentas de usuario";    break;
       case "new group name":  $s = "Nuevo nombre de grupo";    break;
       case "create group":    $s = "Crear Groupo";    break;
       case "kill":        $s = "Matar";        break;
       case "idle":        $s = "idle";        break;
       case "login time":    $s = "Hora de LogOn";      break;
       case "anonymous user":  $s = "Usuario anonimo";    break;
       case "manager":      $s = "Manager";        break;
       case "account active":  $s = "Cuenta activa";    break;
       case "re-enter password": $s = "Re-ingresar contraseña";  break;
       case "group name":     $s = "Nombre Groupo";      break;
       case "display":      $s = "Mostrar";        break;
       case "base url":      $s = "URL Base";      break;
       case "news file":    $s = "Archivo Noticias";      break;
       case "minutes between reloads":  $s = "Minutos entre recargas";    break;
       case "listings displayed":  $s = "Listings Mostrados";    break;
       case "news type":    $s = "Tipo de Noticias";      break;
       case "user groups":    $s = "Grupos de usuarios";      break;
       case "headline sites":  $s = "Headline Sitios";    break;
       case "site":        $s = "Sitios";        break;
       case "view sessions":  $s = "Ver sesiones";    break;
       case "view access log":  $s = "Ver log de acceso";    break;
       case "active":      $s = "Activo";        break;
       case "disabled":      $s = "Deshabilitado";      break;
       case "last time read":  $s = "Ultima lectura";    break;
       case "manager":      $s = "Manager";    break;

       case "are you sure you want to delete this group ?":
  $s = "Esta seguro de querer borrar este grupo ?"; break;

       case "are you sure you want to kill this session ?":
  $s = "Esta seguro de querer matar esta sesion ?"; break;

       case "all records and account information will be lost!":
  $s = "Se perderan todos los registros e informacion de cuentas!";  break;

       case "are you sure you want to delete this account ?":
  $s = "Esta seguro de querer borrar esta cuenta ?";  break;

       case "are you sure you want to delete this news site ?":
  $s = "Esta seguro de querer borrar este sitio de noticias ?";    break;

       case "* make sure that you remove users from this group before you delete it.":
  $s = "* Asegurese de remover todos los usuarios de este grupo antes de borrarlo.";  break;

       case "percent of users that logged out":
  $s = "Porcentaje de usuarios que se desloguearon";      break;

       case "list of current users":
  $s = "Lista de usuarios presentes";            break;

       case "new password [ leave blank for no change ]":
  $s = "Nueva contraseña [ Deje en blanco para NO cambiar ]";  break;

       case "The two passwords are not the same":
  $s = "Las dos contraseñas no son iguales";      break;

       case "the login and password can not be the same":
  $s = "El login y la contraseña NO pueden ser iguales";  break;

       case "You must enter a password":  $s = "Debe entrar una contraseña";    break;

       case "that loginid has already been taken":
  $s = "Ese login ID ya esta siendo utilizado";      break;

       case "you must enter a display":    $s = "Debe entrar un display";    break;
       case "you must enter a base url":  $s = "Debe entrar una dirección url base";    break;
       case "you must enter a news url":  $s = "Debe entrar una dirección url de noticias";    break;

       case "you must enter the number of minutes between reload":
  $s = "Debe entrar el numero de minutos entre recargas";    break;

       case "you must enter the number of listings display":
  $s = "Debe entrar el numero de elementos listados a mostrar";    break;

       case "you must select a file type":
  $s = "Debe seleccionar un tipo de archivo";          break;

       case "that site has already been entered":
  $s = "Este sitio ya fue entrado";      break;

       case "select users for inclusion":
        $s = "Seleccionar usuarios para inclución";  break;

       default: $s = "<b>*</b> ". $message;
    }
    return $s;
  }
?>