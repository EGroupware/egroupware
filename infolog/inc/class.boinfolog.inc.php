<?php
/**
 * InfoLog - xmlrpc access
 *
 * Please note: dont use infolog_... naming convention, as it would break the existing xmlrpc clients
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @copyright (c) 2003-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Class to access AND manipulate InfoLog data via XMLRPC or SOAP
 *
 * eGW's xmlrpc interface is documented at http://egroupware.org/wiki/xmlrpc
 *
 * @link http://egroupware.org/wiki/xmlrpc
 */
class boinfolog extends infolog_bo
{
	var $xmlrpc_methods = array();
	var $soap_functions = array(
		'read' => array(
			'in'  => array('int'),
			'out' => array('array')
		),
		'search' => array(
			'in'  => array('array'),
			'out' => array('array')
		),
		'write' => array(
			'in'  => array('array'),
			'out' => array()
		),
		'delete' => array(
			'in'  => array('int'),
			'out' => array()
		),
		'categories' => array(
			'in'  => array('bool'),
			'out' => array('array')
		),
	);

	/**
	 * handles introspection or discovery by the logged in client,
	 *  in which case the input might be an array.  The server always calls
	 *  this function to fill the server dispatch map using a string.
	 *
	 * @param string $_type='xmlrpc' xmlrpc or soap
	 * @return array
	 */
	function list_methods($_type='xmlrpc')
	{
		if (is_array($_type))
		{
			$_type = $_type['type'] ? $_type['type'] : $_type[0];
		}

		switch($_type)
		{
			case 'xmlrpc':
				$xml_functions = array(
					'read' => array(
						'function'  => 'read',
						'signature' => array(array(xmlrpcInt,xmlrpcInt)),
						'docstring' => lang('Read one record by passing its id.')
					),
					'search' => array(
						'function'  => 'search',
						'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
						'docstring' => lang('Returns a list / search for records.')
					),
					'write' => array(
						'function'  => 'write',
						'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
						'docstring' => lang('Write (add or update) a record by passing its fields.')
					),
					'delete' => array(
						'function'  => 'delete',
						'signature' => array(array(xmlrpcInt,xmlrpcInt)),
						'docstring' => lang('Delete one record by passing its id.')
					),
					'categories' => array(
						'function'  => 'categories',
						'signature' => array(array(xmlrpcBoolean,xmlrpcBoolean)),
						'docstring' => lang('List all categories')
					),
					'list_methods' => array(
						'function'  => 'list_methods',
						'signature' => array(array(xmlrpcStruct,xmlrpcString)),
						'docstring' => lang('Read this list of methods.')
					)
				);
				return $xml_functions;
				break;
			case 'soap':
				return $this->soap_functions;
				break;
			default:
				return array();
				break;
		}
	}

