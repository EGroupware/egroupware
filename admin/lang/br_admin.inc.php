<?php

  function lang_admin($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "last x logins":		$s = "Ultimo $m1 login";	break;
       case "loginid":			$s = "Identificativo Login";	break;
       case "ip":			$s = "IP";			break;
       case "total records":		$s = "Total de registros";	break;
       case "user accounts":		$s = "Contas de Usuarios";	break;
       case "new group name":		$s = "Novo nome do Grupo";	break;
       case "create group":		$s = "Criar Grupo";		break;
       case "kill":			$s = "Matar";			break;
       case "idle":			$s = "inativo";	 		break;
       case "login time":		$s = "Hora do Login";		break;
       case "anonymous user":		$s = "Usuario Anonimo";		break;
       case "account active":		$s = "Conta Inativa"; 		break;
       case "re-enter password": 	$s = "Re-digite sua senha";  	break;
       case "group name": 		$s = "Nome do Grupo";		break;
       case "display":			$s = "Visualiza";		break;
       case "base url":			$s = "URL de base";		break;
       case "news file":		$s = "Arquivo de news";		break;
       case "minutes between reloads":	$s = "Minutos entre reloads";	break;
       case "listings displayed":	$s = "listagens mostradas";	break;
       case "news type":		$s = "Tipo de news";		break;
       case "user groups":		$s = "Grupos de Usuarios";	break;
       case "headline sites":		$s = "Headlines";		break;
       case "site":			$s = "Site";			break;
       case "view sessions":		$s = "Visualiza Sessao";	break;
       case "view access log":		$s = "Visualiza Log de acesso"; break;
       case "active":			$s = "Ativo";			break;
       case "disabled":			$s = "Desabilitato";		break;
       case "last time read":		$s = "Ultima Leitura";		break;
       case "manager":			$s = "Gerente";			break;

       case "are you sure you want to delete this group ?":
	$s = "Voce tem certeza que deseja apagar este grupo ?"; break;

       case "are you sure you want to kill this session ?":
	$s = "Voce tem certeza que deseja matar esta secao ?"; break;

       case "all records and account information will be lost!":
	$s = "Todos registros e informacoes de contas serao perdidas!";	break;

       case "are you sure you want to delete this account ?":
	$s = "Tem certeza que deseja apagar esta conta ?";	break;

       case "are you sure you want to delete this news site ?":
	$s = "Tem certeza que deseja apagar este site news ?";		break;

       case "* make sure that you remove users from this group before you delete it.":
	$s = "* tenha certeza de apagar os usuarios deste grupo antes de voce apaga-lo.";	break;

       case "percent of users that logged out":
	$s = "Percentual de usuarios que terminaram a secao corretamente";	break;

       case "list of current users":
	$s = "Lista de usuarios correntes";				break;

       case "new password [ leave blank for no change ]":
	$s = "Nova Senha [ Deixe em branco para nao alterar ]";		break;

       case "The two passwords are not the same":
	$s = "As duas senhas nao sao a mesma";				break;

       case "the login and password can not be the same":
	$s = "O login nao pode ser o mesmo da senha";		break;

       case "You must enter a password":	
	$s = "Voce deve digitar uma senha";					break;

       case "that loginid has already been taken":		
	$s = "Este nome ja esta em uso";					break;

       case "you must enter a display":
	$s = "Voce deve digitar o display";					break;

       case "you must enter a base url":
	$s = "Insira a URL base";					break;

       case "you must enter a news url":
	$s = "Voce deve digitar uma URL de news";				break;

       case "you must enter the number of minutes between reload":
	$s = "Entre com o numero de minutos entre os reloads";		break;

       case "you must enter the number of listings display":
	$s = "Digite o numero de listagens exibidas";			break;

       case "you must select a file type":
	$s = "Voce deve selecionar um tipo de arquivo";			break;

       case "that site has already been entered":
	$s = "Este site ja foi cadastrado";				break;

       default: $s = "* $message";
    }
    return $s;
  }
?>
