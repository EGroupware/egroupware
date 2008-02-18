<?php
/**
 * eGroupWare API: VFS - WebDAV access using the new stream wrapper VFS interface
 *
 * Using the PEAR HTTP/WebDAV/Server/Filesystem class (which need to be installed!)
 * 
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Hartmut Holzgraefe <hartmut@php.net> original HTTP/WebDAV/Server/Filesystem class, of which some code is used
 * @version $Id$
 */

require_once('HTTP/WebDAV/Server/Filesystem.php');
require_once(EGW_API_INC.'/class.egw_vfs.inc.php');

/**
 * FileManger - WebDAV access using the new stream wrapper VFS interface
 *
 * Using the PEAR HTTP/WebDAV/Server/Filesystem class (which need to be installed!)
 * 
 * @todo table to store locks and properties
 * @todo filesystem class uses PEAR's System::find which we dont require nor know if it works on custom streamwrapper
 */
class vfs_webdav_server extends HTTP_WebDAV_Server_Filesystem 
{
	var $dav_powered_by = 'eGroupWare WebDAV server';
	
	/**
	 * Base directory is the URL of our VFS root
	 *
	 * @var string
	 */
	var $base = 'vfs://default';
	
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
	* @param  string  
	*/
	function ServeRequest($base = false) 
	{
		// special treatment for litmus compliance test
		// reply on its identifier header
		// not needed for the test itself but eases debugging
		foreach (apache_request_headers() as $key => $value) 
		{
			if (stristr($key, "litmus")) 
			{
				error_log("Litmus test $value");
				header("X-Litmus-reply: ".$value);
			}
		}
		
		// let the base class do all the work
		HTTP_WebDAV_Server::ServeRequest();
	}
	
    /**
     * DELETE method handler
     *
     * @param  array  general parameter passing array
     * @return bool   true on success
     */
    function DELETE($options) 
    {
        $path = $this->base . "/" .$options["path"];

        if (!file_exists($path)) 
        {
            return "404 Not found";
        }

        if (is_dir($path)) 
        {
            /*$query = "DELETE FROM {$this->db_prefix}properties 
                           WHERE path LIKE '".$this->_slashify($options["path"])."%'";
            mysql_query($query); */
            // recursive delete the directory
            if ($dir = egw_vfs::dir_opendir($options["path"]))
            {
            	while(($file = readdir($dir)))
            	{
            		if ($file == '.' || $file == '..') continue;

            		if (is_dir($path.'/'.$file))
            		{
            			// recursivly call ourself with the dir
            			$opts = $options;
            			$opts['path'] .= '/'.$file;
            			$this->DELETE($opts);
            		}
            		else
            		{
            			unlink($path.'/'.$file);
            		}
            	}
            	closedir($dir);
            }
        } 
        else 
        {
            unlink($path);
        }
        /*$query = "DELETE FROM {$this->db_prefix}properties 
                       WHERE path = '$options[path]'";
        mysql_query($query);*/

        return "204 No Content";
    }

