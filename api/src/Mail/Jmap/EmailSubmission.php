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
	 * @param bool $destroySubmission true (default): destroy the just-created EmailSubmission
	 *  object again right after a successful submit(), since nothing in this codebase ever polls
	 *  its deliveryStatus afterwards - RFC 8621 §7.1 lets a server keep EmailSubmission objects
	 *  around indefinitely, but doesn't require it to, and Stalwart enforces a hard per-account
	 *  QUOTA on how many it keeps; every submit() that never destroys its own object permanently
	 *  burns one slot, so eventually EVERY account sending through this transport fails outright
	 *  with EmailSubmission/set's "overQuota" SetError - found live 2026-11-19 (Stalwart
	 *  production, pole.egroupware.org). Pass false only for a caller that genuinely needs the
	 *  object to survive (eg. a future deliveryStatus poll).
	 * @return array raw JMAP `created`/`notCreated` result for creation id "s1" unwrapped, ie.
	 *  either the created EmailSubmission object or throws-nothing/returns the `notCreated`
	 *  SetError - caller checks which key is present, same contract as the rest of this hierarchy
	 */
	public function submit(string $emailId, string $identityId, ?array $envelope=null,
		?array $sentEmailPatch=null, bool $destroySentEmail=false, bool $destroySubmission=true) : array
	{
		$create = array_filter([
				'emailId' => $emailId,
				'identityId' => $identityId,
				'envelope' => $envelope,
			], static fn($v) => $v !== null);
		$onSuccessUpdateEmail = $sentEmailPatch !== null ? ['#s1' => $sentEmailPatch] : null;
		$onSuccessDestroyEmail = $destroySentEmail ? ['#s1'] : null;

		$result = $this->set(['s1' => $create], [], [], $onSuccessUpdateEmail, $onSuccessDestroyEmail);

		// Stalwart (and presumably others) enforce a hard per-account QUOTA on how many
		// EmailSubmission objects they keep (RFC 8621 §7.1 allows but doesn't require indefinite
		// retention) - every account that EVER sent mail through an older EGroupware version
		// (before $destroySubmission below existed) has a backlog of these sitting at/near that
		// quota, so EmailSubmission/set create fails outright with an "overQuota" SetError -
		// found live 2026-11-19 (Stalwart production, pole.egroupware.org), affects EVERY
		// Stalwart-hosted account, not just the one that happens to hit the ceiling first.
		// Self-heal instead of failing the send: purge our own backlog of definitely-already-
		// delivered objects and retry ONCE, rather than making every affected user (or an admin
		// on their behalf) manually clean up before they can send mail again.
		if (($result['notCreated']['s1']['type'] ?? null) === 'overQuota')
		{
			$this->destroyStaleSubmissions();
			$result = $this->set(['s1' => $create], [], [], $onSuccessUpdateEmail, $onSuccessDestroyEmail);
		}

		$created = $result['created']['s1'] ?? ['notCreated' => $result['notCreated']['s1'] ?? null];

		if ($destroySubmission && isset($created['id']))
		{
			try
			{
				$this->set([], [], [$created['id']]);
			}
			catch (\Throwable $e)
			{
				// best-effort only - the message was already submitted successfully, a failure
				// to destroy the now-useless tracking object must never fail the send itself
				error_log(__METHOD__."() failed to destroy EmailSubmission id={$created['id']}: ".$e->getMessage());
			}
		}

		return $created;
	}

	/**
	 * Destroy every EmailSubmission object older than $olderThanSeconds - self-healing cleanup
	 * for submit()'s "overQuota" retry, and for any backlog left behind by sends made before
	 * $destroySubmission existed (see submit()'s own docblock).
	 *
	 * Only ever called with a couple of minutes as the threshold, never zero/"everything" - a
	 * genuinely-concurrent submit() from elsewhere (another browser tab, a mail-merge batch, ...)
	 * a split-second away from reaching its own post-success destroy() must never get raced by
	 * this cleanup destroying it first.
	 *
	 * RFC 8621 §7.3's EmailSubmission/query FilterCondition has no date-range property, so this
	 * sorts by sendAt instead and fetches just enough pages to find the age boundary, rather than
	 * filtering every existing object's sendAt client-side.
	 *
	 * @param int $olderThanSeconds
	 * @param int $batch max ids to fetch/destroy per JMAP round-trip
	 * @param int $maxBatches safety cap - never loop forever against a server that keeps
	 *  reporting more stale ids than we just destroyed
	 * @return int number of objects actually destroyed
	 */
	public function destroyStaleSubmissions(int $olderThanSeconds=120, int $batch=500, int $maxBatches=20) : int
	{
		$threshold = time() - $olderThanSeconds;
		$sort = [['property' => 'sendAt', 'isAscending' => true]];
		$destroyed = 0;
		for ($n = 0; $n < $maxBatches; $n++)
		{
			$ids = $this->query([], $sort, 0, $batch)['ids'] ?? [];
			if (!$ids) break;

			$list = $this->get($ids, ['sendAt'])['list'] ?? [];
			// Foo/get does not guarantee preserving the id order of $ids (RFC 8620 §5.1) - the
			// ascending sendAt order from query() above is not reliable here, sort again ourselves
			usort($list, static fn($a, $b) => strtotime($a['sendAt'] ?? '') <=> strtotime($b['sendAt'] ?? ''));

			$stale = [];
			foreach ($list as $submission)
			{
				$sendAt = isset($submission['sendAt']) ? strtotime($submission['sendAt']) : false;
				if ($sendAt === false || $sendAt >= $threshold) break;	// reached the age boundary
				$stale[] = $submission['id'];
			}
			if (!$stale) break;

			$this->set([], [], $stale);
			$destroyed += count($stale);

			if (count($stale) < count($ids)) break;	// boundary was within this very page
		}
		return $destroyed;
	}
}
