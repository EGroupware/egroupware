<?php
/**
 * eGroupWare: GroupDAV access
 *
 * Using the PEAR HTTP/WebDAV/Server class (which need to be installed!)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007/8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

require_once('HTTP/WebDAV/Server.php');

/**
 * eGroupWare: GroupDAV access
 *
 * Using the PEAR HTTP/WebDAV/Server class (which need to be installed!)
 *
 * @link http://www.groupdav.org GroupDAV spec
 */
class groupdav extends HTTP_WebDAV_Server
{
	/**
	 * GroupDAV namespace
	 */
	const GROUPDAV = 'http://groupdav.org/';
	/**
	 * CalDAV namespace
	 */
	const CALDAV = 'urn:ietf:params:xml:ns:caldav';
	/**
	 * CardDAV namespace
	 */
	const CARDDAV = 'urn:ietf:params:xml:ns:carddav';
	/**
	 * Realm and powered by string
	 */
	const REALM = 'eGroupWare CalDAV/CardDAV/GroupDAV server';

	var $dav_powered_by = self::REALM;
	var $http_auth_realm = self::REALM;

	var $root = array(
		'calendar' => array(
			'resourcetype' => array(self::GROUPDAV => 'vevent-collection', self::CALDAV => 'calendar'),
			'component-set' => array(self::GROUPDAV => 'VEVENT'),
		),
		'addressbook' => array(
			'resourcetype' => array(self::GROUPDAV => 'vcard-collection', self::CARDDAV => 'addressbook'),
			'component-set' => array(self::GROUPDAV => 'VCARD'),
		),
		'infolog' => array(
			'resourcetype' => array(self::GROUPDAV => 'vtodo-collection'),
			'component-set' => array(self::GROUPDAV => 'VTODO'),
		),
	);
	/**
	 * Debug level: 0 = nothing, 1 = function calls, 2 = more info, 3 = complete $_SERVER array
	 *
	 * The debug messages are send to the apache error_log
	 *
	 * @var integer
	 */
	var $debug = 0;

	/**
	 * eGW's charset
	 *
	 * @var string
	 */
	var $egw_charset;
	/**
	 * Reference to the translation class
	 *
	 * @var translation
	 */
	var $translation;
	/**
	 * Instance of our application specific handler
	 *
	 * @var groupdav_handler
	 */
	var $handler;

	function __construct()
	{
		if ($this->debug > 2) error_log('groupdav: $_SERVER='.array2string($_SERVER));

		// identify clients, which do NOT support path AND full url in <D:href> of PROPFIND request
		switch(groupdav_handler::get_agent())
		{
			case 'kde':	// KAddressbook (at least in 3.5 can NOT subscribe / does NOT find addressbook)
				$this->client_require_href_as_url = true;
				break;
			case 'davkit':	// iCal app in OS X 10.6 created wrong request, if full url given
				$this->client_require_href_as_url = false;
				break;
		}
		parent::HTTP_WebDAV_Server();

		$this->translation =& $GLOBALS['egw']->translation;
		$this->egw_charset = $this->translation->charset();
	}

	/**
	 * get the handler for $app
	 *
	 * @param string $app
	 * @return groupdav_handler
	 */
	function app_handler($app)
	{
		return groupdav_handler::app_handler($app,$this->debug,$this->base_uri);
	}

	/**
	 * OPTIONS request, allow to modify the standard responses from the pear-class
	 *
	 * @param string $path
	 * @param array &$dav
	 * @param array &$allow
	 */
	function OPTIONS($path, &$dav, &$allow)
	{
		list(,$app) = explode('/',$path);
		switch($app)
		{
			case 'calendar':
				$dav[] = 'calendar-access';
				break;
			case 'addressbook':
				$dav[] = 'addressbook';
				break;
		}
		// not yet implemented: $dav[] = 'access-control';
	}

