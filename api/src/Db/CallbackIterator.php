<?php
/**
 * EGroupware API: Database callback iterator
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage db
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2003-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\Db;

/**
 * Iterator applying a given callback on each element retrived, eg. from a select query
 *
 * Example usage:
 *
 *	function rows(array $where)
 *	{
 *		global $db, $table, $columns, $prefix;
 *
 *		return new EGroupware\Api\Db\CallbackIterator($db->select($table, $columns, $where), function($row) use ($prefix)
 *		{
 *			return self::strip_array_keys($row, $prefix);
 *		});
 *  }
 *
 *  foreach(row(array('attr' => 'value')) as $row)
 *  {
 *		// $row keys have prefix removed, or whatever you implement in callback
 *	}
 *
 * Example with a key-callback:
 *
 *	function rows(array $where)
 *	{
 *		global $db, $table, $columns, $prefix;
 *
 *		return new EGroupware\Api\Db\CallbackIterator($db->select($table, $columns, $where), function($row) use ($prefix)
 *		{
 *			return self::strip_array_keys($row, $prefix);
 *		}, array(), function($row)
 *		{
 *			return $row['id'];
 *		});
 *  }
 *
 *  foreach(rows(array('attr' => 'value')) as $key => $row)
 *  {
 *		// $key is now value of column 'id', $row as above
 *	}
 *
 */
class CallbackIterator implements \Iterator
{
	/**
	 * Reference of so_sql class to use it's db2data method
	 *
	 * @var callback
	 */
	private $callback;

	/**
	 * Further parameter for callback
	 *
	 * @var array
	 */
	private $params = array();

	/**
	 * Optional callback, if you want different keys
	 *
	 * @var callback
	 */
	private $key_callback;

	/**
	 * Instance of ADOdb record set to iterate
	 *
	 * @var Iterator
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
	 * @param Traversable $rs
	 * @param callback $callback
	 * @param array $params =array() additional parameters, row is always first parameter
	 * @param $key_callback =null optional callback, if you want different keys
	 */
	public function __construct(\Traversable $rs, $callback, $params=array(), $key_callback=null)
	{
		$this->callback = $callback;
		$this->params = $params;
		$this->key_callback = $key_callback;

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
	 * @return mixed
	 */
	public function current(): mixed
	{
		if (is_a($this->rs,'iterator'))
		{
			$params = $this->params;
			array_unshift($params, $this->rs->current());
			return call_user_func_array($this->callback, $params);
		}
		return null;
	}

	/**
	 * Return the key of the current element
	 *
	 * @return mixed
	 */
	public function key(): mixed
	{
		if (is_a($this->rs,'iterator'))
		{
			return $this->key_callback ?
				call_user_func($this->key_callback, $this->rs->current()) :
				$this->rs->key();
		}
		return 0;
	}

	/**
	 * Move forward to next element (called after each foreach loop)
	 */
	public function next(): void
	{
		if (is_a($this->rs,'iterator'))
		{
			$this->rs->next();
		}
	}

	/**
	 * Rewind the Iterator to the first element (called at beginning of foreach loop)
	 */
	public function rewind(): void
	{
		if (is_a($this->rs,'iterator'))
		{
			$this->rs->rewind();
		}
	}

	/**
	 * Checks if current position is valid
	 *
	 * @return boolean
	 */
	public function valid (): bool
	{
		if (is_a($this->rs,'iterator'))
		{
			return $this->rs->valid();
		}
		return false;
	}
}