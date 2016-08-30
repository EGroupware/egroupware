<?php
/**
 * eGroupWare API: VFS - WebDAV access using the new stream wrapper VFS interface
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage webdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Hartmut Holzgraefe <hartmut@php.net> original HTTP/WebDAV/Server/Filesystem class, of which some code is used
 * @version $Id$
 */

namespace EGroupware\Api\Vfs;

require_once dirname(__DIR__).'/WebDAV/Server/Filesystem.php';

use HTTP_WebDAV_Server_Filesystem;
use HTTP_WebDAV_Server;
use EGroupware\Api\Vfs;
use EGroupware\Api;

/**
 * FileManger - WebDAV access using the new stream wrapper VFS interface
 *
 * Using modified PEAR HTTP/WebDAV/Server/Filesystem class in API dir
 */
class WebDAV extends HTTP_WebDAV_Server_Filesystem
{
	/**
	 * Realm of eGW's WebDAV server
	 *
	 */
	const REALM = 'eGroupWare WebDAV server';
	var $dav_powered_by = self::REALM;
	var $http_auth_realm = self::REALM;

	/**
	 * Base directory is the URL of our VFS root
	 *
	 * @var string
	 */
	var $base = Vfs::PREFIX;

	/**
	 * Debug level: 0 = nothing, 1 = function calls, 2 = more info, eg. complete $_SERVER array
	 *
	 * The debug messages are send to the apache error_log
	 *
	 * @var integer
	 */
	var $debug = 0;

	/**
	* Serve a webdav request
	*
	* Reimplemented to not check our vfs base path with realpath and connect to mysql DB
	*
	* @access public
    * @param  $prefix =null prefix filesystem path with given path, eg. "/webdav" for owncloud 4.5 remote.php
	*/
	function ServeRequest($prefix=null)
	{
		// special treatment for litmus compliance test
		// reply on its identifier header
		// not needed for the test itself but eases debugging
		if (isset($this->_SERVER['HTTP_X_LITMUS'])) {
			error_log("Litmus test ".$this->_SERVER['HTTP_X_LITMUS']);
			header("X-Litmus-reply: ".$this->_SERVER['HTTP_X_LITMUS']);
		}
		// let the base class do all the work
		HTTP_WebDAV_Server::ServeRequest($prefix);
	}

	/**
	* DELETE method handler
	*
	* @param  array  general parameter passing array
	* @return bool   true on success
	*/
	function DELETE($options)
	{
		$path = $this->base . $options['path'];

		if (!file_exists($path))
		{
			return '404 Not found';
		}

		if (is_dir($path))
		{
			// recursive delete the directory
			try {
				$deleted = Vfs::remove($options['path']);
				$ret = !empty($deleted[$options['path']]);
				//error_log(__METHOD__."() Vfs::remove($options[path]) returned ".array2string($deleted)." --> ".array2string($ret));
			}
			catch (Exception\ProtectedDirectory $e) {
				return '403 Forbidden: '.$e->getMessage();
			}
		}
		else
		{
			$ret = unlink($path);
		}
		if (!$ret)
		{
			return '403 Forbidden';
		}
		return '204 No Content';
	}

    /**
     * MKCOL method handler
     *
     * Reimplemented to NOT use dirname/basename, which has problems with utf-8 chars
     *
     * @param  array  general parameter passing array
     * @return bool   true on success
     */
    function MKCOL($options)
    {
        $path   = $this->_unslashify($this->base .$options["path"]);
        $parent = Vfs::dirname($path);

        if (!file_exists($parent)) {
            return "409 Conflict";
        }

        if (!is_dir($parent)) {
            return "403 Forbidden";
        }

        if ( file_exists($path) ) {
            return "405 Method not allowed";
        }

        if (!empty($this->_SERVER["CONTENT_LENGTH"])) { // no body parsing yet
            return "415 Unsupported media type";
        }

        $stat = mkdir($path, 0777);
        if (!$stat) {
            return "403 Forbidden";
        }

        return ("201 Created");
    }

