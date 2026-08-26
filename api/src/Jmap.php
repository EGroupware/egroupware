<?php
/**
 * EGroupware Api: generic JMAP session
 *
 * @link https://www.egroupware.org
 * @package api
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api;

/**
 * Abstract JMAP session - RFC 8620 Core layer, data-type-agnostic.
 *
 * Holds account/connection context and lazily vends one object per JMAP data type (eg.
 * `$session->mailbox`, `$session->email`), each an instance of a `Jmap\Type` subclass declared by
 * the concrete session subclass via $types. See doc/ai/projects/mail-jmap-imap-inversion.md for the
 * full design and why this layer is deliberately kept free of anything Mail-specific (RFC 8621) or
 * HTTP-specific (`Jmap\Http`) - both are concrete subclasses of this class, not part of its contract.
 */
abstract class Jmap
{
	/**
	 * type-name (eg. "mailbox") => concrete Jmap\Type subclass FQCN, declared by each concrete
	 * session subclass's constructor (or property default) - not by this abstract base, since the
	 * mapping is entirely dependent on which app/backend combination the subclass implements.
	 *
	 * @var array<string,class-string<Jmap\Type>>
	 */
	protected array $types = [];

	/**
	 * Lazily-instantiated, memoized Jmap\Type objects, keyed the same as $types
	 *
	 * @var array<string,Jmap\Type>
	 */
	private array $typeInstances = [];

	/**
	 * Lazy accessor for a per-type object, eg. $session->mailbox / $session->email
	 *
	 * @param string $name
	 * @return Jmap\Type|null null if this session has no such type
	 */
	public function __get(string $name)
	{
		if (!isset($this->types[$name]))
		{
			return null;
		}
		return $this->typeInstances[$name] ??= new $this->types[$name]($this);
	}

	public function __isset(string $name) : bool
	{
		return isset($this->types[$name]);
	}

	/**
	 * Low-level single-method JMAP call, returning the unwrapped method-response object.
	 *
	 * Only meaningful for a session that genuinely speaks JMAP-over-something (eg. `Jmap\Http`,
	 * real HTTP) - `Jmap\Type`'s default get()/query()/set() implementations call this. A session
	 * with no such generic mechanism (eg. an IMAP-backed adapter, whose per-type classes override
	 * get()/query()/set() directly instead) never calls this, so the base implementation here just
	 * throws rather than being abstract - that keeps a pure-adapter session subclass from having to
	 * provide a meaningless implementation just to satisfy an interface it never uses.
	 *
	 * @param string $method eg. "Mailbox/get"
	 * @param array $args
	 * @return array the single method-response's own argument object (already unwrapped)
	 * @throws \BadMethodCallException if this session doesn't support generic JMAP method calls
	 */
	public function call(string $method, array $args) : array
	{
		throw new \BadMethodCallException(static::class.' does not support generic JMAP method calls');
	}

	/**
	 * Boolean filter conditions - generic RFC 8620 §5.5 FilterOperator builder, no dependency on
	 * any particular type/backend (used eg. by Smtp\Stalwart for Group/Individual filters, not
	 * just Mail\Jmap\* for Mailbox/Email ones).
	 *
	 * @param string $operator "AND", "OR" or "NOT"
	 * @param array $filters e.g. ["name" => ["nameA", "nameB"]]
	 * @return array
	 */
	public static function filterConditions(string $operator, array $filters)
	{
		if (!in_array($operator, ['AND', 'OR', 'NOT'])) throw new \InvalidArgumentException("Invalid operator '$operator'!");

		$conditions = [];
		foreach($filters as $name => $values)
		{
			if (is_int($name))
			{
				$conditions[] = $values;
				continue;
			}
			foreach ((array)$values as $value)
			{
				$conditions[] = [$name => $value];
			}
		}
		return [
			'operator' => $operator,
			'conditions' => $conditions,
		];
	}

	/**
	 * Generate a JMAP patch from current IDs and optional old IDs with values true for added and
	 * null for removed - generic, no dependency on any particular type/backend.
	 *
	 * @param array $new new ids
	 * @param array|null $old old ids
	 * @return object with id => true or null pairs
	 */
	public static function boolPatch(array $new, ?array $old=null) : object
	{
		$patch = [];
		if (($added = $old ? array_diff_key($new, $old) : $new))
		{
			$patch = array_combine($added, array_fill(0, count($added), true));
		}
		if ($old && (($removed = array_diff_key($old, $new))))
		{
			$patch += array_combine($removed, array_fill(0, count($removed), null));
		}
		return (object)$patch;
	}
}
