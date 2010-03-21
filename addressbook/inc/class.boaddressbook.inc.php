<?php
/**
 * Addressbook - xmlrpc access
 *
 * The original addressbook xmlrpc interface was written by Joseph Engo <jengo@phpgroupware.org>
 * and Miles Lott <milos@groupwhere.org>
 *
 * Please note: dont use addressbook_... naming convention, as it would break the existing xmlrpc clients
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2007/8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Class to access AND manipulate addressbook data via XMLRPC or SOAP
 *
 * eGW's xmlrpc interface is documented at http://egroupware.org/wiki/xmlrpc
 *
 * @link http://egroupware.org/wiki/xmlrpc
 */
class boaddressbook
{
	/**
	 * Instance of the contacts class
	 *
	 * @var contacts
	 */
	var $contacts;

	/**
	 * Field-mapping for certain user-agents
	 *
	 * @var array
	 */
	var $mapping=array();

	/**
	 * User agent: 'KDE-AddressBook', 'eGWOSync', ...
	 *
	 * @var string
	 */
	var $user_agent;

	/**
	 * Contstructor
	 *
	 * @return boaddressbook
	 */
	function __construct()
	{
		$this->contacts = $GLOBALS['egw']->contacts;

		// are we called via xmlrpc?
		if (!is_object($GLOBALS['server']) || !$GLOBALS['server']->last_method)
		{
			die('not called via xmlrpc');
		}
		$this->set_mapping_for_user_agent();
	}

	/**
	 * This handles introspection or discovery by the logged in client,
	 * in which case the input might be an array.  The server always calls
	 * this function to fill the server dispatch map using a string.
	 *
	 * @param string/array $_type='xmlrpc' xmlrpc or soap
	 * @return array
	 */
	function list_methods($_type='xmlrpc')
	{
		if(is_array($_type))
		{
			$_type = $_type['type'] ? $_type['type'] : $_type[0];
		}
		switch($_type)
		{
			case 'xmlrpc':
				return array(
					'read' => array(
						'function'  => 'read',
						'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
						'docstring' => lang('Read a single entry by passing the id and fieldlist.')
					),
					'save' => array(
						'function'  => 'save',
						'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
						'docstring' => lang('Write (update or add) a single entry by passing the fields.')
					),
					'write' => array(	// old 1.2 name
						'function'  => 'save',
						'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
						'docstring' => lang('Write (update or add) a single entry by passing the fields.')
					),
					'delete' => array(
						'function'  => 'delete',
						'signature' => array(array(xmlrpcString,xmlrpcString)),
						'docstring' => lang('Delete a single entry by passing the id.')
					),
					'search' => array(
						'function'  => 'search',
						'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
						'docstring' => lang('Read a list / search for entries.')
					),
					'categories' => array(
						'function'  => 'categories',
						'signature' => array(array(xmlrpcBoolean,xmlrpcBoolean)),
						'docstring' => lang('List all categories')
					),
					'customfields' => array(
						'function'  => 'customfields',
						'signature' => array(array(xmlrpcArray,xmlrpcArray)),
						'docstring' => lang('List all customfields')
					),
					'list_methods' => array(
						'function'  => 'list_methods',
						'signature' => array(array(xmlrpcStruct,xmlrpcString)),
						'docstring' => lang('Read this list of methods.')
					)
				);

			case 'soap':
				return 	array(
					'read' => array(
						'in'  => array('int','struct'),
						'out' => array('array')
					),
					'write' => array(
						'in'  => array('int','struct'),
						'out' => array()
					),
					'categories' => array(
						'in'  => array('bool'),
						'out' => array('struct')
					),
					'customfields' => array(
						'in' => array('array'),
						'out'=> array('struct')
					)
				);

			default:
				return array();
		}
	}

	/**
	 * Get field-mapping for user agents expecting old / other field-names
	 *
	 * @internal
	 */
	function set_mapping_for_user_agent()
	{
		//error_log("set_mapping_for_user_agent(): HTTP_USER_AGENT='$_SERVER[HTTP_USER_AGENT]'");
		switch($this->user_agent = $_SERVER['HTTP_USER_AGENT'])
		{
			case 'KDE-AddressBook':
				$this->mapping = array(
					'n_fn' => 'fn',
					'modified' => 'last_mod',
					'tel_other' => 'ophone',
					'adr_one_street2' => 'address2',
					'adr_two_street2' => 'address3',
					'freebusy_uri' => 'freebusy_url',
					'grants[owner]' => 'rights',
					'jpegphoto' => false,	// gives errors in KAddressbook, maybe the encoding is wrong
					'photo' => false,		// is uncomplete anyway
					'private' => 'access',	// special handling necessary
					'adr_one_type' => "'Work'",	// defines how KAddresbook labels the address
					'adr_two_type' => "'Home'",
				);
				break;

			case 'eGWOSync':	// no idea what is necessary
				break;
		}
	}