	/**
     * COPY method handler
     *
     * @param  array  general parameter passing array
     * @return bool   true on success
     */
    function COPY($options, $del=false)
    {
        // TODO Property updates still broken (Litmus should detect this?)

        if (!empty($this->_SERVER["CONTENT_LENGTH"])) { // no body parsing yet
            return "415 Unsupported media type";
        }

        // no copying to different WebDAV Servers yet
        if (isset($options["dest_url"])) {
            return "502 bad gateway";
        }

        $source = $this->base .$options["path"];
        if (!file_exists($source)) return "404 Not found";

        if (is_dir($source)) { // resource is a collection
            switch ($options["depth"]) {
            case "infinity": // valid
                break;
            case "0": // valid for COPY only
                if ($del) { // MOVE?
                    return "400 Bad request";
                }
                break;
            case "1": // invalid for both COPY and MOVE
            default:
                return "400 Bad request";
            }
        }

        $dest         = $this->base . $options["dest"];
        $destdir      = dirname($dest);

        if (!file_exists($destdir) || !is_dir($destdir)) {
            return "409 Conflict";
        }

        $new          = !file_exists($dest);
        $existing_col = false;

        if (!$new) {
            if ($del && is_dir($dest)) {
                if (!$options["overwrite"]) {
                    return "412 precondition failed";
                }
                $dest .= basename($source);
                if (file_exists($dest)) {
                    $options["dest"] .= basename($source);
                } else {
                    $new          = true;
                    $existing_col = true;
                }
            }
        }

        if (!$new) {
            if ($options["overwrite"]) {
                $stat = $this->DELETE(array("path" => $options["dest"]));
                if (($stat{0} != "2") && (substr($stat, 0, 3) != "404")) {
                    return $stat;
                }
            } else {
                return "412 precondition failed";
            }
        }

        if ($del) {
			try {
				if (!rename($source, $dest)) {
					return "500 Internal server error";
				}
			}
			catch (Exception\ProtectedDirectory $e) {
				return "403 Forbidden: ".$e->getMessage();
			}
        } else {
            if (is_dir($source) && $options['depth'] == 'infinity') {
            	$files = Vfs::find($source,array('depth' => true,'url' => true));	// depth=true: return dirs first, url=true: allow urls!
            } else {
                $files = array($source);
            }

            if (!is_array($files) || empty($files)) {
                return "500 Internal server error";
            }


            foreach ($files as $file) {
                if (is_dir($file)) {
                    $file = $this->_slashify($file);
                }

                $destfile = str_replace($source, $dest, $file);

                if (is_dir($file)) {
                    if (!is_dir($destfile)) {
                        // TODO "mkdir -p" here? (only natively supported by PHP 5)
                        if (!@mkdir($destfile)) {
                            return "409 Conflict";
                        }
                    }
                } else {
                    if (!@copy($file, $destfile)) {
                        return "409 Conflict";
                    }
                }
            }
        }
        // adding Location header as shown in example in rfc2518 section 8.9.5
		header('Location: '.$this->base_uri.$options['dest']);

        return ($new && !$existing_col) ? "201 Created" : "204 No Content";
    }

