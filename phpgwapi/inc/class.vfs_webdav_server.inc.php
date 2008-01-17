<?php
/**
 * eGroupWare API: VFS - WebDAV access
 *
 * Using the PEAR HTTP/WebDAV/Server class (which need to be installed!)
 * 
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

require_once('HTTP/WebDAV/Server.php');
require_once(EGW_API_INC.'/class.vfs_home.inc.php');

/**
 * FileManger - WebDAV access
 *
 * Using the PEAR HTTP/WebDAV/Server class (which need to be installed!)
 */
class vfs_webdav_server extends HTTP_WebDAV_Server
{
	/**
	 * instance of the vfs class
	 *
	 * @var vfs_home
	 */
	var $vfs;

	var $dav_powered_by = 'eGroupWare WebDAV server';
	
	/**
	 * Debug level: 0 = nothing, 1 = function calls, 2 = more info, eg. complete $_SERVER array
	 * 
	 * The debug messages are send to the apache error_log
	 *
	 * @var integer
	 */
	var $debug = 0;

	function vfs_webdav_server()
	{
		if ($this->debug === 2) foreach($_SERVER as $name => $val) error_log("vfs_webdav_server: \$_SERVER[$name]='$val'");

		parent::HTTP_WebDAV_Server();
		
		$this->vfs =& new vfs_home;
	}
	
	/**
	 * PROPFIND method handler
	 *
	 * @param  array  general parameter passing array
	 * @param  array  return array for file properties
	 * @return bool   true on success
	 */
	function PROPFIND(&$options, &$files) 
	{
		$vfs_data = array(
			'string'    => $GLOBALS['egw']->translation->convert($options['path'],'utf-8'),
			'relatives'	=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
			// at first only list the given path itself
			'checksubdirs'	=> False,
			'nofiles'	=> True
		);
		if (!($vfs_files = $this->vfs->ls($vfs_data)))	// path not found
		{
			// check if the users home-dir is just not yet created (should be done by the vfs-class!)
			// ToDo: group-dirs
			if ($vfs_data['string'] == '/home/'.$GLOBALS['egw_info']['user']['account_lid'])
			{
				$this->vfs->override_acl = true;	// user has no right to create dir in /home
				$created = $this->vfs->mkdir(array(
					'string'    => $GLOBALS['egw']->translation->convert($options['path'],'utf-8'),
					'relatives'	=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
				));
				$this->vfs->override_acl = false;
				
				if (!$created)
				{
					if ($this->debug) error_log("vfs_webdav_server::PROPFIND(path='$options[path]',depth=$options[depth]) could not create home dir");
				}
				$vfs_files = $this->vfs->ls($vfs_data);
			}
			if (!$vfs_files)
			{
				if ($this->debug) error_log("vfs_webdav_server::PROPFIND(path='$options[path]',depth=$options[depth]) return false (path not found)");
				return false;	// path not found
			}
		}
		// if depth > 0 and path is a directory => show it's contents
		if (!empty($options['depth']) && $vfs_files[0]['mime_type'] == 'Directory')
		{
			$vfs_data['checksubdirs'] = (int) $options['depth'] != 1;
			$vfs_data['nofiles'] = false;

			if ($vfs_files[0]['directory'] == '/')	// sub-dirs of the root?
			{
				$vfs_files = array();	// dont return the directory, it shows up double in konq
			}
			else	// return the dir itself with a trailing slash, otherwise empty dirs are reported as non-existent
			{
				$vfs_files[0]['name'] .= '/';
			}
			$vfs_files = array_merge($vfs_files,$this->vfs->ls($vfs_data));
		}
		if ($this->debug) error_log("vfs_webdav_server::PROPFIND(path='$options[path]',depth=$options[depth]) ".count($vfs_files).' files');
	
		$files['files'] = array();
		$egw_charset = $GLOBALS['egw']->translation->charset();
		foreach($vfs_files as $fileinfo)
		{
			if ($this->debug) error_log('dir="'.$fileinfo['directory'].'", name="'.$fileinfo['name'].'": '.$fileinfo['mime_type']);
			foreach(array('modified','created') as $date)
			{
				// our vfs has no modified set, if never modified, use created
				list($y,$m,$d,$h,$i,$s) = split("[- :]",$fileinfo[$date] ? $fileinfo[$date] : $fileinfo['created']);
				$fileinfo[$date] = mktime((int)$h,(int)$i,(int)$s,(int)$m,(int)$d,(int)$y);
			}
			$info = array(
            	'path'  => $GLOBALS['egw']->translation->convert($fileinfo['directory'].'/'.$fileinfo['name'],$egw_charset,'utf-8'),
            	'props' => array(
            		$this->mkprop('displayname',$GLOBALS['egw']->translation->convert($fileinfo['name'],$egw_charset,'utf-8')),
            		$this->mkprop('creationdate',$fileinfo['created']),
            		$this->mkprop('getlastmodified',$fileinfo['modified']),
            	),
            );
            if ($fileinfo['mime_type'] == 'Directory')
            {
            	$info['props'][] = $this->mkprop('resourcetype', 'collection');
                $info['props'][] = $this->mkprop('getcontenttype', 'httpd/unix-directory');             
            }
            else
            {
            	$info['props'][] = $this->mkprop('resourcetype', '');
                $info['props'][] = $this->mkprop('getcontenttype', $fileinfo['mime_type']);             
            	$info['props'][] = $this->mkprop('getcontentlength', $fileinfo['size']);
            }
            $files['files'][] = $info;
		}
		if ($this->debug == 2) foreach($files['files'] as $info) error_log(print_r($info,true));
 		// ok, all done
		return true;
	}
	
