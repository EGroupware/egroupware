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
class JsonCF extends Api\Storage
{
	use JsonTrait;

	/**
	 * constructor of the class
	 *
	 * NEED to be called from the constructor of the derived class !!!
	 *
	 * @param string $app should be set if table-defs to be read from <app>/setup/tables_current.inc.php
	 * @param string $table should be set if table-defs to be read from <app>/setup/tables_current.inc.php
	 * @param string $extra_table name of the custom field table
	 * @param string $column_prefix ='' column prefix to automatic remove from the column-name, if the column name starts with it
	 * @param string $extra_key ='_name' column name for cf name column (will be prefixed with colum prefix, if starting with _)
	 * @param string $extra_value ='_value' column name for cf value column (will be prefixed with colum prefix, if starting with _)
	 * @param string $extra_id ='_id' column name for cf id column (will be prefixed with colum prefix, if starting with _)
	 * @param string $json_column ='' name of column to store JSON blob
	 * @param ?Api\Db $db database object, if not the one in $GLOBALS['egw']->db should be used, eg. for an other database
	 * @param boolean $no_clone =false can we avoid to clone the db-object, default no
	 * 	new code using appnames and foreach(select(...,$app) can set it to avoid an extra instance of the db object
	 * @param string $timestamp_type =null default null=leave them as is, 'ts'|'integer' use integer unix timestamps,
	 *    'object' use Api\DateTime objects or 'string' use DB timestamp (Y-m-d H:i:s) string
	 */
	function __construct($app='', $table='', $extra_table, $column_prefix='', $extra_key='_name', $extra_value='_value',
	                     $extra_id='_id', $json_column='', ?Api\Db $db=null, $no_clone=true, $timestamp_type='object', $column_preg=null)
	{
		parent::__construct($app, $table, $extra_table, $column_prefix, $extra_key, $extra_value, $extra_id, $db, $no_clone, false, $timestamp_type);

		$this->json_column = $json_column;

		if (isset($column_preg))
		{
			$this->column_preg = $column_preg;
		}
	}
}