    /**
     * Get properties for a single file/resource
     *
     * @param  string  resource path
     * @return array   resource properties
     */
    function fileinfo($path) 
    {
		error_log(__METHOD__."($path)");
        // map URI path to filesystem path
        $fspath = $this->base . $path;

        // create result array
        $info = array();
        // TODO remove slash append code when base clase is able to do it itself
        $info["path"]  = is_dir($fspath) ? $this->_slashify($path) : $path; 
        $info["props"] = array();
            
        // no special beautified displayname here ...
        $info["props"][] = $this->mkprop("displayname", strtoupper($path));
            
        // creation and modification time
        $info["props"][] = $this->mkprop("creationdate",    filectime($fspath));
        $info["props"][] = $this->mkprop("getlastmodified", filemtime($fspath));

        // type and size (caller already made sure that path exists)
        if (is_dir($fspath)) {
            // directory (WebDAV collection)
            $info["props"][] = $this->mkprop("resourcetype", "collection");
            $info["props"][] = $this->mkprop("getcontenttype", "httpd/unix-directory");             
        } else {
            // plain file (WebDAV resource)
            $info["props"][] = $this->mkprop("resourcetype", "");
            if (egw_vfs::is_readable($path)) {
                $info["props"][] = $this->mkprop("getcontenttype", egw_vfs::mime_content_type($path));
            } else {
				error_log(__METHOD__."($path) $fspath is not readable!");
                $info["props"][] = $this->mkprop("getcontenttype", "application/x-non-readable");
            }               
            $info["props"][] = $this->mkprop("getcontentlength", filesize($fspath));
        }
/*
        // get additional properties from database
        $query = "SELECT ns, name, value 
                        FROM {$this->db_prefix}properties 
                       WHERE path = '$path'";
        $res = mysql_query($query);
        while ($row = mysql_fetch_assoc($res)) {
            $info["props"][] = $this->mkprop($row["ns"], $row["name"], $row["value"]);
        }
        mysql_free_result($res);
*/
		//error_log(__METHOD__."($path) info=".print_r($info,true));
        return $info;
    }

	/**
	 * Used eg. by get
	 *
	 * @todo replace all calls to _mimetype with egw_vfs::mime_content_type()
	 * @param string $path
	 * @return string
	 */
	function _mimetype($path)
	{
		return egw_vfs::mime_content_type($path);
	}

	/**
	 * PROPPATCH method handler
	 * 
	 * The current version only allows Webdrive to set creation and modificaton dates.
	 * They are not stored as (arbitrary) WebDAV properties with their own namespace and name,
	 * but in the regular vfs attributes.
	 *
	 * @todo Store a properties in the DB and retrieve them in PROPFIND again.
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function PROPPATCH(&$options) 
	{
		$path = $GLOBALS['egw']->translation->convert($options['path'],'utf-8');

		foreach ($options["props"] as $key => $prop) {
			$attributes = array();
			switch($prop['ns'])
			{
				// allow Webdrive to set creation and modification time
				case 'http://www.southrivertech.com/':
					switch($prop['name'])
					{
						case 'srt_modifiedtime':
						case 'getlastmodified':
							egw_vfs::touch($path,strtotime($prop['val']));
							break;
						case 'srt_creationtime':
							// not supported via the streamwrapper interface atm.
							//$attributes['created'] = strtotime($prop['val']);
							break;
					}
					break;
					
				case 'DAV:':
					switch($prop['name'])
					{
						// allow netdrive to change the modification time
						case 'getlastmodified':
							egw_vfs::touch($path,strtotime($prop['val']));
							break;
						// not sure why, the filesystem example of the WebDAV class does it ...
						default:
							$options["props"][$key]['status'] = "403 Forbidden";
							break;
					}
					break;
			}
			if ($this->debug) $props[] = '('.$prop["ns"].')'.$prop['name'].'='.$prop['val'];
		}
		if ($this->debug)
		{
			error_log(__METHOD__.": path=$options[path], props=".implode(', ',$props));
			if ($attributes) error_log(__METHOD__.": path=$options[path], set attributes=".str_replace("\n",' ',print_r($attributes,true)));
		}

		return "";	// this is as the filesystem example handler does it, no true or false ...
	}
	
     /**
     * LOCK method handler
     *
     * @param  array  general parameter passing array
     * @return bool   true on success
     */
    function LOCK(&$options) 
    {
    	// behaving like LOCK is not implemented
		return "412 Precondition failed";
/*
        // get absolute fs path to requested resource
        $fspath = $this->base . $options["path"];

        // TODO recursive locks on directories not supported yet
        if (is_dir($fspath) && !empty($options["depth"])) {
            return "409 Conflict";
        }

        $options["timeout"] = time()+300; // 5min. hardcoded

        if (isset($options["update"])) { // Lock Update
            $where = "WHERE path = '$options[path]' AND token = '$options[update]'";

            $query = "SELECT owner, exclusivelock FROM {$this->db_prefix}locks $where";
            $res   = mysql_query($query);
            $row   = mysql_fetch_assoc($res);
            mysql_free_result($res);

            if (is_array($row)) {
                $query = "UPDATE {$this->db_prefix}locks 
                                 SET expires = '$options[timeout]' 
                                   , modified = ".time()."
                              $where";
                mysql_query($query);

                $options['owner'] = $row['owner'];
                $options['scope'] = $row["exclusivelock"] ? "exclusive" : "shared";
                $options['type']  = $row["exclusivelock"] ? "write"     : "read";

                return true;
            } else {
                return false;
            }
        }
            
        $query = "INSERT INTO {$this->db_prefix}locks
                        SET token   = '$options[locktoken]'
                          , path    = '$options[path]'
                          , created = ".time()."
                          , modified = ".time()."
                          , owner   = '$options[owner]'
                          , expires = '$options[timeout]'
                          , exclusivelock  = " .($options['scope'] === "exclusive" ? "1" : "0")
            ;
        mysql_query($query);

        return mysql_affected_rows() ? "200 OK" : "409 Conflict";*/
    }

