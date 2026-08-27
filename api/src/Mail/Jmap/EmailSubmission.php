<?php
/**
 * EGroupware Api: JMAP EmailSubmission type, real-JMAP-over-HTTP implementation
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Jmap;

use EGroupware\Api\Jmap\Type;

/**
 * RFC 8621 §7 EmailSubmission - the actual "send" object, referencing an already-created Email
 * (eg. a Drafts-mailbox Email/set create) plus an Identity to send as.
 *
 * `set()` is overridden (not just the generic Type::set()) because RFC 8621 §7.4 defines
 * `onSuccessUpdateEmail`/`onSuccessDestroyEmail` as extra top-level EmailSubmission/set request
 * arguments (not per-object create properties) - they're what moves/removes the Drafts copy after
 * a successful submission, so a caller has no way to express them through the generic 3-arg
 * create/update/destroy shape alone.
 *
 * `submit()` is the actual reusable "send a message" primitive - deliberately backend-agnostic in
 * shape (MIME-id in, delivery out) rather than compose-specific (no draft-window/compose-state
 * concepts), so both the mail-compose JMAP migration and Storage/Merge.php's mail-merge send can
 * call the same thing later (doc/ai/projects/mail-compose-jmap-migration.md, "Merge scope"
 * decision, 2026-08-27) instead of compose growing its own one-off submit helper first.
 */
class EmailSubmission extends Type
{
	const TYPE_NAME = 'EmailSubmission';

	/**
	 * @param array<string,array> $create id => {emailId, identityId, envelope?}
	 * @param array<string,array> $update id => PatchObject
	 * @param string[] $destroy ids
	 * @param array<string,array>|null $onSuccessUpdateEmail creation-id (`#id`) or id => PatchObject
	 *  to apply to the Email after successful submission (eg. move it out of Drafts, add $sent)
	 * @param string[]|null $onSuccessDestroyEmail creation-id (`#id`) or id list to destroy after
	 *  successful submission (eg. delete a one-shot Drafts copy)
	 * @return array{created?: array, updated?: array, destroyed?: string[], notCreated?: array, notUpdated?: array, notDestroyed?: array}
	 */
	public function set(array $create=[], array $update=[], array $destroy=[],
		?array $onSuccessUpdateEmail=null, ?array $onSuccessDestroyEmail=null) : array
	{
		return $this->jmap->call(static::TYPE_NAME.'/set', array_filter([
			'accountId' => $this->jmap->accountId,
			'create' => $create ?: null,
			'update' => $update ?: null,
			'destroy' => $destroy ?: null,
			'onSuccessUpdateEmail' => $onSuccessUpdateEmail,
			'onSuccessDestroyEmail' => $onSuccessDestroyEmail,
		], static fn($v) => $v !== null));
	}

	/**
	 * Submit an already-created Email for delivery, then patch it to reflect "sent"
	 *
	 * @param string $emailId id of an already-created Email (eg. a Drafts-mailbox Email/set create)
	 * @param string $identityId id of the Identity to send as
	 * @param array|null $envelope {mailFrom: Address, rcptTo: Address[]}, null = server-derived from the Email
	 * @param array|null $sentEmailPatch PatchObject applied to the Email once submission succeeds
	 *  (eg. `{"mailboxIds/DRAFTS_ID": null, "mailboxIds/SENT_ID": true, "keywords/$draft": null}`) -
	 *  null to leave the Email untouched
	 * @param bool $destroySentEmail true to delete the Email once submission succeeds instead of
	 *  patching it (mutually exclusive with $sentEmailPatch - a one-shot send)
	 * @return array raw JMAP `created`/`notCreated` result for creation id "s1" unwrapped, ie.
	 *  either the created EmailSubmission object or throws-nothing/returns the `notCreated`
	 *  SetError - caller checks which key is present, same contract as the rest of this hierarchy
	 */
	public function submit(string $emailId, string $identityId, ?array $envelope=null,
		?array $sentEmailPatch=null, bool $destroySentEmail=false) : array
	{
		$result = $this->set(['s1' => array_filter([
				'emailId' => $emailId,
				'identityId' => $identityId,
				'envelope' => $envelope,
			], static fn($v) => $v !== null)], [], [],
			$sentEmailPatch !== null ? ['#s1' => $sentEmailPatch] : null,
			$destroySentEmail ? ['#s1'] : null);

		return $result['created']['s1'] ?? ['notCreated' => $result['notCreated']['s1'] ?? null];
	}
}
