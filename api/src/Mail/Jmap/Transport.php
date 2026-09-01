<?php
/**
 * EGroupware Api: JMAP EmailSubmission (RFC 8621 §7) as a Horde_Mail_Transport plugin
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Jmap;

use EGroupware\Api\Mail;
use EGroupware\Api\Mail\Imap\Jmap as ImapJmap;

/**
 * Sends mail via JMAP (RFC 8621 §7 EmailSubmission) instead of SMTP - Mail\Account::
 * smtpTransport() instantiates this whenever acc_smtp_ssl's protocol bits are JMAP_HTTP/
 * JMAP_HTTPS (mirrors how acc_imap_ssl/acc_sieve_ssl already select JMAP), not via acc_smtp_type
 * (that field stays the unrelated account-*provisioning* selector, see normalizeAccountType()).
 *
 * Unlike SMTP, JMAP submission needs a structured Email object (RFC 8621 §4.1), not a byte
 * stream - send() parses the already-rendered MIME (Api\Mailer/Horde_Mime_Mail flattens
 * everything before calling a transport) back into its plain/html body parts and attachments via
 * Horde_Mime_Part::parseMessage(), uploads each attachment as its own blob, creates the Email in
 * Drafts, then submits it and moves the Drafts copy to Sent on success - the same JMAP mechanics
 * mail/src/ApiHandler.php's sendViaJmap() already uses for compose (Identity resolution by From
 * address match, Drafts/Sent-by-role lookup, onSuccessUpdateEmail housekeeping), generalized here
 * to handle multipart bodies/attachments and to work for ANY Api\Mailer caller (notifications,
 * mail-merge, cron jobs, ...), not just interactive compose.
 */
class Transport extends \Horde_Mail_Transport
{
	protected Mail\Account $account;
	protected ?Http $jmap = null;

	public function __construct(Mail\Account $account)
	{
		$this->account = $account;
	}

	/**
	 * Resolve the JMAP session to submit through
	 *
	 * If acc_smtp_host/username match acc_imap_host/username (the common case: one JMAP server
	 * for both), reuse the already-authenticated connection imapServer()->jmapClient() uses for
	 * everything else - no duplicate authentication, no separate credential story to maintain.
	 * Otherwise (a genuinely different submission server/account) opens its own connection from
	 * the acc_smtp_* fields.
	 */
	protected function jmapClient() : Http
	{
		if (!isset($this->jmap))
		{
			// getParamOverwrites(), not raw acc_smtp_*/acc_imap_* properties - matches
			// Account::smtpTransport()'s own classic-SMTP branch, which reads from there too
			// (a rare hosting-specific hook, /var/www/mail-overwrites.inc.php - a no-op on most
			// installs, but honored here for consistency)
			$params = $this->account->getParamOverwrites();
			$sameServer = empty($params['acc_smtp_host']) ||
				($params['acc_smtp_host'] === $params['acc_imap_host'] &&
					(empty($params['acc_smtp_username']) ||
						$params['acc_smtp_username'] === $params['acc_imap_username']));
			$imap = $sameServer ? $this->account->imapServer() : null;

			if ($imap && is_a($imap, ImapJmap::class))
			{
				$this->jmap = $imap->jmapClient();
			}
			else
			{
				$ssl = (int)$params['acc_smtp_ssl'];
				$verify = ($ssl & Mail\Account::VERIFY_MASK) !== Mail\Account::VERIFY_DISABLED;
				try {
					$accountId = null;
					$this->jmap = new Http(
						Mail\Account::jmapUrl($params['acc_smtp_host'], (int)$params['acc_smtp_port'], $ssl),
						$params['acc_smtp_username'], $params['acc_smtp_password'],
						$accountId, $verify, $params['acc_id'] ?: null);
				}
				catch (\Throwable $e) {
					throw new \Horde_Mail_Exception($e);
				}
			}
		}
		return $this->jmap;
	}

