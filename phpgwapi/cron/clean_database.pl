#!/usr/bin/perl

use DBI;

$db_host = 'localhost';
$db_name = 'phpGroupWare';
$db_user = 'phpgroupware';
$db_pass = 'phpgr0upwar3';

$dbase = DBI->connect("DBI:mysql:$db_name;$db_host",$db_user,$db_pass);


$command = $dbase->do("delete from webcal_entry");
$command = $dbase->do("delete from webcal_entry_user");
$command = $dbase->do("delete from webcal_entry_groups");
$command = $dbase->do("delete from webcal_entry_repeats");

$dbase->disconnect();
