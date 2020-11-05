#!/usr/bin/php
<?php

$error_time = 3600;     // only show for that time as an error
$warn_time = 86400;     // only warn for that time

$cmd = basename($_SERVER['argv'][0], '.php');
$metadata = '/var/lib/egroupware/default/files/saml/metadata/saml20-idp-remote.php';

if (!file_exists($metadata))
{
	$status = 2;
	$verbose = "Missing metadata file $metadata";
}
elseif (time()-filemtime($metadata) > 86400+60)
{
	$status = 1;
}
else
{
	$status = 0;
}
if (!isset($verbose))
{
	$mtime = new DateTime('@'.filemtime($metadata));
	$mtime->setTimeZone(new DateTimeZone('Europe/Berlin'));
	$verbose = "Metadata last refreshed ".$mtime->format('Y-m-d H:i:s');
}
echo "$status $cmd - $verbose\n";
