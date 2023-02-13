<?php
/**
 * EGroupware: CalDAV/CardDAV/GroupDAV access: iterator for (huge) propfind sets
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage caldav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\CalDAV;

/**
 * Iterator for propfinds using propfind callback of a Handler to query results in chunks
 *
 * The propfind method just computes a filter and then returns an instance of this iterator instead of the files:
 *
 *	function propfind($path,$options,&$files,$user,$id='')
 *	{
 *		$filter = array();
 * 		// compute filter from path, options, ...
 *
 * 		$files['files'] = new groupdav_propfind_iterator($this,$filter,$files['files']);
 *
 * 		return true;
 * 	}
 */
class PropfindIterator implements \Iterator
{
	/**
	 * current path
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * Handler to call for entries
	 *
	 * @var Handler
	 */
	protected $handler;

	/**
	 * Filter of propfind call
	 *
	 * @var array
	 */
	protected $filter;

	/**
	 * Extra responses to return too
	 *
	 * @var array
	 */
	protected $common_files;

	/**
	 * current chunk
	 *
	 * @var array
	 */
	protected $files;


	/**
	 * Start value for callback
	 *
	 * @var int
	 */
	protected $start=0;

	/**
	 * Number of entries queried from callback in one call
	 *
	 */
	const CHUNK_SIZE = 500;

	/**
	 * Log calls via error_log()
	 *
	 * @var boolean
	 */
	public $debug = false;

	/**

	/**
	 * Constructor
	 *
	 * @param Handler $handler
	 * @param array $filter filter for propfind call
	 * @param array $files =array() extra files/responses to return too
	 */
	public function __construct(Handler $handler, $path, array $filter,array &$files=array())
	{
		if ($this->debug) error_log(__METHOD__."('$path', ".array2string($filter).",)");
		$this->path    = $path;
		$this->handler = $handler;
		$this->filter  = $filter;
		$this->files   = $this->common_files = $files;
		reset($this->files);
	}

	/**
	 * Return the current element
	 *
	 * @return array
	 */
	public function current()
	{
		if ($this->debug) error_log(__METHOD__."() returning ".array2string(current($this->files)));
		return current($this->files);
	}

	/**
	 * Return the key of the current element
	 *
	 * @return int|string
	 */
	public function key()
	{
		$current = current($this->files);

		if ($this->debug) error_log(__METHOD__."() returning ".array2string($current['path']));
		return $current['path'];	// we return path as key
	}

	/**
	 * Move forward to next element (called after each foreach loop)
	 */
	public function next()
	{
		if (next($this->files) !== false)
		{
			if ($this->debug) error_log(__METHOD__."() returning TRUE");
			return true;
		}
		// check if previous query gave not equal CHUNK_SIZE entries (or the last one is a not-found entry with just path / count()===1) --> we're done
		if ($this->start && (count($this->files) != self::CHUNK_SIZE || count($this->files[self::CHUNK_SIZE-1]) === 1))
		{
			if ($this->debug) error_log(__METHOD__."() returning FALSE (no more entries)");
			return false;
		}
		// try query further files via propfind callback of handler and store result in $this->files
		$this->files = $this->handler->propfind_callback($this->path,$this->filter,array($this->start,self::CHUNK_SIZE));
		if (!is_array($this->files) || !($entries = count($this->files)))
		{
			if ($this->debug) error_log(__METHOD__."() returning FALSE (no more entries)");
			return false;	// no further entries
		}
		$this->start += self::CHUNK_SIZE;
		reset($this->files);

		if ($this->debug) error_log(__METHOD__."() this->start=$this->start, entries=$entries, count(this->files)=".count($this->files)." returning ".array2string(current($this->files) !== false));

		return current($this->files) !== false;
	}

	/**
	 * Rewind the Iterator to the first element (called at beginning of foreach loop)
	 */
	public function rewind()
	{
		if ($this->debug) error_log(__METHOD__."()");

		$this->start = 0;
		$this->files = $this->common_files;
		if (!$this->files) $this->next();	// otherwise valid will return false and nothing get returned
		reset($this->files);
	}

	/**
	 * Checks if current position is valid
	 *
	 * @return boolean
	 */
	public function valid ()
	{
		if ($this->debug) error_log(__METHOD__."() returning ".array2string(current($this->files) !== false));
		return current($this->files) !== false;
	}
}