	/**
	 * PROPFIND and REPORT method handler
	 *
	 * @param  array  general parameter passing array
	 * @param  array  return array for file properties
	 * @return bool   true on success
	 */
	function PROPFIND(&$options, &$files,$method='PROPFIND')
	{
		if ($this->debug) error_log(__CLASS__."::$method(".array2string($options,true).')');

		// parse path in form [/account_lid]/app[/more]
		if (!self::_parse_path($options['path'],$id,$app,$user) && $app && !$user)
		{
			if ($this->debug > 1) error_log(__CLASS__."::$method: user=$user, app=$app, id=$id: 404 not found!");
			return '404 Not Found';


		}
		if ($this->debug > 1) error_log(__CLASS__."::$method: user=$user, app='$app', id=$id");

		$files = array('files' => array());

		if (!$app)	// root folder containing apps
		{
			// self url
			$files['files'][] = array(
				'path'  => '/',
				'props' => array(
					self::mkprop('displayname','EGroupware (Cal|Card|Group)DAV server'),
					self::mkprop('resourcetype','collection'),
					// adding the calendar extra property (calendar-home-set, etc.) here, allows apple iCal to "autodetect" the URL
					self::mkprop(groupdav::CALDAV,'calendar-home-set',$this->base_uri.'/calendar/'),
				),
			);
			if ($options['depth'])
			{
				// principals collection
				$files['files'][] = array(
	            	'path'  => '/principals/',
	            	'props' => array(
	            		self::mkprop('displayname',lang('Accounts')),
	            		self::mkprop('resourcetype','collection'),
					),
	            );
				// groups collection
				$files['files'][] = array(
	            	'path'  => '/groups/',
	            	'props' => array(
	            		self::mkprop('displayname',lang('Groups')),
	            		self::mkprop('resourcetype','collection'),
					),
	            );

				foreach($this->root as $app => $data)
				{
					if (!$GLOBALS['egw_info']['user']['apps'][$app]) continue;	// no rights for the given app

					$files['files'][] = array(
		            	'path'  => '/'.$app.'/',
		            	'props' => $this->_properties($app),
		            );
				}
			}
			return true;
		}
		if (!in_array($app,array('principals','groups')) && !$GLOBALS['egw_info']['user']['apps'][$app])
		{
			if ($this->debug) error_log(__CLASS__."::$method(path=$options[path]) 403 Forbidden: no app rights for '$app'");
			return "403 Forbidden: no app rights for '$app'";	// no rights for the given app
		}
		if (($handler = self::app_handler($app)))
		{
			if ($method != 'REPORT' && !$id)	// no self URL for REPORT requests (only PROPFIND) or propfinds on an id
			{
				$files['files'][] = array(
		        	'path'  => '/'.$app.'/',
					// KAddressbook doubles the folder, if the self URL contains the GroupDAV/CalDAV resourcetypes
		        	'props' => $this->_properties($app,$app=='addressbook'&&strpos($_SERVER['HTTP_USER_AGENT'],'KHTML') !== false),
		        );
			}
			if (!$options['depth'] && !$id)
			{
				return true;	// depth 0 --> show only the self url
			}
			return $handler->propfind($options['path'],$options,$files,$user,$id);
		}
		return '501 Not Implemented';
	}

	/**
	 * Get the properties of a collection
	 *
	 * @param string $app
	 * @param boolean $no_extra_types=false should the GroupDAV and CalDAV types be added (KAddressbook has problems with it in self URL)
	 * @return array of DAV properties
	 */
	function _properties($app,$no_extra_types=false)
	{
		$props = array(
    		self::mkprop('displayname',$this->translation->convert(lang($app),$this->egw_charset,'utf-8')),
 		);
		foreach($this->root[$app] as $prop => $values)
		{
			if ($prop == 'resourcetype')
			{
				$resourcetype = array(
					self::mkprop('collection','collection'),
				);
				if (!$no_extra_types)
				{
					foreach($this->root[$app]['resourcetype'] as $ns => $type)
					{
						$resourcetype[] = self::mkprop($ns,'resourcetype', $type);
					}
				}
				$props[] = self::mkprop('resourcetype',$resourcetype);
			}
			else
			{
				foreach($values as $ns => $value)
				{
					$props[] = self::mkprop($ns,$prop,$value);
				}
			}
		}
		if (method_exists($app.'_groupdav','extra_properties'))
		{
			$props = ExecMethod($app.'_groupdav::extra_properties',$props);
		}
		return $props;
	}

