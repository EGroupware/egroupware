<?php
/**
 * API - contacts service provided by the addressbook application
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @copyright (c) 2006-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * contacts service provided by the addressbook application
 */
class contacts extends addressbook_bo
{
	/**
	 * @deprecated since 1.3 use total
	 * @var int $total_records
	 */
	var $total_records;
	/**
	 * @deprecated since 1.3 use contact_fields
	 * @var array $stock_contact_fields
	 */
	var $stock_contact_fields = array();

	/**
	 * constructor calling the constructor of the extended class
	 */
	function __construct($contact_app='addressbook')
	{
		parent::__construct($contact_app);

		$this->total_records =& $this->total;
		$this->stock_contact_fields = array_keys($this->contact_fields);
	}

	/**
	* reads contacts matched by key and puts all cols in the data array
	*
	* reimplemented from bocontacts to call the old read function, if more then one param
	*
	* @param int/string $contact_id
	* @return array/boolean contact data or false on error
	*/
	function read($contact_id)
	{
		if (func_num_args() > 1)	// calling the old / depricated read function
		{
			$args = func_get_args();
			return call_user_func_array(array(&$this,'old_read'),$args);
		}
		return parent::read($contact_id);
	}

	/**
	 * Deprecated methods for compatibility with the old contacts class
	 *
	 * They will be removed after one release, so dont use them in new code!!!
	 */

	/**
	 * Searches for contacts meating certain criteria and evtl. return only a range of them
	 *
	 * This method was named read in eGW 1.0 and 1.2
	 *
	 * @deprecated since 1.3 use search() instead
	 * @param int $start=0 starting number of the range, if $limit != 0
	 * @param int $limit=0 max. number of entries to return, 0=all
	 * @param array $fields=null fields to return or null for all stock fields, fields are either in the keys or values
	 * @param string $query='' search pattern or '' for none
	 * @param string $filter='' filters with syntax like <name>=<value>,<name2>=<value2>,<name3>=!'' for not empty
	 * @param string $sort='' sorting: ASC or DESC
	 * @param string $order='' column to order, default ('') n_family,n_given,email ASC
	 * @param int $lastmod=-1 return only values modified after given timestamp, default (-1) return all
	 * @param string $cquery='' return only entries starting with given character, default ('') all
	 * @return array of contacts
	 */
	function old_read($start=0,$limit=0,$fields=null,$query='',$filter='',$sort='',$order='', $lastmod=-1,$cquery='')
	{
		//error_log("contacts::old_read($start,$limit,".print_r($fields,true).",$query,'$filter','$sort','$order',$lastmod,$cquery)");
		//echo "<p>contacts::old_read($start,$limit,".print_r($fields,true).",$query,'$filter','$sort','$order',$lastmod,$cquery)</p>\n";
		$sfilter = array();
		if ($filter)
		{
			foreach(explode(',',$filter) as $expr)
			{
				list($col,$value) = explode('=',$expr);

				if ($col == 'access')	// text field access is renamed to private and using boolean 0/1
				{
					$col = 'private';
					$value = $value == 'private';
				}
				$sfilter[$col] = $value;
			}
		}
		if ($lastmod != -1)
		{
			$sfilter[] = 'contact_modified > '.(int)$lastmod;
		}
		static $old2new = array('fn' => 'n_fn','bday' => 'bday');

		if (is_array($fields))
		{
			$fields2 = array_values($fields);
			// check if the fields are in the keys with values true or 1
			$fields = $fields2[0] === true || $fields2[0] === 1 ? array_keys($fields) : $fields2;


			foreach($old2new as $old => $new)
			{
				if (($key = array_search($old,$fields)) !== false)
				{
					$fields[$key] = $new;
				}
			}
		}
		elseif (is_string($fields))
		{
			$fields = explode(',',$fields);
		}
		if (!$order) $order = $fields ? $fields[0] : 'org_name,n_family,n_given';
		if ($order && strpos($order,'_') === false) $order = 'contact_'.$order;

		//echo '<p>contacts::search('.($cquery ? $cquery.'*' : $query).','.print_r($fields,true).",'$order $sort','','".($cquery ? '' : '%')."',false,'OR',".(!$limit ? 'false' : "array($start,$limit)").",".print_r($sfilter,true).");</p>\n";
		$rows =& $this->search($cquery ? $cquery.'*' : $query,$fields,$order.($sort ? ' '.$sort : ''),'',
			$cquery ? '' : '%',false,'OR',!$limit ? false : array((int)$start,(int)$limit),$sfilter);

		// return the old birthday format
		if ($rows && (is_null($fields) || array_intersect($old2new,$fields)))
		{
			foreach($rows as $n => $row)
			{
				foreach($old2new as $old => $new)
				{
					if (isset($row[$new])) $rows[$n][$old] = $row[$new];

					if (isset($row['bday']) || isset($row['contact_bday']))
					{
						$bdayset=true;
						if (isset($row['bday']) && ($row['bday']=='0000-00-00 0' || $row['bday']=='0000-00-00' || $row['bday']=='0.0.00'))
						{
							$rows[$n]['bday'] = $row['bday']=null;
							$bdayset=false;
						}
						if (isset($row['contact_bday']) && ($row['contact_bday']=='0000-00-00 0' || $row['contact_bday']=='0000-00-00' || $row['contact_bday']=='0.0.00'))
						{
							$rows[$n]['contact_bday'] = $row['contact_bday']=null;
							$bdayset=false;
						}
						if ($bdayset==false) continue; // dont try to make a date out of that
						$row['bday'] = egw_time::to((isset($row['bday'])?$row['bday']:$row['contact_bday']),"Y-m-d");
						list($y,$m,$d) = explode('-',$row['bday']);
						$rows[$n]['bday'] = sprintf('%d/%d/%04d',$m,$d,$y);
						
					}
				}
			}
		}
		return $rows;
	}

