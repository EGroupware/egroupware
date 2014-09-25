<?php
/**
 * Profiling of diverse mail functions
 *
 * For Apache FCGI you need the following rewrite rule:
 *
 * 	RewriteEngine on
 * 	RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]
 *
 * Otherwise authentication request will be send over and over again, as password is NOT available to PHP!
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package mail
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2014 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

$starttime = microtime(true);

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'disable_Template_class' => True,
		'noheader'  => True,
		'currentapp' => 'mail',
		'autocreate_session_callback' => 'egw_digest_auth::autocreate_session_callback',
		'auth_realm' => 'EGroupware mail profile',
	)
);
//require_once('../phpgwapi/inc/class.egw_digest_auth.inc.php');
include(dirname(__DIR__).'/header.inc.php');

$headertime = microtime(true);

// on which mail account do we work, if not specified use default one (connects to imap server!)
$acc_id = isset($_GET['acc_id']) && (int)$_GET['acc_id'] > 0 ? (int)$_GET['acc_id'] : emailadmin_account::get_default_acc_id();
// calling emailadmin_account::read with explicit account_id to not cache object for current user!
$account = emailadmin_account::read($acc_id, $GLOBALS['egw_info']['user']['account_id']);

// switching off caching by default
// if caching is enabled mail_times will always provit from previous running horde_times!
$cache = isset($_GET['cache']) && $_GET['cache'];
if (!$cache) unset(emailadmin_imap::$default_params['cache']);

$accounttime = microtime(true);

$times = array(
	'header' => $headertime - $starttime,
	'acc_id' => $acc_id,
	'account' => (string)$account,
	'cache' => $cache,
	'read' => $accounttime - $headertime,
);

php_times($account, $times);
horde_times($account, $times);
mail_times($acc_id, $times);

Header('Content-Type: application/json; charset=utf-8');
echo json_encode($times, JSON_PRETTY_PRINT);

function php_times($account, array &$times, $prefix='php_')
{
	$starttime = microtime(true);
	switch($account->acc_imap_ssl & ~emailadmin_account::SSL_VERIFY)
	{
		case emailadmin_account::SSL_SSL:
			$schema = 'ssl';
			break;
		case emailadmin_account::SSL_TLS:
			$schema = 'tls';
			break;
		case emailadmin_account::SSL_STARTTLS:
		default:
			$schema = 'tcp';
			break;
	}
	$error_number = $error_string = null;
	$stream = stream_socket_client(
		$schema . '://' . $account->acc_imap_host . ':' . $account->acc_imap_port,
		$error_number,
		$error_string,
		20,
		STREAM_CLIENT_CONNECT,
		/* @todo: As of PHP 5.6, TLS connections require valid certs.
		 * However, this is BC-breaking to this library. For now, keep
		 * pre-5.6 behavior. */
		stream_context_create(array(
			'ssl' => array(
				'verify_peer' => false,
				'verify_peer_name' => false
			)
		))
	);
	$connect_response = fgets($stream);

	// starttls (untested)
	if ($stream && ($account->acc_imap_ssl & ~emailadmin_account::SSL_VERIFY) == emailadmin_account::SSL_STARTTLS)
	{
		fwrite($stream, "10 STARTTLS\r\n");
		stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
		$starttls_response = fgets($stream);
	}
	stream_set_timeout($stream, 20);

	if (function_exists('stream_set_read_buffer')) {
		stream_set_read_buffer($stream, 0);
	}
	stream_set_write_buffer($stream, 0);

	$connect = microtime(true);

	fwrite($stream, "20 LOGIN $account->acc_imap_username $account->acc_imap_password\r\n");
	$login_response = fgets($stream);
	$endtime = microtime(true);

	$times += array(
		$prefix.'connect' => $connect - $starttime,
		//$prefix.'connect_response' => $connect_response,
		$prefix.'login' => $endtime - $starttime,
		//$prefix.'login_response' => $login_response,
	);

	fclose($stream);
	unset($connect_response, $starttls_response, $login_response, $error_number, $error_string);
}

