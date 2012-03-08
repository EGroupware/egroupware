<?php
/**
 * eGroupWare eTemplate Extension - VFS Widgets
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @copyright 2008-10 by RalfBecker@outdoor-training.de
 * @package etemplate
 * @subpackage extensions
 * @version $Id$
 */

/**
 * eTemplate extension to display stuff from the VFS system
 *
 * Contains the following widgets:
 * - vfs      aka File name+link:	clickable filename, with evtl. clickable path-components
 * - vfs-name aka Filename:         filename automatically urlencoded on return (urldecoded on display to user)
 * - vfs-size aka File size:		human readable filesize, eg. 1.4k
 * - vfs-mode aka File mode:		posix mode as string eg. drwxr-x---
 * - vfs-mime aka File icon:	    mime type icon or thumbnail (if configured AND enabled in the user-prefs)
 * - vfs-uid  aka File owner:       Owner of file, or 'root' if none
 * - vfs-gid  aka File group:       Group of file, or 'root' if none
 * - vfs-upload aka VFS file:       displays either download and delete (x) links or a file upload
 *   + value is either a vfs path or colon separated $app:$id:$relative_path, eg: infolog:123:special/offer
 *   + if empty($id) / new entry, file is created in a hidden temporary directory in users home directory
 *     and calling app is responsible to move content of that dir to entry directory, after entry is saved
 *   + option: required mimetype or regular expression for mimetype to match, eg. '/^text\//i' for all text files
 *   + if path ends in a slash, multiple files can be uploaded, their original filename is kept then
 *
 * All widgets accept as value a full path.
 * vfs-mime and vfs itself also allow an array with values like stat (incl. 'path'!) as value.
 * vfs-mime also allows just the mime type as value.
 * All other widgets allow additionally the nummeric value from the stat call (to not call it again).
 */
class vfs_widget
{
	/**
	 * exported methods of this class
	 *
	 * @var array
	 */
	var $public_functions = array(
		'pre_process' => True,
		'post_process' => true,		// post_process is only used for vfs-upload (all other widgets set $cell['readlonly']!)
	);
	/**
	 * availible extensions and there names for the editor
	 *
	 * @var array
	 */
	var $human_name = array(
		'vfs'      => 'File name+link',	// clickable filename, with evtl. clickable path-components
		'vfs-name' => 'File name',		// filename automatically urlencoded
		'vfs-size' => 'File size',		// human readable filesize
		'vfs-mode' => 'File mode',		// posix mode as string eg. drwxr-x---
		'vfs-mime' => 'File icon',		// mime type icon or thumbnail
		'vfs-uid'  => 'File owner',		// Owner of file, or 'root' if none
		'vfs-gid'  => 'File group',		// Group of file, or 'root' if none
		'vfs-upload' => 'VFS file',		// displays either download and delete (x) links or a file upload
	);

