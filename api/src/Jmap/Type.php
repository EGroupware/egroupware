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
	 * @return array{list: array[], notFound?: string[]}
	 */
	public function get(?array $ids=null, ?array $properties=null) : array
	{
		return $this->jmap->call(static::TYPE_NAME.'/get', array_filter([
			// RFC 8620 §5.1: every standard method call requires accountId - both concrete
			// session types (Api\Jmap, Mail\Jmap\Imap) expose it via their own __get()
			'accountId' => $this->jmap->accountId,
			'ids' => $ids,
			'properties' => $properties,
		], static fn($v) => $v !== null));
	}

	/**
	 * @param array $filter FilterCondition or FilterOperator object
	 * @param array $sort Comparator objects
	 * @return array{ids: string[], ...}
	 */
	public function query(array $filter=[], array $sort=[]) : array
	{
		return $this->jmap->call(static::TYPE_NAME.'/query', array_filter([
			'accountId' => $this->jmap->accountId,
			'filter' => $filter ?: null,
			'sort' => $sort ?: null,
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