	/**
	 * GET method handler
	 * 
	 * @param  array  parameter passing array
	 * @return bool   true on success
	 */
	function GET(&$options) 
	{
		if ($this->debug) error_log('vfs_webdav_server::GET('.print_r($options,true).')');
		
		$vfs_data = array(
			'string'    => $GLOBALS['egw']->translation->convert($options['path'],'utf-8'),
			'relatives'	=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
			'checksubdirs'	=> False,
			'nofiles'	=> True
		);
		// sanity check
		if (!($vfs_file = $this->vfs->ls($vfs_data)))
		{
			return false;
		}
		$options['mimetype'] = $vfs_file[0]['mime_type'];
		$options['size']     = $vfs_file[0]['size'];
		
		if (($options['data'] = $this->vfs->read($vfs_data)) === false)
		{
			return '403 Forbidden';		// not sure if this is the right code for access denied
		}
		return true;
	}

	/**
	 * PUT method handler
	 * 
	 * @param  array  parameter passing array
	 * @return bool   true on success
	 */
	function PUT(&$options) 
	{
		if ($this->debug) error_log('vfs_webdav_server::PUT('.print_r($options,true).')');

		$vfs_data = array(
			'string'    => dirname($GLOBALS['egw']->translation->convert($options['path'],'utf-8')),
			'relatives'	=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
			'checksubdirs'	=> False,
			'nofiles'	=> True
		);
		if (!($vfs_file = $this->vfs->ls($vfs_data)) || $vfs_file[0]['mime_type'] != 'Directory')
		{
			return '409 Conflict';
		}
		$vfs_data = array(
			'string'    => $GLOBALS['egw']->translation->convert($options['path'],'utf-8'),
			'relatives'	=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
		);
		$options['new'] = !$this->vfs->file_exists($vfs_data);
		
		$vfs_data['content'] = '';
		while(!feof($options['stream']))
		{
			$vfs_data['content'] .= fread($options['stream'],8192);
		}
		return $this->vfs->write($vfs_data);
	}
	