    /**
	* Get properties for a single file/resource
	*
	* @param  string  $_path resource path
	* @return array   resource properties
	*/
	function fileinfo($_path)
	{
		// internally we require some url-encoding, as vfs_stream_wrapper uses URL's internally
		$path = str_replace(array('#','?'),array('%23','%3F'),$_path);

		//error_log(__METHOD__."($path)");
		// map URI path to filesystem path
		$fspath = $this->base . $path;

		// create result array
		$info = array();
		// TODO remove slash append code when base class is able to do it itself
		$info['path']  = is_dir($fspath) ? $this->_slashify($path) : $path;

		// remove all urlencoding we need internally in EGw, HTTP_WebDAV_Server will add it's own!
		// rawurldecode does NOT touch +
		$info['path'] = rawurldecode($info['path']);

		$info['props'] = array();

		// no special beautified displayname here ...
		$info['props'][] = self::mkprop	('displayname', Vfs::basename(self::_unslashify($info['path'])));

		// creation and modification time
		$info['props'][] = self::mkprop	('creationdate',    filectime($fspath));
		$info['props'][] = self::mkprop	('getlastmodified', filemtime($fspath));

        // Microsoft extensions: last access time and 'hidden' status
        $info["props"][] = self::mkprop("lastaccessed",    fileatime($fspath));
        $info["props"][] = self::mkprop("ishidden",        Vfs::is_hidden($fspath));

		// type and size (caller already made sure that path exists)
		if (is_dir($fspath)) {
			// directory (WebDAV collection)
			$info['props'][] = self::mkprop	('resourcetype', array(
			 	self::mkprop('collection', '')));
			$info['props'][] = self::mkprop	('getcontenttype', 'httpd/unix-directory');
		} else {
			// plain file (WebDAV resource)
			$info['props'][] = self::mkprop	('resourcetype', '');
			if (Vfs::is_readable($path)) {
				$info['props'][] = self::mkprop	('getcontenttype', Vfs::mime_content_type($path));
			} else {
				error_log(__METHOD__."($path) $fspath is not readable!");
				$info['props'][] = self::mkprop	('getcontenttype', 'application/x-non-readable');
			}
			$info['props'][] = self::mkprop	('getcontentlength', filesize($fspath));
		}
		// generate etag from inode (sqlfs: fs_id), modification time and size
		$stat = stat($fspath);
		$info['props'][] = self::mkprop('getetag', '"'.$stat['ino'].':'.$stat['mtime'].':'.$stat['size'].'"');

/*		returning the supportedlock property causes Windows DAV provider and Konqueror to not longer work
		ToDo: return it only if explicitly requested ($options['props'])
		// supportedlock property
		$info['props'][] = self::mkprop('supportedlock','
      <D:lockentry>
       <D:lockscope><D:exclusive/></D:lockscope>
       <D:locktype><D:write/></D:lockscope>
      </D:lockentry>
      <D:lockentry>
       <D:lockscope><D:shared/></D:lockscope>
       <D:locktype><D:write/></D:lockscope>
      </D:lockentry>');
*/
		//error_log(__METHOD__."($path) info=".array2string($info));
		return $info;
	}

	/**
	 * Which regular properties should be copied to different namespaces and names,
	 * because PROPPATCH stores them not as properties under their namespace and name,
	 * but simply sets the standard stat values instead.
	 *
	 * @var array stat-attr => array(array('ns'=>namespace, 'name'=>attribute-name)[, ...])
	 */
	public static $auto_props = array(
		'mtime' => array(
			array('ns' => 'urn:schemas-microsoft-com:', 'name' => 'Win32LastModifiedTime'),
			array('ns' => 'http://www.southrivertech.com/', 'name' => 'srt_modifiedtime'),
			array('ns' => 'http://www.southrivertech.com/', 'name' => 'getlastmodified'),
		),
		'ctime' => array(
			// no streamwrapper interface / php function to set the ctime currently
			//array('ns' => 'urn:schemas-microsoft-com:', 'name' => 'Win32CreationTime'),
			//array('ns' => 'http://www.southrivertech.com/', 'name' => 'srt_creationtime'),
		),
	);