	/**
	 * CalDAV/CardDAV REPORT method handler
	 *
	 * just calls PROPFIND()
	 *
	 * @param  array  general parameter passing array
	 * @param  array  return array for file properties
	 * @return bool   true on success
	 */
	function REPORT(&$options, &$files)
	{
		if ($this->debug > 1) error_log(__METHOD__.'('.array2string($options).')');

		return $this->PROPFIND($options,$files,'REPORT');
	}

	/**
	 * CalDAV/CardDAV REPORT method handler to get HTTP_WebDAV_Server to process REPORT requests
	 *
	 * Just calls http_PROPFIND()
	 */
	function http_REPORT()
	{
		parent::http_PROPFIND('REPORT');
	}

	/**
	 * GET method handler
	 *
	 * @param  array $options parameter passing array
	 * @return bool   true on success
	 */
	function GET(&$options)
	{
		if ($this->debug) error_log(__METHOD__.'('.array2string($options).')');

		if (!$this->_parse_path($options['path'],$id,$app,$user))
		{
			return $this->autoindex($options);

			error_log(__METHOD__."(".array2string($options).") 404 Not Found");
			return '404 Not Found';
		}
		if (($handler = self::app_handler($app)))
		{
			return $handler->get($options,$id);
		}
		error_log(__METHOD__."(".array2string($options).") 501 Not Implemented");
		return '501 Not Implemented';
	}

	/**
	 * Display an automatic index (listing and properties) for a collection
	 *
	 * @param array $options parameter passing array, index "path" contains requested path
	 */
	protected function autoindex($options)
	{
		$propfind_options = array(
			'path'  => $options['path'],
			'depth' => 1,
		);
		$files = array();
		if (($ret = $this->PROPFIND($propfind_options,$files)) !== true)
		{
			return $ret;	// no collection
		}
		header('Content-type: text/html; charset='.$GLOBALS['egw']->translation->charset());
		echo "<html>\n<head>\n\t<title>".'EGroupware (Cal|Card|Group)DAV server '.htmlspecialchars($options['path'])."</title>\n";
		echo "\t<meta http-equiv='content-type' content='text/html; charset=utf-8' />\n";
		echo "\t<style type='text/css'>\n.th { background-color: #e0e0e0; }\n.row_on { background-color: #F1F1F1; }\n".
			".row_off { background-color: #ffffff; }\ntd { padding-left: 5px; }\nth { padding-left: 5px; text-align: left; }\n\t</style>\n";
		echo "</head>\n<body>\n";

		echo '<h1>(Cal|Card|Group)DAV ';
		$path = '/groupdav.php';
		foreach(explode('/',substr($options['path'],0,-1)) as $n => $name)
		{
			$path .= ($n != 1 ? '/' : '').$name;
			echo html::a_href(htmlspecialchars($name.'/'),$path.($n ? '/' : ''));
		}
		echo "</h1>\n";
		$collection_props = self::props2array($files['files'][0]['props']);
		echo '<h3>'.lang('Collection listing').': '.htmlspecialchars($collection_props['DAV:displayname'])."</h3>\n";
		//_debug_array($files['files']);

		if (count($files['files']) <= 1)
		{
			echo '<p>'.lang('Collection empty.')."</p>\n";
		}
		else
		{
			echo "<table>\n\t<tr class='th'><th>".lang('Name')."</th><th>".lang('Size')."</th><th>".lang('Last modified')."</th><th>".
				lang('ETag')."</th><th>".lang('Content type')."</th><th>".lang('Resource type')."</th></tr>\n";

			foreach($files['files'] as $n => $file)
			{
				if (!$n) continue;	// own entry --> displaying properies later

				$props = self::props2array($file['props']);
				//echo $file['path']; _debug_array($props);
				$class = $class == 'row_on' ? 'row_off' : 'row_on';
				if (substr($file['path'],-1) == '/')
				{
					$name = basename(substr($file['path'],0,-1)).'/';
				}
				else
				{
					$name = basename($file['path']);
				}
				echo "\t<tr class='$class'>\n\t\t<td>".html::a_href(htmlspecialchars($name),'/groupdav.php'.$file['path'])."</td>\n";
				echo "\t\t<td>".$props['DAV:getcontentlength']."</td>\n";
				echo "\t\t<td>".(!empty($props['DAV:getlastmodified']) ? date('Y-m-d H:i:s',$props['DAV:getlastmodified']) : '')."</td>\n";
				echo "\t\t<td>".$props['DAV:getetag']."</td>\n";
				echo "\t\t<td>".htmlspecialchars($props['DAV:getcontenttype'])."</td>\n";
				echo "\t\t<td>".self::prop_value($props['DAV:resourcetype'])."</td>\n\t</tr>\n";
			}
			echo "</table>\n";
		}
		echo '<h3>'.lang('Properties')."</h3>\n";
		echo "<table>\n\t<tr class='th'><th>".lang('Namespace')."</th><th>".lang('Name')."</th><th>".lang('Value')."</th></tr>\n";
		foreach($collection_props as $name => $value)
		{
			$class = $class == 'row_on' ? 'row_off' : 'row_on';
			$ns = explode(':',$name);
			$name = array_pop($ns);
			$ns = implode(':',$ns);
			echo "\t<tr class='$class'>\n\t\t<td>".htmlspecialchars($ns)."</td><td>".htmlspecialchars($name)."</td>\n";
			echo "\t\t<td>".self::prop_value($value)."</td>\n\t</tr>\n";
		}
		echo "</table>\n";

		echo "</body>\n</html>\n";

		common::egw_exit();
	}

