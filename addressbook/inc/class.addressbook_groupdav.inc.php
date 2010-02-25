<?php
/**
 * EGroupware: GroupDAV access: addressbook handler
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package addressbook
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * EGroupware: GroupDAV access: addressbook handler
 *
 * Propfind now uses a groupdav_propfind_iterator with a callback to query huge addressbooks in chunk,
 * without getting into problems with memory_limit.
 */
class addressbook_groupdav extends groupdav_handler
{
	/**
	 * bo class of the application
	 *
	 * @var addressbook_bo
	 */
	var $bo;

	var $filter_prop2cal = array(
		'UID' => 'uid',
		//'NICKNAME',
		'EMAIL' => 'email',
		'FN' => 'n_fn',
	);

	/**
	 * Charset for exporting data, as some clients ignore the headers specifying the charset
	 *
	 * @var string
	 */
	var $charset = 'utf-8';

	/**
	 * What attribute is used to construct the path, default id, can be uid too
	 */
	const PATH_ATTRIBUTE = 'id';

	/**
	 * Constructor
	 *
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param int $debug=null debug-level to set
	 * @param string $base_uri=null base url of handler
	 */
	function __construct($app,$debug=null,$base_uri=null)
	{
		parent::__construct($app,$debug,$base_uri);

		$this->bo = new addressbook_bo();
	}

	/**
	 * Create the path for a contact
	 *
	 * @param array $contact
	 * @return string
	 */
	static function get_path($contact)
	{
		return '/addressbook/'.$contact[self::PATH_ATTRIBUTE].'.vcf';
	}

	/**
	 * Handle propfind in the addressbook folder
	 *
	 * @param string $path
	 * @param array $options
	 * @param array &$files
	 * @param int $user account_id
	 * @param string $id=''
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function propfind($path,$options,&$files,$user,$id='')
	{
		$filter = array();
		// show addressbook of a single user?
		if ($user && $path != '/addressbook/') $filter['contact_owner'] = $user;
		// should we hide the accounts addressbook
		if ($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts']) $filter['account_id'] = null;

		// process REPORT filters or multiget href's
		if (($id || $options['root']['name'] != 'propfind') && !$this->_report_filters($options,$filter,$id))
		{
			return false;
		}
		if ($this->debug) error_log(__METHOD__."($path,".array2string($options).",,$user,$id) filter=".array2string($filter));

		// check if we have to return the full calendar data or just the etag's
		if (!($filter['address_data'] = $options['props'] == 'all' && $options['root']['ns'] == groupdav::CARDDAV) && is_array($options['props']))
		{
			foreach($options['props'] as $prop)
			{
				if ($prop['name'] == 'address-data')
				{
					$filter['address_data'] = true;
					break;
				}
			}
		}
		// return iterator, calling ourself to return result in chunks
		$files['files'] = new groupdav_propfind_iterator($this,$filter,$files['files']);

		return true;
	}

	/**
	 * Callback for profind interator
	 *
	 * @param array $filter
	 * @param array|boolean $start=false false=return all or array(start,num)
	 * @return array with "files" array with values for keys path and props
	 */
	function &propfind_callback(array $filter,$start=false)
	{
		$starttime = microtime(true);

		if (($address_data = $filter['address_data']))
		{
			$handler = self::_get_handler();
		}
		unset($filter['address_data']);

		$files = array();
		// we query etag and modified, as LDAP does not have the strong sql etag
		if (($contacts =& $this->bo->search(array(),$address_data ? false : array('id','uid','etag','modified'),'contact_id','','',False,'AND',$start,$filter)))
		{
			foreach($contacts as &$contact)
			{
				$props = array(
					HTTP_WebDAV_Server::mkprop('getetag',$this->get_etag($contact)),
					HTTP_WebDAV_Server::mkprop('getcontenttype', 'text/x-vcard'),
					// getlastmodified and getcontentlength are required by WebDAV and Cadaver eg. reports 404 Not found if not set
					HTTP_WebDAV_Server::mkprop('getlastmodified', $contact['modified']),
				);
				if ($address_data)
				{
					$content = $handler->getVCard($contact,$this->charset,false);
					$props[] = HTTP_WebDAV_Server::mkprop('getcontentlength',bytes($content));
					$props[] = HTTP_WebDAV_Server::mkprop(groupdav::CARDDAV,'address-data',$content);
				}
				else
				{
					$props[] = HTTP_WebDAV_Server::mkprop('getcontentlength', '');		// expensive to calculate and no CalDAV client uses it
				}
				$files[] = array(
	            	'path'  => self::get_path($contact),
	            	'props' => $props,
				);
			}
		}
		if ($this->debug) error_log(__METHOD__.'('.array2string($filter).','.array2string($start).") took ".(microtime(true) - $starttime).' to return '.count($files).' items');
		return $files;
	}

