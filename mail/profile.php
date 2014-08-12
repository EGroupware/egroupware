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

$accounttime = microtime(true);

$times = array(
	'header' => $headertime - $starttime,
	'acc_id' => $acc_id,
	'account' => (string)$account,
	'read' => $accounttime - $headertime,
);

horde_times($account, $times);
mail_times($acc_id, $times);

Header('Content-Type: application/json; charset=utf-8');
echo json_encode($times, JSON_PRETTY_PRINT);

function mail_times($acc_id, array &$times, $prefix='mail_')
{
	$starttime = microtime(true);
	// instanciate mail for given acc_id - have to set it as preference ;-)
	$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = $acc_id;
	// instanciation should call openConnection
	$mail_ui = new mail_ui();
	$mail_ui->mail_bo->openConnection($acc_id);
	$logintime = microtime(true);

	// fetch mailboxes
	$mboxes = $mail_ui->getFolderTree();
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

	$times += array(
		$prefix.'login' => $logintime - $starttime,
		$prefix.'listmailboxes' => $listmailboxestime - $logintime,
		$prefix.'fetch' => $fetchtime - $listmailboxestime,
		$prefix.'total' => $fetchtime - $starttime,
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
