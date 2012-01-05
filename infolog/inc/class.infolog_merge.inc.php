<?php
/**
 * Infolog - document merge
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Nathan Gray
 * @package infolog
 * @copyright (c) 2007-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright 2011 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Infolog - document merge object
 */
class infolog_merge extends bo_merge
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
		$this->bo = new infolog_bo();
	}

	/**
	 * Get infolog replacements
	 *
	 * @param int $id id of entry
	 * @param string &$content=null content to create some replacements only if they are use
	 * @return array|boolean
	 */
	protected function get_replacements($id,&$content=null)
	{
		if (!($replacements = $this->infolog_replacements($id)))
		{
			return false;
		}
		if (!(strpos($content,'$$info_contact/') === false))
		{
			// Check to see if it's actually a contact, then load
			if(is_array($replacements['$$info_link$$']) && $replacements['$$info_link$$']['app'] == 'addressbook')
			{
				$replacements += $this->contact_replacements($replacements['$$info_link$$']['id'],'info_contact');
			}
			if(is_array($replacements['$$info_link$$'])) unset($replacements['$$info_link$$']);
		}
		return $replacements;
	}

	/**
	 * Get infolog replacements
	 *
	 * @param int $id id of entry
	 * @param string $prefix='' prefix like eg. 'erole'
	 * @return array|boolean
	 */
	public function infolog_replacements($id,$prefix='') 
	{
		$record = new infolog_egw_record($id);
		$info = array();

		// Convert to human friendly values
		$types = infolog_egw_record::$types;
		$_selects = $this->bo->enums + array('status' => $this->bo->status[$record->info_type]);
		foreach($_selects as $name => $value)
		{
			$selects['info_'.$name] = $value;
			$types['select'][] = 'info_'.$name;
		}
		importexport_export_csv::convert($record, $types, 'infolog', $selects);
		if($record->info_contact)
		{
			$array['info_contact'] = $array['info_link']['title'];
		}

		// Make sure line breaks get preserved (Needed for emails in particular)
		switch($this->mimetype)
		{
			case 'application/vnd.oasis.opendocument.text':         // open office
			case 'application/vnd.oasis.opendocument.spreadsheet':
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document': // ms office 2007
			case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
				$record->info_des = nl2br($record->info_des);
		}

		// Set any missing custom fields, or the marker will stay
		$array = $record->get_record_array();
		foreach($this->bo->customfields as $name => $field)
		{
			if(!$array['#'.$name]) $array['#'.$name] = '';
		}

		// Links
		$array['links'] = $this->get_links('infolog', $id, '!'.egw_link::VFS_APPNAME);
		$array['attachments'] = $this->get_links('infolog', $id, egw_link::VFS_APPNAME);
		$array['links_attachments'] = $this->get_links('infolog', $id);
		// Need to set each app, to make sure placeholders are removed
		foreach(array_keys($GLOBALS['egw_info']['user']['apps']) as $app)
		{
			$array["links/$app"] = $this->get_links('infolog',$id,$app);
		}

		// Check for linked project ID	 
		$links = egw_link::get_links('infolog', $id, 'projectmanager');	 
		foreach($links as $link_id => $app_id) {
			$array['pm_id'] = $app_id;	 
			$array['project'] = egw_link::title('projectmanager', $app_id);	 
			break;	 
		}	 
 
		// Add markers
		foreach($array as $key => &$value)
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
		$GLOBALS['egw_info']['flags']['app_header'] = lang('infolog').' - '.lang('Replacements for inserting entries into documents');
		$GLOBALS['egw_info']['flags']['nonavbar'] = false;
		common::egw_header();

		echo "<table width='90%' align='center'>\n";
		echo '<tr><td colspan="4"><h3>'.lang('Infolog fields:')."</h3></td></tr>";

		$n = 0;
		$tracking = new infolog_tracking($this->bo);
		$fields = array('info_id' => lang('Infolog ID'), 'pm_id' => lang('Project ID'), 'project' => lang('Project name')) + $tracking->field2label;
		translation::add_app('projectmanager');
		foreach($fields as $name => $label)
		{
			if (in_array($name,array('custom'))) continue;	// dont show them

			if (in_array($name,array('info_subject', 'info_des')) && $n&1)		// main values, which should be in the first column
			{
				echo "</tr>\n";
				$n++;
			}
			if (!($n&1)) echo '<tr>';
			echo '<td>{{'.$name.'}}</td><td>'.lang($label).'</td>';
			if ($n&1) echo "</tr>\n";
			$n++;
		}

		echo '<tr><td colspan="4"><h3>'.lang('Custom fields').":</h3></td></tr>";
		foreach($this->bo->customfields as $name => $field)
		{
			echo '<tr><td>{{#'.$name.'}}</td><td colspan="3">'.$field['label']."</td></tr>\n";
		}

		echo '<tr><td colspan="4"><h3>'.lang('Contact fields').':</h3></td></tr>';
		$n = 0;
                foreach($this->contacts->contact_fields as $name => $label)
                {
                        if (in_array($name,array('tid','label','geo'))) continue;       // dont show them, as they are not used in the UI atm.

                        if (in_array($name,array('email','org_name','tel_work','url')) && $n&1)         // main values, which should be in the first column
                        {
                                echo "</tr>\n";
                                $n++;
                        }
                        if (!($n&1)) echo '<tr>';
                        echo '<td>{{info_contact/'.$name.'}}</td><td>'.$label.'</td>';
                        if ($n&1) echo "</tr>\n";
                        $n++;
                }

                echo '<tr><td colspan="4"><h3>'.lang('Custom fields').":</h3></td></tr>";
                foreach($this->contacts->customfields as $name => $field)
                {
                        echo '<tr><td>{{info_contact/#'.$name.'}}</td><td colspan="3">'.$field['label']."</td></tr>\n";
                }

		echo '<tr><td colspan="4"><h3>'.lang('General fields:')."</h3></td></tr>";
		foreach(array(
			'links' => lang('Titles of any entries linked to the current record, excluding attached files'),
			'attachments' => lang('List of files linked to the current record'),
			'links_attachments' => lang('Links and attached files'),
			'links/[appname]' => lang('Links to specified application.  Example: {{links/addressbook}}'),
			'date' => lang('Date'),
			'user/n_fn' => lang('Name of current user, all other contact fields are valid too'),
			'user/account_lid' => lang('Username'),
			'pagerepeat' => lang('For serial letter use this tag. Put the content, you want to repeat between two Tags.'),
			'label' => lang('Use this tag for addresslabels. Put the content, you want to repeat, between two tags.'),
			'labelplacement' => lang('Tag to mark positions for address labels'),
			'IF fieldname' => lang('Example {{IF n_prefix~Mr~Hello Mr.~Hello Ms.}} - search the field "n_prefix", for "Mr", if found, write Hello Mr., else write Hello Ms.'),
			'NELF' => lang('Example {{NELF role}} - if field role is not empty, you will get a new line with the value of field role'),
			'NENVLF' => lang('Example {{NELFNV role}} - if field role is not empty, set a LF without any value of the field'),
			'LETTERPREFIX' => lang('Example {{LETTERPREFIX}} - Gives a letter prefix without double spaces, if the title is emty for example'),
			'LETTERPREFIXCUSTOM' => lang('Example {{LETTERPREFIXCUSTOM n_prefix title n_family}} - Example: Mr Dr. James Miller'),
			) as $name => $label)
		{
			echo '<tr><td>{{'.$name.'}}</td><td colspan="3">'.$label."</td></tr>\n";
		}

		echo "</table>\n";

		common::egw_footer();
	}
}