	/**
	 * Process the filters from the CalDAV REPORT request
	 *
	 * @param array $options
	 * @param array &$cal_filters
	 * @param string $id
	 * @return boolean true if filter could be processed, false for requesting not here supported VTODO items
	 */
	function _report_filters($options,&$filters,$id)
	{
		if ($options['filters'])
		{
			foreach($options['filters'] as $filter)
			{
				switch($filter['name'])
				{
					case 'prop-filter':
						if ($this->debug > 1) error_log(__METHOD__."($path,...) prop-filter='{$filter['attrs']['name']}'");
						$prop_filter = $filter['attrs']['name'];
						break;
					case 'text-match':
						if ($this->debug > 1) error_log(__METHOD__."($path,...) text-match: $prop_filter='{$filter['data']}'");
						if (!isset($this->filter_prop2cal[strtoupper($prop_filter)]))
						{
							if ($this->debug) error_log(__METHOD__."($path,".str_replace(array("\n",'    '),'',print_r($options,true)).",,$user) unknown property '$prop_filter' --> ignored");
						}
						else
						{
							switch($filter['attrs']['match-type'])
							{
								default:
								case 'equals':
									$filters[$this->filter_prop2cal[strtoupper($prop_filter)]] = $filter['data'];
									break;
								case 'substr':	// ToDo: check RFC4790
									$filters[] = $this->filter_prop2cal[strtoupper($prop_filter)].' LIKE '.$GLOBALS['egw']->db->quote($filter['data']);
									break;
							}
						}
						unset($prop_filter);
						break;
					case 'param-filter':
						if ($this->debug) error_log(__METHOD__."($path,...) param-filter='{$filter['attrs']['name']}' not (yet) implemented!");
						break;
					default:
						if ($this->debug) error_log(__METHOD__."($path,".array2string($options).",,$user) unknown filter --> ignored");
						break;
				}
			}
		}
		// multiget --> fetch the url's
		if ($options['root']['name'] == 'addressbook-multiget')
		{
			$ids = array();
			foreach($options['other'] as $option)
			{
				if ($option['name'] == 'href')
				{
					$parts = explode('/',$option['data']);
					if (($id = array_pop($parts))) $ids[] = basename($id,'.vcf');
				}
			}
			if ($ids) $filters[self::PATH_ATTRIBUTE] = $ids;
			if ($this->debug) error_log(__METHOD__."($path,,,$user) addressbook-multiget: ids=".implode(',',$ids));
		}
		elseif ($id)
		{
			$filters[self::PATH_ATTRIBUTE] = basename($id,'.vcf');
		}
		return true;
	}

