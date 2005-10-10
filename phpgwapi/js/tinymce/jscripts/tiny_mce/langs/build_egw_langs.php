#!/usr/bin/php -qC
<?php

function readlang($lang)
{
	$langs = array();
	foreach(file($lang.'.js') as $line)
	{
		if (preg_match("/tinyMCELang\['([^']+)'] = '([^']+)';/",$line,$matches))
		{
			$langs[$matches[1]] = $matches[2];
		}
	}
	return $langs;
}

function writelang($lang,$langs)
{
	global $en_langs;
	
	$f = fopen('phpgw_'.$lang.'.lang','a');
	foreach($langs as $key => $msg)
	{
		if (isset($en_langs[$key])) fwrite($f,$en_langs[$key]."\ttinymce\t$lang\t$msg\n");
	}
	fclose($f);	
}

$langs = readlang('en');
$en_langs = array();
foreach($langs as $key => $msg)
{
	$en_langs[$key] = strtolower($msg);
}
writelang('en',$langs);

$d = opendir('.');
while (($f = readdir($d)))
{
	list($lang,$js) = explode('.',$f);
	if (!$lang || $lang == 'en' || $js != 'js') continue;
	
	$langs = readlang($lang);
	writelang($lang,$langs);
}

$d = opendir('../plugins');
while (($p = readdir($d)))
{
	if (is_dir($ldir = '../plugins/'.$p.'/langs/'))
	{
		$langs = readlang($ldir.'en');
		$en_langs = array();
		foreach($langs as $key => $msg)
		{
			$en_langs[$key] = strtolower($msg);
		}
		writelang('en',$langs);
		
		$d2 = opendir('.');
		while (($f = readdir($d2)))
		{
			list($lang,$js) = explode('.',$f);
			if (!$lang || $lang == 'en' || $js != 'js') continue;
			
			$langs = readlang($ldir.$lang);
			writelang($lang,$langs);
		}
	}
}

