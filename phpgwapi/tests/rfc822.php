<?php
/**
 * Test for imap_rfc822_parse_adrlist replacement in common_functions.inc.php
 *
 * @author Ralf Becker <rb@stylite.de>
 */

if (php_sapi_name() != 'cli')
{
	echo "<pre>\n";
}
else
{
	chdir(__DIR__);
}

include '../inc/common_functions.inc.php';

$addresses = array(
	'Joe Doe <doe@example.com>',
	'"Doe, Joe" <doe@example.com>',
	'"\\\'Joe Doe\\\'" <doe@example.com>',	// "\'Joe Doe\'" <doe@example.com>
	'postmaster@example.com',
	'root',
	'"Joe on its way Down Under :-\)" <doe@example.com>',
	'"Giant; \\"Big\\" Box" <sysservices@example.net>',		// "Giant; \"Big\" Box" <sysservices@example.net>
	'"sysservices@example.net" <sysservices@example.net>',
);
$addresses[] = implode(', ', $addresses);

$default_host = 'default.host';

foreach($addresses as $address)
{
	echo "\n\n$address:\n";
	$parsed = my_imap_rfc822_parse_adrlist($address, $default_host);
	print_r($parsed);
	echo my_imap_rfc822_write_address($parsed[0]->mailbox,
		$parsed[0]->host !== $default_host ? $parsed[0]->host : '',
		!empty($parsed[0]->personal) ? $parsed[0]->personal : '')."\n";
}