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
		'show_replacements'		=> true,
	);

	/**
	 * Fields that are numeric, for special numeric handling
	 */
	protected $numeric_fields = array(
	);

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
		$file = Vfs::lstat($id,true);

		$file['mtime'] = Api\DateTime::to($file['mtime']);
		$file['ctime'] = Api\DateTime::to($file['ctime']);

		$file['name'] = Vfs::basename($id);
		$file['dir'] = ($dir = Vfs::dirname($id)) ? Vfs::decodePath($dir) : '';
		$dirlist = explode('/',$file['dir']);
		$file['folder'] = array_pop($dirlist);
		$file['folder_file'] = $file['folder'] . '/'.$file['name'];
		$file['path'] = $id;
		$file['rel_path'] = str_replace($this->dir.'/', '', $id);
		$file['hsize'] = Vfs::hsize($file['size']);
		$file['mime'] = Vfs::mime_content_type($id);
		$file['gid'] *= -1;  // our widgets use negative gid's
		if (($props = Vfs::propfind($id)))
		{
			foreach($props as $prop)
			{
				$file[$prop['name']] = $prop['val'];
			}
		}
		if (($file['is_link'] = Vfs::is_link($id)))
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
				if(!$file['#'.$name])
				{
					$file['#'.$name] = '';
					continue;
				}

				// Format date cfs per user Api\Preferences
				if($field['type'] == 'date' || $field['type'] == 'date-time')
				{
					$this->date_fields[] = '#'.$name;
					$file['#'.$name] = Api\DateTime::to($file['#'.$name], $field['type'] == 'date' ? true : '');
				}
			}
		}

		// If in apps folder, try for app-specific placeholders
		if($dirlist[1] == 'apps' && count($dirlist) > 1)
		{
			// Try this first - a normal path /apps/appname/id/file
			list($app, $app_id) = explode('/', substr($file['path'], strpos($file['path'], 'apps/')+5));
			// Symlink?
			if(!$app || !(int)$app_id || !array_key_exists($app, $GLOBALS['egw_info']['user']['apps'])) {
				// Try resolving just app + ID - /apps/App Name/Record Title/file
				$resolved = Vfs::resolve_url_symlinks(implode('/',array_slice(explode('/',$file['dir']),0,4)));
				list($app, $app_id) = explode('/', substr($resolved, strpos($resolved, 'apps/')+5));

				if(!$app || !(int)$app_id || !array_key_exists($app, $GLOBALS['egw_info']['user']['apps'])) {
					// Get rid of any virtual folders (eg: All$) and symlinks
					$resolved = Vfs::resolve_url_symlinks($file['path']);
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
					catch(Exception $e) {
						unset($e);	// not used
					}
				}
			}
		}
		$link = Link::mime_open($file['url'], $file['mime']);
		if(is_array($link))
		{
			// Directories have their internal protocol in path here
			if($link['path'] && strpos($link['path'], '://') !== false) $link['path'] = $file['path'];
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
			$info['$$'.($prefix ? $prefix.'/':'').$key.'$$'] = $value;
		}
		if($app_placeholders)
		{
			$info = array_merge($app_placeholders, $info);
		}
		return $info;
	}

	/**
	 * Generate table with replacements for the Api\Preferences
	 *
	 */
	public function show_replacements()
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('filemanager').' - '.lang('Replacements for inserting entries into documents');
		$GLOBALS['egw_info']['flags']['nonavbar'] = false;
		echo $GLOBALS['egw']->framework->header();

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
			'webdav_url' => 'External path using webdav',
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
		foreach(Api\Storage\Customfields::get('filemanager') as $name => $field)
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
			'NENVLF' => lang('Example {{NENVLF role}} - if field role is not empty, set a LF without any value of the field'),
			'LETTERPREFIX' => lang('Example {{LETTERPREFIX}} - Gives a letter prefix without double spaces, if the title is empty for example'),
			'LETTERPREFIXCUSTOM' => lang('Example {{LETTERPREFIXCUSTOM n_prefix title n_family}} - Example: Mr Dr. James Miller'),
			) as $name => $label)
		{
			echo '<tr><td>{{'.$name.'}}</td><td colspan="3">'.$label."</td></tr>\n";
		}

		echo "</table>\n";

		echo $GLOBALS['egw']->framework->footer();
	}
}
