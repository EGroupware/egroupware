<?php

  function lang_pref($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "max matchs per page":
	$s = "Numero maximo de resultados por pagina";			break;

       case "time zone offset":
	$s = "Diferenca de fuso horario";				break;

       case "this server is located in the x timezone":
	$s = "Este servidor esta localizado no fuso horario $m1";		break;

       case "date format":	$s = "Formato data";			break;
       case "time format":	$s = "Formato horario";			break;
       case "language":		$s = "Lingua";				break;

       case "show text on navigation icons":
	$s = "Mostra texto na barra de navegacao";		break;

       case "show current users on navigation bar":
	$s = "Mostra usuarios ativos na barra de navegacao";	break;

       case "show new messages on main screen":
	$s = "Mostra novas mensagens na tela principal";	break;

       case "email signature":
	$s = "Assinatura do email";					break;

       case "show birthday reminders on main screen":
	$s = "Mostra Aniversariantes na primeira tela";	break;

       case "show high priority events on main screen":
	$s = "Mostra eventos de alta prioridade na primeira tela";		break;

       case "weekday starts on":
	$s = "A Semana comeca em";					break;

       case "work day starts on":
	$s = "A jornada de trabalho comeca em";			break;

       case "work day ends on":
	$s = "A jornada de trabalho termina em";			break;

       case "select headline news sites":
	$s = "Selecione os sites de noticias";		break;

       case "change your password":
	$s = "Mude sua senha";					break;

       case "select different theme":
	$s = "Selecione um tema diferente";				break;

       case "change your settings":
	$s = "mude suas preferencias";					break;

       case "enter your new password":
	$s = "Entre uma nova senha";				break;

       case "re-enter your password":
	$s = "Re-digite sua senha";					break;

       case "the two passwords are not the same":
	$s = "As duas senhas nao sao a mesma";				break;

       case "you must enter a password":
	$s = "Voce deve digitar uma senha";				break;

       case "your current theme is: x":
	$s = "Seu tema corrente e': " . $m1;				break;

       case "please, select a new theme":
	$s = "por favor, selecione um tema";				break;

       case "note: this feature does *not* change your email password. this will need to be done manually.":
	$s = "Nota: Esta funcao *nao* muda sua senha de email. voce devera fazer isto manualmente.";	break;

       default: $s = "* $message";
    }
    return $s;
  }
?>