	/**
	 * translate array of internal datas to xmlrpc, eg. format bday as iso8601
	 *
	 * @internal
	 * @param array $datas array of contact arrays
	 * @param boolean $read_customfields=false should the customfields be read, default no (contacts::read() does it already)
	 * @return array
	 */
	function data2xmlrpc($datas,$read_customfields=false)
	{
		if(is_array($datas))
		{
			if ($read_customfields)
			{
				$ids = array();
				foreach($datas as $data)
				{
					$ids[] = $data['id'];
				}
				$customfields = $this->contacts->read_customfields($ids);
			}
			foreach($datas as $n => $nul)
			{
				$data =& $datas[$n];	// $n => &$data is php5 ;-)

				if ($customfields && isset($customfields[$data['id']]))
				{
					foreach($customfields[$data['id']] as $name => $value)
					{
						$data['#'.$name] = $value;
					}
				}
				// remove empty or null elements, they dont need to be transfered
				$data = array_diff($data,array('',null));

				// translate birthday to a iso8601 date
				if(isset($data['bday']) && $data['bday'])
				{
					$y = $m = $d = null;
					list($y,$m,$d) = explode('-',$data['bday']);
					if (is_null($d)) list($m,$d,$y) = explode('/',$data['bday']);
					$data['bday'] = $GLOBALS['server']->date2iso8601(array('year'=>$y,'month'=>$m,'mday'=>$d));
				}
				// translate timestamps
				foreach($this->contacts->timestamps as $name)
				{
					if(isset($data[$name]))
					{
						$data[$name] = $GLOBALS['server']->date2iso8601($data[$name]);
					}
				}
				// translate categories-id-list to array with id-name pairs
				if(isset($data['cat_id']))
				{
					$data['cat_id'] = $GLOBALS['server']->cats2xmlrpc(explode(',',$data['cat_id']));
				}
				// replacing the fieldname in tel_prefer with the actual number, if it exists and is non-empty
				if (substr($data['tel_prefer'],0,4) === 'tel_' && $data[$data['tel_prefer']])
				{
					$data['tel_prefer'] = $data[$data['tel_prefer']];
				}
				// translate fieldnames if required
				foreach($this->mapping as $from => $to)
				{
					switch($from)
					{
						case 'grants[owner]':
							$data[$to] = $this->contacts->grants[$data['owner']];
							break;

						case 'private':
							$data[$to] = $data['private'] ? 'private' : 'public';
							break;

						default:
							if ($to{0} == "'")	// constant value enclosed in single quotes
							{
								$data[$from] = substr($to,1,-1);
							}
							elseif(isset($data[$from]))
							{
								if ($to) $data[$to] =& $data[$from];
								unset($data[$from]);
							}
							break;
					}
				}
			}
		}
		return $datas;
	}

	/**
	 * retranslate from xmlrpc / iso8601 to internal format
	 *
	 * @internal
	 * @param array $data
	 * @return array
	 */
	function xmlrpc2data($data)
	{
		// translate fieldnames if required
		foreach($this->mapping as $to => $from)
		{
			if ($from && isset($data[$from]))
			{
				switch($to)
				{
					case 'private':
						$data[$to] = $data['access'] == 'private';
						break;

					default:
						$data[$to] =& $data[$from]; unset($data[$from]);
						break;
				}
			}
		}
		// translate birthday
		if(isset($data['bday']))
		{
			$arr = $GLOBALS['server']->iso86012date($data['bday']);
			$data['bday'] = $arr['year'] && $arr['month'] && $arr['mday'] ? sprintf('%04d-%02d-%02d',$arr['year'],$arr['month'],$arr['mday']) : null;
		}
		// translate timestamps
		foreach($this->contacts->timestamps as $name)
		{
			if(isset($data[$name]))
			{
				$data[$name] = $GLOBALS['server']->date2iso8601($data[$name]);
			}
		}
		// translate cats
		if(isset($data['cat_id']))
		{
			$cats = $GLOBALS['server']->xmlrpc2cats($data['cat_id']);
			$data['cat_id'] = count($cats) > 1 ? ','.implode(',',$cats).',' : (int)$cats[0];
		}
		// replacing the number in tel_prefer with the fieldname, if it matches a phone-number, otherwise keep it's content as is
		if ($data['tel_prefer'])
		{
			$prefer = $data['tel_prefer'];
			unset($data['tel_prefer']);
			if (($key = array_search($prefer,$data)) !== false && substr($key,0,4) === 'tel_')
			{
				$data['tel_prefer'] = $key;
			}
			else
			{
				$data['tel_prefer'] = $prefer;
			}
		}
		return $data;
	}