    /**
     * UNLOCK method handler
     *
     * @param  array  general parameter passing array
     * @return bool   true on success
     */
    function UNLOCK(&$options) 
    {
    	// behaving like LOCK is not implemented
		return "405 Method not allowed";
/*
        $query = "DELETE FROM {$this->db_prefix}locks
                      WHERE path = '$options[path]'
                        AND token = '$options[token]'";
        mysql_query($query);

        return mysql_affected_rows() ? "204 No Content" : "409 Conflict";*/
    }

    /**
     * checkLock() helper
     *
     * @param  string resource path to check for locks
     * @return bool   true on success
     */
    function checkLock($path) 
    {
    	// behave like checkLock is not implemented
		return false;
/*		
        $result = false;
            
        $query = "SELECT owner, token, created, modified, expires, exclusivelock
                  FROM {$this->db_prefix}locks
                 WHERE path = '$path'
               ";
        $res = mysql_query($query);

        if ($res) {
            $row = mysql_fetch_array($res);
            mysql_free_result($res);

            if ($row) {
                $result = array( "type"    => "write",
                                 "scope"   => $row["exclusivelock"] ? "exclusive" : "shared",
                                 "depth"   => 0,
                                 "owner"   => $row['owner'],
                                 "token"   => $row['token'],
                                 "created" => $row['created'],   
                                 "modified" => $row['modified'],   
                                 "expires" => $row['expires']
                                 );
            }
        }

        return $result;*/
    }
    
    /**
     * Remove not (yet) implemented LOCK methods, so we can use the mostly unchanged HTTP_WebDAV_Server_Filesystem class
     *
     * @return array
     */
    function _allow()
    {
    	$allow = parent::_allow();
    	unset($allow['LOCK']);
    	unset($allow['UNLOCK']);
    	return $allow;
    }

   /**
	 * auth check in the session creation in dav.php, to avoid being redirected to login.php
	 *
	 * @param string $type
	 * @param string $login account_lid or account_lid@domain
	 * @param string $password this is checked in the session creation
	 * @return boolean true if authorized or false otherwise
	 */
	function checkAuth($type,$login,$password)
	{
		list($account_lid,$domain) = explode('@',$login);
		
		$auth = ($login === $GLOBALS['egw_info']['user']['account_lid'] ||
			($account_lid === $GLOBALS['egw_info']['user']['account_lid'] && $domain === $GLOBALS['egw']->session->account_domain)) &&
			$GLOBALS['egw_info']['user']['apps']['filemanager'];

		if ($this->debug) error_log("vfs_webdav_server::checkAuth('$type','$login','\$password'): account_lid='$account_lid', domain='$domain' ==> ".(int)$auth);

		return $auth;
	}
}