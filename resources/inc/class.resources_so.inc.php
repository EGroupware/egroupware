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

/**
 * General storage object for resources
 *
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @package resources
 */
class resources_so extends so_sql_cf
{
	function __construct()
	{
		parent::__construct('resources','egw_resources', 'egw_resources_extra', '',
			'extra_name', 'extra_value', 'extra_id' );

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
	 * @return array|boolean data if row could be retrived else False
	 */
	function read($res_id)
	{
		if (is_array($res_id) && count($res_id) == 1 && isset($res_id['res_id'])) $res_id = $res_id['res_id'];

		/*if (!is_array($res_id) && $res_id == $this->data['res_id'])
		{
			error_log(__METHOD__.'('.array2string($res_id).') this->data[res_id]='.array2string($this->data['res_id']).' --> returning this->data');
		}
		else
		{
			error_log(__METHOD__.'('.array2string($res_id).') this->data[res_id]='.array2string($this->data['res_id']).' --> returning parent::read()');
		}*/
		return !is_array($res_id) && $res_id == $this->data['res_id'] ? $this->data : parent::read($res_id);
	}

	/**
	 * deletes resource
	 *
	 * Reimplemented to do some minimal caching (re-use already read data)
	 *
	 * @param int|array $res_id id of resource
	 * @return int|array affected rows, should be 1 if ok, 0 if an error or array with id's if $only_return_ids
	 */
	function delete($res_id)
	{
		if (($ok = parent::delete($res_id)) && !is_array($res_id) && $res_id == $this->data['res_id'])
		{
			unset($this->data);
		}
		return $ok;
	}

	/**
	 * saves a resource including extra fields
	 *
	 * @param array $resource key => value
	 * @return mixed id of resource if all right, false if fale
	 */
	function save($resource)
	{
		$this->data = $resource;
		if(parent::save() != 0) return false;
		$res_id = $this->data['res_id'];

		return $res_id;
	}
}