	/**
	 * PROPFIND method handler
	 *
	 * Reimplemented to fetch all extra property of a PROPFIND request in one go.
	 *
	 * @param  array  general parameter passing array
	 * @param  array  return array for file properties
	 * @return bool   true on success
	 */
	function PROPFIND(&$options, &$files)
	{
		if (!parent::PROPFIND($options,$files))
		{
			return false;
		}
		$path2n = array();
		foreach($files['files'] as $n => $info)
		{
			// do NOT report /clientsync/.favorites/, as it fails
			if (strpos($info['path'],'/clientsync/.favorites/') === 0)
			{
				unset($files['files'][$n]);
				continue;
			}
			$_path = $info['path'];
			if (!$n && $info['path'] != '/' && substr($info['path'],-1) == '/') $_path = substr($info['path'],0,-1);

			// need to encode path again, as $info['path'] is NOT encoded, but Vfs::(stat|propfind) require it
			// otherwise pathes containing url special chars like ? or # will not stat
			$path = Vfs::encodePath($_path);
			$path2n[$path] = $n;

			// adding some properties used instead of regular DAV times
			if (($stat = Vfs::stat($path)))
			{
				$fileprops =& $files['files'][$path2n[$path]]['props'];
				foreach(self::$auto_props as $attr => $props)
				{
					switch($attr)
					{
						case 'ctime':
						case 'mtime':
						case 'atime':
							$value = gmdate('D, d M Y H:i:s T',$stat[$attr]);
							break;

						default:
							continue 2;
					}
					foreach($props as $prop)
					{
						$prop['val'] = $value;
						$fileprops[] = $prop;
					}
				}
			}
		}
		if ($path2n && ($path2props = Vfs::propfind(array_keys($path2n),null)))
		{
			foreach($path2props as $path => $props)
			{
				$fileprops =& $files['files'][$path2n[$path]]['props'];
				foreach($props as $prop)
				{
					if ($prop['ns'] == Vfs::DEFAULT_PROP_NAMESPACE && $prop['name'][0] == '#')	// eGW's customfields
					{
						$prop['ns'] .= 'customfields/';
						$prop['name'] = substr($prop['name'],1);
					}
					$fileprops[] = $prop;
				}
			}
		}
		if ($this->debug) error_log(__METHOD__."() props=".array2string($files['files']));
		return true;
	}

 	/**
	 * Used eg. by get
	 *
	 * @todo replace all calls to _mimetype with Vfs::mime_content_type()
	 * @param string $path
	 * @return string
	 */
	function _mimetype($path)
	{
		return Vfs::mime_content_type($path);
	}

    /**
     * Check if path is readable by current user
     *
     * @param string $fspath
     * @return boolean
     */
    function _is_readable($fspath)
    {
    	return Vfs::is_readable($fspath);
    }

    /**
     * Check if path is writable by current user
     *
     * @param string $fspath
     * @return boolean
     */
    function _is_writable($fspath)
    {
    	return Vfs::is_writable($fspath);
    }

	/**
	 * PROPPATCH method handler
	 *
	 * The current version only allows Webdrive to set creation and modificaton dates.
	 * They are not stored as (arbitrary) WebDAV properties with their own namespace and name,
	 * but in the regular vfs attributes.
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function PROPPATCH(&$options)
	{
		$path = Api\Translation::convert($options['path'],'utf-8');

		foreach ($options['props'] as $key => $prop) {
			$attributes = array();
			switch($prop['ns'])
			{
				// allow Webdrive to set creation and modification time
				case 'http://www.southrivertech.com/':
					switch($prop['name'])
					{
						case 'srt_modifiedtime':
						case 'getlastmodified':
							Vfs::touch($path,strtotime($prop['val']));
							break;
						//case 'srt_creationtime':
							// no streamwrapper interface / php function to set the ctime currently
							//$attributes['created'] = strtotime($prop['val']);
							//break;
						default:
							if (!Vfs::proppatch($path,array($prop))) $options['props'][$key]['status'] = '403 Forbidden';
							break;
					}
					break;

				case 'DAV:':
					switch($prop['name'])
					{
						// allow netdrive to change the modification time
						case 'getlastmodified':
							Vfs::touch($path,strtotime($prop['val']));
							break;
						// not sure why, the filesystem example of the WebDAV class does it ...
						default:
							$options['props'][$key]['status'] = '403 Forbidden';
							break;
					}
					break;

				case 'urn:schemas-microsoft-com:':
					switch($prop['name'])
					{
						case 'Win32LastModifiedTime':
							Vfs::touch($path,strtotime($prop['val']));
							break;
						case 'Win32CreationTime':	// eg. "Wed, 14 Sep 2011 15:48:26 GMT"
						case 'Win32LastAccessTime':
						case 'Win32FileAttributes':	// not sure what that is, it was always "00000000"
						default:
							if (!Vfs::proppatch($path,array($prop))) $options['props'][$key]['status'] = '403 Forbidden';
							break;
					}
					break;

				case Vfs::DEFAULT_PROP_NAMESPACE.'customfields/':	// eGW's customfields
					$prop['ns'] = Vfs::DEFAULT_PROP_NAMESPACE;
					$prop['name'] = '#'.$prop['name'];
					// fall through
				default:
					if (!Vfs::proppatch($path,array($prop))) $options['props'][$key]['status'] = '403 Forbidden';
					break;
			}
			if ($this->debug) $props[] = '('.$prop['ns'].')'.$prop['name'].'='.$prop['val'];
		}
		if ($this->debug)
		{
			error_log(__METHOD__.": path=$options[path], props=".implode(', ',$props));
			if ($attributes) error_log(__METHOD__.": path=$options[path], set attributes=".str_replace("\n",' ',print_r($attributes,true)));
		}

		return '';	// this is as the filesystem example handler does it, no true or false ...
	}

	/**
	 * LOCK method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function LOCK(&$options)
	{
		if ($this->debug) error_log(__METHOD__.'('.str_replace(array("\n",'    '),'',print_r($options,true)).')');
		// TODO recursive locks on directories not supported yet
		if (is_dir($this->base . $options['path']) && !empty($options['depth']))
		{
			return '409 Conflict';
		}
		$options['timeout'] = time()+300; // 5min. hardcoded

		// dont know why, but HTTP_WebDAV_Server passes the owner in D:href tags, which get's passed unchanged to checkLock/PROPFIND
		// that's wrong according to the standard and cadaver does not show it on discover --> strip_tags removes eventual tags
		if (($ret = Vfs::lock($options['path'],$options['locktoken'],$options['timeout'],strip_tags($options['owner']),
			$options['scope'],$options['type'],isset($options['update']))) && !isset($options['update']))
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
		if ($this->debug) error_log(__METHOD__.'('.str_replace(array("\n",'    '),'',print_r($options,true)).')');
		return Vfs::unlock($options['path'],$options['token']) ? '204 No Content' : '409 Conflict';
	}

	/**
	 * checkLock() helper
	 *
	 * @param  string resource path to check for locks
	 * @return bool   true on success
	 */
	function checkLock($path)
	{
		return Vfs::checkLock($path);
	}

