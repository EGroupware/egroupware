<?php
/**
 * EGroupware Api: JMAP Email type, plain-IMAP-shim implementation
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Jmap\Imap;

use EGroupware\Api;
use EGroupware\Api\Mail\Jmap\Email as Base;
use EGroupware\Api\Mail\Jmap\Imap;

/**
 * RFC 8621 §4 Email, real IMAP calls (via the owning Imap session's held connection) - thin
 * adapter translating Api\Jmap\Type's generic get/query/set contract into Imap's own
 * (unchanged) emailGet()/emailQuery()/emailSet() static methods. emailDestroy()/
 * emailSetKeywords()/emailMove() are inherited unmodified from `Base` - they just call
 * $this->set(...), which polymorphically reaches this class's own set() override.
 */
class Email extends Base
{
	public function get(?array $ids=null, ?array $properties=null) : array
	{
		/** @var Imap $jmap */
		$jmap = $this->jmap;
		return Imap::emailGet($jmap->accountId, array_filter([
			'ids' => $ids,
			'properties' => $properties,
		], static fn($v) => $v !== null), $jmap->context);
	}

	public function query(array $filter=[], array $sort=[]) : array
	{
		/** @var Imap $jmap */
		$jmap = $this->jmap;
		return Imap::emailQuery($jmap->accountId, array_filter([
			'filter' => $filter ?: null,
			'sort' => $sort ?: null,
		], static fn($v) => $v !== null), $jmap->context);
	}

	/**
	 * Plain IMAP UIDs (this shim's Email ids) are per-mailbox, not globally unique - Imap::
	 * emailSet() needs to know which mailbox $update/$destroy's ids live in, same as get()/
	 * query() already do (see their own docblocks). Reconstructed from $this->context (set by
	 * a preceding query() in the same session/request) rather than needing an explicit
	 * mailboxId param on this generic Type::set() contract - callers should query()/get() the
	 * ids they want to mutate first, same ordering MailJmap.ts's own batching already relies on.
	 */
	public function set(array $create=[], array $update=[], array $destroy=[]) : array
	{
		/** @var Imap $jmap */
		$jmap = $this->jmap;
		$args = array_filter([
			'update' => $update ?: null,
			'destroy' => $destroy ?: null,
		], static fn($v) => $v !== null);
		$mailboxName = $jmap->context['mailbox'][$jmap->accountId] ?? null;
		if ($mailboxName !== null && ($imap = Imap::imapServer($jmap->accountId)))
		{
			$args['mailboxId'] = base64_encode(Imap::canonicalPath($imap, $mailboxName));
		}
		return Imap::emailSet($jmap->accountId, $args);
	}

	/**
	 * @param string $blobId from Imap::upload()/readUploadedBlob() - see Imap::emailImport()
	 * @param string $folder folder-path
	 * @param array $keywords JMAP keyword => true
	 * @return string new (IMAP UID) Email.id
	 * @throws Api\Exception
	 */
	public function emailImport(string $blobId, string $folder, array $keywords=[]) : string
	{
		/** @var Imap $jmap */
		$jmap = $this->jmap;
		$response = Imap::emailImport($jmap->accountId, [
			'emails' => ['x' => [
				'blobId' => $blobId,
				'mailboxIds' => [$this->jmap->mailbox->getMailboxId($folder) => true],
				'keywords' => $keywords,
			]],
		]);
		return $response['created']['x']['id'] ??
			throw new Api\Exception('Email/import failed: '.json_encode($response['notCreated']['x'] ?? []));
	}

	/**
	 * @param string $mailboxId JMAP Mailbox id (base64 folder path)
	 * @param string $uid
	 * @param string $topLevelType see Api\Mail\Smime::resolveMessage()
	 * @param string $fromAddress
	 * @param string $htmlOptions
	 * @param string $passphrase
	 * @return array{body: string, smime: ?array}
	 */
	public function resolveSmime(string $mailboxId, string $uid, string $topLevelType,
		string $fromAddress, string $htmlOptions='', string $passphrase='') : array
	{
		/** @var Imap $jmap */
		$jmap = $this->jmap;
		return Imap::resolveSmime($jmap->accountId, $mailboxId, $uid, $topLevelType, $fromAddress, $htmlOptions, $passphrase);
	}

	/**
	 * @param string $mailboxId JMAP Mailbox id (base64 folder path)
	 * @param string $uid
	 * @param string $partId
	 * @param string $htmlOptions
	 * @return string sanitized HTML body
	 */
	public function resolveTnef(string $mailboxId, string $uid, string $partId, string $htmlOptions='') : string
	{
		/** @var Imap $jmap */
		$jmap = $this->jmap;
		return Imap::resolveTnef($jmap->accountId, $mailboxId, $uid, $partId, $htmlOptions);
	}

	/**
	 * Not implemented for the plain-IMAP shim - there's no push/live-sync support here
	 * (JmapShim never had an equivalent), unlike the real-JMAP (Http) side.
	 */
	public function getStates(string $folder='INBOX', ?string $accountId=null, ?string &$sessionState=null) : array
	{
		throw new \BadMethodCallException(static::class.' does not support getStates() - no push/sync for plain IMAP accounts');
	}

	/**
	 * @see getStates()
	 */
	public function getChanges(?string $accountId, array $states, string $mailbox='INBOX', ?string &$sessionState=null)
	{
		throw new \BadMethodCallException(static::class.' does not support getChanges() - no push/sync for plain IMAP accounts');
	}
}
