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
	 * Does NOT sort by sendAt (an earlier version did, assuming an ascending EmailSubmission/query
	 * sort would let it stop at the first non-stale page) - found live 2026-11-19 against a real,
	 * long-standing backlog (Stalwart production, pole.egroupware.org/Birgit): the purge
	 * destroyed NOTHING, every single time, including on the very FIRST overQuota ever hit, not
	 * just during a burst. Api\Jmap::call() never throws on a JMAP-level method error (RFC 8620
	 * §3.6.2 "error" response) - it just returns that error object as-is - so an unsupported
	 * `sort` property on EmailSubmission/query (RFC 8621 §7.3 does not require sendAt to be
	 * sortable) silently came back with no 'ids' key, `?? []` turned that into an empty list, and
	 * destroyStaleSubmissions() looked like it ran clean while actually never inspecting a single
	 * real object. Walking plain, unsorted pages instead needs more round-trips for a huge
	 * backlog, but cannot be defeated by a server not supporting a particular sort.
	 *
	 * @param int $olderThanSeconds
	 * @param int $batch max ids to fetch/destroy per JMAP round-trip
	 * @param int $maxBatches safety cap - never loop forever against a server that keeps
	 *  reporting more pages than we've walked
	 * @return int number of objects actually destroyed
	 */
	public function destroyStaleSubmissions(int $olderThanSeconds=120, int $batch=500, int $maxBatches=20) : int
	{
		$threshold = time() - $olderThanSeconds;
		$destroyed = 0;
		$position = 0;
		for ($n = 0; $n < $maxBatches; $n++)
		{
			$ids = $this->query([], [], $position, $batch)['ids'] ?? [];
			if (!$ids) break;

			$list = $this->get($ids, ['sendAt'])['list'] ?? [];
			$stale = [];
			foreach ($list as $submission)
			{
				$sendAt = isset($submission['sendAt']) ? strtotime($submission['sendAt']) : false;
				if ($sendAt !== false && $sendAt < $threshold)
				{
					$stale[] = $submission['id'];
				}
			}
			if ($stale)
			{
				$this->set([], [], $stale);
				$destroyed += count($stale);
			}

			if (count($ids) < $batch) break;	// last page

			// destroying some of THIS page shifts every later position down by that many - correct
			// for it, instead of advancing by the full page size and silently skipping entries
			$position += count($ids) - count($stale);
		}
		return $destroyed;
	}
}