	/**
	 * GET method handler for directories
	 *
	 * Reimplemented to send content type header with charset
	 *
	 * @param  string  directory path
	 * @return void    function has to handle HTTP response itself
	 */
    function GetDir($fspath, &$options)
    {
		// add a content-type header to overwrite an existing default charset in apache (AddDefaultCharset directiv)
		header('Content-type: text/html; charset='.Api\Translation::charset());

		parent::GetDir($fspath, $options);
    }

	private $force_download = false;

	/**
	 * Constructor
	 *
	 * Reimplement to add a Content-Disposition header, if ?download is appended to the REQUEST_URI
	 */
	function __construct()
	{
		if ($_SERVER['REQUEST_METHOD'] == 'GET' && (($this->force_download = strpos($_SERVER['REQUEST_URI'],'?download')) !== false))
		{
			$_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'],0,$this->force_download);
		}
		parent::__construct();
	}

	/**
	 * GET method handler
	 *
	 * Reimplement to add a Content-Disposition header, if ?download is appended to the REQUEST_URI
	 *
	 * @param  array  parameter passing array
	 * @return bool   true on success
	 */
	function GET(&$options)
	{
		if (is_dir($this->base . $options["path"]))
		{
			return $this->autoindex($options);
		}
		if (($ok = parent::GET($options)))
		{
			// mitigate risks of serving javascript or css from our domain
			Api\Header\Content::safe($options['stream'], $options['path'], $options['mimetype'], $options['size'], false,
				$this->force_download, true);	// true = do not send content-type and content-length header, but modify values

			if (!is_resource($options['stream']))
			{
				$options['data'] =& $options['stream'];
				unset($options['stream']);
			}
		}
		return $ok;
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
		header('Content-type: text/html; charset='.Api\Translation::charset());
		echo "<html>\n<head>\n\t<title>".'EGroupware WebDAV server '.htmlspecialchars($options['path'])."</title>\n";
		echo "\t<meta http-equiv='content-type' content='text/html; charset=utf-8' />\n";
		echo "\t<style type='text/css'>\n.th { background-color: #e0e0e0; }\n.row_on { background-color: #F1F1F1; vertical-align: top; }\n".
			".row_off { background-color: #ffffff; vertical-align: top; }\ntd { padding-left: 5px; }\nth { padding-left: 5px; text-align: left; }\n\t</style>\n";
		echo "</head>\n<body>\n";

		echo '<h1>WebDAV ';
		list(,$base) = explode(parse_url($GLOBALS['egw_info']['server']['webserver_url'], PHP_URL_PATH), $this->base_uri, 2);
		$path = $base;
		foreach(explode('/',$this->_unslashify($options['path'])) as $n => $name)
		{
			$path .= ($n != 1 ? '/' : '').$name;
			echo Api\Html::a_href(htmlspecialchars($name.'/'),$path);
		}
		echo "</h1>\n";

		static $props2show = array(
			'DAV:displayname'      => 'Displayname',
			'DAV:getlastmodified'  => 'Last modified',
			'DAV:getetag'          => 'ETag',
			'DAV:getcontenttype'   => 'Content type',
			'DAV:resourcetype'     => 'Resource type',
			//'DAV:owner'            => 'Owner',
			//'DAV:current-user-privilege-set' => 'current-user-privilege-set',
			//'DAV:getcontentlength' => 'Size',
			//'DAV:sync-token' => 'sync-token',
		);
		$n = 0;
		$collection_props = null;
		foreach($files['files'] as $file)
		{
			if (!isset($collection_props))
			{
				$collection_props = $this->props2array($file['props']);
				echo '<h3>'.lang('Collection listing').': '.htmlspecialchars($collection_props['DAV:displayname'])."</h3>\n";
				continue;	// own entry --> displaying properies later
			}
			if(!$n++)
			{
				echo "<table>\n\t<tr class='th'>\n\t\t<th>#</th>\n\t\t<th>".lang('Name')."</th>";
				foreach($props2show as $label)
				{
					echo "\t\t<th>".lang($label)."</th>\n";
				}
				echo "\t</tr>\n";
			}
			$props = $this->props2array($file['props']);
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

			echo "\t<tr class='$class'>\n\t\t<td>$n</td>\n\t\t<td>".
				Api\Html::a_href(htmlspecialchars($name),$base.strtr($file['path'], array(
					'%' => '%25',
					'#' => '%23',
					'?' => '%3F',
				)))."</td>\n";
			foreach($props2show as $prop => $label)
			{
				echo "\t\t<td>".($prop=='DAV:getlastmodified'&&!empty($props[$prop])?date('Y-m-d H:i:s',$props[$prop]):$props[$prop])."</td>\n";
			}
			echo "\t</tr>\n";
		}
		if (!$n)
		{
			echo '<p>'.lang('Collection empty.')."</p>\n";
		}
		else
		{
			echo "</table>\n";
		}
		echo '<h3>'.lang('Properties')."</h3>\n";
		echo "<table>\n\t<tr class='th'><th>".lang('Namespace')."</th><th>".lang('Name')."</th><th>".lang('Value')."</th></tr>\n";
		foreach($collection_props as $name => $value)
		{
			$class = $class == 'row_on' ? 'row_off' : 'row_on';
			$parts = explode(':',$name);
			$name = array_pop($parts);
			$ns = implode(':',$parts);
			echo "\t<tr class='$class'>\n\t\t<td>".htmlspecialchars($ns)."</td><td style='white-space: nowrap'>".htmlspecialchars($name)."</td>\n";
			echo "\t\t<td>".$value."</td>\n\t</tr>\n";
		}
		echo "</table>\n";
		/*$dav = array(1);
		$allow = false;
		$this->OPTIONS($options['path'], $dav, $allow);
		echo "<p>DAV: ".implode(', ', $dav)."</p>\n";*/

		echo "</body>\n</html>\n";

		exit;
	}