	/**
	 * Format a property value for output
	 *
	 * @param mixed $value
	 * @return string
	 */
	protected static function prop_value($value)
	{
		if (is_array($value))
		{
			if (isset($value[0]['ns']))
			{
				$value = self::props2array($value);
			}
			$value = htmlspecialchars(array2string($value));
		}
		elseif (preg_match('/^https?:\/\//',$value))
		{
			$value = html::a_href($value,$value);
		}
		else
		{
			$value = htmlspecialchars($value);
		}
		return $value;
	}

	/**
	 * Return numeric indexed array with values for keys 'ns', 'name' and 'val' as array 'ns:name' => 'val'
	 *
	 * @param array $props
	 * @return array
	 */
	protected static function props2array(array $props)
	{
		$arr = array();
		foreach($props as $prop)
		{
			switch($prop['ns'])
			{
				case 'DAV:';
					$ns = 'DAV';
					break;
				case self::CALDAV:
					$ns = 'CalDAV';
					break;
				case self::CARDDAV:
					$ns = 'CardDAV';
					break;
				case self::GROUPDAV:
					$ns = 'GroupDAV';
					break;
				default:
					$ns = $prop['ns'];
			}
			$arr[$ns.':'.$prop['name']] = $prop['val'];
		}
		return $arr;
	}

	/**
	 * PUT method handler
	 *
	 * @param  array  parameter passing array
	 * @return bool   true on success
	 */
	function PUT(&$options)
	{
		// read the content in a string, if a stream is given
		if (isset($options['stream']))
		{
			$options['content'] = '';
			while(!feof($options['stream']))
			{
				$options['content'] .= fread($options['stream'],8192);
			}
		}
		if ($this->debug) error_log(__METHOD__.'('.array2string($options).')');

		if (!$this->_parse_path($options['path'],$id,$app,$user))
		{
			return '404 Not Found';
		}
		if (($handler = self::app_handler($app)))
		{
			$status = $handler->put($options,$id,$user);
			// set default stati: true --> 204 No Content, false --> should be already handled
			if (is_bool($status)) $status = $status ? '204 No Content' : '400 Something went wrong';
			return $status;
		}
		return '501 Not Implemented';
	}

