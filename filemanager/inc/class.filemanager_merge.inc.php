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

/**
 * Filemanager - document merge object
 * Only merges information about the files, not the files themselves
 */
class filemanager_merge extends bo_merge
{
	/**
	 * Functions that can be called via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'show_replacements'		=> true,
	);

	/**
	 * Fields that are numeric, for special numeric handling
	 */
	protected $numeric_fields = array(
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
	}

	/**
	 * Get replacements
	 *
	 * @param int $id id of entry
	 * @param string &$content=null content to create some replacements only if they are use
	 * @return array|boolean
	 */
	protected function get_replacements($id,&$content=null)
	{
		if (!($replacements = $this->filemanager_replacements($id, '', $content)))
		{
			return false;
		}
		return $replacements;
	}

	/**
	 * Get filemanager replacements
	 *
	 * @param int $id id (vfs path) of entry
	 * @param string $prefix='' prefix like eg. 'erole'
	 * @return array|boolean
	 */
	public function filemanager_replacements($id,$prefix='', &$content = null)
	{
		$info = array();
		$file = egw_vfs::lstat($id,true);

		$file['mtime'] = egw_time::to($file['mtime']);
		$file['ctime'] = egw_time::to($file['ctime']);

		$file['name'] = egw_vfs::basename($id);
		$file['dir'] = egw_vfs::decodePath(egw_vfs::dirname($id));
		$dirlist = explode('/',$file['dir']);
		$file['folder'] = array_pop($dirlist);
		$file['folder_file'] = $file['folder'] . '/'.$file['name'];
		$file['path'] = $id;
		$file['rel_path'] = str_replace($this->dir.'/', '', $id);
		$file['hsize'] = egw_vfs::hsize($file['size']);
		$file['mime'] = egw_vfs::mime_content_type($id);
		$file['gid'] *= -1;  // our widgets use negative gid's
		if (($props = egw_vfs::propfind($id)))
		{
			foreach($props as $prop) $file[$prop['name']] = $prop['val'];
		}
		if (($file['is_link'] = egw_vfs::is_link($id)))
		{
			$file['symlink'] = egw_vfs::readlink($id);
		}
		$extra = egw_vfs::getExtraInfo($id);

		// Custom fields
		if($content && strpos($content, '#') !== 0)
                {
			// Expand link-to custom fields
                        $this->cf_link_to_expand($file, $content, $info);
		
			foreach(config::get_customfields('filemanager') as $name => $field)
			{
				// Set any missing custom fields, or the marker will stay
				if(!$file['#'.$name])
				{
					$file['#'.$name] = '';
					continue;
				}

				// Format date cfs per user preferences
				if($field['type'] == 'date' || $field['type'] == 'date-time')
				{
					$file['#'.$name] = egw_time::to($file['#'.$name], $field['type'] == 'date' ? true : '');
				}
			}
                }

		// Links
		/* Not applicable to filemanager
		$file['links'] = $this->get_links('filemanager', $id, '!'.egw_link::VFS_APPNAME);
 		$file['attachments'] = $this->get_links('filemanager', $id, egw_link::VFS_APPNAME);
		$file['links_attachments'] = $this->get_links('filemanager', $id);
		foreach(array_keys($GLOBALS['egw_info']['user']['apps']) as $app)
		{
			$file["links/{$app}"] = $this->get_links('filemanager',$id, $app);
		}
		*/

		// If in apps folder, try for app-specific placeholders
		if($dirlist[1] == 'apps' && count($dirlist) > 1)
		{
			// Try this first - a normal path /apps/appname/id/file
			list($app, $app_id) = explode('/', substr($file['path'], strpos($file['path'], 'apps/')+5));
			// Symlink?
			if(!$app || !(int)$app_id || !array_key_exists($app, $GLOBALS['egw_info']['user']['apps'])) {
				// Try resolving just app + ID - /apps/App Name/Record Title/file
				$resolved = egw_vfs::resolve_url_symlinks(implode('/',array_slice(explode('/',$file['dir']),0,4)));
				list($app, $app_id) = explode('/', substr($resolved, strpos($resolved, 'apps/')+5));

				if(!$app || !(int)$app_id || !array_key_exists($app, $GLOBALS['egw_info']['user']['apps'])) {
					// Get rid of any virtual folders (eg: All$) and symlinks
					$resolved = egw_vfs::resolve_url_symlinks($file['path']);
					list($app, $app_id) = explode('/', substr($resolved, strpos($resolved, 'apps/')+5));
				}
			}
			if($app && $app_id)
			{
				if($app && $GLOBALS['egw_info']['user']['apps'][$app])
				{
					$app_merge = null;
					try
					{
						$classname = $app .'_merge';
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
					catch(Exception $e) {}
				}
			}
		}
		$link = egw_link::mime_open($file['url'], $file['mime']);
		if(is_array($link))
		{
			// Directories have their internal protocol in path here
			if($link['path'] && strpos($link['path'], '://') !== false) $link['path'] = $file['path'];
			$link = egw_session::link('/index.php', $link);
		}
		else
		{
			// Regular files
			$link = egw_session::link($link);
		}

		// Prepend site, if missing
		if ($link{0} == '/')
		{
			$link = ($_SERVER['HTTPS'] || $GLOBALS['egw_info']['server']['enforce_ssl'] ? 'https://' : 'http://').
				($GLOBALS['egw_info']['server']['hostname'] ? $GLOBALS['egw_info']['server']['hostname'] : $_SERVER['HTTP_HOST']).$link;
		}
		$file['link'] = html::a_href(html::htmlspecialchars($file['name']), $link);
		$file['url'] = $link;

		// Add markers
		foreach($file as $key => &$value)
		{
			if(!$value) $value = '';
			$info['$$'.($prefix ? $prefix.'/':'').$key.'$$'] = $value;
		}
		if($app_placeholders)
		{
			$info = array_merge($app_placeholders, $info);
		}
		return $info;
	}

	/**
	 * Generate table with replacements for the preferences
	 *
	 */
	public function show_replacements()
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('filemanager').' - '.lang('Replacements for inserting entries into documents');
		$GLOBALS['egw_info']['flags']['nonavbar'] = false;
		common::egw_header();

		echo "<table width='90%' align='center'>\n";
		echo '<tr><td colspan="4"><h3>'.lang('Filemanager fields:')."</h3></td></tr>";

		$n = 0;
		$fields = array(
			'name' => 'name',
			'path' => 'Absolute path',
			'rel_path' => 'Path relative to current directory',
			'folder' => 'Containing folder',
			'folder_file' => 'Containing folder and file name',
			'url' => 'url',
			'link' => 'Clickable link to file',
			'comment' => 'comment',
			'mtime' => 'modified',
			'ctime' => 'created',
			'mime'	=> 'Type',
			'hsize' => 'Size',
			'size' => 'Size (in bytes)',
		);
		foreach($fields as $name => $label)
		{
			if (!($n&1)) echo '<tr>';
			echo '<td>{{'.$name.'}}</td><td>'.lang($label).'</td>';
			if ($n&1) echo "</tr>\n";
			$n++;
		}

		echo '<tr><td colspan="4"><h3>'.lang('Custom fields').":</h3></td></tr>";
		foreach(config::get_customfields('filemanager') as $name => $field)
		{
			echo '<tr><td>{{#'.$name.'}}</td><td colspan="3">'.$field['label']."</td></tr>\n";
		}

		echo '<tr><td colspan="4"><h3>'.lang('Application fields').":</h3></td></tr>";
		echo '<tr><td colspan="4">'.lang('For files linked to an application entry (inside /apps/appname/id/) the placeholders for that application are also available.  See the specific application for a list of available placeholders.').'</td></tr>';

		echo '<tr><td colspan="4"><h3>'.lang('General fields:')."</h3></td></tr>";
		foreach(array(
			'date' => lang('Date'),
			'user/n_fn' => lang('Name of current user, all other contact fields are valid too'),
			'user/account_lid' => lang('Username'),
			'pagerepeat' => lang('For serial letter use this tag. Put the content, you want to repeat between two Tags.'),
			'label' => lang('Use this tag for addresslabels. Put the content, you want to repeat, between two tags.'),
			'labelplacement' => lang('Tag to mark positions for address labels'),
			'IF fieldname' => lang('Example {{IF n_prefix~Mr~Hello Mr.~Hello Ms.}} - search the field "n_prefix", for "Mr", if found, write Hello Mr., else write Hello Ms.'),
			'NELF' => lang('Example {{NELF role}} - if field role is not empty, you will get a new line with the value of field role'),
			'NENVLF' => lang('Example {{NELFNV role}} - if field role is not empty, set a LF without any value of the field'),
			'LETTERPREFIX' => lang('Example {{LETTERPREFIX}} - Gives a letter prefix without double spaces, if the title is empty for example'),
			'LETTERPREFIXCUSTOM' => lang('Example {{LETTERPREFIXCUSTOM n_prefix title n_family}} - Example: Mr Dr. James Miller'),
			) as $name => $label)
		{
			echo '<tr><td>{{'.$name.'}}</td><td colspan="3">'.$label."</td></tr>\n";
		}

		echo "</table>\n";

		common::egw_footer();
	}
}
