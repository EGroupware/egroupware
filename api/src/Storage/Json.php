<?php
/**
 * EGroupware generalized SQL Storage Object using a JSON blob to store date not in SQL schema
 *
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage storage
 * @link https://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2024 by RalfBecker@outdoor-training.de
 */

namespace EGroupware\Api\Storage;

use EGroupware\Api;

/**
 * generalized SQL Storage Object which uses a JSON blob to store all data NOT directly in the schema
 *
 * the class can be used in following ways:
 * 1) by calling the constructor with an app and table-name or
 * 2) by setting the following documented class-vars in a class derived from this one
 * Of cause can you derive from the class and call the constructor with params.
 *
 * The Json class uses a private $data array and __get and __set methods to set its data.
 * Please note:
 * You have to explicitly declare other object-properties of derived classes, which should NOT
 * be handled by that mechanism!
 */
class Json extends Base
{
	/**
	 * Private array containing all the object-data
	 *
	 * Collides with the original definition in Storage\Base and I dont want to change it there at the moment.
	 *
	 * @var array
	 */
	//private $data;

	/**
	 * @var string column-name for the JSON blob
	 */
	protected $json_column;

	/*
	 * Regular expression used to filter valid JSON columns, if set
	 */
	protected $column_preg;

	/**
	 * constructor of the class
	 *
	 * NEED to be called from the constructor of the derived class !!!
	 *
	 * @param string $app should be set if table-defs to be read from <app>/setup/tables_current.inc.php
	 * @param string $table should be set if table-defs to be read from <app>/setup/tables_current.inc.php
	 * @param ?Api\Db $db database object, if not the one in $GLOBALS['egw']->db should be used, eg. for an other database
	 * @param string $column_prefix ='' column prefix to automatic remove from the column-name, if the column name starts with it
	 * @param boolean $no_clone =false can we avoid to clone the db-object, default no
	 * 	new code using appnames and foreach(select(...,$app) can set it to avoid an extra instance of the db object
	 * @param string $timestamp_type =null default null=leave them as is, 'ts'|'integer' use integer unix timestamps,
	 *    'object' use Api\DateTime objects or 'string' use DB timestamp (Y-m-d H:i:s) string
	 */
	function __construct($app='', $table='', $json_column='', ?Api\Db $db=null, $column_prefix='', $no_clone=true, $timestamp_type='object', $column_preg=null)
	{
		parent::__construct($app, $table, $db, $column_prefix, $no_clone, $timestamp_type);

		$this->json_column = $json_column;

		if (isset($column_preg))
		{
			$this->column_preg = $column_preg;
		}
	}

	/**
	 * changes the data from the db-format to your work-format
	 *
	 * It un-serializes the JSON blob and copies it into $this->data.
	 *
	 * It gets called everytime when data is read from the db.
	 * This default implementation only converts the timestamps mentioned in $this->timestamps from server to user time.
	 * You can reimplement it in a derived class like this:
	 *
	 * function db2data($data=null)
	 * {
	 * 		if (($intern = !is_array($data)))
	 * 		{
	 * 			$data =& $this->data;
	 * 		}
	 * 		// do your own modifications here
	 *
	 * 		return parent::db2data($intern ? null : $data);	// important to use null, if $intern!
	 * }
	 *
	 * @param array $data =null if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function db2data($data=null)
	{
		if (($intern = !is_array($data)))
		{
			$data = &$this->data;
		}
		if ($this->json_column && !empty($data[$this->json_column]))
		{
			if (is_string($data[$this->json_column]))
			{
				$data += (array)json_decode($data[$this->json_column], true);
			}
			elseif (is_array($data[$this->json_column]))
			{
				$data += $data[$this->json_column];
			}
		}
		unset($data[$this->json_column]);

		return parent::db2data($intern ? null : $data);
	}

	/**
	 * changes the data from your work-format to the db-format
	 *
	 * It gets called everytime when data gets writen into db or on keys for db-searches.
	 * This default implementation only converts the timestamps mentioned in $this->timestampfs from user to server time.
	 * You can reimplement it in a derived class like this:
	 *
	 * function data2db($data=null)
	 * {
	 * 		if (($intern = !is_array($data)))
	 * 		{
	 * 			$data =& $this->data;
	 * 		}
	 * 		// do your own modifications here
	 *
	 * 		return parent::data2db($intern ? null : $data);	// important to use null, if $intern!
	 * }
	 *
	 * @param array $data =null if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function data2db($data=null)
	{
		if (($intern = !is_array($data)))
		{
			$data = &$this->data;
		}
		// json-encode non db columns into ths json blob,
		// omitting NULL values and every key not matching the column_preg, if set
		if ($this->json_column && is_array($data) && ($json = array_filter($data, function($value, $key)
			{
				return isset($value) && !is_int($key) && !isset($this->db_cols[$key]) && !in_array($key, [self::USER_TIMEZONE_READ]) &&
					(!isset($this->column_preg) || preg_match($this->column_preg, $key));
			}, ARRAY_FILTER_USE_BOTH)))
		{
			$data = [
				$this->json_column => json_encode($json, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
			] + array_diff_key($data, $json);
		}
		return parent::data2db($intern ? null : $data);
	}

	/**
	 * merges in new values from the given new data-array
	 *
	 * @param $new array in form col => new_value with values to set
	 */
	function data_merge($new)
	{
		parent::data_merge($new);

		foreach($new as $name => $value)
		{
			if (!in_array($name, $this->db_cols, true) && (!isset($this->column_preg) || preg_match($this->column_preg, $name)))
			{
				$this->data[$name] = $value;
			}
		}
	}

		/**
	 * magic method to read a property from $this->data
	 *
	 * The special property 'id' always refers to the auto-increment id of the object, independent of its name.
	 *
	 * @param string $property
	 * @return mixed
	 */
	function __get($property)
	{
		switch($property)
		{
			case 'id':
				$property = $this->autoinc_id;
				break;
		}
		return $this->data[$property] ?? null;
	}

	/**
	 * magic method to set a property in $this->data
	 *
	 * The special property 'id' always refers to the auto-increment id of the object, independent of its name.
	 *
	 * @param string $property
	 * @param mixed $value
	 */
	function __set($property, $value)
	{
		switch($property)
		{
			case 'id':
				$property = $this->autoinc_id;
				break;
		}
		$this->data[$property] = $value;
	}

	/**
	 * magic method to check a property is set in $this->data
	 *
	 * The special property 'id' always refers to the auto-increment id of the object, independent of its name.
	 *
	 * @param string $property
	 * @param mixed $value
	 */
	function __isset($property)
	{
		switch($property)
		{
			case 'id':
				$property = $this->autoinc_id;
				break;
		}
		return isset($this->data[$property]);
	}

	/**
	 * magic method to unset a property in $this->data
	 *
	 * The special property 'id' always refers to the auto-increment id of the object, independent of its name.
	 *
	 * @param string $property
	 * @param mixed $value
	 */
	function __unset($property)
	{
		switch($property)
		{
			case 'id':
				$property = $this->autoinc_id;
				break;
		}
		unset($this->data[$property]);
	}

	/**
	 * Return the whole object-data as array, it's a cast of the object to an array
	 *
	 * @return array
	 */
	function as_array()
	{
		return $this->data;
	}
}