<?php
/**
 * EGroupware: GroupDAV access: addressbook handler
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package addressbook
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-12 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * EGroupware: GroupDAV access: addressbook handler
 *
 * Propfind now uses a groupdav_propfind_iterator with a callback to query huge addressbooks in chunk,
 * without getting into problems with memory_limit.
 *
 * @todo create extra addressbook eg. "/accounts/" which shows accounts, even if they are in LDAP (no carddav_name column!)
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
		'ORG' => 'org_name',
	);

	/**
	 * Charset for exporting data, as some clients ignore the headers specifying the charset
	 *
	 * @var string
	 */
	var $charset = 'utf-8';

	/**
	 * Constructor
	 *
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param groupdav $groupdav calling class
	 */
	function __construct($app, groupdav $groupdav)
	{
		parent::__construct($app, $groupdav);

		$this->bo = new addressbook_bo();

		// since 1.9.007 we allow clients to specify the URL when creating a new contact, as specified by CardDAV
		if (version_compare($GLOBALS['egw_info']['apps']['phpgwapi']['version'], '1.9.007', '>='))
		{
			groupdav_handler::$path_attr = 'carddav_name';
			groupdav_handler::$path_extension = '';
		}
		else
		{
			groupdav_handler::$path_extension = '.vcf';
		}
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
		if ($user && $path != '/addressbook/' || $user === 0) $filter['contact_owner'] = $user;
		// should we hide the accounts addressbook
		if ($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts']) $filter['account_id'] = null;

		// process REPORT filters or multiget href's
		if (($id || $options['root']['name'] != 'propfind') && !$this->_report_filters($options,$filter,$id, $nresults))
		{
			return false;
		}
		if ($id) $path = dirname($path).'/';	// carddav_name get's added anyway in the callback

		if ($this->debug) error_log(__METHOD__."($path,".array2string($options).",,$user,$id) filter=".array2string($filter));

		// check if we have to return the full contact data or just the etag's
		if (!($filter['address_data'] = $options['props'] == 'all' &&
			$options['root']['ns'] == groupdav::CARDDAV) && is_array($options['props']))
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
		if (isset($nresults))
		{
			$files['files'] = $this->propfind_callback($path, $filter, array(0, (int)$nresults));
		}
		else
		{
			// return iterator, calling ourself to return result in chunks
			$files['files'] = new groupdav_propfind_iterator($this,$path,$filter,$files['files']);
		}
		return true;
	}

	/**
	 * Callback for profind interator
	 *
	 * @param string $path
	 * @param array $filter
	 * @param array|boolean $start=false false=return all or array(start,num)
	 * @return array with "files" array with values for keys path and props
	 */
	function &propfind_callback($path,array $filter,$start=false)
	{
		$starttime = microtime(true);

		if (($address_data = $filter['address_data']))
		{
			$handler = self::_get_handler();
		}
		unset($filter['address_data']);
		if (isset($filter['order']))
		{
			$order = $filter['order'];
			unset($filter['order']);
		}
		else
		{
			$order = 'egw_addressbook.contact_id';
		}
		$files = array();
		// we query etag and modified, as LDAP does not have the strong sql etag
		$cols = array('id','uid','etag','modified','n_fn');
		if (!in_array(self::$path_attr,$cols)) $cols[] = self::$path_attr;
		if (($contacts =& $this->bo->search(array(),$cols,$order,'','',False,'AND',$start,$filter)))
		{
			foreach($contacts as &$contact)
			{
				$props = array(
					'getcontenttype' => HTTP_WebDAV_Server::mkprop('getcontenttype', 'text/vcard'),
					'getlastmodified' => $contact['modified'],
					'displayname' => $contact['n_fn'],
				);
				if ($address_data)
				{
					$content = $handler->getVCard($contact['id'],$this->charset,false);
					$props['getcontentlength'] = bytes($content);
					$props[] = HTTP_WebDAV_Server::mkprop(groupdav::CARDDAV,'address-data',$content,true);
				}
				$files[] = $this->add_resource($path, $contact, $props);
			}
		}
		// add groups after contacts
		if (!$start || count($contacts) < $start[1])
		{
			$where = array(
				'list_owner' => isset($filter['contact_owner'])?$filter['contact_owner']:array_keys($this->bo->grants)
			);
			if (isset($filter[self::$path_attr]))	// multiget report?
			{
				$where['list_'.self::$path_attr] = $filter[self::$path_attr];
			}
			//error_log(__METHOD__."() filter=".array2string($filter).', where='.array2string($where));
			if (($lists = $this->bo->read_lists($where,'contact_uid',true)))	// true = limit to contacts in same AB!
			{
				//_debug_array($lists);
				foreach($lists as $list)
				{
					$list['carddav_name'] = $list['list_carddav_name'];
					$props = array(
						'getcontenttype' => HTTP_WebDAV_Server::mkprop('getcontenttype', 'text/vcard'),
						'getlastmodified' => egw_time::to($list['list_modified'],'ts'),
						'displayname' => $list['list_name'],
						'getetag' => '"'.$list['list_id'].':'.$list['list_etag'].'"',
					);
					if ($address_data)
					{
						$content = $handler->getGroupVCard($list);
						$props['getcontentlength'] = bytes($content);
						$props[] = HTTP_WebDAV_Server::mkprop(groupdav::CARDDAV,'address-data',$content,true);
					}
					$files[] = $this->add_resource($path, $list, $props);
				}
			}
		}
		if ($this->debug) error_log(__METHOD__."($path,".array2string($filter).','.array2string($start).") took ".(microtime(true) - $starttime).' to return '.count($files).' items');
		return $files;
	}

	/**
	 * Process the filters from the CalDAV REPORT request
	 *
	 * @param array $options
	 * @param array &$cal_filters
	 * @param string $id
	 * @param int &$nresult on return limit for number or results or unchanged/null
	 * @return boolean true if filter could be processed
	 */
	function _report_filters($options,&$filters,$id, &$nresults)
	{
		if ($options['filters'])
		{
			/* Example of a complex filter used by Mac Addressbook
			  <B:filter test="anyof">
			    <B:prop-filter name="FN" test="allof">
			      <B:text-match collation="i;unicode-casemap" match-type="contains">becker</B:text-match>
			      <B:text-match collation="i;unicode-casemap" match-type="contains">ralf</B:text-match>
			    </B:prop-filter>
			    <B:prop-filter name="EMAIL" test="allof">
			      <B:text-match collation="i;unicode-casemap" match-type="contains">becker</B:text-match>
			      <B:text-match collation="i;unicode-casemap" match-type="contains">ralf</B:text-match>
			    </B:prop-filter>
			    <B:prop-filter name="NICKNAME" test="allof">
			      <B:text-match collation="i;unicode-casemap" match-type="contains">becker</B:text-match>
			      <B:text-match collation="i;unicode-casemap" match-type="contains">ralf</B:text-match>
			    </B:prop-filter>
			  </B:filter>
			*/
			$filter_test = isset($options['filters']['attrs']) && isset($options['filters']['attrs']['test']) ?
				$options['filters']['attrs']['test'] : 'anyof';
			$prop_filters = array();

			foreach($options['filters'] as $n => $filter)
			{
				if (!is_int($n)) continue;	// eg. attributes of filter xml element

				switch((string)$filter['name'])
				{
					case 'param-filter':
						error_log(__METHOD__."(...) param-filter='{$filter['attrs']['name']}' not (yet) implemented!");
						break;
					case 'prop-filter':	// can be multiple prop-filter, see example
						if ($matches) $prop_filters[] = implode($prop_test=='allof'?' AND ':' OR ',$matches);
						$matches = array();
						$prop_filter = strtoupper($filter['attrs']['name']);
						$prop_test = isset($filter['attrs']['test']) ? $filter['attrs']['test'] : 'anyof';
						if ($this->debug > 1) error_log(__METHOD__."(...) prop-filter='$prop_filter', test='$prop_test'");
						break;
					case 'is-not-defined':
						$matches[] = '('.$column."='' OR ".$column.' IS NULL)';
						break;
					case 'text-match':	// prop-filter can have multiple text-match, see example
						if (!isset($this->filter_prop2cal[$prop_filter]))	// eg. not existing NICKNAME in EGroupware
						{
							if ($this->debug || $prop_filter != 'NICKNAME') error_log(__METHOD__."(...) text-match: $prop_filter {$filter['attrs']['match-type']} '{$filter['data']}' unknown property '$prop_filter' --> ignored");
							$column = false;	// to ignore following data too
						}
						else
						{
							switch($filter['attrs']['collation'])	// todo: which other collations allowed, we are allways unicode
							{
								case 'i;unicode-casemap':
								default:
									$comp = ' '.$GLOBALS['egw']->db->capabilities[egw_db::CAPABILITY_CASE_INSENSITIV_LIKE].' ';
									break;
							}
							$column = $this->filter_prop2cal[strtoupper($prop_filter)];
							if (strpos($column, '_') === false) $column = 'contact_'.$column;
							if (!isset($filters['order'])) $filters['order'] = $column;
							$match_type = $filter['attrs']['match-type'];
							$negate_condition = isset($filter['attrs']['negate-condition']) && $filter['attrs']['negate-condition'] == 'yes';
						}
						break;
					case '':	// data of text-match element
						if (isset($filter['data']) && isset($column))
						{
							if ($column)	// false for properties not known to EGroupware
							{
								$value = str_replace(array('%', '_'), array('\\%', '\\_'), $filter['data']);
								switch($match_type)
								{
									case 'equals':
										$sql_filter = $column . $comp . $GLOBALS['egw']->db->quote($value);
										break;
									default:
									case 'contains':
										$sql_filter = $column . $comp . $GLOBALS['egw']->db->quote('%'.$value.'%');
										break;
									case 'starts-with':
										$sql_filter = $column . $comp . $GLOBALS['egw']->db->quote($value.'%');
										break;
									case 'ends-with':
										$sql_filter = $column . $comp . $GLOBALS['egw']->db->quote('%'.$value);
										break;
								}
								$matches[] = ($negate_condition ? 'NOT ' : '').$sql_filter;

								if ($this->debug > 1) error_log(__METHOD__."(...) text-match: $prop_filter $match_type' '{$filter['data']}'");
							}
							unset($column);
							break;
						}
						// fall through
					default:
						error_log(__METHOD__."(".array2string($options).",,$id) unknown filter=".array2string($filter).' --> ignored');
						break;
				}
			}
			if ($matches) $prop_filters[] = implode($prop_test=='allof'?' AND ':' OR ',$matches);
			if ($prop_filters)
			{
				$filters[] = $filter = '(('.implode($filter_test=='allof'?') AND (':') OR (', $prop_filters).'))';
				if ($this->debug) error_log(__METHOD__."($path,...) sql-filter: $filter");
			}
		}
		// parse limit from $options['other']
		/* Example limit
		  <B:limit>
		    <B:nresults>10</B:nresults>
		  </B:limit>
		*/
		foreach((array)$options['other'] as $option)
		{
			switch($option['name'])
			{
				case 'nresults':
					$nresults = (int)$option['data'];
					//error_log(__METHOD__."(...) options[other]=".array2string($options['other'])." --> nresults=$nresults");
					break;
				case 'limit':
					break;
				case 'href':
					break;	// from addressbook-multiget, handled below
				default:
					error_log(__METHOD__."(...) unknown xml: options[other]=".array2string($options['other']));
					break;
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
					if (($id = array_pop($parts)))
					{
						$ids[] = groupdav_handler::$path_extension ? basename($id,groupdav_handler::$path_extension) : $id;
					}
				}
			}
			if ($ids) $filters[self::$path_attr] = $ids;
			if ($this->debug) error_log(__METHOD__."(...) addressbook-multiget: ids=".implode(',',$ids));
		}
		elseif ($id)
		{
			$filters[self::$path_attr] = groupdav_handler::$path_extension ? basename($id,groupdav_handler::$path_extension) : $id;
		}
		return true;
	}

	/**
	 * Handle get request for an event
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user=null account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function get(&$options,$id,$user=null)
	{
		if (!is_array($contact = $this->_common_get_put_delete('GET',$options,$id)))
		{
			return $contact;
		}
		$handler = self::_get_handler();
		$options['data'] = $contact['list_id'] ? $handler->getGroupVCard($contact) :
			$handler->getVCard($contact['id'],$this->charset,false);
		// e.g. Evolution does not understand 'text/vcard'
		$options['mimetype'] = 'text/x-vcard; charset='.$this->charset;
		header('Content-Encoding: identity');
		header('ETag: "'.$this->get_etag($contact).'"');
		return true;
	}

	/**
	 * Handle put request for a contact
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user=null account_id of owner, default null
	 * @param string $prefix=null user prefix from path (eg. /ralf from /ralf/addressbook)
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function put(&$options,$id,$user=null,$prefix=null)
	{
		if ($this->debug) error_log(__METHOD__.'('.array2string($options).",$id,$user)");

		$oldContact = $this->_common_get_put_delete('PUT',$options,$id);
		if (!is_null($oldContact) && !is_array($oldContact))
		{
			if ($this->debug) error_log(__METHOD__."(,'$id', $user, '$prefix') returning ".array2string($oldContact));
			return $oldContact;
		}

		$handler = self::_get_handler();
		$vCard = htmlspecialchars_decode($options['content']);
		// Fix for Apple Addressbook
		$vCard = preg_replace('/item\d\.(ADR|TEL|EMAIL|URL)/', '\1', $vCard);
		$charset = null;
		if (!empty($options['content_type']))
		{
			$content_type = explode(';', $options['content_type']);
			if (count($content_type) > 1)
			{
				array_shift($content_type);
				foreach ($content_type as $attribute)
				{
					trim($attribute);
					list($key, $value) = explode('=', $attribute);
					switch (strtolower($key))
					{
						case 'charset':
							$charset = strtoupper(substr($value,1,-1));
					}
				}
			}
		}

		$contact = $handler->vcardtoegw($vCard, $charset);

		if (is_array($oldContact) || ($oldContact = $this->bo->read(array('contact_uid' => $contact['uid']))))
		{
			$contactId = $oldContact['id'];
			$retval = true;
		}
		else
		{
			// new entry
			$contactId = -1;
			$retval = '201 Created';
		}
		$is_group = $contact['##X-ADDRESSBOOKSERVER-KIND'] == 'group';
		if ($oldContact && $is_group !== isset($oldContact['list_id']))
		{
			throw new egw_exception_assertion_failed(__METHOD__."(,'$id',$user,'$prefix') can contact into group or visa-versa!");
		}

		if (!$is_group && is_array($contact['cat_id']))
		{
			$contact['cat_id'] = implode(',',$this->bo->find_or_add_categories($contact['cat_id'], $contactId));
		}
		elseif ($contactId > 0)
		{
			$contact['cat_id'] = $oldContact['cat_id'];
		}
		if (is_array($oldContact))
		{
			$contact['id'] = $oldContact['id'];
			// dont allow the client to overwrite certain values
			$contact['uid'] = $oldContact['uid'];
			$contact['owner'] = $oldContact['owner'];
			$contact['private'] = $oldContact['private'];
			$contact['carddav_name'] = $oldContact['carddav_name'];
		}
		else
		{
			$contact['carddav_name'] = $id;
		}
		// only set owner, if user is explicitly specified in URL (check via prefix, NOT for /addressbook/ !)
		if ($prefix)
		{
			// check for modified owners, if user has an add right for the new addressbook and
			// delete rights for the old addressbook (_common_get_put_delete checks for PUT only EGW_ACL_EDIT)
			if ($oldContact && $user != $oldContact['owner'] && !($this->bo->grants[$user] & EGW_ACL_ADD) &&
				(!$this->bo->grants[$oldContact['owner']] & EGW_ACL_DELETE))
			{
				if ($this->debug) error_log(__METHOD__."(,'$id', $user, '$prefix') returning '403 Forbidden'");
				return '403 Forbidden';
			}
			$contact['owner'] = $user;
		}
		if ($this->http_if_match) $contact['etag'] = self::etag2value($this->http_if_match);

		if (!($save_ok = $is_group ? $this->save_group($contact, $oldContact) : $this->bo->save($contact)))
		{
			if ($this->debug) error_log(__METHOD__."(,$id) save(".array2string($contact).") failed, Ok=$save_ok");
			if ($save_ok === 0)
			{
				return '412 Precondition Failed';
			}
			return '403 Forbidden';	// happens when writing new entries in AB's without ADD rights
		}

		if (!isset($contact['etag']))
		{
			$contact = $this->read($save_ok);
		}

		// we should not return an etag here, as we never store the PUT vcard byte-by-byte
		//header('ETag: "'.$this->get_etag($contact).'"');

		// send GroupDAV Location header only if we dont use carddav_name as path-attribute
		if ($retval !== true && self::$path_attr == 'id')
		{
			$path = preg_replace('|(.*)/[^/]*|', '\1/', $options['path']);
			header($h='Location: '.$this->base_uri.$path.self::get_path($contact));
			if ($this->debug) error_log(__METHOD__."($method,,$id) header('$h'): $retval");
		}
		if ($this->debug > 1) error_log(__METHOD__."(,'$id', $user, '$prefix') returning ".array2string($retval));
		return $retval;
	}

	/**
	 * Save distribition-list / group
	 *
	 * @param array $contact
	 * @param array|false $oldContact
	 * @param int|boolean $list_id or false on error
	 */
	function save_group(array $contact, $oldContact=null)
	{
		$data = array('list_name' => $contact['n_fn']);
		foreach(array('id','carddav_name','uid') as $name)
		{
			if ($name != self::$path_attr) $data['list_'.$name] = $contact[$name];
		}
		//error_log(__METHOD__.'('.array2string($contact).', '.array2string($oldContact).') data='.array2string($data));
		if (($list_id=$this->bo->add_list(array('list_'.self::$path_attr => $contact[self::$path_attr]),
			$contact['owner'], null, $data)))
		{
			// update members given in $contact['##X-ADDRESSBOOKSERVER-MEMBER']
			$new_members = $contact['##X-ADDRESSBOOKSERVER-MEMBER'];
			if ($new_members[1] == ':' && ($n = unserialize($new_members)))
			{
				$new_members = $n['values'];
			}
			else
			{
				$new_members = array($new_members);
			}
			foreach($new_members as &$uid) $uid = substr($uid,9);	// cut off "urn:uuid:" prefix

			if ($oldContact)
			{
				$to_add = array_diff($new_members,$oldContact['members']);
				$to_delete = array_diff($oldContact['members'],$new_members);
			}
			else
			{
				$to_add = $new_members;
			}
			//error_log('to_add='.array2string($to_add).', to_delete='.array2string($to_delete));
			if ($to_add || $to_delete)
			{
				$to_add_ids = $to_delete_ids = array();
				$filter = array('uid' => $to_delete ? array_merge($to_add, $to_delete) : $to_add);
				if (($contacts =& $this->bo->search(array(),'id,uid','','','',False,'AND',false,$filter)))
				{
					foreach($contacts as $contact)
					{
						if ($to_delete && in_array($contact['uid'], $to_delete))
						{
							$to_delete_ids[] = $contact['id'];
						}
						else
						{
							$to_add_ids[] = $contact['id'];
						}
					}
				}
				//error_log('to_add_ids='.array2string($to_add_ids).', to_delete_ids='.array2string($to_delete_ids));
				if ($to_add_ids) $this->bo->add2list($to_add_ids, $list_id, array());
				if ($to_delete_ids) $this->bo->remove_from_list($to_delete_ids, $list_id);
			}
		}
		if ($this->debug > 1) error_log(__METHOD__.'('.array2string($contact).', '.array2string($oldContact).') returning '.array2string($list_id));
		return $list_id;
	}

	/**
	 * Query ctag for addressbook
	 *
	 * @return string
	 */
	public function getctag($path,$user)
	{
		// not showing addressbook of a single user?
		if (!$user || $path == '/addressbook/') $user = null;

		return max($this->bo->get_ctag($user),$this->bo->lists_ctag($user));
	}

	/**
	 * Add the privileges of the current user
	 *
	 * @param array $props=array() regular props by the groupdav handler
	 * @return array
	 */
	static function current_user_privilege_set(array $props=array())
	{
		$props[] = HTTP_WebDAV_Server::mkprop(groupdav::DAV,'current-user-privilege-set',
			array(HTTP_WebDAV_Server::mkprop(groupdav::DAV,'privilege',
				array(
					HTTP_WebDAV_Server::mkprop(groupdav::DAV,'read',''),
					HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'read-free-busy',''),
					HTTP_WebDAV_Server::mkprop(groupdav::DAV,'read-current-user-privilege-set',''),
					HTTP_WebDAV_Server::mkprop(groupdav::DAV,'bind',''),
					HTTP_WebDAV_Server::mkprop(groupdav::DAV,'unbind',''),
					HTTP_WebDAV_Server::mkprop(groupdav::DAV,'schedule-post',''),
					HTTP_WebDAV_Server::mkprop(groupdav::DAV,'schedule-post-vevent',''),
					HTTP_WebDAV_Server::mkprop(groupdav::DAV,'schedule-respond',''),
					HTTP_WebDAV_Server::mkprop(groupdav::DAV,'schedule-respond-vevent',''),
					HTTP_WebDAV_Server::mkprop(groupdav::DAV,'schedule-deliver',''),
					HTTP_WebDAV_Server::mkprop(groupdav::DAV,'schedule-deliver-vevent',''),
					HTTP_WebDAV_Server::mkprop(groupdav::DAV,'write',''),
					HTTP_WebDAV_Server::mkprop(groupdav::DAV,'write-properties',''),
					HTTP_WebDAV_Server::mkprop(groupdav::DAV,'write-content',''),
				))));
		return $props;
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
	 * @param string $displayname
	 * @param string $base_uri=null base url of handler
	 * @param int $user=null account_id of owner of collection
	 * @return array
	 */
	public function extra_properties(array $props=array(), $displayname, $base_uri=null, $user=null)
	{
		if (!isset($props['addressbook-description']))
		{
			// default addressbook description: can be overwritten via PROPPATCH, in which case it's already set
			$props['addressbook-description'] = HTTP_WebDAV_Server::mkprop(groupdav::CARDDAV,'addressbook-description',$props['displayname']);
		}
		// setting an max image size, so iOS scales the images before transmitting them
		$props['max-image-size'] = HTTP_WebDAV_Server::mkprop(groupdav::CARDDAV,'max-image-size',4096);

		// supported reports (required property for CardDAV)
		$props['supported-report-set'] =	HTTP_WebDAV_Server::mkprop('supported-report-set',array(
			HTTP_WebDAV_Server::mkprop('supported-report',array(
				HTTP_WebDAV_Server::mkprop('report',array(
					HTTP_WebDAV_Server::mkprop(groupdav::CARDDAV,'addressbook-query',''))))),
			HTTP_WebDAV_Server::mkprop('supported-report',array(
				HTTP_WebDAV_Server::mkprop('report',array(
					HTTP_WebDAV_Server::mkprop(groupdav::CARDDAV,'addressbook-multiget',''))))),
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
		// Apple iOS or OS X addressbook
		if ($this->agent = 'cfnetwork' || $this->agent == 'dataaccess')
		{
			$supportedFields = $handler->supportedFields;
			// Apple Addressbook don't support CLASS
			unset($supportedFields['CLASS']);
			unset($supportedFields['CATEGORIES']);
			// use just CELL and IPHONE, CELL;WORK and CELL;HOME are NOT understood
			//'TEL;CELL;WORK'		=> array('tel_cell'),
			//'TEL;CELL;HOME'		=> array('tel_cell_private'),
			$supportedFields['TEL;CELL'] = array('tel_cell');
			unset($supportedFields['TEL;CELL;WORK']);
			$supportedFields['TEL;IPHONE'] = array('tel_cell_private');
			unset($supportedFields['TEL;CELL;HOME']);
		}
		$handler->setSupportedFields('GroupDAV',$this->agent, isset($supportedFields) ?
			$supportedFields : $handler->supportedFields);

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
		return true;
	}

	/**
	 * Read a contact
	 *
	 * We have to make sure to not return or even consider in read deleted contacts, as the might have
	 * the same UID and/or carddav_name as not deleted contacts and would block access to valid entries
	 *
	 * @param string|id $id
	 * @return array/boolean array with entry, false if no read rights, null if $id does not exist
	 */
	function read($id)
	{
		static $non_deleted_tids;
		if (is_null($non_deleted_tids))
		{
			$non_deleted_tids = $this->bo->content_types;
			unset($non_deleted_tids[addressbook_so::DELETED_TYPE]);
			$non_deleted_tids = array_keys($non_deleted_tids);
		}
		$contact = $this->bo->read(array(self::$path_attr => $id, 'tid' => $non_deleted_tids));

		// see if we have a distribution-list / group with that id
		// bo->read_list(..., true) limits returned uid to same owner's addressbook, as iOS and OS X addressbooks
		// only understands/shows that and if return more, save_lists would delete the others ones on update!
		if (!$contact && ($contact = $this->bo->read_lists(array('list_'.self::$path_attr => $id),'contact_uid',true)))
		{
			$contact = array_shift($contact);
			$contact['n_fn'] = $contact['n_family'] = $contact['list_name'];
			foreach(array('owner','id','carddav_name','modified','modifier','created','creator','etag','uid') as $name)
			{
				$contact[$name] = $contact['list_'.$name];
			}
		}
		elseif($contact === array())	// not found from read_lists()
		{
			$contact = null;
		}

		if ($contact && $contact['tid'] == addressbook_so::DELETED_TYPE)
		{
			$contact = null;	// handle deleted events, as not existing (404 Not Found)
		}
		if ($this->debug > 1) error_log(__METHOD__."('$id') returning ".array2string($contact));
		return $contact;
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

	/**
	 * Return calendars/addressbooks shared from other users with the current one
	 *
	 * return array account_id => account_lid pairs
	 */
	function get_shared()
	{
		$shared = array();
		$addressbook_home_set = $GLOBALS['egw_info']['user']['preferences']['groupdav']['addressbook-home-set'];
		if (empty($addressbook_home_set)) $addressbook_home_set = 'P';	// personal addressbook
		$addressbook_home_set = explode(',',$addressbook_home_set);
		// replace symbolic id's with real nummeric id's
		foreach(array(
			'G' => $GLOBALS['egw_info']['user']['account_primary_group'],
			'U' => '0',
		) as $sym => $id)
		{
			if (($key = array_search($sym, $addressbook_home_set)) !== false)
			{
				$addressbook_home_set[$key] = $id;
			}
		}
		foreach($this->bo->get_addressbooks(EGW_ACL_READ) as $id => $label)
		{
			if (($id || !$GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts']) &&
				$user != $id &&	// no current user and no accounts, if disabled in ab prefs
				(in_array('A',$addressbook_home_set) || in_array((string)$id,$addressbook_home_set)) &&
				is_numeric($id) && ($owner = $id ? $this->accounts->id2name($id) : 'accounts'))
			{
				$shared[$id] = $owner;
			}
		}
		return $shared;
	}
}