	/**
	 * Format a property value for output
	 *
	 * @param mixed $value
	 * @return string
	 */
	protected function prop_value($value)
	{
		if (is_array($value))
		{
			if (isset($value[0]['ns']))
			{
				$value = $this->_hierarchical_prop_encode($value);
			}
			$value = array2string($value);
		}
		if ($value[0] == '<' && function_exists('tidy_repair_string'))
		{
			$value = tidy_repair_string($value, array(
				'indent'          => true,
				'show-body-only'  => true,
				'output-encoding' => 'utf-8',
				'input-encoding'  => 'utf-8',
				'input-xml'       => true,
				'output-xml'      => true,
				'wrap'            => 0,
			));
		}
		if (($href=preg_match('/\<(D:)?href\>[^<]+\<\/(D:)?href\>/i',$value)))
		{
			$value = preg_replace('/\<(D:)?href\>('.preg_quote($this->base_uri.'/','/').')?([^<]+)\<\/(D:)?href\>/i','<\\1href><a href="\\2\\3">\\3</a></\\4href>',$value);
		}
		$ret = $value[0] == '<'  || strpos($value, "\n") !== false ?
			'<pre>'.htmlspecialchars($value).'</pre>' : htmlspecialchars($value);

		if ($href)
		{
			$ret = str_replace('&lt;/a&gt;', '</a>',
				preg_replace('/&lt;a href=&quot;(.+)&quot;&gt;/', '<a href="\\1">', $ret));
		}
		return $ret;
	}