function mail_times($acc_id, array &$times, $prefix='mail_')
{
	global $cache;
	$starttime = microtime(true);
	// instanciate mail for given acc_id - have to set it as preference ;-)
	$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = $acc_id;
	// instanciation should call openConnection
	$mail_ui = new mail_ui();
	$mail_ui->mail_bo->openConnection($acc_id);
	$logintime = microtime(true);

	// fetch mailboxes
	$mboxes = $mail_ui->getFolderTree(/*$_fetchCounters=*/false, null, /*$_subscribedOnly=*/true,
		/*$_returnNodeOnly=*/true, $cache, /*$_popWizard=*/false);
	$listmailboxestime = microtime(true);

	// get first 20 mails
	$query = array(
		'start' => 0,
		'num_rows' => 20,
		'filter' => 'any',
		'filter2' => 'quick',
		'search' => '',
		'order' => 'date',
		'sort' => 'DESC',
	);
	$rows = $readonlys = array();
	$mail_ui->get_rows($query, $rows, $readonlys);
	$fetchtime = microtime(true);

	if (isset($_GET['uid']) && (int)$_GET['uid'] > 0)
	{
		$uid = (int)$_GET['uid'];
	}
	else	// use uid of first returned row
	{
		$row = array_shift($rows);
		$uid = $row['uid'];
	}
	$mail_ui->get_load_email_data($uid, null, 'INBOX');
	$bodytime = microtime(true);

	$times += array(
		$prefix.'login' => $logintime - $starttime,
		$prefix.'listmailboxes' => $listmailboxestime - $logintime,
		$prefix.'fetch' => $fetchtime - $listmailboxestime,
		$prefix.'total' => $fetchtime - $starttime,
		$prefix.'body' => $bodytime - $fetchtime,
		//$prefix.'mboxes' => $mboxes,
	);
	unset($mboxes);
	$mail_ui->mail_bo->icServer->close();
	$mail_ui->mail_bo->icServer->logout();
}

function horde_times(emailadmin_account $account, array &$times, $prefix='horde_')
{
	$starttime = microtime(true);
	$imap = $account->imapServer();

	// connect to imap server
	$imap->login();
	$logintime = microtime(true);

	// list all subscribed mailboxes incl. attributes and children
	$mboxes = $imap->listMailboxes('*', Horde_Imap_Client::MBOX_SUBSCRIBED, array(
		'attributes' => true,
		'children' => true,
	));
	$listmailboxestime = microtime(true);

	// fetch 20 newest mails
	horde_fetch($imap);
	$fetchtime = microtime(true);

	$times += array(
		$prefix.'login' => $logintime - $starttime,
		$prefix.'listmailboxes' => $listmailboxestime - $logintime,
		$prefix.'fetch' => $fetchtime - $listmailboxestime,
		$prefix.'total' => $fetchtime - $starttime,
		//$prefix.'mboxes' => $mboxes,
	);
	unset($mboxes);
	$imap->close();
	$imap->logout();
}

function horde_fetch(Horde_Imap_Client_Socket $client, $mailbox='INBOX')
{
	$squery = new Horde_Imap_Client_Search_Query();
	// using a date filter to limit returned uids, gives huge speed improvement on big mailboxes, because less uids returned
	//$squery->dateSearch(new DateTime('-30days'), Horde_Imap_Client_Search_Query::DATE_SINCE, false, false);
	$squery->flag('DELETED', $set=false);
	$sorted = $client->search($mailbox, $squery, array(
		'sort' => array(Horde_Imap_Client::SORT_REVERSE, Horde_Imap_Client::SORT_SEQUENCE),
	));

	$first20uids = new Horde_Imap_Client_Ids();
	$first20uids->add(array_slice($sorted['match']->ids, 0, 20));

	$fquery = new Horde_Imap_Client_Fetch_Query();
	$fquery->headers('headers', array('Subject', 'From', 'To', 'Cc', 'Date'), array('peek' => true,'cache' => true));
	$fquery->structure();
	$fquery->flags();
	$fquery->imapDate();

	return $client->fetch($mailbox, $fquery, array(
		'ids' => $first20uids,
	));
}