	/**
	 * DELETE method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function DELETE($options)
	{
		if ($this->debug) error_log(__METHOD__.'('.array2string($options).')');

		if (!$this->_parse_path($options['path'],$id,$app,$user))
		{
			return '404 Not Found';
		}
		if (($handler = self::app_handler($app)))
		{
			$status = $handler->delete($options,$id);
			// set default stati: true --> 204 No Content, false --> should be already handled
			if (is_bool($status)) $status = $status ? '204 No Content' : '400 Something went wrong';
			return $status;
		}
		return '501 Not Implemented';
	}

	/**
	 * MKCOL method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function MKCOL($options)
	{
		if ($this->debug) error_log(__METHOD__.'('.array2string($options).')');

		return '501 Not Implemented';
	}

	/**
	 * MOVE method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function MOVE($options)
	{
		if ($this->debug) error_log(__METHOD__.'('.array2string($options).')');

		return '501 Not Implemented';
	}

	/**
	 * COPY method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function COPY($options, $del=false)
	{
		if ($this->debug) error_log('groupdav::'.($del ? 'MOVE' : 'COPY').'('.array2string($options).')');

		return '501 Not Implemented';
	}

	/**
	 * LOCK method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function LOCK(&$options)
	{
		self::_parse_path($options['path'],$id,$app,$user);
		$path = egw_vfs::app_entry_lock_path($app,$id);

		if ($this->debug) error_log(__METHOD__.'('.array2string($options).") path=$path");

		// get the app handler, to check if the user has edit access to the entry (required to make locks)
		$handler = self::app_handler($app);

		// TODO recursive locks on directories not supported yet
		if (!$id || !empty($options['depth']) || !$handler->check_access(EGW_ACL_EDIT,$id))
		{
			return '409 Conflict';
		}
		$options['timeout'] = time()+300; // 5min. hardcoded

		// dont know why, but HTTP_WebDAV_Server passes the owner in D:href tags, which get's passed unchanged to checkLock/PROPFIND
		// that's wrong according to the standard and cadaver does not show it on discover --> strip_tags removes eventual tags
		if (($ret = egw_vfs::lock($path,$options['locktoken'],$options['timeout'],strip_tags($options['owner']),
			$options['scope'],$options['type'],isset($options['update']),false)) && !isset($options['update']))		// false = no ACL check
		{
			return $ret ? '200 OK' : '409 Conflict';
		}
		return $ret;
	}

	/**
	 * UNLOCK method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function UNLOCK(&$options)
	{
		self::_parse_path($options['path'],$id,$app,$user);
		$path = egw_vfs::app_entry_lock_path($app,$id);

		if ($this->debug) error_log(__METHOD__.'('.array2string($options).") path=$path");
		return egw_vfs::unlock($path,$options['token']) ? '204 No Content' : '409 Conflict';
	}

	/**
	 * checkLock() helper
	 *
	 * @param  string resource path to check for locks
	 * @return bool   true on success
	 */
	function checkLock($path)
	{
		self::_parse_path($path,$id,$app,$user);
		$path = egw_vfs::app_entry_lock_path($app,$id);

		return egw_vfs::checkLock($path);
	}

	/**
	 * Parse a path into it's id, app and user parts
	 *
	 * @param string $path
	 * @param int &$id
	 * @param string &$app addressbook, calendar, infolog (=infolog)
	 * @param int &$user
	 * @return boolean true on success, false on error
	 */
	function _parse_path($path,&$id,&$app,&$user)
	{
		if ($this->debug) error_log(__METHOD__." called with ('$path') id=$id, app='$app', user=$user");
		$parts = explode('/',$path);
		list($id) = explode('.',array_pop($parts));		// remove evtl. .ics extension

		$app = array_pop($parts);

		if (($user = array_pop($parts)))
		{
			$user = $GLOBALS['egw']->accounts->name2id($user,'account_lid',$app != 'addressbook' ? 'u' : null);
		}
		else
		{
			$user = $GLOBALS['egw_info']['user']['account_id'];
		}
		if (!($ok = $id && in_array($app,array('addressbook','calendar','infolog','principals','groups')) && $user))
		{
			if ($this->debug) error_log(__METHOD__."('$path') returning false: id=$id, app='$app', user=$user");
		}
		return $ok;
	}
}
