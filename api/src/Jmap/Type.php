<?php
/**
 * EGroupware Api: generic JMAP per-type object
 *
 * @link https://www.egroupware.org
 * @package api
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Jmap;

/**
 * Abstract per-type JMAP object - RFC 8620 §5's standard get/query/set method shapes, generically.
 *
 * The default get()/query()/set() here just proxy a single method call to the owning session's
 * call() - correct as-is for any real-JMAP-shaped session (eg. `Api\Jmap`, real-JMAP-over-HTTP),
 * since there's nothing backend-specific about "ask the server" once you have a working transport.
 * A session backed by something that isn't itself JMAP (eg. an IMAP connection) has no such
 * generic mechanism to proxy to - its per-type subclasses override get()/query()/set() directly
 * instead, with real backend-specific code, rather than relying on this default.
 */
abstract class Type
{
	/**
	 * RFC 8620 §5 / RFC 8621 type name, eg. "Mailbox", "Email", "Thread" - set by each concrete
	 * subclass.
	 */
	const TYPE_NAME = '';

	public function __construct(protected Base $jmap)
	{
	}

	/**
	 * @param string[]|null $ids null = all
	 * @param string[]|null $properties null = server default (usually all)
	 * @param bool $fetchAllBodyValues RFC 8621 §4.3 Email/get-only extra argument - populate
	 *  every text/html body part's value in the response's bodyValues, not just ones explicitly
	 *  referenced. Meaningless for any other type - never pass true except for Email, since a
	 *  real JMAP server may reject an argument its method doesn't define.
	 * @param string|null $mailboxId NON-standard, JmapShim-only: the mailbox the ids live in.
	 *  Api\Mail\Jmap\Imap::emailGet() needs a mailbox to FETCH from and otherwise relies on the
	 *  context a preceding Email/query left behind, so a standalone Email/get (one email by id,
	 *  no listing first) has to say which mailbox itself. RFC 8620 §3.6.1 makes a real JMAP
	 *  server reject arguments its method does not define, so callers must only pass this for
	 *  shim-backed sessions - see Mail\ApiHandler::isRealJmapSession().
	 *
	 *  IMPORTANT for every override of this method: keep $mailboxId (and any future parameter
	 *  added here) in the override's own signature even if unused - PHP raises a FATAL
	 *  "Declaration must be compatible" error at class-load time otherwise, for EVERY class that
	 *  overrides get(), the moment this base signature gains a parameter an override doesn't also
	 *  declare (found live twice now: 2026-09-09 for $fetchAllBodyValues, see Identity::get()'s
	 *  own docblock; 2026-09-10 for $mailboxId, this exact parameter, missed on first attempt).
	 * @return array{list: array[], notFound?: string[]}
	 */
	public function get(?array $ids=null, ?array $properties=null, bool $fetchAllBodyValues=false, ?string $mailboxId=null) : array
	{
		return $this->jmap->call(static::TYPE_NAME.'/get', array_filter([
			// RFC 8620 §5.1: every standard method call requires accountId - both concrete
			// session types (Api\Jmap, Mail\Jmap\Imap) expose it via their own __get()
			'accountId' => $this->jmap->accountId,
			'ids' => $ids,
			'properties' => $properties,
			'fetchAllBodyValues' => $fetchAllBodyValues ?: null,
			'mailboxId' => $mailboxId,
		], static fn($v) => $v !== null));
	}

	/**
	 * @param array $filter FilterCondition or FilterOperator object
	 * @param array $sort Comparator objects
	 * @param int|null $position RFC 8620 §5.5 - zero-based index of the first id to return
	 * @param int|null $limit RFC 8620 §5.5 - max number of ids to return
	 * @param bool $calculateTotal RFC 8620 §5.5 - include the query's total match count in the response
	 * @return array{ids: string[], total?: int, ...}
	 */
	public function query(array $filter=[], array $sort=[], ?int $position=null, ?int $limit=null, bool $calculateTotal=false) : array
	{
		return $this->jmap->call(static::TYPE_NAME.'/query', array_filter([
			'accountId' => $this->jmap->accountId,
			'filter' => $filter ?: null,
			'sort' => $sort ?: null,
			'position' => $position,
			'limit' => $limit,
			'calculateTotal' => $calculateTotal ?: null,
		], static fn($v) => $v !== null));
	}

	/**
	 * @param array<string,array> $create id => properties
	 * @param array<string,array> $update id => PatchObject
	 * @param string[] $destroy ids
	 * @return array{created?: array, updated?: array, destroyed?: string[], notCreated?: array, notUpdated?: array, notDestroyed?: array}
	 */
	public function set(array $create=[], array $update=[], array $destroy=[]) : array
	{
		return $this->jmap->call(static::TYPE_NAME.'/set', array_filter([
			'accountId' => $this->jmap->accountId,
			'create' => $create ?: null,
			'update' => $update ?: null,
			'destroy' => $destroy ?: null,
		], static fn($v) => $v !== null));
	}
}
