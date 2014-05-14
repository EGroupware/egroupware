<?php
/**
 * EGroupware EMailAdmin: IMAP tests
 *
 * @link http://www.stylite.de
 * @package emailadmin
 * @author Ralf Becker <rb-AT-stylite.de>
 * @copyright (c) 2013 by Ralf Becker <rb-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'emailadmin'
	)
);
require '../header.inc.php';

/**
 * @link http://dev.horde.org/imap_client/documentation.php
 */

$request_start = microtime(true);

function stop_time($total = false)
{
	global $request_start;
	static $now;

	$start = $total || !isset($now) ? $request_start : $now;
	$now = microtime(true);

	echo "<b>took ".number_format($now-$start, 3)."s</b>\n";
}

function horde_connect(array $data)
{
	// Connect to an IMAP server.
/*	$client = new Horde_Imap_Client_Socket(array_merge(array(
		//'port' => '993',
		'secure' => 'ssl',
		//'debug_literal' => true,
		'debug' => '/tmp/imap.log',
		'cache' => array(
			'backend' => new Horde_Imap_Client_Cache_Backend_Cache(array(
				'cacheob' => new emailadmin_horde_cache(),
//				'cacheob' => new Horde_Cache_Storage_Memcache(array(
//					'prefix' => 'test-imap',
//					'memcache' => new Horde_Memcache(),
//				)),
			)),
		),
	), $data));*/

	$client = new emailadmin_imap(array(
		'acc_imap_username' => $data['username'],
		'acc_imap_password' => $data['password'],
		'acc_imap_host' => $data['hostspec'],
		'acc_imap_ssl'	=> 3,	// ssl
	));

	var_dump($client->capability());

	echo "\nisSecureConnection():";
	var_dump($client->isSecureConnection());

	echo "\n(bool)getCache():";
	var_dump((boolean)$client->getCache());

	echo "\ngetNamespaces():";
	var_dump($client->getNamespaces());

	return $client;
}

function horde_fetch(Horde_Imap_Client_Socket $client, $mailbox, $show=true)
{
	$squery = new Horde_Imap_Client_Search_Query();
	$squery->dateSearch(new DateTime('-30days'), Horde_Imap_Client_Search_Query::DATE_SINCE, $header=false, $not=false);
	$squery->flag('DELETED', $set=false);
	$sorted = $client->search($mailbox, $squery, array(
		'sort' => array(Horde_Imap_Client::SORT_REVERSE, Horde_Imap_Client::SORT_SEQUENCE),
	));
	$query_str = $squery->build();
	echo $query_str['query']." search returned $results[count] uids sorted by reverse sequence: ";
	//var_dump($results['match']);
	stop_time();

	$first20uids = new Horde_Imap_Client_Ids();
	$first20uids->add(array_slice($sorted['match']->ids, 0, 20));

	echo "\nUID FETCH (BODY.PEEK[HEADER.FIELDS (SUBJECT FROM TO CC DATE)]): ";
	$fquery = new Horde_Imap_Client_Fetch_Query();
	$fquery->headers('headers', array('Subject', 'From', 'To', 'Cc', 'Date'), array('peek' => true,'cache' => true));
	$fquery->structure();
	$fquery->flags();
	$fquery->imapDate();
	$fetched = $client->fetch($mailbox, $fquery, array(
		'ids' => $first20uids,
	));
	if ($show) var_dump($fetched);
	stop_time();
}

function mail_connect(array $data)
{
	$icServer = new emailadmin_oldimap();
	$icServer->ImapServerId	= 'test-'.$data['username'];
	$icServer->encryption	= 3;	// ssl
	$icServer->host		= $data['hostspec'];
	$icServer->port 	= 993;
	$icServer->validatecert	= false;
	$icServer->username 	= $data['username'];
	$icServer->loginName 	= $data['username'];
	$icServer->password	= $data['password'];
	$icServer->enableSieve	= false;

	$client	= felamimail_bo::getInstance(false, $icServer->ImapServerId, false, $icServer);
	$client->openConnection($icServer->ImapServerId);

	return $client;
}

function mail_fetch(felamimail_bo $client, $mailbox, $show=true)
{
	$filter = $client->createIMAPFilter($mailbox, array(
//		'range' => '0:20',
	));
	//$sorted = $client->getSortedList($mailbox, 'ARRIVAL', $reverse=true, $filter, $resultByUid=true, $setSession=true);
	//_debug_array($sorted);
	$fetched = $client->getHeaders($mailbox, 0, 20, 'ARRIVAL', $reverse=true, array(), $_thisUIDOnly=null, $_cacheResult=true);
	if ($show) _debug_array($fetched);
	stop_time();
}

$show = false;
foreach(array(
	'Horde-IMAP_Client' => array('horde_connect','horde_fetch'),
//	'EGroupware-mail/Net_IMAP' => array('mail_connect','mail_fetch'),
) as $name => $methods)
{;
	$request_start = microtime(true);
	echo "<h1>$name</h1>\n";
	foreach(array(
		'rb@stylite.de' => array(
			'password' => 'secret',
			'hostspec' => 'imap.stylite.de',
			'mailboxes' => array('INBOX')//,'INBOX/Sent'),
		),
	) as $email => $data)
	{
		list($connect, $fetch) = $methods;
		echo "<h2>$email:</h1>\n<pre>";
		$client = $connect(array_merge(array('username'=>$email), $data));
		stop_time();

		foreach($data['mailboxes'] as $mailbox)
		{
			echo "\n</pre><h3>$email: search('$mailbox'):</h2><pre>";
			$fetch($client, $mailbox, $show);
		}
	}
	echo "<h1>total for $name "; stop_time(true);
}

common::egw_exit(true);