	/**
	 * read a single entry
	 *
	 * @deprecated since 1.3 use read() instead
	 * @param int $id
	 * @param string $fields='' we always return all fields now
	 * @return array/boolean contact or false on failure
	 */
	function read_single_entry($id,$fields='')
	{
		return $this->read($id);
	}

	/**
	 * add a contact
	 *
	 * @deprecated since 1.3 use save() instead
	 * @param int $owner owner of the entry
	 * @param array $fields contains access, cat_id and tif if their param is null
	 * @param string $access=null 'private' or 'public'
	 * @param int $cat_id=null
	 * @param string $tid=null 'n'
	 * @return array/boolean contact or false on failure
	 */
	function add($owner,$fields,$access=NULL,$cat_id=NULL,$tid=NULL)
	{
		// access, cat_id and tid can be in $fields now or as extra params
		foreach(array('access','cat_id','tid') as $extra)
		{
			if (!is_null($$extra))
			{
				$fields[$extra] = $$extra;
			}
		}
		if(empty($fields['tid']))
		{
			$fields['tid'] = 'n';
		}
		$fields['private'] = (int) $fields['access'] == 'private';
		unset($fields['id']);	// in case it's set

		return !$this->save($fields) ? $fields['id'] : false;
	}

	/**
	 * update a contact
	 *
	 * @deprecated since 1.3 use save() instead
	 * @param int $id id of the entry
	 * @param int $owner owner of the entry
	 * @param array $fields contains access, cat_id and tif if their param is null
	 * @param string $access=null 'private' or 'public'
	 * @param int $cat_id=null
	 * @param string $tid=null 'n'
	 * @return array/boolean contact or false on failure
	 */
	function update($id,$owner,$fields,$access=NULL,$cat_id=NULL,$tid=NULL)
	{
		// access, cat_id and tid can be in $fields now or as extra params
		foreach(array('access','cat_id','tid') as $extra)
		{
			if (!is_null($$extra))
			{
				$fields[$extra] = $$extra;
			}
		}
		if(empty($fields['tid']))
		{
			$fields['tid'] = 'n';
		}
		$fields['private'] = (int) $fields['access'] == 'private';
		$fields['id'] = $id;

		return $id && !$this->save($fields);
	}
}