	/**
	 * Read an infolog entry specified by $info_id
	 *
	 * @param int/array $info_id integer id or array with key 'info_id' of the entry to read
	 * @return array/boolean infolog entry, null if not found or false if no permission to read it
	 */
	function &read($info_id)
	{
		$data = parent::read($info_id);

		if (is_null($data))
		{
			$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['not_exist'],$GLOBALS['xmlrpcstr']['not_exist']);
		}
		elseif($data === false)
		{
			$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
		}
		else
		{
			$data = $this->data2xmlrpc($data);
		}
		return $data;
	}

	/**
	 * Delete an infolog entry, evtl. incl. it's children / subs
	 *
	 * @param array $data array with keys 'info_id', 'delete_children' and 'new_parent'
	 * @return boolean True if delete was successful, False otherwise ($info_id does not exist or no rights)
	 */
	function delete($data)
	{
		if (is_array($data))
		{
			$delete_children = $data['delete_children'];
			$new_parent = $data['new_parent'];
			$info_id = (int)(isset($data[0]) ? $data[0] : $data['info_id']);
			$status = parent::delete($info_id,$delete_children,$new_parent);
		}
		else
		{
			$status = parent::delete($data);
		}

		if ($status === false)
		{
			$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
		}
		return $status;
	}

	/**
	* writes the given $values to InfoLog, a new entry gets created if info_id is not set or 0
	*
	* checks and asures ACL
	*
	* @param array $values values to write, if contains values for check_defaults and touch_modified,
	*	they have precedens over the parameters. The
	* @param boolean $check_defaults=true check and set certain defaults
	* @param boolean $touch_modified=true touch the modification data and sets the modiefier's user-id
	* @return int/boolean info_id on a successfull write or false
	*/
	function write($values,$check_defaults=True,$touch_modified=True)
	{
		//echo "boinfolog::write()values="; _debug_array($values);
		// allow to (un)set check_defaults and touch_modified via values, eg. via xmlrpc
		foreach(array('check_defaults','touch_modified') as $var)
		{
			if(isset($values[$var]))
			{
				$$var = $values[$var];
				unset($values[$var]);
			}
		}
		$values = $this->xmlrpc2data($values);

		$status = parent::write($values,$check_defaults,$touch_modified);

		if ($status == false)
		{
			$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
		}
		return $status;
	}

	/**
	 * searches InfoLog for a certain pattern in $query
	 *
	 * @param $query[order] column-name to sort after
	 * @param $query[sort] sort-order DESC or ASC
	 * @param $query[filter] string with combination of acl-, date- and status-filters, eg. 'own-open-today' or ''
	 * @param $query[cat_id] category to use or 0 or unset
	 * @param $query[search] pattern to search, search is done in info_from, info_subject and info_des
	 * @param $query[action] / $query[action_id] if only entries linked to a specified app/entry show be used
	 * @param &$query[start], &$query[total] nextmatch-parameters will be used and set if query returns less entries
	 * @param $query[col_filter] array with column-name - data pairs, data == '' means no filter (!)
	 * @return array with id's as key of the matching log-entries
	 */
	function &search(&$query)
	{
		//echo "<p>boinfolog::search(".print_r($query,True).")</p>\n";
		$ret = parent::search($query);

		if (is_array($ret))
		{
			$infos =& $ret;
			unset($ret);
			$ret = array();
			foreach($infos as $id => $data)
			{
				$ret[] = $this->data2xmlrpc($data);
			}
		}
		//echo "<p>boinfolog::search(".print_r($query,True).")=<pre>".print_r($ret,True)."</pre>\n";
		return $ret;
	}

	/**
	 * Convert an InfoLog entry into its xmlrpc representation, eg. convert timestamps to datetime.iso8601
	 *
	 * @param array $data infolog entry in db format
	 *
	 * @return array xmlrpc infolog entry
	 */
	function data2xmlrpc($data)
	{
		$data['rights'] = $this->so->grants[$data['info_owner']];

		// translate timestamps
		if($data['info_enddate'] == 0) unset($data['info_enddate']);
		foreach($this->timestamps as $name)
		{
			if (isset($data[$name]))
			{
				$data[$name] = $GLOBALS['server']->date2iso8601($data[$name]);
			}
		}
		$ret[$id]['info_percent'] = (int)$data['info_percent'].'%';

		// translate cat_id
		if (isset($data['info_cat']))
		{
			$data['info_cat'] = $GLOBALS['server']->cats2xmlrpc(array($data['info_cat']));
		}
		foreach($data as $name => $val)
		{
			if (substr($name,0,5) == 'info_')
			{
				unset($data[$name]);
				$data[substr($name,5)] = $val;
			}
		}
		// unsetting everything which could result in an typeless <value />
		foreach($data as $key => $value)
		{
			if (is_null($value) || is_array($value) && !$value)
			{
				unset($data[$key]);
			}
		}
		return $data;
	}

	/**
	 * Convert an InfoLog xmlrpc representation into the internal one, eg. convert datetime.iso8601 to timestamps
	 *
	 * @param array $data infolog entry in xmlrpc representation
	 *
	 * @return array infolog entry in db format
	 */
	function xmlrpc2data($data)
	{
		foreach($data as $name => $val)
		{
			if (substr($name,0,5) != 'info_')
			{
				unset($data[$name]);
				$data['info_'.$name] = $val;
			}
		}
		// translate timestamps
		foreach($this->timestamps as $name)
		{
			if (isset($data[$name]))
			{
				$data[$name] = $GLOBALS['server']->iso86012date($data[$name],True);
			}
		}
		// translate cat_id
		if (isset($data['info_cat']))
		{
			$cats = $GLOBALS['server']->xmlrpc2cats($data['info_cat']);
			$data['info_cat'] = (int)$cats[0];
		}
		return $data;
	}

	/**
	 * return array with all infolog categories (for xmlrpc)
	 *
	 * @param boolean $complete true returns array with all data for each cat, else only the title is returned
	 * @return array with cat_id / title or data pairs (see above)
	 */
	function categories($complete = False)
	{
		return $GLOBALS['server']->categories($complete);
	}
}
