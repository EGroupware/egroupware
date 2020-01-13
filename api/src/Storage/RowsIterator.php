<?php
/**
 * EGroupware generalized SQL Storage Object: Iterator for get_rows
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage storage
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright 2020 by Ralf Becker <rb@egroupware.org>
 */

namespace EGroupware\Api\Storage;
use EGroupware\Api;

/**
 * Iterator using a get_rows method and querying it in chunks instead of all rows as once
 *
 * You should use a consisten sorting eg. by id, in case rows change while the iterator is instanciated!
 *
 * Instead of ($query['num_rows']=-1, $query['start']=0):
 *
 * $storage->get_rows($query, $rows, $readonlys);
 *
 * Use:
 *
 * $rows = new RowsIterator($storage, $query);
 */
class RowsIterator implements \Iterator
{
	/**
	 * Reference of Storage\Base class or other object with a get_rows method
	 *
	 * @var Base
	 */
	protected $storage;

	/**
	 * query parameter for get_rows
	 *
	 * @var array
	 */
	protected $query;

	/**
	 * name of (unique) key in row or null to use $this->start + key returned by get_rows
	 *
	 * @var string
	 */
	protected $key;

	/**
	 * current chunk
	 *
	 * @var array
	 */
	protected $rows;

	/**
	 * Start value for callback
	 *
	 * @var int
	 */
	protected $start=0;

	/**
	 * Number of rows queried from get_rows in one call
	 */
	const CHUNK_SIZE = 500;

	/**
	 * Log calls via error_log()
	 *
	 * @var boolean
	 */
	public $debug = false;

	/**
	 * Constructor
	 *
	 * @param Base $storage only requirement is class to have a get_rows method
	 * @param array $query query parameter for get_rows
	 * @param string $key =null name of (unique) key in row, default use $this->start + key from get_rows
	 */
	public function __construct($storage, array $query, $key=null)
	{
		if (!is_object($storage) || !method_exists($storage, 'get_rows'))
		{
			throw new Api\Exception\WrongParameter("\$storage parameter needs to be object with a get_rows method!");
		}
		$this->storage = $storage;
		$this->query = $query;
		$this->key = $key;
	}

	/**
	 * Return the current element
	 *
	 * @return array
	 */
	public function current()
	{
		if ($this->debug) error_log(__METHOD__."() returning ".array2string(current($this->rows)));
		return current($this->rows);
	}

	/**
	 * Return the key of the current element
	 *
	 * @return int|string
	 */
	public function key()
	{
		$current = current($this->rows);

		$key = !empty($this->key) ? $current[$this->key] : $this->start + key($this->rows);
		if ($this->debug) error_log(__METHOD__."() returning ".array2string($key));
		return $key;
	}

	/**
	 * Move forward to next element (called after each foreach loop)
	 */
	public function next()
	{
		if (next($this->rows) !== false)
		{
			if ($this->debug) error_log(__METHOD__."() returning TRUE");
			return true;
		}
		// check if previous query gave less then CHUNK_SIZE entries --> we're done
		if ($this->start && count($this->rows) < self::CHUNK_SIZE)
		{
			if ($this->debug) error_log(__METHOD__."() returning FALSE (no more entries)");
			return false;
		}
		// try query further rows via get_rows method and store result in $this->rows
		$readonlys = null;
		$this->query['start'] = $this->start;
		$this->query['num_rows'] = self::CHUNK_SIZE;
		$this->storage->get_rows($this->query, $this->rows, $readonlys);

		// remove non-rows returned (sel_options and the like, or leading false old eTemplate required)
		foreach($this->rows as $key => $row)
		{
			if (!is_int($key) || !is_array($row))
			{
				unset($this->rows[$key]);
			}
		}

		if (!is_array($this->rows) || !($entries = count($this->rows)))
		{
			if ($this->debug) error_log(__METHOD__."() returning FALSE (no more entries)");
			return false;	// no further entries
		}
		$this->start += self::CHUNK_SIZE;
		reset($this->rows);

		if ($this->debug) error_log(__METHOD__."() this->start=$this->start, entries=$entries, count(this->files)=".count($this->rows)." returning ".array2string(current($this->rows) !== false));

		return current($this->rows) !== false;
	}

	/**
	 * Rewind the Iterator to the first element (called at beginning of foreach loop)
	 */
	public function rewind()
	{
		if ($this->debug) error_log(__METHOD__."()");

		$this->start = 0;
		$this->rows = [];
		if (!$this->rows) $this->next();	// otherwise valid will return false and nothing get returned
		reset($this->rows);
	}

	/**
	 * Checks if current position is valid
	 *
	 * @return boolean
	 */
	public function valid ()
	{
		if ($this->debug) error_log(__METHOD__."() returning ".array2string(current($this->rows) !== false));
		return current($this->rows) !== false;
	}
}
