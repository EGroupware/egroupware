<?php
/**
 * EGroupware Api: real JMAP-over-HTTP session for a mail account (eg. Stalwart)
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Jmap;

use EGroupware\Api\Jmap;
use EGroupware\Api\Framework;

/**
 * Mail-specific wiring on top of the generic `Api\Jmap\Http` session: resolves EGroupware's own
 * sentinel hostnames ("mail", "stalwart", ...) before bootstrapping, and registers this app's
 * per-type classes (Mailbox/Email/Thread/Quota).
 *
 * Declares the full mail-related capability set (Core+Mail+Quota+mail-sharing+principals) for
 * every call rather than narrowly scoping "using" per call like the former `Api\Mail\Jmap` client
 * did (eg. Quota-only for getQuota()) - a deliberate simplification, since the only real-JMAP
 * server this codebase talks to (Stalwart) supports all of these; would need revisiting if a real
 * non-Stalwart JMAP server without one of these extensions is ever targeted.
 */
class Http extends Jmap\Http
{
	/**
	 * JMAP mail (includes core!)
	 */
	const JMAP_MAIL = [self::JMAP_CORE, "urn:ietf:params:jmap:mail"];
	/**
	 * JMAP quota extension, see https://www.rfc-editor.org/rfc/rfc9425
	 */
	const JMAP_QUOTA = "urn:ietf:params:jmap:quota";
	/**
	 * JMAP mail sharing extension (editable Mailbox shareWith/myRights), see
	 * https://www.ietf.org/archive/id/draft-ietf-jmap-mail-sharing (base RFC 8621's myRights
	 * is read-only)
	 */
	const JMAP_MAIL_SHARE = "urn:ietf:params:jmap:mail:share";
	/**
	 * JMAP principals extension, needed to resolve shareWith's identifiers (Principal ids, NOT
	 * IMAP usernames/emails), see https://www.rfc-editor.org/rfc/rfc9670
	 */
	const JMAP_PRINCIPALS = "urn:ietf:params:jmap:principals";
	/**
	 * JMAP submission extension (Identity + EmailSubmission - sending), see RFC 8621 §6/§7
	 */
	const JMAP_SUBMISSION = "urn:ietf:params:jmap:submission";

	protected array $types = [
		'mailbox' => Mailbox::class,
		'email' => Email::class,
		'thread' => Thread::class,
		'quota' => Quota::class,
		'identity' => Identity::class,
		'emailSubmission' => EmailSubmission::class,
	];

	/**
	 * @param string $host_or_url JMAP url, or hostname/sentinel to bootstrap from
	 * @param string $user username
	 * @param string $secret password
	 * @param string|null &$accountId jmap accountId
	 * @param bool $verify =true false: disable TLS certificate verification for this connection
	 */
	public function __construct(string $host_or_url, string $user, string $secret, ?string &$accountId=null, bool $verify=true)
	{
		// EGroupware Mail "mail" service (the local JMAP-over-IMAP shim's own endpoint)
		if ($host_or_url === 'mail')
		{
			$host_or_url = Framework::getUrl('/jmap/');
		}
		// EGroupware Hosting
		elseif ($host_or_url === 'stalwart' || $host_or_url === 'internal.k8s.farm.egroupware.org')
		{
			$host_or_url = 'https://stalwart.egroupware.org/jmap/';
		}
		parent::__construct($host_or_url, $user, $secret,
			array_merge(self::JMAP_MAIL, [self::JMAP_QUOTA, self::JMAP_MAIL_SHARE, self::JMAP_PRINCIPALS,
				self::JMAP_SUBMISSION]),
			$accountId, $verify);
	}

	// --- Convenience passthroughs to $this->email/$this->mailbox/$this->quota -----------------
	//
	// Kept flat on the session itself (not just reachable via $jmap->email->emailGet(...)) since
	// this is exactly the shape the former Api\Mail\Jmap client exposed, and ~20 existing call
	// sites across api/src/Mail.php, Imap/Jmap.php, Smtp/Stalwart.php, mail/src/Ui/*.php already
	// call jmapClient()->emailGet(...) etc directly - cheaper and lower-risk to keep that surface
	// working than to rewrite every one of those call sites for this promotion. New code can use
	// either $jmap->emailGet(...) or the more explicit $jmap->email->emailGet(...) - both reach
	// the same method.

	/** @see Email::emailGet() */
	public function emailGet(string $id, array $properties, bool $fetchAllBodyValues=true) : array
	{
		return $this->email->emailGet($id, $properties, $fetchAllBodyValues);
	}

	/** @see Email::emailQuery() */
	public function emailQuery(string $folder, array $conditions, string $sortProperty='receivedAt',
		bool $ascending=false, int $limit=1000) : array
	{
		return $this->email->emailQuery($folder, $conditions, $sortProperty, $ascending, $limit);
	}

	/** @see Email::emailImport() */
	public function emailImport(string $blobId, string $folder, array $keywords=[]) : string
	{
		return $this->email->emailImport($blobId, $folder, $keywords);
	}

	/** @see Email::emailDestroy() */
	public function emailDestroy(array $ids) : void
	{
		$this->email->emailDestroy($ids);
	}

	/** @see Email::emailSetKeywords() */
	public function emailSetKeywords(array $ids, array $patch) : void
	{
		$this->email->emailSetKeywords($ids, $patch);
	}

	/** @see Email::emailMove() */
	public function emailMove(array $ids, string $targetFolder) : void
	{
		$this->email->emailMove($ids, $targetFolder);
	}

	/** @see Mailbox::getMailboxId() */
	public function getMailboxId(string $folder, ?string $accountId=null) : ?string
	{
		return $this->mailbox->getMailboxId($folder, $accountId);
	}

	/** @see Mailbox::folderId2path() */
	public function folderId2path(string $folderId)
	{
		return $this->mailbox->folderId2path($folderId);
	}

	/** @see Email::getStates() */
	public function getStates(string $folder='INBOX', ?string $accountId=null, ?string &$sessionState=null) : array
	{
		return $this->email->getStates($folder, $accountId, $sessionState);
	}

	/** @see Email::getChanges() */
	public function getChanges(?string $accountId, array $states, string $mailbox='INBOX', ?string &$sessionState=null)
	{
		return $this->email->getChanges($accountId, $states, $mailbox, $sessionState);
	}

	/**
	 * @param string|null $accountId unused - kept to match the former Api\Mail\Jmap::getQuota()
	 *  signature; this session is already scoped to one account
	 * @return array[]|null list of Quota objects, or null if the server doesn't advertise the
	 *  quota capability (same "not supported" contract the former client used - callers fall
	 *  back to a non-JMAP way of getting the quota in that case)
	 */
	public function getQuota(?string $accountId=null) : ?array
	{
		if (!in_array(self::JMAP_QUOTA, $this->capabilities ?? []))
		{
			return null;
		}
		return $this->quota->get()['list'] ?? null;
	}
}
