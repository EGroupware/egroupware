<?php

  function lang_pref($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "max matchs per page":
  $s = "Maximo de coincidencias por pagina";    break;

       case "time zone offset":
  $s = "Zona horaria";    break;

       case "this server is located in the x timezone":
  $s = "Este servidor se encuentra en la zona horaria " . $m1;  break;

       case "date format":  $s = "Formato fecha";      break;
       case "time format":  $s = "Formato hora";      break;
       case "language":    $s = "Lenguaje";      break;

       case "show text on navigation icons":
  $s = "Mostrar descripción sobre los iconos";      break;

       case "show current users on navigation bar":
  $s = "Mostrar usuarios conectados en la barra de navegación";  break;

       case "show new messages on main screen":
  $s = "Mostar nuevos mensajes en pantalla principal";  break;

       case "email signature":
  $s = "Firma de E-Mail";  break;

       case "show birthday reminders on main screen":
  $s = "Mostrar recordatorios de cumpleaños en pantalla principal";  break;

       case "show high priority events on main screen":
  $s = "Mostar eventos de alta prioridad en pantalla principal";  break;

       case "weekday starts on":
  $s = "La semana comienza el";  break;

       case "work day starts on":
  $s = "Comienzo día laboral";  break;

       case "work day ends on":
  $s = "Final día laboral";  break;

       case "select headline news sites":
  $s = "Seleccione sites de Encabezados de Noticias";  break;

       case "change your password":
  $s = "Cambie su contraseña";    break;

       case "select different theme":
  $s = "Seleccione un tema diferente";    break;

       case "change your settings":
  $s = "Cambie sus Seteos";    break;

       case "enter your new password":
  $s = "Entre su nueva contraseña";    break;

       case "re-enter your password":
  $s = "Re-Entre su contraseña";  break;

       case "the two passwords are not the same":
  $s = "Las dos contraseñas son distintas";  break;

       case "you must enter a password":
  $s = "Debe entrar una contraseña";  break;

       case "your current theme is: x":
  $s = "Su actual tema es: " . $m1;  break;

       case "please, select a new theme":
  $s = "Por favor, seleccione un nuevo tema";  break;

       case "note: this feature does *not* change your email password. this will need to be done manually.":
  $s = "Nota: Esta opcion no cambia la contraseña de su email. Esto deberá ser hecho manualmente.";  break;

       default: $s = "<b>*</b> ". $message;
    }
    return $s;
  }