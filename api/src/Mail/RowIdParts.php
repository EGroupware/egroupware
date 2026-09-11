<?php
/**
 * EGroupware Api: lazily-resolving Api\Mail::splitRowID() result
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb-AT-egroupware.org>
 * @copyright (c) 2026 by Ralf Becker <rb-AT-egroupware.org>
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail;

/**
 * Array-like return value for Api\Mail::splitRowID(), behaving exactly like the plain array it
 * replaces (bracket read/write/isset/unset, foreach, count()) for every existing caller, EXCEPT
 * that "folder" and "msgUID" are only actually computed the first time either key is read.
 *
 * Why: for a Stalwart opaque-id row, resolving "folder"/"msgUID" means a real IMAP EMAILID search
 * (Imap\Jmap::emailId2uid()) - the one remaining "time-consuming fallback" this whole mail JMAP
 * modernization project set out to avoid. Most JMAP-native callers only ever need "emailID"/
 * "folderID"/"profileID" (already known from the row-id string alone, no IMAP involved) and never
 * touch the numeric UID at all - this class means they no longer pay for it regardless. Callers
 * that DO need "folder"/"msgUID" (the classic-path fallback) get it transparently, resolved once
 * and cached for any further access on the same instance.
 */
class RowIdParts implements \ArrayAccess, \Countable, \IteratorAggregate
{
	private array $eager;
	private \Closure $resolver;
	private ?array $lazy = null;

	/**
	 * @param array $eager already-known keys, e.g. app/accountID/profileID/folderID/emailID/is_jmap
	 * @param \Closure $resolver () : array{folder:?string, msgUID:?string} - called at most once,
	 *  on first access of either key
	 */
	public function __construct(array $eager, \Closure $resolver)
	{
		$this->eager = $eager;
		$this->resolver = $resolver;
	}

	/**
	 * New instance with additional eager keys merged in, sharing this instance's (still unresolved,
	 * if not yet accessed) lazy resolver - used by Api\Mail::splitRowID() to add app/accountID/
	 * profileID on top of whatever the backend-specific splitRowID() already returned.
	 *
	 * @param array $extra
	 * @return self
	 */
	public function withEager(array $extra) : self
	{
		$copy = new self($extra + $this->eager, $this->resolver);
		$copy->lazy = $this->lazy;
		return $copy;
	}

	private function resolve() : array
	{
		return $this->lazy ??= ($this->resolver)();
	}

	private function isLazyKey($offset) : bool
	{
		return $offset === 'folder' || $offset === 'msgUID';
	}

	public function offsetExists($offset) : bool
	{
		return $this->isLazyKey($offset) || array_key_exists($offset, $this->eager);
	}

	#[\ReturnTypeWillChange]
	public function offsetGet($offset)
	{
		if ($this->isLazyKey($offset))
		{
			return $this->resolve()[$offset] ?? null;
		}
		return $this->eager[$offset] ?? null;
	}

	public function offsetSet($offset, $value) : void
	{
		if ($this->isLazyKey($offset))
		{
			$this->lazy ??= [];
			$this->lazy[$offset] = $value;
		}
		else
		{
			$this->eager[$offset] = $value;
		}
	}

	public function offsetUnset($offset) : void
	{
		if ($this->isLazyKey($offset))
		{
			$this->resolve();
			$this->lazy[$offset] = null;
		}
		else
		{
			unset($this->eager[$offset]);
		}
	}

	public function count() : int
	{
		return count($this->toArray());
	}

	public function getIterator() : \Iterator
	{
		return new \ArrayIterator($this->toArray());
	}

	/**
	 * Force resolution and return a plain array - same shape callers got before this class existed.
	 *
	 * @return array
	 */
	public function toArray() : array
	{
		return $this->eager + $this->resolve();
	}
}