	/**
	 * MKCOL method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function MKCOL($options) 
	{           
		if ($this->debug) error_log('vfs_webdav_server::MKCOL('.print_r($options,true).')');

		$vfs_data = array(
			'string'    => dirname($GLOBALS['egw']->translation->convert($options['path'],'utf-8')),
			'relatives'	=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
			'checksubdirs'	=> False,
			'nofiles'	=> True
		);
		if (!($vfs_file = $this->vfs->ls($vfs_data)))
		{
			return '409 Conflict';
		}
		if ($this->debug) error_log(print_r($vfs_file,true));

		if ($vfs_file[0]['mime_type'] != 'Directory')
		{
			return '403 Forbidden';
		}
		
		$vfs_data = array(
			'string'    => $GLOBALS['egw']->translation->convert($options['path'],'utf-8'),
			'relatives'	=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
		);
		if ($this->vfs->file_exists($vfs_data) ) 
		{
			return '405 Method not allowed';
		}
		
		if (!empty($_SERVER['CONTENT_LENGTH']))  // no body parsing yet
		{
			return '415 Unsupported media type';
		}
		
		if (!$this->vfs->mkdir($vfs_data))
		{
			return '403 Forbidden';                 
		}
		
		return '201 Created';
	}

	/**
	 * DELETE method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function DELETE($options) 
	{
		if ($this->debug) error_log('vfs_webdav_server::DELETE('.print_r($options,true).')');

		$vfs_data = array(
			'string'    => $GLOBALS['egw']->translation->convert($options['path'],'utf-8'),
			'relatives'	=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
		);
		if (!$this->vfs->file_exists($vfs_data))
		{
			return '404 Not found';
		}
		if (!$this->vfs->rm($vfs_data))
		{
			return '403 Forbidden';                 
		}
		return '204 No Content';
	}

	/**
	 * MOVE method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function MOVE($options) 
	{
		return $this->COPY($options, true);
	}
	
	/**
	 * COPY method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function COPY($options, $del=false)
	{
		if ($this->debug) error_log('vfs_webdav_server::'.($del ? 'MOVE' : 'COPY').'('.print_r($options,true).')');
		
		// TODO Property updates still broken (Litmus should detect this?)
		
		if (!empty($_SERVER['CONTENT_LENGTH']))  // no body parsing yet
		{
			return '415 Unsupported media type';
		}
		
		// no copying to different WebDAV Servers yet
		if (isset($options['dest_url'])) 
		{
			return '502 bad gateway';
		}
		
		$source = array(
			'string' => $GLOBALS['egw']->translation->convert($options['path'],'utf-8'),
			'relatives'	=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
		);
		if (!$this->vfs->file_exists($source)) 
		{
			return '404 Not found';
		}
		
		$dest = array(
			'string' => $options['dest'],
			'relatives'	=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
		);
		$new = !$this->vfs->file_exists($dest);
		$existing_col = false;
		
		if (!$new) 
		{
			if ($del && $this->vfs->file_type($dest) == 'Directory') 
			{
				if (!$options['overwrite']) 
				{
					return '412 precondition failed';
				}
				$dest['string'] .= basename($GLOBALS['egw']->translation->convert($options['path'],'utf-8'));
				if ($this->vfs->file_exists($dest)) 
				{
					$options['dest'] .= basename($GLOBALS['egw']->translation->convert($options['path'],'utf-8'));
				} 
				else 
				{
					$new = true;
					$existing_col = true;
				}
			}
		}
		
		if (!$new) 
		{
			if ($options['overwrite']) 
			{
				$stat = $this->DELETE(array('path' => $options['dest']));
				if (($stat{0} != '2') && (substr($stat, 0, 3) != '404')) 
				{
					return $stat; 
				}
			} 
			else 
			{                
				return '412 precondition failed';
			}
		}
		
		if ($this->vfs->file_type($source) == 'Directory' && ($options['depth'] != 'infinity')) 
		{
			// RFC 2518 Section 9.2, last paragraph
			return '400 Bad request';
		}
		
		$op = $del ? 'mv' : 'cp';
		$vfs_data = array(
			'from' => $source['string'],
			'to'   => $dest['string'],
			'relatives' => array(RELATIVE_ROOT,RELATIVE_ROOT)
		);
		if (!$this->vfs->$op($vfs_data))
		{
			return '500 Internal server error';
		}
		return ($new && !$existing_col) ? '201 Created' : '204 No Content';         
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
							$attributes['modified'] = strtotime($prop['val']);
							break;
						case 'srt_creationtime':
							$attributes['created'] = strtotime($prop['val']);
							break;
					}
					break;
					
				case 'DAV:':
					switch($prop['name'])
					{
						// allow netdrive to change the modification time
						case 'getlastmodified':
							$attributes['modified'] = strtotime($prop['val']);
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
		if ($attributes)
		{
			$vfs_data = array(
				'string'    => $GLOBALS['egw']->translation->convert($options['path'],'utf-8'),
				'relatives'	=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
				'attributes'=> $attributes,
			);
			$this->vfs->set_attributes($vfs_data);
		}
		if ($this->debug)
		{
			error_log(__CLASS__.'::'.__METHOD__.": path=$options[path], props=".implode(', ',$props));
			if ($attributes) error_log(__CLASS__.'::'.__METHOD__.": path=$options[path], set attributes=".str_replace("\n",' ',print_r($attributes,true)));
		}

		
		return "";	// this is as the filesystem example handler does it, no true or false ...
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