	/**
	 * Search the addressbook
	 *
	 * Todo: use contacts::search and all it's possebilities instead of the depricated contacts::old_read()
	 *
	 * @param array $param
	 * @param int $param['start']=0 starting number of the range, if $param['limit'] != 0
	 * @param int $param['limit']=0 max. number of entries to return, 0=all
	 * @param array $param['fields']=null fields to return or null for all stock fields, fields are in the values (!)
	 * @param string $param['query']='' search pattern or '' for none
	 * @param string $param['filter']='' filters with syntax like <name>=<value>,<name2>=<value2>,<name3>=!'' for not empty
	 * @param string $param['sort']='' sorting: ASC or DESC
	 * @param string $param['order']='' column to order, default ('') n_family,n_given,email ASC
	 * @param int $param['lastmod']=-1 return only values modified after given timestamp, default (-1) return all
	 * @param string $param['cquery']='' return only entries starting with given character, default ('') all
	 * @param string $param['customfields']=true return the customfields too, default yes
	 * @return array of contacts
	 */
	function search($param)
	{
		$read_customfields = !isset($param['customfields']) || $param['customfields'];

		$extra_accounts = array();
		if ($this->user_agent == 'KDE-AddressBook' && strpos($this->contacts->contact_repository,$this->contacts->account_repository) === false)
		{
			$extra_accounts = $this->contacts->search(array(),false,'','','',false,'AND',false,array(
				'contact_owner' => 0,
			));
		}
		$searchResults = $this->contacts->old_read(
			(int) $param['start'],
			(int) $param['limit'],
			$param['fields'],
			$param['query'],
			$param['filter'],
			$param['sort'],
			$param['order'],
			$param['lastmod'] ? $param['lastmod'] : -1,
			$param['cquery']
		);
		if (!is_array($searchResults)) $searchResults = array();

		return $this->data2xmlrpc(array_merge($searchResults,$extra_accounts),$read_customfields);
	}

	/**
	 * Read one contact
	 *
	 * @param mixed $id $id, $id[0] or $id['id'] contains the id of the contact
	 * @return array contact
	 */
	function read($id)
	{
		if(is_array($id)) $id = isset($id[0]) ? $id[0] : $id['id'];

		$data = $this->contacts->read($id);

		if($data !== false)	// permission denied
		{
			$data = $this->data2xmlrpc(array($data));

			return $data[0];
		}
		$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
	}

	/**
	 * Save a contact
	 *
	 * @param array $data
	 * @return int new contact_id
	 */
	function save($data)
	{
		$data = $this->xmlrpc2data($data);

		$id = $this->contacts->save($data);

		if($id) return $id;

		$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
	}

	/**
	 * Delete a contact
	 *
	 * @param mixed $id $id, $id[0] or $id['id'] contains the id of the contact
	 * @param boolean true
	 */
	function delete($id)
	{
		if(is_array($id)) $id = isset($id[0]) ? $id[0] : $id['id'];

		if ($this->contacts->delete($id))
		{
			return true;
		}
		$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
	}

	/**
	 * return all addressbook categories
	 *
	 * @param boolean $complete complete cat-array or just the name
	 * @param array with cat_id => name or cat_id => cat-array pairs
	 */
	function categories($complete = False)
	{
		return $GLOBALS['server']->categories($complete);
	}

	/**
	 * get or set addressbook customfields
	 *
	 * @param array $new_fields=null
	 * @return array
	 */
	function customfields($new_fields=null)
	{
		if(is_array($new_fields) && count($new_fields))
		{
			if(!$GLOBALS['egw_info']['user']['apps']['admin'])
			{
				$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
			}
			require_once(EGW_INCLUDE_ROOT.'/admin/inc/class.customfields.inc.php');
			$fields = new customfields('addressbook');

			foreach($new_fields as $new)
			{
				if (!is_array($new))
				{
					$new = array('name' => $new);
				}
				$fields->create_field(array('fields' => $new));
			}
		}
		$customfields = array();
		foreach($this->contacts->customfields as $name => $data)
		{
			$customfields[$name] = $data['label'];
		}
		return $customfields;
	}
}
