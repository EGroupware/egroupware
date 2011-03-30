<?php
/**
 * eGroupWare API: egw action grid columns classes
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage egw action grid
 * @author Andreas Stöckel
 * @copyright (c) 2011 Stylite
 * @version $Id$
 */

/**
 * The egw_grid_columns class in this file is the PHP counterpart to the implementation
 * in the JS file egw_grid_columns.js. It can generate column json data, verify
 * it and store user settings for columns in the preferences
 */

class egw_json_object
{
	private $supported_properties = array(
		"id" => array(
			"types" => "string",
			"default" => ""
		)
	);

	protected $data = array();

	/**
	 * Merges the given properties into the supported properties array.
	 *
	 * @param array $props are the supported properties which will be added to the
	 * 	object.
	 */
	protected function add_supported_properties($props)
	{
		$this->supported_properties = array_merge($this->supported_properties, $props);
	}

	/**
	 * Reads the object data from the 
	 */
	public function load_assoc(array $assoc)
	{
		/**
		 * Calls the magic setter for each data element
		 */
		foreach ($assoc as $key => $data)
		{
			$this->$key = $data;
		}
	}

	/**
	 * Returns an associative array with the object data
	 */
	public function store_assoc($include_defaults = false)
	{
		$result = array();

		foreach ($this->supported_properties as $key => $opt)
		{
			if (!array_key_exists("store", $opt) || $opt[$store])
			{
				$val = $this->$key;

				if ($val != $opt["default"] || $include_defaults )
				{
					$result[$key] = $val;
				}
			}
		}

		return $result;
	}

	/**
	 * Magic setter function - checks whether the specified key is supported by the
	 * the object and the given value is of the supported type.
	 */
	public function __set($key,$val)
	{
		if (array_key_exists($key, $this->supported_properties))
		{
			$sup_entry = $this->supported_properties[$key];

			// Test for the type (PHP-Docu says not to use gettype here)
			$correct_type = true;

			if (array_key_exists("types", $sup_entry))
			{
				$types = explode(",", $sup_entry["types"]);

				foreach ($types as $type)
				{
					switch ($type)
					{
						case "bool":
							$correct_type = $correct_type || is_bool($val);
							break;
						case "string":
							$correct_type = $correct_type || is_string($val);
							break;
						case "int":
							$correct_type = $correct_type || is_int($val);
							break;
						case "float":
							$correct_type = $correct_type || is_float($val);
							break;
					}
				}
			}

			// Set the value in the data array or call a setter function an inherited
			// class might have specified
			if ($correct_type)
			{
				if (method_exists($this, "set_".$key))
				{
					call_user_func(array($this, "set_".$key), $val);
				}
				else
				{
					$this->data[$key] = $val;
				}
			}
		}
	}

	/**
	 * Magic getter function - returns the default value if the data key has not
	 * been set yet, returns null if the property does not exists.
	 */
	public function __get($key) {
		if (array_key_exists($key, $this->supported_properties))
		{
			// Check whether the inherited class has a special getter implemented
			if (method_exists($this, "get_".$key))
			{
				return call_user_func(array($this, "get_".$key), $val);
			}
			else
			{
				if (array_key_exists($key, $this->data))
				{
					return $this->data[$key];
				}
				else
				{
					return $this->supported_properties[$key]["default"];
				}
			}
		}
		return null;
	}
}


/**
 * Define some constants as they occur in egw_grid_columns.js
 */
define("EGW_COL_TYPE_DEFAULT", 0);
define("EGW_COL_TYPE_NAME_ICON_FIXED", 1);
define("EGW_COL_TYPE_CHECKBOX", 2);

define("EGW_COL_VISIBILITY_ALWAYS", 0);
define("EGW_COL_VISIBILITY_VISIBLE", 1);
define("EGW_COL_VISIBILITY_INVISIBLE", 2);
define("EGW_COL_VISIBILITY_ALWAYS_NOSELECT", 3);

define("EGW_COL_SORTABLE_NONE", 0);
define("EGW_COL_SORTABLE_ALPHABETIC", 1);
define("EGW_COL_SORTABLE_NUMERICAL", 2);
define("EGW_COL_SORTABLE_NATURAL", 3);

