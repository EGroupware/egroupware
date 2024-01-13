#!/usr/bin/env php -qC
<?php
/**
 * EGroupware - analyse PHP log for PHP Warnings, Deprecated, Fatal error and aggregate them file and line
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author rb@egroupware.org
 * @copyright 2024 by rb@egroupware.org
 */

if (php_sapi_name() !== 'cli')	// security precaution: forbid calling as web-page
{
	die('<h1>'.basename($_SERVER['PHP_SELF']).' must NOT be called as web-page --> exiting !!!</h1>');
}
$warnings_by_file = [];

// allow to pipe in tail of log and stop with Ctrl-C to show analysis
pcntl_signal(SIGINT, function() use ($warnings_by_file)
{
	echo "Caught SIGINT\n";
	analyse($warnings_by_file);
});
pcntl_signal(SIGTERM, function() use ($warnings_by_file)
{
	echo "Caught SIGTERM\n";
	analyse($warnings_by_file);
});

if ($_SERVER['argc'] > 1)
{
	$files = $_SERVER['argv'];
	array_shift($files);
	$fp = popen('cat '.implode(' ', array_map('escapeshellarg', $files)), 'r');
}
else
{
	$fp = STDIN;
}

$n = 1;
while(($line = fgets($fp)))
{
	if (preg_match('#PHP (Warning|Deprecated|Fatal error): (.*) in (/[^ ]+\.php) on line (\d+)\r?\n?$#', $line, $matches))
	{
		list(, $type, $warning, $file, $line) = $matches;
		$warnings_by_file[$file][] = $line.': '.$type.' '.$warning;
	}
	error_log("\r$n: $line"); $n++;
}
error_log("Analysing ...");
analyse($warnings_by_file);

function analyse(array $warnings_by_file)
{
	uasort($warnings_by_file, $count_sort = static function ($a, $b) {
		return count($b) <=> count($a);
	});

	foreach ($warnings_by_file as $file => $messages)
	{
		echo "\n$file: ".count($messages)."\n";

		$warnings_by_line = [];
		foreach ($messages as $line_msg)
		{
			list($line, $msg) = explode(':', $line_msg, 2);
			$warnings_by_line[$line][] = $msg;
		}
		uasort($warnings_by_line, $count_sort);

		foreach ($warnings_by_line as $line => $warnings)
		{
			echo 'Line '.$line . ': '.count($warnings).' times: ' . implode(', ', array_unique($warnings)) . "\n";
		}
	}
}