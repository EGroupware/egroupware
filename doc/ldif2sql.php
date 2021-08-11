#!/usr/bin/env php
<?php
if (!$_SERVER['argc'])
{
	echo "cat test.ldif | ".basename($_SERVER['argv'][0])."attr1[, attr2[, ...]]\n";
	exit(1);
}

$attrs = array_slice($_SERVER['argv'], 1);

$values = $rows = [];
while(!feof(STDIN))
{
	$line = trim(fgets(STDIN));
	if (empty($line) || $line[0] === '#' ||
		!preg_match('/^([^:]+): (.*)$/', $line, $matches))
	{
	    $values = [];
	    continue;
    }
	if ($matches[1] === 'dn') $values = [];

	$values[$matches[1]] = $matches[2];

	if (count(array_intersect(array_keys($values), $attrs)) === count($attrs))
	{
		$cols = [];
		foreach($attrs as $attr)
		{
			$cols[$attr] = "'".addslashes($values[$attr])."'";
		}
		$cols = '('.implode(', ', $cols).')';
		if (!in_array($cols, $rows)) $rows[] = $cols;
	}
}
echo implode(",\n", $rows)."\n";