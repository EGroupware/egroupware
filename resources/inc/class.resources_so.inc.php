<?php
/**
 * EGroupware - resources
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package resources
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @author Lukas Weiss <wnz_gh05t@users.sourceforge.net>
 * @version $Id$
 */

use EGroupware\Api;

/**
 * General storage object for resources
 *
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @package resources
 */
class resources_so extends Api\Storage
{
	function __construct()
	{
		parent::__construct('resources','egw_resources', 'egw_resources_extra', '',
			'extra_name', 'extra_value', 'extra_id' );
		$this->convert_all_timestamps();

		$this->columns_to_search = array('name','short_description','inventory_number','long_description','location');
	}

	/**
	 * gets the value of $key from resource of $res_id
	 *
	 * @param string $key key of value to get
	 * @param int $res_id resource id
	 * @return mixed value of key and resource, false if key or id not found.
	 */
	function get_value($key,$res_id)
	{
		return $res_id == $this->data['res_id'] ? $this->data[$key] :
			$this->db->select($this->table_name,$key,array('res_id' => $res_id),__LINE__,__FILE__)->fetchColumn();
	}

	/**
	 * reads resource including custom fields
	 *
	 * Reimplemented to do some minimal caching (re-use already read data)
	 *
	 * @param int|array $res_id res_id
	 * @param string|array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $join sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 * @return array|boolean data if row could be retrived else False
	 */
	function read($keys, $extra_cols='', $join='')
	{
		if (is_array($keys) && count($keys) == 1 && isset($keys['keys'])) $keys = $keys['keys'];

		/*if (!is_array($keys) && $keys == $this->data['keys'])
		{
			error_log(__METHOD__.'('.array2string($keys).') this->data[keys]='.array2string($this->data['keys']).' --> returning this->data');
		}
		else
		{
			error_log(__METHOD__.'('.array2string($keys).') this->data[keys]='.array2string($this->data['keys']).' --> returning parent::read()');
		}*/
		return !is_array($keys) && $keys == $this->data['keys'] ? $this->data : parent::read($keys, $extra_cols, $join);
	}

	/**
	 * deletes resource
	 *
	 * Reimplemented to do some minimal caching (re-use already read data)
	 *
	 * @param int|array $res_id id of resource
	 * @param boolean $only_return_ids =false return $ids of delete call to db object, but not run it (can be used by extending classes!)
	 * @return int|array affected rows, should be 1 if ok, 0 if an error or array with id's if $only_return_ids
	 */
	function delete($keys=null,$only_return_ids=false)
	{
		if (($ok = parent::delete($keys, $only_return_ids)) && !$only_return_ids && !is_array($keys) && $keys == $this->data['res_id'])
		{
			unset($this->data);
		}
		return $ok;
	}

	/**
	 * saves a resource including extra fields
	 *
	 * @param array $resource key => value
	 * @param string|array $extra_where =null extra where clause, eg. to check an etag, returns true if no affected rows!
	 * @return int|boolean 0 on success, or errno != 0 on error, or true if $extra_where is given and no rows affected
	 */
	function save($keys=null,$extra_where=null)
	{
		$this->data = $keys;
		if(parent::save(null, $extra_where) != 0) return false;
		$res_id = $this->data['res_id'];

		return $res_id;
	}
}