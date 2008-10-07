<?php
/**
 * eGroupWare eTemplate Extension - VFS Widgets
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @copyright 2008 by RalfBecker@outdoor-training.de
 * @package etemplate
 * @subpackage extensions
 * @version $Id$
 */

/**
 * eTemplate extension to display stuff from the VFS system
 *
 * Contains the following widgets:
 * - vfs      aka File name+link:	clickable filename, with evtl. clickable path-components
 * - vfs-size aka File size:		human readable filesize, eg. 1.4k
 * - vfs-mode aka File mode:		posix mode as string eg. drwxr-x---
 * - vfs-mime aka File icon:	    mime type icon or thumbnail (if configured AND enabled in the user-prefs)
 * - vfs-uid  aka File owner:       Owner of file, or 'root' if none
 * - vfs-gid  aka File group:       Group of file, or 'root' if none
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
	);
	/**
	 * availible extensions and there names for the editor
	 *
	 * @var array
	 */
	var $human_name = array(
		'vfs'      => 'File name+link',	// clickable filename, with evtl. clickable path-components
		'vfs-size' => 'File size',		// human readable filesize
		'vfs-mode' => 'File mode',		// posix mode as string eg. drwxr-x---
		'vfs-mime' => 'File icon',		// mime type icon or thumbnail
		'vfs-uid'  => 'File owner',		// Owner of file, or 'root' if none
		'vfs-gid'  => 'File group',		// Group of file, or 'root' if none
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
		$type = $cell['type'];
		$readonly = $cell['readonly'] || $readonlys;

		// check if we have a path and not the raw value, in that case we have to do a stat first
		if (in_array($type,array('vfs-size','vfs-mode','vfs-uid','vfs-gid')) && !is_numeric($value))
		{
			if (!$value || !($stat = egw_vfs::stat($value)))
			{
				if ($value) $value = lang("File '%1' not found!",$value);
				$cell = etemplate::empty_cell();
				return true;	// allow extra value;
			}
		}
		$cell['type'] = 'label';

		switch($type)
		{
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
					$cell_name = array_pop($arr=explode('][',substr($form_name,0,-1)));
				}
				$cell['name'] = '';
				$cell['type'] = 'hbox';
				$cell['size'] = '0,,0,0';
				foreach($comps=explode('/',$name) as $n => $component)
				{
					if ($n)
					{
						$sep = soetemplate::empty_cell('label','',array('label' => '/'));
						soetemplate::add_child($cell,$sep);
						unset($sep);
					}
					$value['c'.$n] = $component;
					$path .= '/'.$component;
					if (egw_vfs::check_access($path,egw_vfs::READABLE))	// show link only if we have access to the file or dir
					{
						if ($n < count($comps)-1 || $mime == egw_vfs::DIR_MIME_TYPE)
						{
							$value['l'.$n] = '/index.php?menuaction=filemanager.filemanager_ui.index&path='.$path;
							$target = '';
						}
						else
						{
							$value['l'.$n] = egw_vfs::download_url($path);
							$target = ',,,_blank';
						}
					}
					$comp = etemplate::empty_cell('label',$cell_name.'[c'.$n.']',array(
						'size'    => ',@'.$cell_name.'[l'.$n.']'.$target,
						'no_lang' => true,
						'span'    => ',vfsFilename'
					));
					etemplate::add_child($cell,$comp);
					unset($comp);
				}
				break;

			case 'vfs-mime':
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
				$cell['label'] = $mime;
				list($mime_main,$mime_sub) = explode('/',$mime);
				if ($mime_main == 'egw')
				{
					$value = $mime_sub.'/navbar';	// egw-applications for link-widget
					$cell['label'] = lang($mime_sub);
					list($span,$class) = explode(',',$cell['span'],2);
					$class .= ($class ? ' ' : '') . 'vfsMimeIcon';
					$cell['span'] = $span.','.$class;
				}
				elseif($path && $mime_main == 'image' && (string)$GLOBALS['egw_info']['server']['link_list_thumbnail'] != '0' &&
					(string)$GLOBALS['egw_info']['user']['preferences']['common']['link_list_thumbnail'] != '0' &&
					// check the size of the image, as too big images get no icon, but a PHP Fatal error:  Allowed memory size exhausted
					(!is_array($value) && ($stat = egw_vfs::stat($path)) ? $stat['size'] : $value['size']) < 600000)
				{
					$value = $GLOBALS['egw']->link('/etemplate/thumbnail.php',array('path' => $path));
				}
				else
				{
					$value = egw_vfs::mime_icon($mime);
				}
				break;

			default:
				$value = 'Not yet implemented';
		}

		return true;
	}
}
