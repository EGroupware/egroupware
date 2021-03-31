<?php
/**
 * EGroupware generalized SQL Storage Object: Iterator applying db2data method
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage storage
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-16 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

namespace EGroupware\Api\Storage;

/**
 * Iterator applying a Storage's db2data method on each element retrived
 *
 */
class Db2DataIterator implements \Iterator
{
	/**
	 * Reference of Storage\Base class to use it's db2data method
	 *
	 * @var Base
	 */
	private $storage;

	/**
	 * Instance of ADOdb record set to iterate
	 *
	 * @var \Traversable
	 */
	private $rs;

	/**
	 * Total count of entries
	 *
	 * @var int
	 */
	public $total;

	/**
	 * Constructor
	 *
	 * @param Base $storage
	 * @param \Traversable $rs
	 */
	public function __construct(Base $storage, \Traversable $rs=null)
	{
		$this->storage = $storage;

		$this->total = $storage->total;

		if (is_a($rs,'IteratorAggregate'))
		{
			$this->rs = $rs->getIterator();
		}
		else
		{
			$this->rs = $rs;
		}
	}

	/**
	 * Return the current element
	 *
	 * @return array
	 */
	public function current()
	{
		if (is_a($this->rs,'iterator'))
		{
			$data = $this->rs->current();

			return $this->storage->data2db($data);
		}
		return null;
	}

	/**
	 * Return the key of the current element
	 *
	 * @return int
	 */
	public function key()
	{
		if (is_a($this->rs,'iterator'))
		{
			return $this->rs->key();
		}
		return 0;
	}

	/**
	 * Move forward to next element (called after each foreach loop)
	 */
	public function next()
	{
		if (is_a($this->rs,'iterator'))
		{
			return $this->rs->next();
		}
	}

	/**
	 * Rewind the Iterator to the first element (called at beginning of foreach loop)
	 */
	public function rewind()
	{
		if (is_a($this->rs,'iterator'))
		{
			return $this->rs->rewind();
		}
	}

	/**
	 * Checks if current position is valid
	 *
	 * @return boolean
	 */
	public function valid ()
	{
		if (is_a($this->rs,'iterator'))
		{
			return $this->rs->valid();
		}
		return false;
	}
}
