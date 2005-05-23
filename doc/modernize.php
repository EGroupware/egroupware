#!/usr/bin/php -qC
<?php
/**************************************************************************\
* eGroupWare - Tool to modernize the eGW code automaticaly                 *
* http://www.eGroupWare.org                                                *
* Written and (c) by Ralf Becker <RalfBecker@outdoor-training.de>          *
* -------------------------------------------------------                  *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

error_reporting(E_ALL & ~ E_NOTICE);

if (($no_phpgw = $argv[1] == '--no-phpgw'))
{
	array_shift($argv);
	--$argc;
}
if ($argv[1] == '--remove-space-indention')
{
	$remove_space_indention = (int) $argv[2];
	array_shift($argv);	array_shift($argv);
	$argc -= 2;
}
else
{
	$remove_space_indention = 2;	// replace 2 space with a tab
}
if ($argc <= 1 || !file_exists($argv[1])) 
{
	if (!file_exists($argv[1])) echo "File '$argv[1]' not found !!!\n\n";
	echo "Usage: modernize.php [--no-phpgw] [--remove-space-indention N] <filename>\n";
	echo "--no-phpgw dont change phpgw to egw, necessary for some API files\n";
	echo "--remove-space-indention N substitute every N space at the beginning of a line with a tab (default 2)\n\n";
	exit;
}

// some code modernizations
$modernize = array(
	// saves an unnecessary copy
	'= CreateObject'           => '=& CreateObject',
	'= new'                    => '=& new',
	// php5 cloning of the DB object
	'= $GLOBALS[\'egw\']->db;' => '= clone($GLOBALS[\'egw\']->db);',
	'= $this->db;'             => '= clone($this->db);',
	// remove windows lineends (CR)
	"\r"                       => '',
);

foreach(array('GET','POST','SERVER') as $name)
{
	$modernize['$HTTP_'.$name.'_VARS'] = '$_'.$name;
	$modernize['$GLOBALS[\'HTTP_'.$name.'_VARS\']'] = '$_'.$name;
	$modernize['$GLOBALS["HTTP_'.$name.'_VARS"]'] = '$_'.$name;
}

if (!$no_phpgw)
{
	$modernize += array(
		// phpGW --> eGW
		'PHPGW_'	               => 'EGW_',
		'$GLOBALS[\'phpgw_info\']' => '$GLOBALS[\'egw_info\']',
		'$GLOBALS["phpgw_info"]'   => '$GLOBALS[\'egw_info\']',
		'$GLOBALS[\'phpgw\']'      => '$GLOBALS[\'egw\']',
		'$GLOBALS["phpgw"]'        => '$GLOBALS[\'egw\']',
		'common->phpgw_header'     => 'common->egw_header',
		'common->phpgw_footer'     => 'common->egw_footer',
		'common->phpgw_exit'       => 'common->egw_exit',
		'common->phpgw_final'      => 'common->egw_final',
	);
}


$modernize_from = array_keys($modernize);
$modernize_to = array_values($modernize);

$in_doc_block = false;
foreach(file($argv[1]) as $n => $line)
{
	$line = str_replace($modernize_from,$modernize_to,$line);

	if ($remove_space_indention)
	{
		while (preg_match("/^(\t*)".str_repeat(' ',$remove_space_indention).'/',$line))
		{
			$line = preg_replace("/^(\t*)".str_repeat(' ',$remove_space_indention).'/',"\\1\t",$line);
		}
	}
	
	if (!$in_doc_block) 
	{
		$parts = explode('/*!',$line);
		if (count($parts) <= 1)
		{
			echo $line;
			continue;
		}
		$in_doc_block = true;

		list($indent,$rest) = $parts;
		echo $indent."/**\n";
		if (strlen($rest) <= 2)
		{
			continue;
		}
		$line = $indent.$rest;
		
		if (($one_line_block = strstr($line,'*/') !== false)) $line = str_replace('*/','',$line);
	}
	// now we are inside a comment-block

	if (preg_match('/[ \t]*\*\//',$line))	// exiting the comment-block
	{
		$in_doc_block = false;
		echo str_replace('*/',' */',$line);
		continue;
	}
	if (preg_match('/^(.*)@([a-zA-Z]+) (.*)$/',$line,$parts))
	{
		list(,$indent,$cmd,$value) = $parts;
		switch ($cmd)
		{
			// to ignore
			case 'syntax':
			case 'function':
			case 'class':
				break;
			
			case 'abstract':
				echo $indent.' * '.$value."\n".$indent." *\n";
				break;
				
			case 'discussion':
			case 'example':
			default:
				echo $indent.' * '.$value."\n";
				break;
				
			case 'result': 
				$cmd = 'return';
				// fall through
			case 'param':
			case 'return':
			case 'var':
			case 'author':
			case 'copyright':
			case 'licence':
			case 'package':
			case 'access':
				echo $indent.' * @'.$cmd.' '.$value."\n";
				break;
		}
	}
	else
	{
		echo str_replace($indent,$indent.' * ',$line);
	}
	if ($one_line_block)
	{
		echo $indent." */\n";
		$one_line_block = $in_doc_block = false;
	}
}
