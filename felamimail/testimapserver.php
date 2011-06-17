<?php

/**************************************************************************\
* eGroupWare - FeLaMiMail                                                  *
* http://www.egroupware.org                                                *
* Written by Lars Kneschke [l.kneschke@metaways.de]                        *
* -----------------------------------------------                          *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; version 2 of the License.                     *
\**************************************************************************/
/* $Id: index.php 18876 2005-07-23 15:52:49Z ralfbecker $ */

/***************************************************************************
* 
*  This script is doing some tests against your imap server.
* 
*  THE OUTPUT OF THIS SCRIPT WILL CONTAIN YOUR USERNAME AND PASSWORD. 
*  IF YOU LIKE TO REMOVE THIS INFORMATION FROM THE OUTPUT REMOVE ANY 
*  LINES BETWEEN "C: A0002 AUTHENTICATE LOGIN" and 
*  "S: A0002 OK AUTHENTICATE completed".
*
*  Maybe you need to adjust the line starting with "set_include_path" 
*  to your systems need.
*
*  To be able to test the acl functions of your imap server, you need 
*  to supply at least a second username($username2).
+
*  To be able to check all features of your imap server you need to 
*  supply a second username($username2) AND password($password2).
* 
***************************************************************************/

die("<center>THIS SCRIPT IS DISABLED FOR SECURITY REASONS.<br><br><b>PLEASE COMMENT OUT LINE ". __LINE__ ." TO ENABLE THIS SCRIPT</b>.</center>");

########################################
# SSL example
########################################
#$host		= 'ssl://127.0.0.1';
#$port		= 993;
#$username1	= 'username';
#$password1	= 'password';
#$username2	= 'username';
#$password2	= 'password';

########################################
# TLS example
########################################
#$host		= 'tls://127.0.0.1';
#$port		= 993;
#$username1	= 'username';
#$password1	= 'password';
#$username2	= 'username';
#$password2	= 'password';

########################################
# no encryption or STARTTLS
########################################
$host		= '127.0.0.1';
$port		= 143;
$username1	= 'username';
$password1	= 'password';
$username2	= '';
$password2	= '';

# folder to use for testing the SORT feature
$testFolder	= 'INBOX';
$enableSTARTTLS = true;

$startTime = microtime(true);

print "<pre>";

set_include_path('../egw-pear'. PATH_SEPARATOR .'/usr/share/php'. PATH_SEPARATOR . get_include_path());

require_once 'Net/IMAP.php';

print "<h1><span style='color:red;'>ATTENTION: THIS OUTPUT CONTAINS YOUR USERNAME AND PASSWORD!!!</span></h1>";

$elapsedTime = microtime(true) - $startTime;
print "<h1> $elapsedTime :: Login as user $username1 </h1>";
$imapClient = new Net_IMAP($host, $port, $enableSTARTTLS);
$imapClient->setDebug(true);
$imapClient->login($username1, $password1, true, false);
$imapClient->selectMailbox($testFolder);

if(!empty($username2) && !empty($password2)) {
	$elapsedTime = microtime(true) - $startTime;
	print "<h1> $elapsedTime :: Login as user $username2 </h1>";
	$imapClient2 = new Net_IMAP($host);
	$imapClient2->setDebug(true);
	$imapClient2->login($username2, $password2, true, false);
}

$elapsedTime = microtime(true) - $startTime;
print "<h1> $elapsedTime :: Getting hierarchy delimiter </h1>";
$delimiter = $imapClient->getHierarchyDelimiter();
print "delimiter is: $delimiter<br>";

$elapsedTime = microtime(true) - $startTime;
print "<h1> $elapsedTime :: List all folders </h1>";
$imapClient->getMailboxes();

$elapsedTime = microtime(true) - $startTime;
print "<h1> $elapsedTime :: List all subscribed folders </h1>";
$imapClient->listsubscribedMailboxes();

$elapsedTime = microtime(true) - $startTime;
print "<h1> $elapsedTime :: Checking for ACL support: ";
if($imapClient->hasCapability('ACL')) {
	print "<span style='color:green;'>supported</span></h1>";
	$imapClient->getMyRights($testFolder);
	$imapClient->getACLRights($username1, $testFolder);
	if(!empty($username2)) {
		$imapClient->setACL($testFolder, $username2, 'lrswipcda');
		$imapClient->getACLRights($username2, $testFolder);
		$imapClient->deleteACL($testFolder, $username2);
		$imapClient->getACLRights($username2, $testFolder);
	}
	$imapClient->getACL($testFolder);
} else {
	print "<span style='color:red;'>not supported</span></h1>";
}

$elapsedTime = microtime(true) - $startTime;
print "<h1> $elapsedTime :: Checking for NAMESPACE support: ";
if($imapClient->hasCapability('NAMESPACE')) {
  print "<span style='color:green;'>supported</span></h1>";
  $nameSpace = $imapClient->getNameSpace();
  #print "parsed NAMESPACE info:<br>";
  #var_dump($nameSpace);
} else {
  print "<span style='color:red;'>not supported</span></h1>";
}

$elapsedTime = microtime(true) - $startTime;
print "<h1> $elapsedTime :: Checking for QUOTA support: ";
if($imapClient->hasCapability('QUOTA')) {
  print "<span style='color:green;'>supported</span></h1>";
  $quota = $imapClient->getStorageQuotaRoot();
  print "parsed QUOTA info:<br>";
  var_dump($quota);
} else {
  print "<span style='color:red;'>not supported</span></h1>";
}

$elapsedTime = microtime(true) - $startTime;
print "<h1> $elapsedTime :: Checking for SORT support: ";
if($imapClient->hasCapability('SORT')) {
	print "<span style='color:green;'>supported</span></h1>";
	$elapsedTime = microtime(true) - $startTime;
	print "<h2> $elapsedTime :: Sorting $testFolder by DATE:</h2>";
	$sortResult = $imapClient->sort('DATE');
	$elapsedTime = microtime(true) - $startTime;
	print "<h2> $elapsedTime :: Sorting $testFolder by SUBJECT:</h2>";
	$sortResult = $imapClient->sort('SUBJECT');
} else {
	print "<span style='color:red;'>not supported</span></h1>";
}

$elapsedTime = microtime(true) - $startTime;
print "<h1> $elapsedTime :: Logout</h1>";

$imapClient->disconnect();
print "</pre>";


?>