	/**
	 * Handle get request for an event
	 *
	 * @param array &$options
	 * @param int $id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function get(&$options,$id)
	{
		if (!is_array($contact = $this->_common_get_put_delete('GET',$options,$id)))
		{
			return $contact;
		}
		$handler = self::_get_handler();
		$options['data'] = $handler->getVCard($contact['id'],$this->charset,false);
		$options['mimetype'] = 'text/x-vcard; charset='.$this->charset;
		header('Content-Encoding: identity');
		header('ETag: '.$this->get_etag($contact));
		return true;
	}

	/**
	 * Handle put request for an event
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user=null account_id of owner, default null
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function put(&$options,$id,$user=null)
	{
		$ok = $this->_common_get_put_delete('PUT',$options,$id);
		if (!is_null($ok) && !is_array($ok))
		{
			return $ok;
		}
		$handler = self::_get_handler();
		$contact = $handler->vcardtoegw($options['content']);

		if (!is_null($ok))
		{
			$contact['id'] = $ok['id'];
			// dont allow the client to overwrite certain values
			$contact['uid'] = $ok['uid'];
			$contact['owner'] = $ok['owner'];
			$contact['private'] = $ok['private'];
		}
		if ($this->http_if_match) $contact['etag'] = self::etag2value($this->http_if_match);

		if (!($save_ok = $this->bo->save($contact)))
		{
			if ($this->debug) error_log(__METHOD__."(,$id) save(".array2string($contact).") failed, Ok=$save_ok");
			if ($save_ok === 0)
			{
				return '412 Precondition Failed';
			}
			return false;
		}
		if (!isset($contact['etag']))
		{
			$contact = $this->read($contact['id']);
		}

		header('ETag: '.$this->get_etag($contact));
		if (is_null($ok))
		{
			header($h='Location: '.$this->base_uri.self::get_path($contact));
			if ($this->debug) error_log(__METHOD__."($method,,$id) header('$h'): 201 Created");
			return '201 Created';
		}
		return true;
	}

	/**
	 * Query ctag for addressbook
	 * 
	 * @return string
	 */
	public function getctag($path,$user)
	{
		$filter = array();
		// show addressbook of a single user?
		if ($user && $path != '/addressbook/') $filter['contact_owner'] = $user;
		// should we hide the accounts addressbook
		if ($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts']) $filter['account_id'] = null;
		
		$result = $this->bo->search(array(),'MAX(contact_modified) AS contact_modified','','','','','',$filter);
		
		return '"'.$result[0]['modified'].'"';
	}

	/**
	 * Add extra properties for addressbook collections
	 *
	 * Example for supported-report-set syntax from Apples Calendarserver:
	 * <D:supported-report-set>
	 *    <supported-report>
	 *       <report>
	 *          <addressbook-query xmlns='urn:ietf:params:xml:ns:carddav'/>
	 *       </report>
	 *    </supported-report>
	 *    <supported-report>
	 *       <report>
	 *          <addressbook-multiget xmlns='urn:ietf:params:xml:ns:carddav'/>
	 *       </report>
	 *    </supported-report>
	 * </D:supported-report-set>
	 * @link http://www.mail-archive.com/calendarserver-users@lists.macosforge.org/msg01156.html
	 *    
	 * @param array $props=array() regular props by the groupdav handler
	 * @return array
	 */
	static function extra_properties(array $props=array())
	{
		// supported reports (required property for CardDAV)
		$props[] =	HTTP_WebDAV_Server::mkprop('supported-report-set',array(
			HTTP_WebDAV_Server::mkprop('supported-report',array(
				HTTP_WebDAV_Server::mkprop('report',
					HTTP_WebDAV_Server::mkprop(groupdav::CARDDAV,'addressbook-query','')))),
			HTTP_WebDAV_Server::mkprop('supported-report',array(
				HTTP_WebDAV_Server::mkprop('report',
					HTTP_WebDAV_Server::mkprop(groupdav::CARDDAV,'addressbook-multiget','')))),
		));
		return $props;
	}

	/**
	 * Get the handler and set the supported fields
	 *
	 * @return addressbook_vcal
	 */
	private function _get_handler()
	{
		$handler = new addressbook_vcal('addressbook','text/vcard');
		$handler->setSupportedFields('GroupDAV',$this->agent);

		return $handler;
	}

	/**
	 * Handle delete request for an event
	 *
	 * @param array &$options
	 * @param int $id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function delete(&$options,$id)
	{
		if (!is_array($contact = $this->_common_get_put_delete('DELETE',$options,$id)))
		{
			return $contact;
		}
		if (($Ok = $this->bo->delete($contact['id'],self::etag2value($this->http_if_match))) === 0)
		{
			return '412 Precondition Failed';
		}
		return $ok;
	}

	/**
	 * Read a contact
	 *
	 * @param string/id $id
	 * @return array/boolean array with entry, false if no read rights, null if $id does not exist
	 */
	function read($id)
	{
		return $this->bo->read(self::PATH_ATTRIBUTE == 'id' ? $id : array(self::PATH_ATTRIBUTE => $id));
	}

	/**
	 * Check if user has the neccessary rights on a contact
	 *
	 * @param int $acl EGW_ACL_READ, EGW_ACL_EDIT or EGW_ACL_DELETE
	 * @param array/int $contact contact-array or id
	 * @return boolean null if entry does not exist, false if no access, true if access permitted
	 */
	function check_access($acl,$contact)
	{
		return $this->bo->check_perms($acl,$contact);
	}
}
