<?php
	/**************************************************************************\
	* phpGroupWare - InfoLog Links                                             *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	include_once(PHPGW_INCLUDE_ROOT . '/infolog/inc/class.solink.inc.php');
	
	$GLOBALS['phpgw_info']['flags']['included_classes']['bolink'] = True;

	/*!
	@class bolink
	@author ralfbecker
	@copyright GPL - GNU General Public License
	@abstract generalized linking between entries of phpGroupware apps - BO layer
	@discussion This class is the BO-layer of the links<br>
		Links have two ends each pointing to an entry, each entry is a double:<br>
		app   app-name or directory-name of an phpgw application, eg. 'infolog'<br>
		id    this is the id, eg. an integer or a tupple like '0:INBOX:1234'<br>
		The BO-layer implementes 2 extra features on top of the so-layer:<br>
		1) It handles links to not already existing entries. This is used by the eTemplate link-widget, which allows to
			setup links even for new / not already existing entries, before they get saved.
			In that case you have to set the first id to 0 for the link-function and pass the array returned in that id 
			(not the return-value) after saveing your new entry again to the link function.<br>
		2) Attaching files: they are saved in the vfs and not the link-table (!).
	*/
	class bolink extends solink
	{
		// other apps can participate in the linking by implementing a search_link hook, which
		// has to return an array in the format of an app_register entry
		//
		var $app_register = array(
			'addressbook' => array(
				'query' => 'addressbook_query',
				'title' => 'addressbook_title',
				'view' => array(
					'menuaction' => 'addressbook.uiaddressbook.view'
				),
				'view_id' => 'ab_id'
			),
			'projects' => array(
				'query' => 'projects_query',
				'title' => 'projects_title',
				'view' => array (
					'menuaction' => 'projects.uiprojects.view_project'
				),
				'view_id' => 'project_id'
			),
			'calendar' => array(
				'query' => 'calendar_query',
				'title' => 'calendar_title',
				'view' => array (
					'menuaction' => 'calendar.uicalendar.view'
				),
				'view_id' => 'cal_id'
			), 
			'infolog' => array(
				'query' => 'infolog.boinfolog.link_query',
				'title' => 'infolog.boinfolog.link_title',
				'view' => array(
					'menuaction' => 'infolog.uiinfolog.index',
					'action' => 'sp'
				),
				'view_id' => 'action_id',
			),
/*
			'email' => array(
				'view' => array(
					'menuaction' => 'email.uimessage.message'
				),
				'view_id' => 'msgball[acctnum:folder:msgnum]'	// id is a tupple/array, fields separated by ':'
			),
 */
		);
		var $vfs;
		var $vfs_basedir='/infolog';	// might changes to links if class gets imported in the api
		var $vfs_appname='file';		// pseudo-appname for own file-attachments in vfs, this is NOT the vfs-app
		var $valid_pathes = array();
		var $send_file_ips = array();

		/*!
		@function bolink
		@syntax bolink(   )
		@author ralfbecker
		@abstract constructor
		*/
		function bolink( )
		{
			$this->solink( );					// call constructor of derived class
			$this->public_functions += array(	// extend the public_functions of solink
				'query' => True,
				'title' => True,
				'view'  => True,
				'get_file' => True
			);
			$this->vfs = CreateObject('infolog.vfs');

			$config = CreateObject('phpgwapi.config');
			$config->read_repository();
			if (is_array($config->config_data))
			{
				$this->link_pathes   = $config->config_data['link_pathes'];
				$this->send_file_ips = $config->config_data['send_file_ips'];
			}
			unset($config);
			
			// other apps can participate in the linking by implementing a search_link hook, which
			// has to return an array in the format of an app_register entry
			//
			$search_link_hooks = $GLOBALS['phpgw']->hooks->process('search_link');
			if (is_array($search_link_hooks))
			{
				foreach($search_link_hooks as $app => $data)
				{
					if (is_array($data))
					{
						$this->app_register[$app] = $data;
					}
				}
			}
		}

		/*!
		@function link
		@syntax link(  $app1,&$id1,$app2,$id2='',$remark='',$user=0  )
		@author ralfbecker
		@abstract creats a link between $app1,$id1 and $app2,$id2 - $id1 does NOT need to exist yet
		@param $app1 app of $id1
		@param $id1 id of item to linkto or 0 if item not yet created or array with links 
			of not created item or $file-array if $app1 == $this->vfs_appname (see below).
			If $id==0 it will be set on return to an array with the links for the new item.
		@param $app2 app of 2.linkend or array with links ($id2 not used)
		@param $id2 id of 2. item of $file-array if $app2 == $this->vfs_appname (see below)<br>
			$file array with informations about the file in format of the etemplate file-type<br>
			$file['name'] name of the file (no directory)<br>
			$file['type'] mine-type of the file<br>
			$file['tmp_name'] name of the uploaded file (incl. directory)<br>
			$file['path'] path of the file on the client computer<br>
			$file['ip'] of the client (path and ip in $file are only needed if u want a symlink (if possible))
		@param $remark Remark to be saved with the link (defaults to '')
		@param $owner Owner of the link (defaults to user)
		@discussion Does NOT check if link already exists.<br> 
			File-attachments return a negative link-id !!!
		@result False (for db or param-error) or on success link_id (Please not the return-value of $id1)
		*/
		function link( $app1,&$id1,$app2,$id2='',$remark='',$owner=0,$lastmod=0 )
		{
			if ($this->debug)
			{
				echo "<p>bolink.link('$app1',$id1,'$app2',$id2,'$remark',$owner,$lastmod)</p>\n";
			}
			if (!$app1 || !$app2 || $app1 == $app2 && $id1 == $id2)
			{
				return False;
			}
			if (is_array($id1) || !$id1)		// create link only in $id1 array
			{
				if (!is_array($id1))
				{
					$id1 = array( );
				}
				$link_id = $app2 != $this->vfs_appname ? "$app2:$id2" : "$app2:$id2[name]";
				$id1[$link_id] = array(
					'app' => $app2,
					'id'  => $id2,
					'remark' => $remark,
					'owner'  => $owner,
					'link_id' => $link_id,
					'lastmod' => time()
				);
				if ($this->debug)
				{
					_debug_array($id1);
				}
				return $link_id;
			}
			if (is_array($app2) && !$id2)
			{
				reset($app2);
				$link_id = True;
				while ($link_id && list(,$link) = each($app2))
				{
					if (!is_array($link))	// check for unlink-marker
					{
						continue;
					}
					if ($link['app'] == $this->vfs_appname)
					{
						$link_id = -intval($this->attach_file($app1,$id1,$link['id'],$link['remark']));
					}
					else
					{
						$link_id = solink::link($app1,$id1,$link['app'],$link['id'],
							$link['remark'],$link['owner'],$link['lastmod']);
					}
				}
				return $link_id;
			}
			if ($app1 == $this->vfs_appname)
			{
				return -intval($this->attach_file($app2,$id2,$id1,$remark));
			}
			elseif ($app2 == $this->vfs_appname)
			{
				return -intval($this->attach_file($app1,$id1,$id2,$remark));
			}
			return solink::link($app1,$id1,$app2,$id2,$remark,$owner);
		}

		/*!
		@function get_links
		@syntax get_links(  $app,$id,$only_app='',$only_name='',$order='link_lastmod DESC'  )
		@author ralfbecker
		@abstract returns array of links to $app,$id (reimplemented to deal with not yet created items)
		@param $id id of entry in $app or array of links if entry not yet created
		@param $only_app if set return only links from $only_app (eg. only addressbook-entries) or NOT from if $only_app[0]=='!'
		@param $order defaults to newest links first
		@result array of links or empty array if no matching links found
		*/
		function get_links( $app,$id,$only_app='',$order='link_lastmod DESC' )
		{
			//echo "<p>bolink::get_links(app='$app',id='$id',only_app='$only_app',order='$order')</p>\n";

			if (is_array($id) || !$id)
			{
				$ids = array();
				if (is_array($id))
				{
					if ($not_only = $only_app[0])
					{
						$only_app = substr(1,$only_app);
					}
					end($id);
					while ($link = current($id))
					{
						if (!is_array($link) ||		// check for unlink-marker
						    $only_app && $not_only == ($link['app'] == $only_app))
						{
							continue;
						}
						$ids[$link['link_id']] = $link;
						prev($id);
					}
				}
				return $ids;
			}
			$ids = solink::get_links($app,$id,$only_app,$order);

			if (empty($only_app) || $only_apps == $this->vfs_appname ||
			    ($only_app[0] == '!' && $only_app != '!'.$this->vfs_appname))
			{
				if ($vfs_ids = $this->list_attached($app,$id))
				{
					$ids += $vfs_ids;
				}
			}
			//echo "ids=<pre>"; print_r($ids); echo "</pre>\n";

			return $ids;
		}

		/*!
		@function get_link
		@syntax get_link(  $app_link_id,$id='',$app2='',$id2='' )
		@author ralfbecker
		@abstract returns data of a link
		@param $app_link_id > 0 link_id of link or app-name of link
		@param $id,$app2,$id2 other param of the link if not link_id given
		@result array with link-data or False
		@discussion If $id is an array (links not yet created) only link_ids are allowed.
		*/ 
		function get_link($app_link_id,$id='',$app2='',$id2='')
		{
			if (is_array($id))
			{
				if (isset($id[$app_link_id]) && is_array($id[$app_link_id]))	// check for unlinked-marker
				{
					return $id[$app_link_id];
				}
				return False;
			}
			if (intval($app_link_id) < 0 || $app_link_id == $this->vfs_appname || $app2 == $this->vfs_appname)
			{
				if (intval($app_link_id) < 0)	// vfs link_id ?
				{
					return $this->fileinfo2link(-$app_link_id);
				}
				if ($app_link_id == $this->vfs_appname)
				{
					return $this->info_attached($app2,$id2,$id);
				}
				return $this->info_attached($app_link_id,$id,$id2);
			}
			return solink::get_link($app_link_id,$id,$app2,$id2);
		}

		/*!
		@function unlink
		@syntax unlink( $link_id,$app='',$id='',$owner='',$app2='',$id2='' )
		@author ralfbecker
		@abstract Remove link with $link_id or all links matching given $app,$id
		@param $link_id link-id to remove if > 0
		@param $app,$id,$owner,$app2,$id2 if $link_id <= 0: removes all links matching the non-empty params
		@discussion Note: if $link_id != '' and $id is an array: unlink removes links from that array only
			unlink has to be called with &$id so see the result !!!
		@result the number of links deleted
		*/
		function unlink($link_id,$app='',$id='',$owner='',$app2='',$id2='')
		{
			if ($link_id < 0)	// vfs-link?
			{
				return $this->delete_attached(-$link_id);
			}
			elseif ($app == $this->vfs_appname)
			{
				return $this->delete_attached($app2,$id2,$id);
			}
			elseif ($app2 == $this->vfs_appname)
			{
				return $this->delete_attached($app,$id,$id2);
			}
			if ($link_id > 0 || !is_array($id))
			{
				return solink::unlink($link_id,$app,$id,$owner,$app2,$id2);
			}
			if (isset($id[$link_id]))
			{
				$id[$link_id] = False;	// set the unlink marker

				return True;
			}
			return False;
		}

		/*!
		@function app_list
		@syntax app_list(   )
		@author ralfbecker
		@abstract get list/array of link-aware apps the user has rights to use
		@result array( $app => lang($app), ... )
		*/
		function app_list( )
		{
			reset ($this->app_register);
			$apps = array();
			while (list($app,$reg) = each($this->app_register))
			{
				if ($GLOBALS['phpgw_info']['user']['apps'][$app])
				{
					$apps[$app] = $GLOBALS['phpgw_info']['apps'][$app]['title'];
				}
			}
			return $apps;
		}

		/*!
		@function query
		@syntax query( $app,$pattern )
		@author ralfbecker
		@abstract Searches for a $pattern in the entries of $app
		@result an array of $id => $title pairs
		*/
		function query($app,$pattern)
		{
			if ($app == '' || !is_array($reg = $this->app_register[$app]) || !isset($reg['query']))
			{
				return array();
			}
			$method = $reg['query'];

			if ($this->debug)
			{
				echo "<p>bolink.query('$app','$pattern') => '$method'</p>\n";
			}
			return strchr($method,'.') ? ExecMethod($method,$pattern) : $this->$method($pattern);
		}

		/*!
		@function title
		@syntax title( $app,$id )
		@author ralfbecker
		@abstract returns the title (short description) of entry $id and $app
		@result the title
		*/
		function title($app,$id,$link='')
		{
			if ($this->debug)
			{
				echo "<p>bolink::title('$app','$id')</p>\n";
			}
			if ($app == $this->vfs_appname)
			{
				if (is_array($id) && $link)
				{
					$link = $id;
					$id = $link['name'];
				}
				if (is_array($link))
				{
					$size = $link['size'];
					if ($size_k = intval($size / 1024))
					{
						if (intval($size_k / 1024))
						{
							$size = sprintf('%3.1dM',doubleval($size_k)/1024.0);
						}
						else
						{
							$size = $size_k.'k';
						}
					}
					$extra = ': '.$link['type'] . ' '.$size;
				}
				return $id.$extra;
			}
			if ($app == '' || !is_array($reg = $this->app_register[$app]) || !isset($reg['title']))
			{
				return array();
			}
			$method = $reg['title'];

			return strchr($method,'.') ? ExecMethod($method,$id) : $this->$method($id);
		}

		/*!
		@function view
		@syntax view( $app,$id )
		@author ralfbecker
		@abstract view entry $id of $app
		@result array with name-value pairs for link to view-methode of $app to view $id
		*/
		function view($app,$id,$link='')
		{
			if ($app == $this->vfs_appname && !empty($id) && is_array($link))
			{
				return $this->get_file($link);
			}
			if ($app == '' || !is_array($reg = $this->app_register[$app]) || !isset($reg['view']) || !isset($reg['view_id']))
			{
				return array();
			}
			$view = $reg['view'];

			$names = explode(':',$reg['view_id']);
			if (count($names) > 1)
			{
				$id = explode(':',$id);
				while (list($n,$name) = each($names))
				{
					$view[$name] = $id[$n];
				}
			}
			else
			{
				$view[$reg['view_id']] = $id;
			}
			return $view;
		}
		
		function get_file($link='')
		{
			if (is_array($link))
			{
				return array(
					'menuaction' => 'infolog.bolink.get_file',
					'app' => $link['app2'],
					'id'  => $link['id2'],
					'filename' => $link['id']
				);
			}
			$app = get_var('app','GET');
			$id  = get_var('id','GET');
			$filename = get_var('filename','GET');

			if (empty($app) || empty($id) || empty($filename) /* || !$this->bo->check_access($info_id,PHPGW_ACL_READ)*/)
			{
				$GLOBALS['phpgw']->redirect_link('/');
			}
			$browser = CreateObject('phpgwapi.browser');

			$local = $this->attached_local($app,$id,$filename,
				get_var('REMOTE_ADDR',Array('SERVER')),$browser->is_windows());

			if ($local)
			{
				Header('Location: ' . $local);
			}
			else
			{
				$info = $this->info_attached($app,$id,$filename);
				$browser->content_header($filename,$info['type']);
				echo $this->read_attached($app,$id,$filename);
			}
			$GLOBALS['phpgw']->common->phpgw_exit();
		}

		/*!
		@function vfs_path
		@syntax vfs_path ( $app,$id,$file='' )
		@abstract path to the attached files of $app/$ip
		@discussion All link-files are based in the vfs-subdir 'infolog'. For other apps
		@discussion separate subdirs with name app are created.
		*/
		function vfs_path($app,$id='',$file='')
		{
			$path = $this->vfs_basedir . ($app == '' || $app == 'infolog' ? '' : '/'.$app) .
				($id != '' ? '/' . $id : '') . ($file != '' ? '/' . $file : '');
			
			if ($this->debug)
			{
				echo "<p>bolink::vfs_path('$app','$id','$file') = '$path'</p>\n";
			}
			return $path;
		}

		/*!
		@function attach_file
		@syntax attach_file ( $app,$id,$file,$comment='' )
		@abstract Put a file to the corrosponding place in the VFS and set the attributes
		@param $app/$id entry which should the file should be linked with
		@param $file array with informations about the file in format of the etemplate file-type
			$file['name'] name of the file (no directory)
			$file['type'] mine-type of the file
			$file['tmp_name'] name of the uploaded file (incl. directory)
			$file['path'] path of the file on the client computer
			$file['ip'] of the client (path and ip are only needed if u want a symlink (if possible))
		@param $comment
		*/
		function attach_file($app,$id,$file,$comment='')
		{
			if ($this->debug)
			{
				echo "<p>attach_file: app='$app', id='$id', tmp_name='$file[tmp_name]', name='$file[name]', size='$file[size]', type='$file[type]', path='$file[path]', ip='$file[ip]', comment='$comment'</p>\n";
			}
			// create the root for attached files in infolog, if it does not exists
			if (!($this->vfs->file_exists($this->vfs_basedir,array(RELATIVE_ROOT))))
			{
				$this->vfs->override_acl = 1;
				$this->vfs->mkdir($this->vfs_basedir,array(RELATIVE_ROOT));
				$this->vfs->override_acl = 0;
			}

			$dir=$this->vfs_path($app);
			if (!($this->vfs->file_exists($dir,array(RELATIVE_ROOT))))
			{
				$this->vfs->override_acl = 1;
				$this->vfs->mkdir($dir,array(RELATIVE_ROOT));
				$this->vfs->override_acl = 0;
			}
			$dir=$this->vfs_path($app,$id);
			if (!($this->vfs->file_exists($dir,array(RELATIVE_ROOT))))
			{
				$this->vfs->override_acl = 1;
				$this->vfs->mkdir($dir,array(RELATIVE_ROOT));
				$this->vfs->override_acl = 0;
			}
			$fname = $this->vfs_path($app,$id,$file['name']);
			$tfname = '';
			if (!empty($file['path']))
			{
				$file['path'] = str_replace('\\\\','/',$file['path']);	// vfs uses only '/'
				@reset($this->link_pathes);
				while ((list($valid,$trans) = @each($this->link_pathes)) && !$tfname)
				{  // check case-insensitive for WIN etc.
					$check = $valid[0] == '\\' || strstr(':',$valid) ? 'eregi' : 'ereg';
					$valid2 = str_replace('\\','/',$valid);
					//echo "<p>attach_file: ereg('".$this->send_file_ips[$valid]."', '$file[ip]')=".ereg($this->send_file_ips[$valid],$file['ip'])."</p>\n";
					if ($check('^('.$valid2.')(.*)$',$file['path'],$parts) &&
					    ereg($this->send_file_ips[$valid],$file['ip']) &&     // right IP
					    $this->vfs->file_exists($trans.$parts[2],array(RELATIVE_NONE|VFS_REAL)))
					{
						$tfname = $trans.$parts[2];
					}
					//echo "<p>attach_file: full_fname='$file[path]', valid2='$valid2', trans='$trans', check=$check, tfname='$tfname', parts=(x,'${parts[1]}','${parts[2]}')</p>\n";
				}
				if ($tfname && !$this->vfs->securitycheck($tfname))
				{
					return False; //lang('Invalid filename').': '.$tfname;
				}
			}
			$this->vfs->override_acl = 1;
			if ($tfname)	// file is local
			{
				$this->vfs->symlink($tfname,$fname,array(RELATIVE_NONE|VFS_REAL,RELATIVE_ROOT));
			}
			else
			{
				$this->vfs->cp($file['tmp_name'],$fname,array(RELATIVE_NONE|VFS_REAL,RELATIVE_ROOT));
			}
			$this->vfs->set_attributes ($fname, array (RELATIVE_ROOT),
				array ('mime_type' => $file['type'],
						 'comment' => stripslashes ($comment),
						 'app' => $app));
			$this->vfs->override_acl = 0;

			$link = $this->info_attached($app,$id,$file['name']);
			return is_array($link) ? $link['file_id'] : False;
		}

		/*!
		@function delete_attached
		@syntax delete_attached( $app,$id,$filename )
		@author ralfbecker
		@abstract deletes an attached file
		@param $app > 0: file_id of an attchemnt or $app/$id entry which linked to
		@param $filename
		*/
		function delete_attached($app,$id='',$fname = '')
		{
			if (intval($app) > 0)	// is file_id
			{
				$link  = $this->fileinfo2link($file_id=$app);
				$app   = $link['app2'];
				$id    = $link['id2'];
				$fname = $link['id'];
			}
			if ($this->debug)
			{
				echo "<p>bolink::delete_attached('$app','$id','$fname') file_id=$file_id</p>\n";
			}
			if (empty($app) || empty($id))
			{
				return False;	// dont delete more than all attachments of an entry
			}
			$file = $this->vfs_path($app,$id,$fname);

			if ($this->vfs->file_exists($file,array(RELATIVE_ROOT)))
			{
				$this->vfs->override_acl = 1;
				$Ok = $this->vfs->delete($file,array(RELATIVE_ROOT));
				$this->vfs->override_acl = 0;
				return $Ok;
			}
			return False;
		}

		/*!
		@function info_attached
		@syntax info_attached( $app,$id,$filename )
		@author ralfbecker
		@abstract converts the infos vfs has about a file into a link
		@param $app/$id entry which linked to
		@param $filename
		@returns a 'kind' of link-array
		*/
		function info_attached($app,$id,$filename)
		{
			$this->vfs->override_acl = 1;
			$attachments = $this->vfs->ls($this->vfs_path($app,$id,$filename),array(REALTIVE_NONE));
			$this->vfs->override_acl = 0;

			if (!count($attachments) || !$attachments[0]['name'])
			{
				return False;
			}
			return $this->fileinfo2link($attachments[0]);
		}

		/*!
		@function fileinfo2link
		@syntax fileinfo2link( $fileinfo )
		@author ralfbecker
		@abstract converts a fileinfo (row in the vfs-db-table) in a link
		@param $fileinfo a row from the vfs-db-table (eg. returned by the vfs ls function)
			or a file_id of that table
		@returns a 'kind' of link-array
		*/
		function fileinfo2link($fileinfo)
		{
			if (!is_array($fileinfo))
			{
				$fileinfo = $this->vfs->fileinfo($fileinfo);
				list(,$fileinfo) = each($fileinfo); 

				if (!is_array($fileinfo))
				{
					return False;
				}
			}
			$lastmod = $fileinfo[!empty($fileinfo['modified']) ? 'modified' : 'created'];
			list($y,$m,$d) = explode('-',$lastmod);
			$lastmod = mktime(0,0,0,$m,$d,$y);

			$dir_parts = array_reverse(explode('/',$fileinfo['directory']));

			return array(
				'app'       => $this->vfs_appname,
				'id'        => $fileinfo['name'],
				'app2'      => $dir_parts[1],
				'id2'       => $dir_parts[0],
				'remark'    => $fileinfo['comment'],
				'owner'     => $fileinfo['owner_id'],
				'link_id'   => -$fileinfo['file_id'],
				'lastmod'   => $lastmod,
				'size'      => $fileinfo['size'],
				'type'      => $fileinfo['mime_type']
			);
		}

		/*!
		@function list_attached
		@syntax list_attached( $app,$id )
		@author ralfbecker
		@abstract lists all attachments to $app/$id
		@returns a 'kind' of link-array
		*/
		function list_attached($app,$id)
		{
			$this->vfs->override_acl = 1;
			$attachments = $this->vfs->ls($this->vfs_path($app,$id),array(REALTIVE_NONE));
			$this->vfs->override_acl = 0;

			if (!count($attachments) || !$attachments[0]['name'])
			{
				return False;
			}
			while (list(,$fileinfo) = each($attachments))
			{
				$link = $this->fileinfo2link($fileinfo);
				$attached[$link['link_id']] = $link;
			}
			return $attached;
		}

		/*!
		@function is_win_path
		@syntax is_win_path( $path )
		@author ralfbecker
		@abstract checks if path starts with a '\\' or has a ':' in it
		*/
		function is_win_path($path)
		{
			return $path[0] == '\\' || strstr($path,':');
		}

		/*!
		@function read_attached
		@syntax read_attached($app,$id,$filename)
		@author ralfbecker
		@abstract reads the attached file and returns the content
		*/
		function read_attached($app,$id,$filename)
		{
			if (empty($app) || !$id || empty($filename) /*|| !$this->check_access($info_id,PHPGW_ACL_READ)*/)
			{
				return False;
			}
			$this->vfs->override_acl = 1;
			return $this->vfs->read($this->vfs_path($app,$id,$filename),array(RELATIVE_ROOT));
		}

		/*!
		@function attached_local
		@syntax attached_local($app,$id,$filename,$ip,$win_user)
		@author ralfbecker
		@abstract Checks if filename should be local availible and if so returns
		@abstract 'file:/path' for HTTP-redirect else return False
		*/
		function attached_local($app,$id,$filename,$ip,$win_user)
		{
			//echo "<p>attached_local(app=$app, id='$id', filename='$filename', ip='$ip', win_user='$win_user', count(send_file_ips)=".count($this->send_file_ips).")</p>\n";

			if (!$id || !$filename || /* !$this->check_access($info_id,PHPGW_ACL_READ) || */
			    !count($this->send_file_ips))
			{
				return False;
			}
			$link = $this->vfs->readlink ($this->vfs_path($app,$id,$filename), array (RELATIVE_ROOT));

			if ($link && is_array($this->link_pathes))
			{
				reset($this->link_pathes); $fname = '';
				while ((list($valid,$trans) = each($this->link_pathes)) && !$fname)
				{
					if (!$this->is_win_path($valid) == !$win_user && // valid for this OS
					    eregi('^'.$trans.'(.*)$',$link,$parts)  &&    // right path
					    ereg($this->send_file_ips[$valid],$ip))      // right IP
					{
						$fname = $valid . $parts[1];
						$fname = !$win_user ? str_replace('\\','/',$fname) : str_replace('/','\\',$fname);
						return 'file:'.($win_user ? '//' : '' ).$fname;
					}
					// echo "<p>attached_local: link=$link, valid=$valid, trans='$trans', fname='$fname', parts=(x,'${parts[1]}','${parts[2]}')</p>\n";
				}
			}
			return False;
		}

		/*!
		@function calendar_title
		@syntax calendar_title(  $id  )
		@author ralfbecker
		@abstract get title for an event, should be moved to bocalendar.link_title
		*/
		function calendar_title( $event )
		{
			if (!is_object($this->bocal))
			{
				$this->bocal = createobject('calendar.bocalendar');
			}
			if (!is_array($event) && (int) $event > 0)
			{
				$event = $this->bocal->read_entry($event);
			}
			if (!is_array($event))
			{
				return 'not an event !!!';
			}
			$name = $GLOBALS['phpgw']->common->show_date($this->bocal->maketime($event['start']) - $this->bocal->datetime->tz_offset);
			$name .= ' -- ' . $GLOBALS['phpgw']->common->show_date($this->bocal->maketime($event['end']) - $this->bocal->datetime->tz_offset);
			$name .= ': ' . $event['title'];

			return $name;
		}

		/*!
		@function calendar_query
		@syntax calendar_query(  $pattern  )
		@author ralfbecker
		@abstract query calendar for an event $matching pattern, should be moved to bocalendar.link_query
		*/
		function calendar_query($pattern)
		{
			if (!is_object($this->bocal))
			{
				$this->bocal = createobject('calendar.bocalendar');
			}
			$event_ids = $this->bocal->search_keywords($pattern);

			$content = array( );
			while (is_array($event_ids) && list( $key,$id ) = each( $event_ids ))
			{
				$content[$id] = $this->calendar_title( $id );
			}
			return $content;
		}

		/*!
		@function addressbook_title
		@syntax addressbook_title(  $id  )
		@author ralfbecker
		@abstract get title for an address, should be moved to boaddressbook.link_title
		*/
		function addressbook_title( $addr )
		{
			if (!is_object($this->contacts))
			{
				$this->contacts = createobject('phpgwapi.contacts');
			}
			if (!is_array($addr))
			{
				list( $addr ) = $this->contacts->read_single_entry( $addr );
			}
			$name = $addr['n_family'];
			if ($addr['n_given'])
			{
				$name .= ', '.$addr['n_given'];
			}
			else
			{
				if ($addr['n_prefix'])
				{
					$name .= ', '.$addr['n_prefix'];
				}
			}
			if ($addr['org_name'])
			{
				$name = $addr['org_name'].($name !== '' ? ': '.$name : '');
			}
			return stripslashes($name);		// addressbook returns quotes with slashes
		}

		/*!
		@function addressbook_query
		@syntax addressbook_query(  $pattern  )
		@author ralfbecker
		@abstract query addressbook for $pattern, should be moved to boaddressbook.link_query
		*/
		function addressbook_query( $pattern )
		{
			if (!is_object($this->contacts))
			{
				$this->contacts = createobject('phpgwapi.contacts');
			}
			$addrs = $this->contacts->read( 0,0,'',$pattern,'','DESC','org_name,n_family,n_given' );
			$content = array( );
			while ($addrs && list( $key,$addr ) = each( $addrs ))
			{
				$content[$addr['id']] = $this->addressbook_title( $addr );
			}
			return $content;
		}

		/*!
		@function projects_title
		@syntax projects_title(  $id  )
		@author ralfbecker
		@abstract get title for a project, should be moved to boprojects.link_title
		*/
		function projects_title( $proj )
		{
			if (!is_object($this->boprojects))
			{
				if (!file_exists(PHPGW_SERVER_ROOT.'/projects'))	// check if projects installed
					return '';  
				$this->boprojects = createobject('projects.boprojects');
			}
			if (!is_array($proj))
			{
				$proj = $this->boprojects->read_single_project( $proj );
			}
			return $proj['title'];
		}

		/*!
		@function projects_query
		@syntax projects_query(  $pattern  )
		@author ralfbecker
		@abstract query for projects matching $pattern, should be moved to boprojects.link_query
		*/
		function projects_query( $pattern )
		{
			if (!is_object($this->boprojects))
			{
				if (!file_exists(PHPGW_SERVER_ROOT.'/projects'))	// check if projects installed
					return array();
				$this->boprojects = createobject('projects.boprojects');
			}
			$projs = $this->boprojects->list_projects( 0,0,$pattern,'','','','',0,'mains','' );
			$content = array();
			while ($projs && list( $key,$proj ) = each( $projs ))
			{
				$content[$proj['project_id']] = $this->projects_title($proj);
			}
			return $content;
		}
	}




