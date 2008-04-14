<?php
/**
 * eGroupWare - Filemanager - user interface
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

class filemanager_ui
{
	/**
	 * Methods callable via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'index' => true,
		'file' => true,
	);
	
	/**
	 * Main filemanager page
	 *
	 * @param array $content=null
	 * @param string $msg=null
	 */
	function index(array $content=null,$msg=null)
	{
		$tpl = new etemplate('filemanager.index');

		//_debug_array($content);
		
		if (!is_array($content))
		{
			$content = array(
				'nm' => $GLOBALS['egw']->session->appsession('index','filemanager'),
			);
			if (!is_array($content['nm']))
			{
				$content['nm'] = array(
					'get_rows'       =>	'filemanager.filemanager_ui.get_rows',	// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
					'filter'         => '1',	// current dir only
					'no_filter2'     => True,	// I  disable the 2. filter (params are the same as for filter)
					'no_cat'         => True,	// I  disable the cat-selectbox
					'lettersearch'   => True,	// I  show a lettersearch
					'searchletter'   =>	false,	// I0 active letter of the lettersearch or false for [all]
					'start'          =>	0,	// IO position in list
					'order'          =>	'name',	// IO name of the column to sort after (optional for the sortheaders)
					'sort'           =>	'ASC',	// IO direction of the sort: 'ASC' or 'DESC'
					'default_cols'   => '!comment,ctime',	// I  columns to use if there's no user or default pref (! as first char uses all but the named columns), default all columns
					'csv_fields'     =>	false, // I  false=disable csv export, true or unset=enable it with auto-detected fieldnames, 
									//or array with name=>label or name=>array('label'=>label,'type'=>type) pairs (type is a eT widget-type)
					'path' => '/home/'.$GLOBALS['egw_info']['user']['account_lid'],
				);
			}
			if (isset($_GET['path']) && ($path = $_GET['path']) && $path[0] == '/' && egw_vfs::is_dir($path))
			{
				$content['nm']['path'] = $path;
			}
			if (isset($_GET['msg'])) $msg = $_GET['msg'];
		}
		// check if we have a failed upload AND upload_max_filesize >= post_max_size --> no $_POST array
		if ($_GET['post_empty'] && self::km2int(ini_get('upload_max_filesize')) >= self::km2int(ini_get('post_max_size')) ||
			// or size bigger as upload_max_filesize
			$content['button']['upload'] && (!is_array($content['upload']) || !is_uploaded_file($content['upload']['tmp_name'])))
		{
			$msg = lang('Error uploading file!')."\n".self::max_upload_size_message();
			unset($content['button']);
		}
		$content['nm']['msg'] = $msg;

		if ($content['action'] || $content['nm']['rows'])
		{
			if ($content['action'])
			{
				$content['nm']['msg'] = self::action($content['action'],$content['nm']['rows']['checked'],$content['nm']['path']);
				unset($content['action']);
			}
			elseif($content['nm']['rows']['delete'])
			{
				$content['nm']['msg'] = self::action('delete',array_keys($content['nm']['rows']['delete']),$content['nm']['path']);
			}
			unset($content['nm']['rows']);
		}
		$clipboard_files = $GLOBALS['egw']->session->appsession('clipboard_files','filemanager');
		$clipboard_type = $GLOBALS['egw']->session->appsession('clipboard_type','filemanager');

		if ($content['button'])
		{
			if ($content['button'])
			{
				list($button) = each($content['button']);
				unset($content['button']);
			}
			switch($button)
			{
				case 'up':
					if ($content['nm']['path'] != '/')
					{
						$content['nm']['path'] = dirname($content['nm']['path']);
					}
					break;
				case 'home':
					$content['nm']['path'] = '/home/'.$GLOBALS['egw_info']['user']['account_lid'];
					break;
				case 'createdir':
					if ($content['nm']['path'][0] != '/')
					{
						$ses = $GLOBALS['egw']->session->appsession('index','filemanager');
						$old_path = $ses['path'];
						$content['nm']['path'] = egw_vfs::concat($old_path,$content['nm']['path']);
					}
					if (!@egw_vfs::mkdir($content['nm']['path'],null,STREAM_MKDIR_RECURSIVE))
					{
						$content['nm']['msg'] = !egw_vfs::is_writable(dirname($content['nm']['path'])) ?
							lang('Permission denied!') : lang('Failed to create directory!');
						if (!$old_path)
						{
							$ses = $GLOBALS['egw']->session->appsession('index','filemanager');
							$old_path = $ses['path'];
						}
						$content['nm']['path'] = $old_path;
					}
					break;
				case 'paste':
					$content['nm']['msg'] = self::action($clipboard_type.'_paste',$clipboard_files,$content['nm']['path']);
					break;
				case 'upload':
					$to = egw_vfs::concat($content['nm']['path'],$content['upload']['name']);
					if ($content['upload'] && is_uploaded_file($content['upload']['tmp_name']) && 
						(egw_vfs::is_writable($content['nm']['path']) || egw_vfs::is_writable($to)) &&
						copy($content['upload']['tmp_name'],egw_vfs::PREFIX.$to))
					{
						$content['nm']['msg'] = lang('File successful uploaded.');
					}
					else
					{
						$content['nm']['msg'] = lang('Error uploading file!');
					}
					break;
			}
		}
		if (!egw_vfs::is_dir($content['nm']['path']))
		{
			$content['nm']['msg'] .= lang('Directory not found or no permission to access it!');
		}
		else
		{
			$dir_is_writable = egw_vfs::is_writable($content['nm']['path']);
		}
		$content['paste_tooltip'] = $clipboard_files ? '<p><b>'.lang('%1 the following files into current directory',
			$clipboard_type=='copy'?lang('Copy'):lang('Move')).':</b><br />'.implode('<br />',$clipboard_files).'</p>' : '';
		$content['upload_size'] = self::max_upload_size_message();
		//_debug_array($content);

		$readonlys['button[paste]'] = !$clipboard_files;
		$readonlys['button[createdir]'] = !$dir_is_writable;
		$readonlys['button[upload]'] = $readonlys['upload'] = !$dir_is_writable;
		
		if ($dir_is_writable || !$content['nm']['filter']) $sel_options['action']['delete'] = lang('Delete');
		$sel_options['action']['copy'] = lang('Copy to clipboard');
		if ($dir_is_writable || !$content['nm']['filter']) $sel_options['action']['cut'] = lang('Cut to clipboard');
		
		$sel_options['filter'] = array(
			'1' => 'Current directory',
			''  => 'All subdirectories',
		);
		$tpl->exec('filemanager.filemanager_ui.index',$content,$sel_options,$readonlys,array('nm' => $content['nm']));
	}

	/**
	 * Convert numbers like '32M' or '512k' to integers
	 *
	 * @param string $size
	 * @return int
	 */
	private static function km2int($size)
	{
		if (!is_numeric($size))
		{
			switch(strtolower(substr($size,-1)))
			{
				case 'm':
					$size = 1024*1024*(int)$size;
					break;
				case 'k':
					$size = 1024*(int)$size;
					break;
			}
		}
		return (int)$size;
	}

	/**
	 * Message containing the max Upload size from the current php.ini settings
	 * 
	 * We have to take the smaler one of upload_max_filesize AND post_max_size-2800 into account.
	 * memory_limit does NOT matter any more, because of the stream-interface of the vfs.
	 *
	 * @return string
	 */
	static function max_upload_size_message()
	{
		$upload_max_filesize = ini_get('upload_max_filesize');
		$post_max_size = ini_get('post_max_size');
		$max_upload = min(self::km2int($upload_max_filesize),self::km2int($post_max_size)-2800);
		
		return lang('Maximum size for uploads').': '.egw_vfs::hsize($max_upload).
			" (php.ini: upload_max_filesize=$upload_max_filesize, post_max_size=$post_max_size)";
	}

	/**
	 * Run a certain action with the selected file
	 *
	 * @param string $action
	 * @param array $selected selected pathes
	 * @param mixed $dir=null current directory
	 * @return string success or failure message displayed to the user
	 */
	static private function action($action,$selected,$dir=null)
	{
		//echo '<p>'.__METHOD__."($action,array(".implode(', ',$selected).",$dir)</p>\n";
		if (!count($selected))
		{
			return lang('You need to select some files first!');
		}
		$errs = $dirs = $files = 0;
		switch($action)
		{
			case 'delete':
				$dirs = $files = $errs = 0;
				foreach(egw_vfs::find($selected,array('depth'=>true)) as $path)
				{
					if (($is_dir = egw_vfs::is_dir($path)) && egw_vfs::rmdir($path,0))
					{
						++$dirs;
					}
					elseif (!$is_dir && egw_vfs::unlink($path))
					{
						++$files;
					}
					else
					{
						++$errs;
					}
				}
				if ($errs)
				{
					return lang('%1 errors deleteting (%2 directories and %3 files deleted)!',$errs,$dirs,$files);
				}
				if ($dirs)
				{
					return lang('%1 directories and %2 files deleted.',$dirs,$files);
				}
				return $files == 1 ? lang('File deleted.') : lang('%1 files deleted.',$files);
				
			case 'copy':
			case 'cut':
				$GLOBALS['egw']->session->appsession('clipboard_files','filemanager',$selected);
				$GLOBALS['egw']->session->appsession('clipboard_type','filemanager',$action);
				return lang('%1 URLs %2 to clipboard.',count($selected),$action=='copy'?lang('copied'):lang('cut'));
				
			case 'copy_paste':
				foreach($selected as $path)
				{
					if (!egw_vfs::is_dir($path))
					{
						$to = egw_vfs::concat($dir,egw_vfs::basename($path));
						if ($path != $to && egw_vfs::copy($path,$to))
						{
							++$files;
						}
						else
						{
							++$errs;
						}
					}
					else
					{
						$len = strlen(dirname($path));
						foreach(egw_vfs::find($path) as $p)
						{
							$to = $dir.substr($p,$len);
							if ($to == $p)	// cant copy into itself!
							{
								++$errs;
								continue;
							}
							if (($is_dir = egw_vfs::is_dir($p)) && egw_vfs::mkdir($to,null,STREAM_MKDIR_RECURSIVE))
							{
								++$dirs;
							}
							elseif(!$is_dir && egw_vfs::copy($p,$to))
							{
								++$files;
							}
							else
							{
								++$errs;
							}
						}
					}
				}
				if ($errs)
				{
					return lang('%1 errors copying (%2 diretories and %3 files copied)!',$errs,$dirs,$files);
				}
				return $dirs ? lang('%1 directories and %2 files copied.',$dirs,$files) : lang('%1 files copied.',$files);
				
			case 'cut_paste':
				foreach($selected as $path)
				{
					$to = egw_vfs::concat($dir,egw_vfs::basename($path));
					if ($path != $to && egw_vfs::rename($path,$to))
					{
						++$files;
					}
					else
					{
						++$errs;
					}
				}
				$GLOBALS['egw']->session->appsession('clipboard_files','filemanager',false);	// cant move again
				if ($errs)
				{
					return lang('%1 errors moving (%2 files moved)!',$errs,$files);
				}
				return lang('%1 files moved.',$files);
		}
		return "Unknown action '$action'!";
	}

	/**
	 * Get the closest mime icon
	 *
	 * @param string $mime_type
	 * @param int $size=16
	 * @param boolean $et_image=true return $app/$icon string for etemplate (default) or html image tag if false
	 * @return string
	 */
	static function mime_icon($mime_type, $size=16)
	{
		if ($mime_type == egw_vfs::DIR_MIME_TYPE)
		{
			$mime_type = 'Directory';
		}
		if(!$mime_type)
		{
			$mime_type='unknown';
		}
		$mime_type=	strtolower(str_replace	('/','_',$mime_type));
		list($mime_part) = explode('_',$mime_type);

		if (!($img=$GLOBALS['egw']->common->image('filemanager',$icon='mime'.$size.'_'.$mime_type)) &&
			!($img=$GLOBALS['egw']->common->image('filemanager',$icon='mime'.$size.'_'.$mime_part)))
		{
			$img = $GLOBALS['egw']->common->image('filemanager',$icon='mime'.$size.'_unknown');
		}
		return 'filemanager/'.$icon;
	}

	/**
	 * Callback to fetch the rows for the nextmatch widget
	 *
	 * @param array $query
	 * @param array &$rows
	 * @param array &$readonlys
	 */
	function get_rows($query,&$rows,&$readonlys)
	{
		$GLOBALS['egw']->session->appsession('index','filemanager',$query);
		//_debug_array($query);

		if (!egw_vfs::is_dir($query['path']))
		{
			$rows = array();
			$query['total'] = 0;
		}
		$rows = $dir_is_writable = array();
		if($query['searchletter'] && !empty($query['search']))
		{
			$namefilter = '/^'.$query['searchletter'].'.*'.str_replace(array('\\?','\\*'),array('.{1}','.*'),preg_quote($query['search'])).'/i';
			if ($query['searchletter'] == strtolower($query['search'][0]))
			{
				$namefilter = '/^('.$query['searchletter'].'.*'.str_replace(array('\\?','\\*'),array('.{1}','.*'),preg_quote($query['search'])).'|'.
					str_replace(array('\\?','\\*'),array('.{1}','.*'),preg_quote($query['search'])).')/i';
			}
		}
		elseif ($query['searchletter'])
		{
			$namefilter = '/^'.$query['searchletter'].'/i';
		}
		elseif(!empty($query['search']))
		{
			$namefilter = '/'.str_replace(array('\\?','\\*'),array('.{1}','.*'),preg_quote($query['search'])).'/i';
		}
		foreach(egw_vfs::find($query['path'],array(
			'mindepth' => 1, 
			'maxdepth' => $query['filter'] ? $query['filter'] : null,
			'order' => $query['order'], 'sort' => $query['sort'],
			'limit' => (int)$query['num_rows'].','.(int)$query['start'],
			'need_mime' => true,
			'name_preg' => $namefilter,
		),true) as $path => $row)
		{
			$row['icon'] = self::mime_icon($row['mime']);
			$row['perms'] = egw_vfs::int2mode($row['mode']);
			// only show link if we have access to the file or dir
			if (egw_vfs::check_access($path,egw_vfs::READABLE))
			{
				if ($row['mime'] == egw_vfs::DIR_MIME_TYPE)
				{
					$row['link'] = '/index.php?menuaction=filemanager.filemanager_ui.index&path='.$path;
				}
				else
				{
					$row['link'] = egw_vfs::download_url($path);
				}
			}
			$row['user'] = $row['uid'] ? $GLOBALS['egw']->accounts->id2name($row['uid']) : 'root';
			$row['group'] = $row['gid'] ? $GLOBALS['egw']->accounts->id2name(-$row['gid']) : 'root';
			$row['hsize'] = egw_vfs::hsize($row['size']);
			
			//echo $path; _debug_array($row);

			$rows[++$n] = $row;
			
			$dir = dirname($path);
			if (!isset($dir_is_writable[$dir]))
			{
				$dir_is_writable[$dir] = egw_vfs::is_writable($dir);
			}
			if (!$dir_is_writable[$dir])
			{
				$readonlys["delete[$path]"] = true;	// no rights to delete the file
			}
		}
		//_debug_array($readonlys);
		return egw_vfs::$find_total;
	}
	
	/**
	 * Preferences of a file/directory
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	function file(array $content=null,$msg='')
	{
		static $tabs = 'general|perms|eacl|preview';
		
		$tpl = new etemplate('filemanager.file');
		
		if (!is_array($content))
		{
			if (!($stat = egw_vfs::stat($path = $_GET['path'])))
			{
				$content['msg'] = lang('File or directory not found!');
			}
			else
			{
				$content = $stat;
				$content['name'] = egw_vfs::basename($path);
				$content['dir'] = dirname($path);
				$content['path'] = $path;
				$content['hsize'] = egw_vfs::hsize($stat['size']);
				$content['mime'] = egw_vfs::mime_content_type($path);
				$content['icon'] = self::mime_icon($content['mime']);
				$content['gid'] *= -1;	// our widgets use negative gid's
			}
			if (!($content['is_dir'] = egw_vfs::is_dir($path)))
			{
				$content['perms']['executable'] = (int)!!($content['mode'] & 0111);
				$mask = 6;
				$content['link'] = $GLOBALS['egw']->link(egw_vfs::download_url($path));
				if (preg_match('/^text/',$content['mime']) && $content['size'] < 100000)
				{
					$content['text_content'] = file_get_contents(egw_vfs::PREFIX.$path);
				}
			}
			else
			{
				//currently not implemented in backend $content['perms']['sticky'] = (int)!!($content['mode'] & 0x201);
				$mask = 7;
			}
			foreach(array('owner' => 6,'group' => 3,'other' => 0) as $name => $shift)
			{
				$content['perms'][$name] = ($content['mode'] >> $shift) & $mask;
			}
			$content['is_owner'] = egw_vfs::has_owner_rights($path,$content);
		}
		else
		{
			//_debug_array($content);
			$path = $content['path'];
			
			list($button) = @each($content['button']); unset($content['button']);
			if (in_array($button,array('save','apply')))
			{
				foreach($content['old'] as $name => $old_value)
				{
					if (isset($content[$name]) && $old_value != $content[$name])
					{
						switch($name)
						{
							case 'name':
								if (egw_vfs::rename($path,$to = egw_vfs::concat($content['dir'],$content['name'])))
								{
									$msg = lang('Renamed %1 to %2.',$path,$to).' ';
								}
								$path = $to;
								break;
							default:
								static $name2cmd = array('uid' => 'chown','gid' => 'chgrp','perms' => 'chmod');
								$cmd = array('egw_vfs',$name2cmd[$name]);
								$value = $name == 'perms' ? self::perms2mode($content['perms']) : $content[$name];
								if ($content['modify_subs'] && $name == 'perms')
								{
									egw_vfs::find($path,array('type'=>'d'),$cmd,array($value));
									egw_vfs::find($path,array('type'=>'f'),$cmd,array($value & 0666));	// no execute for files
								}
								elseif ($content['modify_subs'])
								{
									egw_vfs::find($path,null,$cmd,array($value));
								}
								else
								{
									call_user_func_array($cmd,array($path,$value));
								}
								$msg .= lang('Permissions changed for %1.',$path.($content['modify_subs']?' '.lang('and all it\'s childeren'):''));
								break;
						}
					}
				}
			}
			elseif ($content['eacl'] && $content['is_owner'])
			{
				if ($content['eacl']['delete'])
				{
					list($ino_owner) = each($content['eacl']['delete']);
					list($ino,$owner) = explode('-',$ino_owner);
					$msg .= egw_vfs::eacl($path,null,$owner) ? lang('ACL deleted.') : lang('Error deleting the ACL entry!');
				}
				elseif ($button == 'eacl')
				{
					if (!$content['eacl']['owner'])
					{
						$msg .= lang('You need to select an owner!');
					}
					else
					{
						$msg .= egw_vfs::eacl($path,$content['eacl']['rights'],$content['eacl']['owner']) ?
							lang('ACL added.') : lang('Error adding the ACL!');
					}
				}
			}
			// refresh opender and close our window
			$link = $GLOBALS['egw']->link('/index.php',array('menuaction'=>'filemanager.filemanager_ui.index','msg'=>$msg));
			$js = "opener.location.href='$link';";
			if ($button == 'save') $js .= "window.close();";
			echo "<html>\n<body>\n<script>\n$js\n</script>\n</body>\n</html>\n";
			if ($button == 'save')$GLOBALS['egw']->common->egw_exit();
		}
		$content['msg'] = $msg;

		if (($readonlys['uid'] = !egw_vfs::$is_root) && !$content['uid']) $content['ro_uid_root'] = 'root';
		// only owner can change group & perms
		if (($readonlys['gid'] = !$content['is_owner'] ||
			parse_url(egw_vfs::resolve_url($content['path']),PHP_URL_SCHEME) == 'oldvfs'))	// no uid, gid or perms in oldvfs
		{
			if (!$content['gid']) $content['ro_gid_root'] = 'root';
			foreach($content['perms'] as $name => $value)
			{
				$readonlys['perms['.$name.']'] = true;
			}
		}
		$readonlys['name'] = !egw_vfs::is_writable($content['dir']);
		
		$readonlys[$tabs]['eacl'] = true;	// eacl off by default
		if ($content['is_dir'])
		{
			$readonlys[$tabs]['preview'] = true;	// no preview tab for dirs
			$sel_options['rights']=$sel_options['owner']=$sel_options['group']=$sel_options['other'] = array(
				7 => lang('Display and modification of content'),
				5 => lang('Display of content'),
				0 => lang('No access'),
			);
			if(($content['eacl'] = egw_vfs::get_eacl($content['path'])) !== false)	// backend supports eacl
			{
				unset($readonlys[$tabs]['eacl']);	// --> switch the tab on again
				foreach($content['eacl'] as &$eacl)
				{
					$eacl['path'] = parse_url($eacl['path'],PHP_URL_PATH);
					$readonlys['delete['.$eacl['ino'].'-'.$eacl['owner'].']'] = $eacl['ino'] != $content['ino'] || 
						$eacl['path'] != $content['path'] || !$content['is_owner'];
				}
				array_unshift($content['eacl'],false);	// make the keys start with 1, not 0
				$content['eacl']['rights'] = $content['eacl']['owner'] = 0;
			}
		}
		else
		{
			$sel_options['owner']=$sel_options['group']=$sel_options['other'] = array(
				6 => lang('Read & write access'),
				4 => lang('Read access only'),
				0 => lang('No access'),
			);
		}
		$preserve = $content;
		if (!isset($preserve['old']))
		{
			$preserve['old'] = array(
				'perms' => $content['perms'],
				'name'  => $content['name'],
				'uid'   => $content['uid'],
				'gid'   => $content['gid'],
			);
		}
		$GLOBALS['egw_info']['flags']['java_script'] = "<script>window.focus();</script>\n";
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Preferences').' '.$path;

		$tpl->exec('filemanager.filemanager_ui.file',$content,$sel_options,$readonlys,$preserve,2);
	}
	
	/**
	 * Convert perms array back to integer mode
	 *
	 * @param array $perms with keys owner, group, other, executable, sticky
	 * @return int
	 */
	private function perms2mode(array $perms)
	{
		$mode = $perms['owner'] << 6 | $perms['group'] << 3 | $prems['other'];
		if ($mode['executable'])
		{
			$mode |= 0111;
		}
		if ($mode['sticky'])
		{
			$mode |= 0x201;
		}
		return $mode;
	}
}