define("EGW_COL_SORTMODE_NONE", 0);
define("EGW_COL_SORTMODE_ASC", 1);
define("EGW_COL_SORTMODE_DESC", 2);

define("EGW_COL_DEFAULT_FETCH", -10000);

/**
 * Object which represents a single column
 */
class egw_grid_column extends egw_json_object
{
	public function __construct($id = "")
	{
		// Add the supported properties
		$this->add_supported_properties(array(
			"width" => array("types" => "bool,int,string", "default" => false),
			"maxWidth" => array("types" => "int,bool", "default" => false),
			"caption" => array("types" => "string", "default" => ""),
			"visibility" => array("types" => "int", "default" => EGW_COL_VISIBILITY_VISIBLE),
			"visible" => array("types" => "bool", "default" => true, "store" => false),
			"sortable" => array("types" => "int", "default" => EGW_COL_SORTABLE_NONE),
			"sortmode" => array("types" => "int", "default" => EGW_COL_SORTMODE_NONE),
			"default" => array("types" => "string,int", "default" => EGW_COL_DEFAULT_FETCH),
			"type" => array("types" => "int", "default" => EGW_COL_TYPE_DEFAULT),
		));

		// Set the column id
		$this->id = $id;
	}

	public function get_visible()
	{
		return $this->visibility != EGW_COL_VISIBILITY_INVISIBLE;
	}

	public function set_visible($val)
	{
		if ($this->visibility != EGW_COL_VISIBILITY_ALWAYS &&
		    $this->visibility != EGW_COL_VISIBILITY_ALWAYS_NOSELECT)
		{
			$this->visibility = $val ? EGW_COL_VISIBILITY_VISIBLE : EGW_COL_VISIBILITY_INVISIBLE;
		}
	}
}

class egw_grid_columns
{
	private $app_name;
	private $grid_name;

	private $grid_data = array();

	public function __construct($app_name, $grid_name = "main")
	{
		$this->app_name = $app_name;
		$this->grid_name = $grid_name;
	}

	public function load_grid_data($data)
	{
		foreach ($data as $col)
		{
			$colobj = new egw_grid_column();
			$colobj->load_assoc($col);

			$this->grid_data[] = $colobj;
		}
	}

	private function get_col($id)
	{
		foreach ($this->grid_data as $col)
		{
			if ($col->id == $id)
			{
				return $col;
			}
		}

		return null;
	}

	/**
	 * Loads the given column data in the user preferences for this grid
	 */
	private function get_userdata()
	{
		if ($GLOBALS['egw_info']['user']['preferences'][$this->app_name][$this->grid_name.'_column_data'])
		{
			return unserialize($GLOBALS['egw_info']['user']['preferences'][$this->app_name][$this->grid_name.'_column_data']);
		}
		return array();
	}

	/**
	 * Stores the given column data in the user preferences for this grid
	 */
	private function set_userdata($data)
	{
		$GLOBALS['egw']->preferences->read_repository();

		$GLOBALS['egw']->preferences->change($this->app_name, $this->grid_name.'_column_data',
			serialize($data));

		$GLOBALS['egw']->preferences->save_repository(true);
	}

	public function load_userdata()
	{
		// Read the userdata from the user preferences
		$data = $this->get_userdata();

		// Merge the userdata into the column data
		foreach ($data as $col_id => $col_data)
		{
			$col = $this->get_col($col_id);
			if ($col && is_array($col_data))
			{
				$col->load_assoc($col_data);
			}
		}
	}

	public function store_userdata($data)
	{
		$store_data = array();

		// Check whether the specified columns exists
		foreach ($data as $col_id => $col_data)
		{
			$col = $this->get_col($col_id);
			if ($col)
			{
				$store_data[$col_id] = $col_data;
			}
		}

		// Store the verified data columns
		$this->set_userdata($store_data);
	}

	/**
	 * Returns the associative array containing the column data which can be
	 * JSON-encoded and sent to the client
	 */
	public function get_assoc()
	{
		$result = array();
		foreach ($this->grid_data as $col)
		{
			$result[] = $col->store_assoc();
		}

		return $result;
	}
}