	/**
	 * @param mixed $recipients comma-separated string or array of bare RFC822 addresses - the
	 *  real RCPT TO list, may include Bcc recipients not present in $headers
	 * @param array $headers header-name => value (value may be an array for multi-address headers)
	 * @param mixed $body full message body/MIME string or stream - already fully rendered, no
	 *  structured access to individual parts at this layer (see class docblock)
	 * @throws \Horde_Mail_Exception
	 */
	public function send($recipients, array $headers, $body)
	{
		try {
			$this->sendJmap($recipients, $headers, $body);
		}
		catch (\Horde_Mail_Exception $e) {
			throw $e;
		}
		catch (\Throwable $e) {
			throw new \Horde_Mail_Exception($e);
		}
	}

	private function sendJmap($recipients, array $headers, $body) : void
	{
		$jmap = $this->jmapClient();

		[$fromAddr, $textHeaders] = $this->prepareHeaders($headers) ?: [null, null];
		if (!$fromAddr)
		{
			throw new \Horde_Mail_Exception('No From address provided.');
		}

		$header = static function(string $name) use ($headers)
		{
			foreach ($headers as $key => $value)
			{
				if (strcasecmp($key, $name) === 0) return $value;
			}
			return null;
		};
		// address widgets can hand a caller a full "Display Name <address@example.com>" string,
		// not a bare address - Api\Mail::parseAddressList() is this codebase's own battle-tested
		// RFC 822 parser, same one mail/src/ApiHandler.php's sendViaJmap() already relies on
		$addresses = static function($value)
		{
			if (empty($value)) return null;
			$list = [];
			foreach (Mail::parseAddressList($value) as $address)
			{
				if (!$address->valid) continue;
				$list[] = array_filter([
					'email' => $address->bare_address,
					'name' => $address->personal,
				], static fn($v) => $v !== null && $v !== '');
			}
			return $list ?: null;
		};
		$to = $addresses($header('To'));
		$cc = $addresses($header('Cc'));
		$bcc = $addresses($header('Bcc'));
		if ($bcc === null)
		{
			// a Bcc header, if ever present, is typically stripped before this point - the real
			// Bcc recipients only show up in $recipients (see this method's own @param doc),
			// as whatever's left over once To/Cc are subtracted
			$known = array_map(static fn($a) => strtolower($a['email']), array_merge($to ?: [], $cc ?: []));
			$rcptOnly = array_values(array_diff(array_map('strtolower', $this->parseRecipients($recipients)), $known));
			$bcc = $rcptOnly ? array_map(static fn($e) => ['email' => $e], $rcptOnly) : null;
		}

		// $body is only the BODY (see this method's own @param doc) - Horde_Mime_Part::
		// parseMessage() needs the Content-Type/boundary headers alongside it to correctly
		// interpret a multipart structure, so recombine before parsing, same as
		// Horde_Mail_Transport_Smtphorde::send() recombines them for the wire
		$rawBody = is_resource($body) ? stream_get_contents($body) : (string)$body;
		$mime = \Horde_Mime_Part::parseMessage($textHeaders."\r\n\r\n".$rawBody);

		$bodyValues = [];
		$textBody = $htmlBody = null;
		$plainId = $mime->findBody('plain');
		$htmlId = $mime->findBody('html');
		if ($plainId !== null)
		{
			$part = $mime->getPart($plainId);
			$bodyValues['plain'] = ['value' => $part->getContents(), 'charset' => $part->getCharset() ?: 'utf-8'];
			$textBody = [['partId' => 'plain', 'type' => 'text/plain']];
		}
		if ($htmlId !== null)
		{
			$part = $mime->getPart($htmlId);
			$bodyValues['html'] = ['value' => $part->getContents(), 'charset' => $part->getCharset() ?: 'utf-8'];
			$htmlBody = [['partId' => 'html', 'type' => 'text/html']];
		}
		$skip = array_filter([$plainId, $htmlId], static fn($id) => $id !== null);
		$attachments = [];
		foreach ($mime->partIterator() as $part)
		{
			$id = $part->getMimeId();
			if (in_array($id, $skip, true) || $part->getPrimaryType() === 'multipart')
			{
				continue;
			}
			$attachments[] = array_filter([
				'blobId' => $jmap->uploadBlob($part->getContents(), $part->getType()),
				'type' => $part->getType(),
				'name' => $part->getName() ?: null,
				'disposition' => $part->getDisposition() ?: 'attachment',
				'cid' => $part->getContentId() ?: null,
			], static fn($v) => $v !== null && $v !== '');
		}

		[$drafts_id, $sent_id, $identities] = $this->resolveMailboxesAndIdentities($jmap);
		// prefer the identity matching the From address, else fall back to the first one -
		// EGroupware's own ident_id is NOT a valid JMAP identityId (Identity/get is always
		// synthesized locally, never synced to the real backend, see Mail\Jmap\Identity's
		// docblock) - the From address is the only reliable link to the backend's real Identity
		$identity = current(array_filter($identities, static fn($i) => strcasecmp($i['email'], $fromAddr) === 0)) ?: $identities[0];

		$create = array_filter([
			'mailboxIds' => [$drafts_id => true],
			'keywords' => ['$draft' => true],
			'from' => [array_filter(['email' => $identity['email'], 'name' => $identity['name'] ?? null])],
			'to' => $to,
			'cc' => $cc,
			'bcc' => $bcc,
			'subject' => $header('Subject') ?? '',
			'bodyValues' => $bodyValues ?: null,
			'textBody' => $textBody,
			'htmlBody' => $htmlBody,
			'attachments' => $attachments ?: null,
		], static fn($value) => $value !== null);

		$email_set = $jmap->email->set(['s1' => $create]);
		if (!empty($email_set['notCreated']['s1']))
		{
			throw new \Horde_Mail_Exception('Email/set create failed: '.json_encode($email_set['notCreated']['s1']));
		}
		$email_id = $email_set['created']['s1']['id'];

		$result = $jmap->emailSubmission->submit($email_id, $identity['id'], null, [
			"mailboxIds/$drafts_id" => null,
			"mailboxIds/$sent_id" => true,
			'keywords/$draft' => null,
			// a Sent-folder copy is a message the user themselves sent, not new/incoming mail -
			// mark it read, matching sendViaJmap()'s own convention
			'keywords/$seen' => true,
		]);
		if (isset($result['notCreated']))
		{
			throw new \Horde_Mail_Exception('EmailSubmission/set failed: '.json_encode($result['notCreated']));
		}
	}

