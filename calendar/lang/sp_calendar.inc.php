<?php

  function lang_calendar($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "today":    $s = "Hoy";  break;
       case "this week":  $s = "Esta semana";  break;
       case "this month":  $s = "Este mes";  break;

       case "generate printer-friendly version":
  $s = "Generar versión para impresion";  break;

       case "printer friendly":    $s = "Versión impresión";  break;

       case "you have not entered a\\nbrief description":
  $s = "Ud. no ha ingresado una\\nBrief descripción";  break;

       case "you have not entered a\\nvalid time of day.":
  $s = "Ud. no ha ingresado una\\nvalid hora valida.";  break;

       case "Are you sure\\nyou want to\\ndelete this entry ?":
  $s = "Esta seguro\\nde querer\\nborrar esta entrada ?";  break;

       case "participants":    $s = "Participantes";  break;
       case "calendar - edit":  $s = "Calendario - Edicion";  break;
       case "calendar - add":  $s = "Calendario - Agregar";  break;
       case "brief description":$s = "Descripción breve";break;
       case "full description":  $s = "Descripción completa";break;
       case "duration":      $s = "Duración";    break;
       case "minutes":      $s = "minutos";      break;
       case "repeat type":    $s = "Tipo repetición";    break;
       case "none":        $s = "Ninguno";      break;
       case "daily":      $s = "Diario";      break;
       case "weekly":      $s = "Semanal";      break;
       case "monthly (by day)":  $s = "Mensual (por día)";break;
       case "monthly (by date)":$s = "Mensual (por fecha)";break;
       case "yearly":      $s = "Anual";  break;
       case "repeat end date":  $s = "Repetir fecha final";  break;
       case "use end date":    $s = "Usar fecha final";  break;
       case "repeat day":    $s = "Repetir día";    break;
       case "(for weekly)":    $s = "(por semanal)";  break;
       case "frequency":    $s = "Frequencia";    break;
       case "sun":        $s = "Dom";        break;
       case "mon":        $s = "Lun";        break;
       case "tue":        $s = "Mar";        break;
       case "wed":        $s = "Mie";        break;
       case "thu":        $s = "Jue";        break;
       case "fri":        $s = "Vie";        break;
       case "sat":        $s = "Sab";        break;
       case "su":        $s = "Do";        break;
       case "mo":        $s = "L";        break;
       case "tu":        $s = "M";        break;
       case "we":        $s = "Mi";        break;
       case "th":        $s = "J";        break;
       case "fr":        $s = "V";        break;
       case "sa":        $s = "Sa";        break;
       case "search results":  $s = "Resultados de la busqueda";  break;
       case "no matches found.":$s = "No se encontraron coincidencias.";break;
       case "1 match found":  $s = "1 coincidencia encontrada";  break;
       case "x matches found":  $s = "$m1 coincidencias encontradas";break;
       case "description":    $s = "Descripción";    break;
       case "repetition":    $s = "Repetición";    break;
       case "days repeated":  $s = "días repetidos";  break;
       case "go!":        $s = "Ir!";        break;
       case "year":        $s = "Año";      break;
       case "month":      $s = "Mes";      break;
       case "week":        $s = "Semana";      break;
       case "new entry":    $s = "Nueva Entrada";    break;
       case "view this entry":  $s = "Ver esta entrada";  break;

       case "the following conflicts with the suggested time:<ul>x</ul>":
  $s = "Los siguientes conflictos con las horas sugeridas:<ul>$m1</ul>";  break;

       case "your suggested time of <B> x - x </B> conflicts with the following existing calendar entries:":
  $s = "Sus horas sugeridas de <B> $m1 - $m2 </B> estan en conflicto con las siguientes entradas en el calendario:";  break;

       case "you must enter one or more search keywords":
  $s = "Ud. debe entrar una o mas claves de busqueda";  break;

       case "are you sure\\nyou want to\\ndelete this entry ?\\n\\nthis will delete\\nthis entry for all users.":     $s = "Esta seguro\\nde querer\\nborrar esta entrarda ?\\n\\nEsto borrara\\nla entrada para todos los usuarios.";  break;

       case "":    $s = "";  break;
       case "":    $s = "";  break;
       case "":    $s = "";  break;
       case "":    $s = "";  break;
       case "":    $s = "";  break;
       case "":    $s = "";  break;
       default: $s = "<b>*</b> ". $message;
    }
    return $s;
  }
?>