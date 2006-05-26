<?php
/**************************************************************************\
* eGroupWare - FileManger - WebDAV access                                  *
* http://www.egroupware.org                                                *
* Written and (c) 2006 by  Ralf Becker <RalfBecker-AT-outdoor-training.de> *
* ------------------------------------------------------------------------ *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id: class.socontacts_sql.inc.php 21634 2006-05-24 02:28:57Z ralfbecker $ */

require_once('HTTP/WebDAV/Server.php');

/**
 * FileManger - WebDAV access
 *
 * Using the PEAR HTTP/WebDAV/Server class (which need to be installed!)
 * 
 * @package filemanger
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

class vfs_webdav_server extends HTTP_WebDAV_Server
{
	var $vfs;

	var $dav_powered_by = 'eGroupWare WebDAV server';
	
	var $debug = true;

	function vfs_webdav_server()
	{
		if ($this->debug) foreach($_SERVER as $name => $val) error_log("vfs_webdav_server: \$_SERVER[$name]='$val'");

		parent::HTTP_WebDAV_Server();
		
		$this->vfs =& CreateObject('phpgwapi.vfs');
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
		if (!($vfs_files = $this->vfs->ls($vfs_data)))
		{
			if ($this->debug) error_log("vfs_webdav_server::PROPFIND(path='$options[path]',depth=$options[depth]) return false");
			return false;	// path not found
		}
/*
		if (!$this->vfs->acl_check($vfs_data+array('operation'=>EGW_ACL_READ)))
		{
			if ($this->debug) error_log("vfs_webdav_server::PROPFIND(path='$options[path]',depth=$options[depth]) return 403 forbidden");
			return '403 Forbidden';
		}
*/
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
			error_log('dir="'.$fileinfo['directory'].'", name="'.$fileinfo['name'].'": '.$fileinfo['mime_type']);
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
			'string'    => dirname($GLOBALS['egw']->translation->convert($options['path'],'utf-8')),
			'relatives'	=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
		);
		if (!$this->vfs->file_exists($path)) 
		{
			return '404 Not found';
		}
		$vfs_data = array(
			'string'    => $GLOBALS['egw']->translation->convert($options['path'],'utf-8'),
			'relatives'	=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
		);
		$this->vfs->rm($vfs_data);
		
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
	 * auth check in the session creation in dav.php, to avoid being redirected to login.php
	 *
	 * @param unknown_type $type
	 * @param unknown_type $user
	 * @param unknown_type $password
	 * @return boolean true if authorized or false otherwise
	 */
	function checkAuth($type,$user,$password)
	{
		if ($this->debug) error_log("vfs_webdav_server::checkAuth('$type','$user','$password')");
		
		return $user == $GLOBALS['egw_info']['user']['account_lid'] && $GLOBALS['egw_info']['user']['apps']['filemanager'];
	}
}