	/**
	 * Return numeric indexed array with values for keys 'ns', 'name' and 'val' as array 'ns:name' => 'val'
	 *
	 * @param array $props
	 * @return array
	 */
	protected function props2array(array $props)
	{
		$arr = array();
		foreach($props as $prop)
		{
			$ns_hash = array('DAV:' => 'D');
			switch($prop['ns'])
			{
				case 'DAV:';
					$ns = 'DAV';
					break;
				default:
					$ns = $prop['ns'];
			}
			if (is_array($prop['val']))
			{
				$prop['val'] = $this->_hierarchical_prop_encode($prop['val'], $prop['ns'], $ns_defs='', $ns_hash);
				// hack to show real namespaces instead of not (visibly) defined shortcuts
				unset($ns_hash['DAV:']);
				$value = strtr($v=$this->prop_value($prop['val']),array_flip($ns_hash));
			}
			else
			{
				$value = $this->prop_value($prop['val']);
			}
			$arr[$ns.':'.$prop['name']] = $value;
		}
		return $arr;
	}

	/**
	 * PUT method handler
	 *
	 * Reimplemented to safely rejected PUT with Transfer-Encoding: Chunk, if server does not support it.
	 * Currently on Apache with mod_php and newer Ngix support it.
	 * We reject it with "501 Unimplemented" for fastCGI, if SERVER_SOFTWARE does NOT contain Nginx.
	 * This stops OS X Finder from destroying files.
	 *
	 * @param  array  parameter passing array
	 * @return bool   true on success
	 */
	function PUT(&$options)
	{
		if (strtolower($_SERVER['HTTP_TRANSFER_ENCODING']) == 'chunked' &&
			in_array(php_sapi_name(), array('cgi', 'cgi-fcgi', 'fpm-fcgi')) && !preg_match('/nginx/i', $_SERVER['SERVER_SOFTWARE']))
		{
			error_log(__METHOD__.'() '.$_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI'].' HTTP/1.1');
			error_log(__METHOD__.'() _SERVER[HTTP_TRANSFER_ENCODING='.$_SERVER['HTTP_TRANSFER_ENCODING'].', php_sapi_name()='.php_sapi_name().', _SERVER[SERVER_SOFTWARE]='.$_SERVER['SERVER_SOFTWARE']);
			/*foreach($_SERVER as $name => $value)
			{
				list($type,$name) = explode('_',$name,2);
				if ($type == 'HTTP' || $type == 'CONTENT')
				{
					error_log(__METHOD__.'() '.str_replace(' ','-',ucwords(strtolower(($type=='HTTP'?'':$type.' ').str_replace('_',' ',$name)))).
							': '.($name=='AUTHORIZATION'?'Basic ***************':$value));
				}
			}*/
			error_log(__METHOD__.'() Rejected PUT with Transfer-Encoding: Chunked, as server does NOT support it!');
			// http://www.w3.org/Protocols/rfc2616/rfc2616-sec3.html#sec3.6
			return '501 Unimplemented (Transfer-Encoding: Chunked)';
		}
		return parent::PUT($options);
	}
}