	/**
	 * Verify this account can actually submit via JMAP - everything send() needs BEFORE it would
	 * touch a real message (a reachable session, a Drafts/Sent mailbox by role, at least one
	 * Identity) - used by the account-edit wizard's SMTP step to test a JMAP submission choice
	 * the same way it already tests a classic SMTP one, without sending anything.
	 *
	 * @throws \Horde_Mail_Exception
	 */
	public function testConnection() : void
	{
		try {
			$this->resolveMailboxesAndIdentities($this->jmapClient());
		}
		catch (\Horde_Mail_Exception $e) {
			throw $e;
		}
		catch (\Throwable $e) {
			throw new \Horde_Mail_Exception($e);
		}
	}

	/**
	 * @param Http $jmap
	 * @return array{0: string, 1: string, 2: array[]} Drafts mailbox id, Sent mailbox id, list of
	 *  RFC 8621 §6.1 Identity objects (at least one)
	 * @throws \Horde_Mail_Exception no Drafts/Sent mailbox by role, or no Identity at all
	 */
	private function resolveMailboxesAndIdentities(Http $jmap) : array
	{
		$drafts_id = $sent_id = null;
		foreach ($jmap->mailbox->get()['list'] ?? [] as $mailbox)
		{
			if (($mailbox['role'] ?? null) === 'drafts') $drafts_id = $mailbox['id'];
			if (($mailbox['role'] ?? null) === 'sent') $sent_id = $mailbox['id'];
		}
		if (!$drafts_id || !$sent_id)
		{
			throw new \Horde_Mail_Exception('Could not find Drafts/Sent mailbox by role for account #'.$this->account->acc_id);
		}
		if (!($identities = $jmap->identity->get()['list'] ?? []))
		{
			throw new \Horde_Mail_Exception('No JMAP identity found for account #'.$this->account->acc_id);
		}
		return [$drafts_id, $sent_id, $identities];
	}
}
