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
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();
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
		$file['path'] = $id;
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

		// Convert to human friendly values
		if($content && strpos($content, '#') !== 0)
                {
                        $this->cf_link_to_expand($file, $content, $info);
                }

		// Set any missing custom fields, or the marker will stay
		foreach(config::get_customfields('filemanager') as $name => $field)
		{
			if(!$file['#'.$name]) $file['#'.$name] = '';
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

		// Add markers
		foreach($file as $key => &$value)
		{
			if(!$value) $value = '';
			$info['$$'.($prefix ? $prefix.'/':'').$key.'$$'] = $value;
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
			'path' => 'path',
			'url' => 'url',
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
