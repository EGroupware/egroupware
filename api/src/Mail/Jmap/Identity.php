<?php
/**
 * EGroupware Api: JMAP Identity type - always synthesized from our own Mail\Account, never a
 * real passthrough to either backend
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Jmap;

use EGroupware\Api\Accounts;
use EGroupware\Api\Contacts\Merge;
use EGroupware\Api\Jmap\Type;
use EGroupware\Api\Mail\Account;
use EGroupware\Api\Mail\Html;

/**
 * RFC 8621 §6.1 Identity, always built directly from `Mail\Account::identities()` - deliberately
 * NOT `Type`'s generic get()/set() passthrough (unlike every other real-JMAP type here), and used
 * unconditionally for both backends (`Http` below, and `Imap::dispatch()`'s own `Identity/get`
 * case, which calls synthesize() directly).
 *
 * Why: we don't sync any identity/signature data to Stalwart today - confirmed via
 * `Api\Mail\Smtp\Stalwart`'s account provisioning, which only ever pushes the mailbox's own
 * `description`/full name, nothing resembling an Identity (no email/replyTo/bcc/signature, no
 * concept of EGroupware's multiple-identities-per-mailbox model) - so Stalwart's own native
 * Identity/get would return nothing usable. This also sidesteps a non-issue: RFC 8621's Identity
 * object has no signature-*placement* concept at all on any JMAP server - a server only ever hands
 * back signature text, where it goes in a composed body is always a client decision (ralf,
 * 2026-08-27).
 *
 * Escape hatch if EGroupware identities/signatures are ever synced to Stalwart natively later:
 * remove this class's overrides (or Http's `Identity` entry falls back to `Type`'s own generic
 * get()/set()) and it becomes a real passthrough again - no other code needs to change.
 */
class Identity extends Type
{
	const TYPE_NAME = 'Identity';

	/**
	 * @param string[]|null $ids null = all
	 * @param string[]|null $properties ignored - always returns every property, same as every
	 *  other type here when the caller doesn't ask for a narrower set
	 */
	public function get(?array $ids=null, ?array $properties=null) : array
	{
		return self::synthesize($this->jmap->acc_id ?? (int)$this->jmap->accountId, $ids);
	}

	/**
	 * Identities are configured through EGroupware's own account/identity admin UI, not editable
	 * via JMAP - decline everything explicitly rather than silently reaching Stalwart via `Type`'s
	 * generic set() passthrough (which has no concept of them anyway).
	 */
	public function set(array $create=[], array $update=[], array $destroy=[]) : array
	{
		$forbidden = static fn() => ['type' => 'forbidden'];
		return [
			'accountId' => (string)($this->jmap->acc_id ?? $this->jmap->accountId),
			'oldState' => '0',
			'newState' => '0',
			'created' => (object)[],
			'updated' => (object)[],
			'destroyed' => [],
			'notCreated' => (object)array_map($forbidden, $create),
			'notUpdated' => (object)array_map($forbidden, $update),
			'notDestroyed' => (object)array_combine($destroy, array_map($forbidden, $destroy)),
		];
	}

	/**
	 * Build RFC 8621 §6.1 Identity objects straight from `Mail\Account::identities()` - the one
	 * implementation shared by both backends (see this class's own docblock).
	 *
	 * @param int $acc_id EGroupware mail account id (NOT Stalwart's own opaque JMAP accountId)
	 * @param string[]|null $ids null = all
	 * @return array{accountId: string, state: string, list: array[], notFound: string[]}
	 */
	public static function synthesize(int $acc_id, ?array $ids=null) : array
	{
		$list = [];
		foreach (Account::identities($acc_id, true, 'params') as $row)
		{
			if ($ids !== null && !in_array((string)$row['ident_id'], $ids, true))
			{
				continue;
			}
			$list[] = self::identityObject($row);
		}
		return [
			'accountId' => (string)$acc_id,
			'state' => '0',
			'list' => $list,
			'notFound' => $ids !== null ? array_values(array_diff($ids, array_column($list, 'id'))) : [],
		];
	}

	/**
	 * One identity row (`Mail\Account::identities()`'s full/'params' shape) -> RFC 8621 Identity
	 * object, including the merge-resolved HTML signature and its server-converted plain-text
	 * counterpart (RFC 8621's htmlSignature/textSignature) - the client picks whichever variant
	 * matches its current compose mimeType, never needing a separate conversion call for either.
	 * Same merge+convert steps `mail_compose.inc.php`'s own signature insertion already does
	 * (`Mail::merge($sig, [person_id])`, `Html::convertHTMLToText()`), ported here directly rather
	 * than via `Api\Mail`/mail_bo (ralf, 2026-08-27: avoid mail_bo, use Mail\Account directly).
	 *
	 * @param array $row
	 * @return array
	 */
	private static function identityObject(array $row) : array
	{
		$htmlSignature = '';
		$textSignature = '';
		if (!empty($row['ident_signature']))
		{
			$personId = Accounts::id2name($GLOBALS['egw_info']['user']['account_id'], 'person_id');
			$err = '';
			$htmlSignature = (new Merge())->merge_string($row['ident_signature'], [$personId], $err,
				'text/html', [], 'utf-8');
			// merge_string() can return empty on error (eg. an unresolvable placeholder) - fall
			// back to the raw, unmerged signature rather than silently dropping it
			if ($htmlSignature === '' && $err !== '')
			{
				$htmlSignature = $row['ident_signature'];
			}
			$textSignature = Html::convertHTMLToText($htmlSignature, 'utf-8', true, true);
		}
		return [
			'id' => (string)$row['ident_id'],
			'name' => $row['ident_realname'] ?: ($row['ident_name'] ?? ''),
			'email' => $row['ident_email'] ?? '',
			'replyTo' => null,
			'bcc' => null,
			'textSignature' => $textSignature,
			'htmlSignature' => $htmlSignature,
			'mayDelete' => false,
		];
	}
}
