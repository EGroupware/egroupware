<?php

  function lang_calendar($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "today":		$s = "Hoje";		break;
       case "this week":	$s = "Esta Semana"; 	break;
       case "this month":	$s = "Este mes";	break;

       case "generate printer-friendly version":
	$s = "Gera versoes imprimiveis?!?";	break;

       case "printer friendly":		$s = "imprimivel";	break;

       case "you have not entered a\\nbrief description":
	$s = "Voce nao digitou uma \\nBreve Descricao";	break;

       case "you have not entered a\\nvalid time of day.":
	$s = "Voce nao digitou \\num horario valido.";		break;

       case "Are you sure\\nyou want to\\ndelete this entry ?":
	$s = "Tem certeza\\nque quer apagar\\nesta entrada ?";	break;

       case "participants":		$s = "Participante";		break;
       case "calendar - edit":		$s = "Calendario - Editar";	break;
       case "calendar - add":		$s = "Calendario - Adicionar";	break;
       case "brief description":	$s = "Curta Descricao";		break;
       case "full description":		$s = "Descricao  Completa";	break;
       case "duration":			$s = "Duracao";			break;
       case "minutes":			$s = "minutos";			break;
       case "repeat type":		$s = "Tipo de repeticao";	break;
       case "none":			$s = "Nenhuma";			break;
       case "daily":			$s = "Diaria";			break;
       case "weekly":			$s = "semanal";			break;
       case "monthly (by day)":		$s = "mensal (por dia)";	break;
       case "monthly (by date)":	$s = "mensal (pela data)";	break;
       case "yearly":			$s = "Anual";			break;
       case "repeat end date":		$s = "Data final da repeticao";	break;
       case "use end date":		$s = "Usar data final";		break;
       case "repeat day":		$s = "Repeticao Diaria";	break;
       case "(for weekly)":		$s = "(por semana)";		break;
       case "frequency":		$s = "Frequencia";		break;
       case "sun":			$s = "Dom";			break;
       case "mon":			$s = "Seg";			break;
       case "tue":			$s = "Ter";			break;
       case "wed":			$s = "Qua";			break;
       case "thu":			$s = "Qui";			break;
       case "fri":			$s = "Sex";			break;
       case "sat":			$s = "Sab";			break;
       case "search results":		$s = "Resultados da pesquisa";	break;
       case "no matches found.":	$s = "Nenhuma ocorrencia encontrada.";	break;
       case "1 match found":		$s = "Encontrada 1 ocorrencia";	break;
       case "x matches found":		$s = "$m1 ocorrencias encontradas";	break;
       case "description":		$s = "Descricao";		break;
       case "repetition":		$s = "Repeticao";		break;
       case "days repeated":		$s = "Dias Repetidos";		break;
       case "go!":			$s = "Vai!";			break;
       case "year":			$s = "Ano";			break;
       case "month":			$s = "Mes";			break;
       case "week":			$s = "Semana";			break;
       case "new entry":		$s = "Nova Entrada";		break;
       case "view this entry":		$s = "Visualiza esta entrada";	break;

       case "the following conflicts with the suggested time:<ul>x</ul>":
	$s = "os seguintes conflitos com o horario sugerido:<ul>$m1</ul>";	break;

       case "Your suggested time of <B> x - x </B> conflicts with the following existing calendar entries:":
	$s = "Horiario Sugerido: <B> $m1 - $m2 </B> esta em conflito com as seguintes entradas no calendario:"; break;

       case "you must enter one or more search keywords":
	$s = "voce deve digitar uma ou mais palavras chave";	break;

       case "are you sure\\nyou want to\\ndelete this entry ?\\n\\nthis will delete\\nthis entry for all users.":
	$s = "Tem certeza\\nvoce quer\\napagar esta entrada ?\\n\\nIsto apagara a entrada\\npara todos usuarios."; break;

       default: $s = "* $message";
    }
    return $s;
  }

?>
