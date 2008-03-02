<?php
/**
 * Filemanager - user interface
 *
 * @link http://www.egroupware.org
 * @package admin
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
	);
	
	/**
	 * Main filemanager page
	 *
	 * @param array $content=null
	 * @param string $msg=null
	 */
	function index($content=null,$msg=null)
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
//					'filter_label'   =>	// I  label for filter    (optional)
//					'filter_help'    =>	// I  help-msg for filter (optional)
					'no_filter'      => True,	// I  disable the 1. filter
					'no_filter2'     => True,	// I  disable the 2. filter (params are the same as for filter)
					'no_cat'         => True,	// I  disable the cat-selectbox
//					'cat_app'        =>     // I  application the cat's should be from, default app in get_rows
//					'template'       =>	// I  template to use for the rows, if not set via options
//					'header_left'    =>	// I  template to show left of the range-value, left-aligned (optional)
//					'header_right'   =>	// I  template to show right of the range-value, right-aligned (optional)
//					'bottom_too'     => True// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
//					'never_hide'     => True,	// I  never hide the nextmatch-line if less then maxmatch entrie
//					'lettersearch'   => True,	// I  show a lettersearch
					'searchletter'   =>	false,	// I0 active letter of the lettersearch or false for [all]
					'start'          =>	0,	// IO position in list
//					'num_rows'       =>	// IO number of rows to show, defaults to maxmatches from the general prefs
//					'cat_id'         =>	// IO category, if not 'no_cat' => True
//					'search'         =>	// IO search pattern
					'order'          =>	'name',	// IO name of the column to sort after (optional for the sortheaders)
					'sort'           =>	'ASC',	// IO direction of the sort: 'ASC' or 'DESC'
//					'col_filter'     =>	// IO array of column-name value pairs (optional for the filterheaders)
//					'filter'         =>	// IO filter, if not 'no_filter' => True
//					'filter_no_lang' => True// I  set no_lang for filter (=dont translate the options)
//					'filter_onchange'=> 'this.form.submit();'// I onChange action for filter, default: this.form.submit();
//					'filter2'        =>	// IO filter2, if not 'no_filter2' => True
//					'filter2_no_lang'=> True// I  set no_lang for filter2 (=dont translate the options)
//					'filter2_onchange'=> 'this.form.submit();'// I onChange action for filter, default: this.form.submit();
					'default_cols'   => '!comment',	// I  columns to use if there's no user or default pref (! as first char uses all but the named columns), default all columns
					'csv_fields'     =>	false, // I  false=disable csv export, true or unset=enable it with auto-detected fieldnames, 
									//or array with name=>label or name=>array('label'=>label,'type'=>type) pairs (type is a eT widget-type)
					'path' => '/home/'.$GLOBALS['egw_info']['user']['account_lid'],
				);
			}
			if (isset($_GET['path']) && ($path = $_GET['path']) && $path[0] == '/' && egw_vfs::is_dir($path))
			{
				$content['nm']['path'] = $path;
			}
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
						$content['nm']['path'] = $old_path.'/'.$content['nm']['path'];
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
					$clipboard_type = $GLOBALS['egw']->session->appsession('clipboard_type','filemanager');
					$content['nm']['msg'] = self::action($clipboard_type.'_paste',$clipboard_files,$content['nm']['path']);
					break;
				case 'upload':
					$to = $content['nm']['path'].'/'.$content['upload']['name'];
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
		//_debug_array($content);
		$readonlys['button[paste]'] = !$clipboard_files;
		$readonlys['button[createdir]'] = !$dir_is_writable;
		$readonlys['button[upload]'] = !$dir_is_writable;
		
		if ($dir_is_writable) $sel_options['action']['delete'] = lang('Delete');
		$sel_options['action']['copy'] = lang('Copy to clipboard');
		if ($dir_is_writable) $sel_options['action']['cut'] = lang('Cut to clipboard');

		$tpl->exec('filemanager.filemanager_ui.index',$content,$sel_options,$readonlys,array('nm' => $content['nm']));
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
						$to = $dir.'/'.egw_vfs::basename($path);
						if (egw_vfs::copy($path,$to))
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
					$to = $dir.'/'.egw_vfs::basename($path);
					if (egw_vfs::rename($path,$to))
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
	 * @return string
	 */
	static private function mime_icon($mime_type, $size=16)
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
		$dir_is_writable = egw_vfs::is_writable($query['path']);

		$rows = array();
		foreach(egw_vfs::find($query['path'],array('mindepth'=>1,'maxdepth'=>1)) as $path)
		{
			$row = egw_vfs::stat($path);
			$row['name'] = egw_vfs::basename($path);
			$row['path'] = $path;
			$row['mime'] = egw_vfs::mime_content_type($path);
			$row['icon'] = self::mime_icon($row['mime']);
			$row['perms'] = egw_vfs::int2mode($row['mode']);
			// only show link if we have access to the file or dir
			if (egw_vfs::check_access($row,egw_vfs::READABLE))
			{
				if ($row['mime'] == egw_vfs::DIR_MIME_TYPE)
				{
					$row['link'] = '/index.php?menuaction=filemanager.filemanager_ui.index&path='.$path;
				}
				else
				{
					$row['link'] = '/index.php?menuaction=filemanager.uifilemanager.view&path='.base64_encode(dirname($path)).'&file='.base64_encode(egw_vfs::basename($path));
//					$row['link'] = '/filemanager/webdav.php'.$path;
				}
			}
			$row['user'] = $row['uid'] ? $GLOBALS['egw']->accounts->id2name($row['uid']) : 'root';
			$row['group'] = $row['gid'] ? $GLOBALS['egw']->accounts->id2name(-$row['gid']) : 'root';
			$row['hsize'] = egw_vfs::hsize($row['size']);
			
			//echo $path; _debug_array($row);

			$rows[++$n] = $row;
			
			if (!$dir_is_writable)
			{
				$readonlys["delete[$path]"] = true;	// no rights to delete the file
			}
		}
		//_debug_array($readonlys);
		return count($rows);
	}
}