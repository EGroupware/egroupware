#!/usr/bin/env php
<?php
/**
 * Convert Lotus Structured Text to CSV to e.g. use CSV Import
 *
 * Writes CSV file to stdout with:
 * - separator: ,
 * - enclosure: "
 * - escape: \
 * - eol: \n
 *
 * Header 1,Header 2,"Header, with comma",Header N
 * Data1,Data2,Data3,DataN
 * ...
 *
 * Usage: ./lotus-structured-text.php <filename>
 *        ./lotus-structured-text.php < <filename>
 */
if (PHP_SAPI !== 'cli')
{
	die('This script can only be run from the command line.');
}

if (!isset($argv[1]))
{
	$fp = fopen('php://stdin', 'r');
}
elseif (!($fp = fopen($argv[1], 'r')))
{
	die("Unable to open '$argv[1]'!");
}

$ctrl_l = chr(ord("L")-ord("@"));
$records = $record = $keys = [];
while (($line = fgets($fp)) !== false)
{
	$line = trim($line);
	if (($line === '' || $line === $ctrl_l))
	{
		if ($record)
		{
			if (!$keys || array_diff(array_keys($record), $keys))
			{
				$keys = array_unique(array_merge($keys, array_keys($record)));
			}
			$records[] = $record;
		}
		$record = [];
	}
	else
	{
		list($key, $value) = preg_split('/: */', $line, 2)+['', ''];
		$record[$key] = $value;
	}
}
if ($record)
{
	if (!$keys || array_diff(array_keys($record), $keys))
	{
		$keys = array_unique(array_merge($keys, array_keys($record)));
	}
	$records[] = $record;
}
fputcsv(STDOUT, $keys);
foreach($records as $record)
{
	fputcsv(STDOUT, array_map(static function($key) use ($record) {
		return $record[$key] ?? '';
	}, $keys));
}