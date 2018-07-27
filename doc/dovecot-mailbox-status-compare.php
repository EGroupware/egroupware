#!/usr/bin/env php
<?php
/**
 * Compare status of multiple/all Dovecot mailboxes eg. after a migration
 *
 * Usage: dovecot-mailbox-status-compare.php -c <dovecot.conf to compare with> [-p <prefix eg "INBOX">] [-s <hierarchy separator, default />] \
 *	<status fields, eg. "messages"> [mailbox or wildcard] [user(s)]
 *
 * If no users are given they are read from stdin
 */

$args = $_SERVER['argv'];
$cmd = array_shift($args);

$options = [
	'-c' => true,	// required
	'-p' => null,	// optional prefix incl. hierarchy seperator
	0 => ['messages', 'unseen'],	// first arg: fields
	1 => '*',		// second, optional arg: mailbox/folder to compare
];
$num_options = count($options);
$fields =& $options[0];
$mailbox_pattern = &$options[1];
$prefix =& $options['-p'];

for($n = 0; ($arg = array_shift($args)) !== null; )
{
	if ($arg[0] == '-')
	{
		if (!array_key_exists($arg, $options))
		{
			die("Unknown arg '$arg'!\n\n");
		}
		$options[$arg] = array_shift($args);
	}
	else
	{
		$options[$n++] = $arg;
	}
}
$users = array_slice($options, $num_options);

foreach($options as $opt => $value)
{
	if ($value === true) die("Required option $opt missing!\n\n".
		"Usage: $cmd -c <dovecot.conf to compare with> [-p <prefix incl. hiearachy seperator eg \"INBOX/\">] \\\n".
		"\t<status fields, eg. \"messages\"> [mailbox or wildcard] [user(s)]\n\n");
}

if (empty($users))
{
	$users = preg_split('/\r?\n/', file_get_contents('php://stdin'));
	array_pop($users);
}

//var_dump($options);
//var_dump($users);

foreach((array)$users as $user)
{
	$cmd_opts = ['mailbox', 'status', '-u', $user, implode(' ', (array)$fields), $mailbox_pattern];
	$cmd = 'doveadm '.implode(' ', array_map('escapeshellarg', $cmd_opts));
	echo $cmd."\n";
	$lines = $ret = null;
	exec($cmd, $lines, $ret);
	if ($ret) die("Error: $cmd\n\n".implode("\n", $lines)."\n");
	$mailboxes = parse_flow($lines);
	//var_dump($mailboxes);

	$cmd2_opts = ['-c', $options['-c'], 'mailbox', 'status', '-u', $user, implode(' ', (array)$fields), $prefix.$mailbox_pattern];
	if (!empty($prefix) && $mailbox_pattern == '*') $cmd2_opts[] = 'INBOX';	// wont get reported otherwise
	$cmd2 = 'doveadm '.implode(' ', array_map('escapeshellarg', $cmd2_opts));
	echo $cmd2."\n";
	$lines2 = $ret2 = null;
	exec($cmd2, $lines2, $ret2);
	if ($ret2) die("Error: $cmd2\n\n".implode("\n", $lines2)."\n");
	$mailboxes2 = parse_flow($lines2, $prefix);
	//var_dump($mailboxes2);

	// first check for missing folders from mailboxes (existing in mailboxes2)
	$missing = array_diff_key($mailboxes2, $mailboxes);
	foreach($missing as $mailbox => $values)
	{
		echo "$user: missing folder $mailbox with ".format_fields($values)."\n";
	}

	// check mailboxes in both for field values
	$differences = [];
	foreach(array_intersect_key($mailboxes2, $mailboxes) as $mailbox => $values)
	{
		foreach($values as $name => $value)
		{
			if ($mailboxes[$mailbox][$name] != $value)
			{
				$differences[$mailbox][$name] = $mailboxes[$mailbox][$name] - $value;
			}
		}
		if (isset($differences[$mailbox]))
		{
			echo "$user: folder $mailbox differs with ".format_fields($differences[$mailbox])."\n";
		}
	}

	// check for new mailboxes not in mailboxes2
	$new = array_diff_key($mailboxes, $mailboxes2);
	foreach($new as $mailbox => $values)
	{
		echo "$user: new folder $mailbox with ".format_fields($values)."\n";
	}
	// write summary to stderr
	fprintf(STDERR, "$user: %d missing, %d different, %d new folders\n", count($missing), count($differences), count($new));
}

/**
 * Parse flow formatted output from doveadm mailbox status
 *
 * @param array $lines output
 * @param string $prefix ='' prefix incl. hierarchy seperator to strip
 * @return array mailbox => [ field => value ]
 */
function parse_flow($lines, $prefix='')
{
	// -f flow returns space separated name=value pairs prefixed with mailbox name (can contain space!)
	$parsed = array_map(function($line)
	{
		$matches = null;
		if (preg_match_all("/([^= ]+)=([^ ]*) */", $line, $matches))
		{
			$values = array_combine($matches[1], $matches[2]);
			$values['mailbox'] = substr($line, 0, strlen($line)-strlen(implode('', $matches[0]))-1);
			return $values;
		}
	}, $lines);

	$mailboxes = [];
	foreach($parsed as $values)
	{
		$mailbox = $values['mailbox'];
		unset($values['mailbox']);
		if (!empty($prefix) && strpos($mailbox, $prefix) === 0)
		{
			$mailbox = substr($mailbox, strlen($prefix));
		}
		$mailboxes[$mailbox] = $values;
	}
	return $mailboxes;
}

function format_fields(array $fields)
{
	return implode(', ', array_map(function($value, $key)
	{
		return "$key: $value";
	}, $fields, array_keys($fields)));
}