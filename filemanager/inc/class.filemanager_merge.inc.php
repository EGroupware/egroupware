<?php
/**
 * Filemanager - document merge
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package filemanager
 * @copyright 2012 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Vfs;

/**
 * Filemanager - document merge object
 * Only merges information about the files, not the files themselves
 */
class filemanager_merge extends Api\Storage\Merge
{
	/**
	 * Functions that can be called via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'show_replacements' => true,
		'merge_entries'     => true
	);

	/**
	 * Fields that are numeric, for special numeric handling
	 */
	protected $numeric_fields = array();

	/**
	 * Fields that are dates or timestamps
	 */
	public $date_fields = array(
		'mtime', 'ctime'
	);

	/**
	 * Business object to pull records from
	 */
	protected $bo = null;

	/**
	 * Current directory - affects how files are named
	 */
	protected $dir = '';

	/**
	 * Constructor
	 *
	 */
	function __construct($_dir = '')
	{
		parent::__construct();

		if($_dir)
		{
			$this->dir = $_dir;
		}

		// switch off handling of html formated content, if html is not used
		$this->parse_html_styles = Api\Storage\Customfields::use_html('filemanager');
	}

	/**
	 * Get replacements
	 *
	 * @param int $id id of entry
	 * @param string &$content =null content to create some replacements only if they are use
	 * @return array|boolean
	 */
	protected function get_replacements($id, &$content = null)
	{
		if(!($replacements = $this->filemanager_replacements($id, '', $content)))
		{
			return false;
		}
		return $replacements;
	}

	/**
	 * Get filemanager replacements
	 *
	 * @param int $id id (vfs path) of entry
	 * @param string $prefix ='' prefix like eg. 'erole'
	 * @return array|boolean
	 */
	public function filemanager_replacements($id, $prefix = '', &$content = null)
	{
		$info = array();
		$file = Vfs::lstat($id, true);

		$file['mtime'] = Api\DateTime::to($file['mtime']);
		$file['ctime'] = Api\DateTime::to($file['ctime']);

		$file['name'] = Vfs::basename($id);
		$file['dir'] = ($dir = Vfs::dirname($id)) ? Vfs::decodePath($dir) : '';
		$dirlist = explode('/', $file['dir']);
		$file['folder'] = array_pop($dirlist);
		$file['folder_file'] = $file['folder'] . '/' . $file['name'];
		$file['path'] = $id;
		$file['rel_path'] = str_replace($this->dir . '/', '', $id);
		$file['hsize'] = Vfs::hsize($file['size']);
		$file['mime'] = Vfs::mime_content_type($id);
		$file['gid'] *= -1;  // our widgets use negative gid's
		if(($props = Vfs::propfind($id)))
		{
			foreach($props as $prop)
			{
				$file[$prop['name']] = $prop['val'];
			}
		}
		if(($file['is_link'] = Vfs::is_link($id)))
		{
			$file['symlink'] = Vfs::readlink($id);
		}
		// Custom fields
		if($content && strpos($content, '#') !== 0)
		{
			// Expand link-to custom fields
			$this->cf_link_to_expand($file, $content, $info);

			foreach(Api\Storage\Customfields::get('filemanager') as $name => $field)
			{
				// Set any missing custom fields, or the marker will stay
				if(!$file['#' . $name])
				{
					$file['#' . $name] = '';
					continue;
				}

				// Format date cfs per user Api\Preferences
				if($field['type'] == 'date' || $field['type'] == 'date-time')
				{
					$this->date_fields[] = '#' . $name;
					$file['#' . $name] = Api\DateTime::to($file['#' . $name], $field['type'] == 'date' ? true : '');
				}
			}
		}

		// If in apps folder, try for app-specific placeholders
		if($dirlist[1] == 'apps' && count($dirlist) > 1)
		{
			// Try this first - a normal path /apps/appname/id/file
			list($app, $app_id) = explode('/', substr($file['path'], strpos($file['path'], 'apps/') + 5));
			// Symlink?
			if(!$app || !(int)$app_id || !array_key_exists($app, $GLOBALS['egw_info']['user']['apps']))
			{
				// Try resolving just app + ID - /apps/App Name/Record Title/file
				$resolved = Vfs::resolve_url_symlinks(implode('/', array_slice(explode('/', $file['dir']), 0, 4)));
				list($app, $app_id) = explode('/', substr($resolved, strpos($resolved, 'apps/') + 5));

				if(!$app || !(int)$app_id || !array_key_exists($app, $GLOBALS['egw_info']['user']['apps']))
				{
					// Get rid of any virtual folders (eg: All$) and symlinks
					$resolved = Vfs::resolve_url_symlinks($file['path']);
					list($app, $app_id) = explode('/', substr($resolved, strpos($resolved, 'apps/') + 5));
				}
			}
			if($app && $app_id)
			{
				if($app && $GLOBALS['egw_info']['user']['apps'][$app])
				{
					$app_merge = null;
					try
					{
						$classname = $app . '_merge';
						if(class_exists($classname))
						{
							$app_merge = new $classname();
							if($app_merge && method_exists($app_merge, 'get_replacements'))
							{
								$app_placeholders = $app_merge->get_replacements($app_id, $content);
							}
						}
					}
						// Silently discard & continue
					catch (Exception $e)
					{
						unset($e);    // not used
					}
				}
			}
		}
		$link = Link::mime_open($file['url'], $file['mime']);
		if(is_array($link))
		{
			// Directories have their internal protocol in path here
			if (!empty($link['path']) && strpos($link['path'], '://') !== false) $link['path'] = $file['path'];
			$link = Api\Session::link('/index.php', $link);
		}
		else
		{
			// Regular files
			$link = Api\Session::link($link);
		}

		// Prepend site, if missing
		if ($link[0] == '/') $link = Api\Framework::getUrl($link);

		$file['link'] = Api\Html::a_href(Api\Html::htmlspecialchars($file['name']), $link);
		$file['webdav_url'] = Api\Session::link(Vfs::download_url($file['url']));
		$file['url'] = $link;

		// Add markers
		foreach($file as $key => &$value)
		{
			if(!$value) $value = '';
			$info['$$' . ($prefix ? $prefix . '/' : '') . $key . '$$'] = $value;
		}
		if (!empty($app_placeholders) && is_array($app_placeholders))
		{
			$info = array_merge($app_placeholders, $info);
		}
		return $info;
	}