	/**
	 * pre-processing of the extension
	 *
	 * This function is called before the extension gets rendered
	 *
	 * @param string $form_name form-name of the control
	 * @param mixed &$value value / existing content, can be modified
	 * @param array &$cell array with the widget, can be modified for ui-independent widgets
	 * @param array &$readonlys names of widgets as key, to be made readonly
	 * @param mixed &$extension_data data the extension can store persisten between pre- and post-process
	 * @param object &$tmpl reference to the template we belong too
	 * @return boolean true if extra label is allowed, false otherwise
	 */
	function pre_process($form_name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
	{
		//echo "<p>".__METHOD__."($form_name,$value,".array2string($cell).",...)</p>\n";
		$type = $cell['type'];
		if (!in_array($type,array('vfs-name','vfs-upload'))) $cell['readonly'] = true;	// to not call post-process

		// check if we have a path and not the raw value, in that case we have to do a stat first
		if (in_array($type,array('vfs-size','vfs-mode','vfs-uid','vfs-gid')) && !is_numeric($value) || $type == 'vfs' && !$value)
		{
			if (!$value || !($stat = egw_vfs::stat($value)))
			{
				if ($value) $value = lang("File '%1' not found!",egw_vfs::decodePath($value));
				$cell = etemplate::empty_cell();
				return true;	// allow extra value;
			}
		}
		$cell['type'] = 'label';

		switch($type)
		{
			case 'vfs-upload':		// option: required mimetype or regular expression for mimetype to match, eg. '/^text\//i' for all text files
				if (empty($value) && preg_match('/^exec.*\[([^]]+)\]$/',$form_name,$matches))	// if no value via content array, use widget name
				{
					$value = $matches[1];
				}
				$extension_data = array('value' => $value, 'mimetype' => $cell['size'], 'type' => $type);
				if ($value[0] != '/')
				{
					list($app,$id,$relpath) = explode(':',$value,3);
					if (empty($id))
					{
						static $tmppath = array();	// static var, so all vfs-uploads get created in the same temporary dir
						if (!isset($tmppath[$app])) $tmppath[$app] = '/home/'.$GLOBALS['egw_info']['user']['account_lid'].'/.'.$app.'_'.md5(time().session_id());
						$value = $tmppath[$app];
						unset($cell['onchange']);	// no onchange, if we have to use a temporary dir
					}
					else
					{
						$value = egw_link::vfs_path($app,$id,'',true);
					}
					if (!empty($relpath)) $value .= '/'.$relpath;
				}
				$path = $extension_data['path'] = $value;
				if (substr($path,-1) != '/' && self::file_exists($path) && !egw_vfs::is_dir($path))	// display download link and delete icon
				{
					$extension_data['path'] = $path;
					$cell = $this->file_widget($value,$path,$cell['name'],$cell['label']);
				}
				else	// file does NOT exists --> display file upload
				{
					$cell['type'] = 'file';
					// if no explicit help message set and we only allow certain file types --> show them
					if (empty($cell['help']) && $cell['size'])
					{
						if (($type = mime_magic::mime2ext($cell['size'])))
						{
							$type = '*.'.strtoupper($type);
						}
						else
						{
							$type = $cell['size'];
						}
						$cell['help'] = lang('Allowed file type: %1',$type);
					}
				}
				// check if directory (trailing slash) is given --> upload of multiple files
				if (substr($path,-1) == '/' && egw_vfs::file_exists($path) && ($files = egw_vfs::scandir($path)))
				{
					//echo $path; _debug_array($files);
					$upload = $cell;
					$cell = etemplate::empty_cell('vbox','',array('size' => ',,0,0'));
					$extension_data['files'] = $files;
					$value = array();
					foreach($files as $file)
					{
						$file = $path.$file;
						$basename = basename($file);
						unset($widget);
						$widget = $this->file_widget($value[$basename],$file,$upload['name']."[$basename]");
						etemplate::add_child($cell,$widget);
					}
					etemplate::add_child($cell,$upload);
				}
				break;

			case 'vfs-size':	// option: add size in bytes in brackets
				$value = egw_vfs::hsize($size = is_numeric($value) ? $value : $stat['size']);
				if ($cell['size']) $value .= ' ('.$size.')';
				$cell['type'] = 'label';
				break;

			case 'vfs-mode':
				$value = egw_vfs::int2mode(is_numeric($value) ? $value : $stat['mode']);
				list($span,$class) = explode(',',$cell['span'],2);
				$class .= ($class ? ' ' : '') . 'vfsMode';
				$cell['span'] = $span.','.$class;
				$cell['no_lang'] = true;
				break;

			case 'vfs-uid':
			case 'vfs-gid':
				$uid = !is_numeric($value) ? $stat[$type=='vfs-uid'?'uid':'gid'] : $value;
				$value = !$uid ? 'root' : $GLOBALS['egw']->accounts->id2name($type=='vfs-uid'?$uid:-$uid);	// our internal gid's are negative!
				break;

			case 'vfs':
				if (is_array($value))
				{
					$name = $value['name'];
					$path = substr($value['path'],0,-strlen($name)-1);
					$mime = $value['mime'];
				}
				else
				{
					$name = $value;
					$path = '';
					$mime = egw_vfs::mime_content_type($value);
					$value = array();
				}
				if (($cell_name = $cell['name']) == '$row')
				{
					$arr = explode('][',substr($form_name,0,-1));
					$cell_name = array_pop($arr);
				}
				$cell['name'] = '';
				$cell['type'] = 'hbox';
				$cell['size'] = '0,,0,0';
				foreach($name != '/' ? explode('/',$name) : array('') as $n => $component)
				{
					if ($n > (int)($path === '/'))
					{
						$sep = soetemplate::empty_cell('label','',array('label' => '/'));
						soetemplate::add_child($cell,$sep);
						unset($sep);
					}
					$value['c'.$n] = $component !== '' ? egw_vfs::decodePath($component) : '/';
					$path .= ($path != '/' ? '/' : '').$component;
					// replace id's in /apps again with human readable titles
					$path_parts = explode('/',$path);
					if ($path_parts[1] == 'apps')
					{
						switch(count($path_parts))
						{
							case 2:
								$value['c'.$n] = lang('Applications');
								break;
							case 3:
								$value['c'.$n] = lang($path_parts[2]);
								break;
							case 4:
								if (is_numeric($value['c'.$n])) $value['c'.$n] .= egw_link::title($path_parts[2],$path_parts[3]);
								break;
						}
					}
					$popup = null;
					if (egw_vfs::is_readable($path))	// show link only if we have access to the file or dir
					{
						if ($n < count($comps)-1 || $mime == egw_vfs::DIR_MIME_TYPE || egw_vfs::is_dir($path))
						{
							$value['l'.$n] = egw_link::mime_open($path, egw_vfs::DIR_MIME_TYPE, $popup);
							$target = '';
						}
						else
						{
							$value['l'.$n] = egw_link::mime_open($path, $mime, $popup);
							$target = '_blank';
						}
					}

					if ($cell['onclick'])
					{
						$comp = etemplate::empty_cell('button',$cell_name.'[c'.$n.']',array(
							'size'    => '1',
							'no_lang' => true,
							'span'    => ',vfsFilename',
							'label'   => $value['c'.$n],
							'onclick' => str_replace('$path',"'".addslashes($path)."'",$cell['onclick']),
						));
					}
					else
					{
						$comp = etemplate::empty_cell('label',$cell_name.'[c'.$n.']',array(
							'size'    => ',@'.$cell_name.'[l'.$n.'],,,'.$target.','.$popup,
							'no_lang' => true,
							'span'    => ',vfsFilename',
						));
					}
					etemplate::add_child($cell,$comp);
					unset($comp);
				}
				unset($cell['onclick']);	// otherwise it's handled by the grid too
				//_debug_array($comps); _debug_array($cell); _debug_array($value);
				break;

			case 'vfs-name':	// size: [length][,maxLength[,allowPath]]
				$cell['type'] = 'text';
				list($length,$maxLength,$allowPath) = $options = explode(',',$cell['size']);
				$preg = $allowPath ? '' : '/[^\\/]/';	// no slash '/' allowed, if not allowPath set
				$cell['size'] = "$length,$maxLength,$preg";
				$value = egw_vfs::decodePath($value);
				$extension_data = array('type' => $type,'allowPath' => $allowPath);
				break;

			case 'vfs-mime':  // size: [thsize] (thumbnail size)
				//Read the thumbnail size
				list($thsize) = explode(',', $cell['size']);
				if (!is_numeric($thsize))
				{
					$thsize = NULL;
				}

				if (!$value)
				{
					$cell = etemplate::empty_cell();
					return true;
				}
				if (!is_array($value))
				{
					if ($value[0] == '/' || count(explode('/',$value)) != 2)
					{
						$mime = egw_vfs::mime_content_type($path=$value);
					}
					else
					{
						$mime = $value;
					}
				}
				else
				{
					$path = $value['path'];
					$mime = $value['mime'];
				}
				//error_log(__METHOD__."() type=vfs-mime: value=".array2string($value).": mime=$mime, path=$path");
				$cell['type'] = 'image';
				$cell['label'] = mime_magic::mime2label($mime);

				list($mime_main,$mime_sub) = explode('/',$mime);
				if ($mime_main == 'egw' || isset($GLOBALS['egw_info']['apps'][$mime_main]))
				{
					$value = $mime_main == 'egw' ? $mime_sub.'/navbar' : $mime;	// egw-applications for link-widget
					$cell['label'] = lang($mime_main == 'egw' ? $mime_sub : $mime_main);
					list($span,$class) = explode(',',$cell['span'],2);
					$class .= ($class ? ' ' : '') . 'vfsMimeIcon';
					$cell['span'] = $span.','.$class;
				}
				elseif($path && $mime_main == 'image' && in_array($mime_sub,array('png','jpeg','jpg','gif','bmp')) &&
					(string)$GLOBALS['egw_info']['server']['link_list_thumbnail'] != '0' &&
					(string)$GLOBALS['egw_info']['user']['preferences']['common']['link_list_thumbnail'] != '0' &&
					// check the size of the image, as too big images get no icon, but a PHP Fatal error:  Allowed memory size exhausted
					(!is_array($value) && ($stat = egw_vfs::stat($path)) ? $stat['size'] : $value['size']) < 600000)
				{
					if (substr($path,0,6) == '/apps/')
					{
						$path = parse_url(egw_vfs::resolve_url_symlinks($path),PHP_URL_PATH);
					}

					//Assemble the thumbnail parameters
					$thparams = array('path' => $path);

					if ($thsize)
					{
						$thparams['thsize'] = $thsize;
					}

					$value = $GLOBALS['egw']->link('/etemplate/thumbnail.php', $thparams);
				}
				else
				{
					$value = egw_vfs::mime_icon($mime);
				}
				// mark symlinks (check if method exists, to allow etemplate to run on 1.6 API!)
				if (method_exists('egw_vfs','is_link') && egw_vfs::is_link($path))
				{
					$broken = !egw_vfs::stat($path);
					list($span,$class) = explode(',',$cell['span'],2);
					$class .= ($class ? ' ' : '') . ($broken ? 'vfsIsBrokenLink' : 'vfsIsLink');
					$cell['span'] = $span.','.$class;
					$cell['label'] = ($broken ? lang('Broken link') : lang('Link')).': '.egw_vfs::decodePath(egw_vfs::readlink($path)).
						(!$broken ? ' ('.$cell['label'].')' : '');
				}
				break;

			default:
				$value = 'Not yet implemented';
		}
		return true;
	}

	/**
	 * Create widget with download and delete (only if dir is writable) link
	 *
	 * @param mixed &$value
	 * @param string $path vfs path of download
	 * @param string $name name of widget
	 * @param string $label=null label, if not set basename($path) is used
	 * @return array
	 */
	static function file_widget(&$value,$path,$name,$label=null)
	{
		$value = empty($label) ? egw_vfs::decodePath(egw_vfs::basename($path)) : lang($label);	// display (translated) Label or filename (if label empty)

		$vfs_link = etemplate::empty_cell('label',$name,array(
			'size' => ','.egw_vfs::download_url($path).',,,_blank,,'.$path,
		));
		// if dir is writable, add delete link
		if (egw_vfs::is_writable(egw_vfs::dirname($path)))
		{
			$cell = etemplate::empty_cell('hbox','',array('size' => ',,0,0'));
			etemplate::add_child($cell,$vfs_link);
			$delete_icon = etemplate::empty_cell('button',$path,array(
				'label' => 'delete',
				'size'  => 'delete',	// icon
				'onclick' => "return confirm('Delete this file');",
				'span' => ',leftPad5',
			));
			etemplate::add_child($cell,$delete_icon);
		}
		else
		{
			$cell = $vfs_link;
		}
		return $cell;
	}

	/**
	 * Check if vfs file exists *without* using the extension
	 *
	 * If you rename a file, you have to clear the cache ($clear_after=true)!
	 *
	 * @param string &$path on call path without extension, if existing on return full path incl. extension
	 * @param boolean $clear_after=null clear file-cache after (true) or before (false), default dont clear
	 * @return
	 */
	static function file_exists(&$path,$clear_after=null)
	{
		static $files = array();	// static var, to scan each directory only once
		$dir = egw_vfs::dirname($path);
		if ($clear_after === false) unset($files[$dir]);
		if (!isset($files[$dir])) $files[$dir] = egw_vfs::file_exists($dir) ? egw_vfs::scandir($dir) : array();

		$basename = egw_vfs::basename($path);
		$basename_len = strlen($basename);
		$found = false;
		foreach($files[$dir] as $file)
		{
			if (substr($file,0,$basename_len) == $basename)
			{
				$path = $dir.'/'.$file;
				$found = true;
			}
		}
		if ($clear_after === true) unset($files[$dir]);
		//echo "<p>".__METHOD__."($path) returning ".array2string($found)."</p>\n";
		return $found;
	}

	/**
	 * postprocessing method, called after the submission of the form
	 *
	 * It has to copy the allowed/valid data from $value_in to $value, otherwise the widget
	 * will return no data (if it has a preprocessing method). The framework insures that
	 * the post-processing of all contained widget has been done before.
	 *
	 * Only used by vfs-upload so far
	 *
	 * @param string $name form-name of the widget
	 * @param mixed &$value the extension returns here it's input, if there's any
	 * @param mixed &$extension_data persistent storage between calls or pre- and post-process
	 * @param boolean &$loop can be set to true to request a re-submision of the form/dialog
	 * @param object &$tmpl the eTemplate the widget belongs too
	 * @param mixed &value_in the posted values (already striped of magic-quotes)
	 * @return boolean true if $value has valid content, on false no content will be returned!
	 */
	function post_process($name,&$value,&$extension_data,&$loop,&$tmpl,$value_in)
	{
		//error_log(__METHOD__."('$name',".array2string($value).','.array2string($extension_data).",$loop,,".array2string($value_in).')');
		//echo '<p>'.__METHOD__."('$name',".array2string($value).','.array2string($extension_data).",$loop,,".array2string($value_in).")</p>\n";

		if (!$extension_data) return false;

		switch($extension_data['type'])
		{
			case 'vfs-name':
				$value = $extension_data['allowPath'] ? egw_vfs::encodePath($value_in) : egw_vfs::encodePathComponent($value_in);
				return true;

			case 'vfs-upload':
				break;	// handeled below

			default:
				return false;
		}
		// from here on vfs-upload only!

		// check if delete icon clicked
		if ($_POST['submit_button'] == ($fname = str_replace($extension_data['value'],$extension_data['path'],$name)) ||
			substr($extension_data['path'],-1) == '/' && substr($_POST['submit_button'],0,strlen($fname)-1) == substr($fname,0,-1))
		{
			if (substr($extension_data['path'],-1) == '/')	// multiple files?
			{
				foreach($extension_data['files'] as $file)	// check of each single file, to not allow deleting of arbitrary files
				{
					if ($_POST['submit_button'] == substr($fname,0,-1).$file.']')
					{
						if (!egw_vfs::unlink($extension_data['path'].$file))
						{
							etemplate::set_validation_error($name,lang('Error deleting %1!',egw_vfs::decodePath($extension_data['path'].$file)));
						}
						break;
					}
				}
			}
			elseif (!egw_vfs::unlink($extension_data['path']))
			{
				etemplate::set_validation_error($name,lang('Error deleting %1!',egw_vfs::decodePath($extension_data['path'])));
			}
			$loop = true;
			return false;
		}

		// handle file upload
		$name = preg_replace('/^exec\[([^]]+)\](.*)$/','\\1\\2',$name);	// remove exec prefix

		if (!is_array($_FILES['exec']) || !($filename = etemplate::get_array($_FILES['exec']['name'],$name)))
		{
			return false;	// no file attached
		}
		$tmp_name = etemplate::get_array($_FILES['exec']['tmp_name'],$name);
		$error = etemplate::get_array($_FILES['exec']['error'],$name);
		if ($error)
		{
			etemplate::set_validation_error($name,lang('Error uploading file!')."\n".
				etemplate::max_upload_size_message());
			$loop = true;
			return false;
		}
		if (empty($tmp_name) || function_exists('is_uploaded_file') && !is_uploaded_file($tmp_name) || !file_exists($tmp_name))
		{
			return false;
		}
		// check if type matches required mime-type, if specified
		if (!empty($extension_data['mimetype']))
		{
			$type = etemplate::get_array($_FILES['exec']['type'],$name);
			$is_preg = $extension_data['mimetype'][0] == '/';
			if (!$is_preg && strcasecmp($extension_data['mimetype'],$type) || $is_preg && !preg_match($extension_data['mimetype'],$type))
			{
				etemplate::set_validation_error($name,lang('File is of wrong type (%1 != %2)!',$type,$extension_data['mimetype']));
				return false;
			}
		}
		$path = $extension_data['path'];
		if (substr($path,-1) != '/')
		{
			// add extension to path
			$parts = explode('.',$filename);
			if (($extension = array_pop($parts)) && mime_magic::ext2mime($extension))	// really an extension --> add it to path
			{
				$path .= '.'.$extension;
			}
		}
		else	// multiple upload with dir given (trailing slash)
		{
			$path .= egw_vfs::encodePathComponent($filename);
		}
		if (!egw_vfs::file_exists($dir = egw_vfs::dirname($path)) && !egw_vfs::mkdir($dir,null,STREAM_MKDIR_RECURSIVE))
		{
			etemplate::set_validation_error($name,lang('Error create parent directory %1!',egw_vfs::decodePath($dir)));
			return false;
		}
		if (!copy($tmp_name,egw_vfs::PREFIX.$path))
		{
			etemplate::set_validation_error($name,lang('Error copying uploaded file to vfs!'));
			return false;
		}
		$value = $path;	// return path of file, important if only a temporary location is used

		return true;
	}
}
