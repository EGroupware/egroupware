#!/usr/bin/env php
<?php
/**
 * EGroupware - import mail credentials for a given mail-account (acc_id) from a CSV file
 *
 * The files should be Tab-separated with columns: email, password and optional username (if no username email will be searched).
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright 2023 by Ralf Becker <rb@stylite.de>
 */

use EGroupware\Api;
use EGroupware\Api\Mail\Credentials;

if (php_sapi_name() !== 'cli')	// security precaution: forbid calling as web-page
{
	die('<h1>'.basename(__FILE__).'  must NOT be called as web-page --> exiting !!!</h1>');
}
$separator = "\t";

if ($_SERVER['argc'] !== 3 || !is_numeric($acc_id=$_SERVER['argv'][1]) ||
	!file_exists($file=$_SERVER['argv'][2]) || !($fp = fopen($file, 'r')))
{
	echo "Usage: ".basename(__FILE__)." <acc_id> <csv-file>\n\n";
	if (!file_exists($file=$_SERVER['argv'][2]) || !($fp = fopen($file, 'r')))
	{
		echo "CSV file '$file' not found!\n\n";
	}
	exit;
}

$GLOBALS['egw_info'] = [
	'flags' => [
		'currentapp' => 'login',
	],
];

require dirname(__DIR__).'/header.inc.php';

$header = fgetcsv($fp, null, $separator);
$line = fgetcsv($fp, null, $separator);
if (!$header || count($header) < 2 || count($header) !== count($line))
{
	echo "Invalid CSV file e.g. not ".($separator==="\t"?'Tab':$separator)."-separated or not at least 2 columns\n";
	exit(1);
}

$column_aliases = [
	'user' => ['user', 'benutzer', 'username', 'account_lid'],
	'password' => ['passwort'],
	'email' => ['mailaddress', 'mailadresse']
];
//var_dump($header);
$header_cols = [];
foreach($header as $n => $col)
{
	$col = strtolower($col);
	foreach($column_aliases as $name => $aliases)
	{
        if ($col === $name || in_array($col, $aliases))
        {
            $header_cols[$n] = $name;
            break;
        }
	}
    if (!isset($header_cols[$n]))
    {
	    $header_cols[$n] = $col;
    }
}
//var_dump($header_cols, array_combine($header_cols, $line));

$accounts = Api\Accounts::getInstance();
$n = $stored = $ignored = $error = 0;
do {
    $line = array_combine($header_cols, $line);
    echo ++$n.': '.json_encode($line)."\n";
    if (empty($line['password']))
    {
        echo "--> ignored, no password\n";
        ++$ignored;
        continue;
    }
    if (!($account_id = $accounts->name2id($line['user'])) &&
        !($account_id = $accounts->name2id($line['email'], 'account_email')) &&
        !($account_id = $accounts->name2id(explode('@', $line['email'])[0]??'')))
    {
        echo "--> ignored, neither username nor email found\n";
        ++$ignored;
        continue;
    }

    if (Credentials::write($acc_id, $line['email'], $line['password'], Credentials::IMAP|Credentials::SMTP, $account_id) > 0)
    {
	    echo "--> stored credentials for account_id #$account_id\n";
        ++$stored;
    }
    else
    {
	    echo "--> Error storint credentials for account_id #$account_id\n";
        ++$error;
    }
}
while (!feof($fp) && ($line = fgetcsv($fp, null, $separator)));

echo "\nSuccessfull stored credentials for $stored users, ignored $ignored because of not found user or missing password and $error errors\n\n";