	/**
	 * Create a share for an entry
	 *
	 * @param string $app
	 * @param string $id
	 * @param string $path
	 * @param type $content
	 * @return type
	 */
	protected function create_share($app, $id, &$content)
	{
		// Check if some other process created the share (with custom options)
		// and put it in the session cache for us
		$path = $id;
		$session = \EGroupware\Api\Cache::getSession(Api\Sharing::class, $path);
		if($session && $session['share_path'] == $path)
		{
			return $session;
		}
		else
		{
			if(($session = \EGroupware\Api\Cache::getSession(Api\Sharing::class, "$app::$id")) &&
				substr($session['share_path'], -strlen($path)) === $path)
			{
				return $session;
			}
		}
		// Need to create the share here.
		// No way to know here if it should be writable, or who it's going to
		$mode = /* ?  ? Sharing::WRITABLE :*/
			Api\Sharing::READONLY;
		$recipients = array();
		$extra = array();

		return \EGroupware\Api\Vfs\Sharing::create('', $path, $mode, NULL, $recipients, $extra);
	}

	/**
	 * Hook for extending apps to customise the replacements UI without having to override the whole method
	 *
	 * @param string $template_name
	 * @param $content
	 * @param $sel_options
	 * @param $readonlys
	 */
	protected function show_replacements_hook(&$template_name, &$content, &$sel_options, &$readonlys)
	{
		$content['extra_template'] = 'filemanager.replacements';
	}

	/**
	 * Get a list of placeholders provided.
	 *
	 * Placeholders are grouped logically.  Group key should have a user-friendly translation.
	 */
	public function get_placeholder_list($prefix = '')
	{
		$placeholders = parent::get_placeholder_list($prefix);

		$fields = array(
			'name'        => 'name',
			'path'        => 'Absolute path',
			'rel_path'    => 'Path relative to current directory',
			'folder'      => 'Containing folder',
			'folder_file' => 'Containing folder and file name',
			'url'         => 'url',
			'webdav_url'  => 'External path using webdav',
			'link'        => 'Clickable link to file',
			'comment'     => 'comment',
			'mtime'       => 'modified',
			'ctime'       => 'created',
			'mime'        => 'Type',
			'hsize'       => 'Size',
			'size'        => 'Size (in bytes)',
		);
		$group = 'placeholders';
		foreach($fields as $name => $label)
		{
			$marker = $this->prefix($prefix, $name, '{');
			if(!array_filter($placeholders, function ($a) use ($marker)
			{
				return array_key_exists($marker, $a);
			}))
			{
				$placeholders[$group][] = [
					'value' => $marker,
					'label' => $label
				];
			}
		}

		return $placeholders;
	}
}
