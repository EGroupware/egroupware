<?php
/**
 * eGroupWare - importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

/**
 * Userinterface to define {im|ex}ports
 *
 * @package importexport
 */
class importexport_definitions_ui
{
	const _debug = false;

	const _appname = 'importexport';

	public $public_functions = array(
		'edit' => true,
		'index' => true,
		'wizard' => true,
		'import_definition' => true,
		'site_config' => true,
	);

	/**
	 * holds all available plugins
	 *
	 * @var array
	 */
	var $plugins;

	/**
	 * holds user chosen plugin after step20
	 *
	 * @var object
	 */
	var $plugin;

	/**
	 * xajax response object
	 *
	 * @var object
	 */
	var $response = true;

	function __construct()
	{
		// we cant deal with notice and warnings, as we are on ajax!
		error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
		$GLOBALS['egw']->translation->add_app(self::_appname);
		$GLOBALS['egw_info']['flags']['currentapp'] = self::_appname;

		$GLOBALS['egw_info']['flags']['include_xajax'] = true;
		$this->etpl = new etemplate();
		$this->clock = html::image(self::_appname,'clock');
		$this->steps = array(
			'wizard_step10' => lang('Choose an application'),
			'wizard_step20' => lang('Choose a plugin'),
			'wizard_step21' => lang('Choose a name for this definition'),
			'wizard_step90' => lang('Which users are allowed to use this definition'),
			'wizard_finish' => '',
		);
		//register plugins
		$this->plugins = importexport_helper_functions::get_plugins();
	}

	/**
	 * List defined {im|ex}ports
	 *
	 * @param array $content=null
	 */
	function index($content = null,$msg='')
	{
		$filter = array('name' => '*');

		if($GLOBALS['egw_info']['user']['apps']['admin']) {
			// Any public definition
			$filter[] = '(owner=0 OR owner IS NULL OR allowed_users IS NOT NULL OR owner = ' . $GLOBALS['egw_info']['user']['account_id'] . ')';
		} else {
			// Filter private definitions
			$filter['owner'] = $GLOBALS['egw_info']['user']['account_id'];
			$config = config::read('phpgwapi');
			if($config['export_limit'] == 'no') {
				$filter['type'] = 'import';
			}
		}

		$bodefinitions = new importexport_definitions_bo(false, true);
		if (is_array($content))
		{
			if (isset($content['rows']['delete']))
			{
				$bodefinitions->delete(array_keys($content['delete'],'pressed'));
			}
			elseif(($button = array_search('pressed',$content['nm']['rows'])) !== false)
			{
				$selected = $content['nm']['rows']['selected'];
				if(count($selected) < 1 || !is_array($selected)) exit();
				switch ($button)
				{
					case 'delete_selected' :
						$bodefinitions->delete($selected);
						break;

					case 'export_selected' :
						$mime_type = ($GLOBALS['egw']->html->user_agent == 'msie' || $GLOBALS['egw']->html->user_agent == 'opera') ?
							'application/octetstream' : 'application/octet-stream';
						$name = 'importexport_definition.xml';
						header('Content-Type: ' . $mime_type);
						header('Content-Disposition: attachment; filename="'.$name.'"');
						echo $bodefinitions->export($selected);
						exit();

						break;

					default:
				}
			}

		}

		if(!is_array($content['nm'])) {
			$content['nm'] = array(
				'get_rows'	=> 'importexport.importexport_definitions_ui.get_rows',
				'no_cat'	=> true,
				'no_filter'	=> true,
				'no_filter2'	=> true,
				'header_right'	=> 'importexport.definition_index.add',
				'csv_fields'	=> false,	// Disable CSV export, uses own export
				'row_id'	=> 'id',
				'actions'	=> $this->get_actions()
			);
		}
		if(egw_session::appsession('index', 'importexport')) {
			$content['nm'] = array_merge($content['nm'], egw_session::appsession('index', 'importexport'));
		}
		$sel_options = array(
			'type'	=> array(
				'import'	=> lang('import'),
				'export'	=> lang('export'),
			),
			'allowed_users' => array(null => lang('Private'))
		);
		foreach ($this->plugins as $appname => $options)
		{
			if($GLOBALS['egw_info']['user']['apps'][$appname] || $GLOBALS['egw_info']['user']['apps']['admin']) {
				$sel_options['application'][$appname] = lang($appname);
			}
		}

		$etpl = new etemplate(self::_appname.'.definition_index');
		return $etpl->exec( self::_appname.'.importexport_definitions_ui.index', $content, $sel_options, $readonlys, $preserv );
	}

	private function get_actions() {
		$group = 1;
		$actions = array(
			'edit' => array(
				'caption' => 'Edit',
				'allowOnMultiple' => false,
				'url' => 'menuaction=addressbook.addressbook_ui.edit&contact_id=$id',
				'popup' => egw_link::get_registry('addressbook', 'add_popup'),
				'group' => $group,
				'disableClass' => 'rowNoEdit',
			),
		);
		$actions['select_all'] = array(
                        'caption' => 'Whole query',
                        'checkbox' => true,
                        'hint' => 'Apply the action on the whole query, NOT only the shown contacts!!!',
                        'group' => ++$group,
                );
	}

	public function get_rows(&$query, &$rows, &$readonlys) {
		$rows = array();
		egw_session::appsession('index','importexport',$query);
		$bodefinitions = new importexport_definitions_bo($query['col_filter'], true);
		return $bodefinitions->get_rows($query, $rows, $readonlys);
	}

	function edit()
	{
		if(!$_definition = $_GET['definition'])
		{
			//close window
		}
		$definition = array('name' => $_definition);
		$bodefinitions = new importexport_definitions_bo();
		$definition = $bodefinitions->read($definition);
		$definition['edit'] = true;
		$this->wizard($definition);
	}

	function wizard($content = null, $msg='')
	{
		$GLOBALS['egw_info']['flags']['java_script'] .=
			"<script type='text/javascript'>
				function xajax_eT_wrapper_init() {
					//window.resizeTo(document.documentElement.scrollWidth+20,document.documentElement.offsetHeight+40);
					window.moveTo(screen.availWidth/2 - window.outerWidth/2,
						screen.availHeight/2 - window.outerHeight/2);
				}
			</script>";

		$this->etpl->read('importexport.wizardbox');
		$this->wizard_content_template =& $this->etpl->children[0]['data'][1]['A'][2][1]['name'];

		if(is_array($content) &&! $content['edit'])
		{
			if(self::_debug) error_log('importexport.wizard->$content '. print_r($content,true));
			// fetch plugin object
			if($content['plugin'] && $content['application'])
			{
				$wizard_name = $content['application'] . '_wizard_' . str_replace($content['application'] . '_', '', $content['plugin']);

				// we need to deal with the wizard object if exists
				if (file_exists(EGW_SERVER_ROOT . '/'. $content['application'].'/importexport/class.wizard_'. $content['plugin'].'.inc.php'))
				{
					error_log('Deprecated location for importexport wizard.  Please move it to app/inc/ and rename it to follow new conventions');
				}
				elseif (file_exists(EGW_SERVER_ROOT . '/'. $content['application']."/inc/class.$wizard_name.inc.php"))
				{
					$wizard_plugin = $wizard_name;
				}
				else
				{
					$wizard_plugin = $content['plugin'];
				}
				$this->plugin = is_object($GLOBALS['egw']->$wizard_plugin) ? $GLOBALS['egw']->$wizard_plugin : new $wizard_plugin;

				// Global object needs to be the same, or references to plugin don't work
				if(!is_object($GLOBALS['egw']->importexport_definitions_ui) || $GLOBALS['egw']->importexport_definitions_ui !== $this)
					$GLOBALS['egw']->importexport_definitions_ui =& $this;
			}
			// deal with buttons even if we are not on ajax
			if(isset($content['button']) && array_search('pressed',$content['button']) === false && count($content['button']) == 1)
			{
				$button = array_keys($content['button']);
				$content['button'] = array($button[0] => 'pressed');
			}
			// Override next button on step 21, to do a regular submit for the file upload
			if($content['step'] == 'wizard_step21') {
				$this->etpl->set_cell_attribute('button[next]', 'onclick', '');
			}

			// post process submitted step
			if($content['step']) {
				if(!key_exists($content['step'],$this->steps))
					$next_step = $this->plugin->$content['step']($content);
				else
					$next_step = $this->$content['step']($content);
			} else {
				die('Cannot find next step');
			}

			// pre precess next step
			$sel_options = $readonlys = $preserv = array();

			// Disable finish button if required fields are missing
			if(!$content['name'] || !$content['type'] || !$content['plugin']) {
				$GLOBALS['egw']->js->set_onload("disable_button('exec[button][finish]');");
			}
			if(!key_exists($next_step,$this->steps))
			{
				$this->wizard_content_template = $this->plugin->$next_step($content,$sel_options,$readonlys,$preserv);
			}
			else
			{
				$this->wizard_content_template = $this->$next_step($content,$sel_options,$readonlys,$preserv);
			}

			$html = $this->etpl->exec(self::_appname.'.importexport_definitions_ui.wizard',$content,$sel_options,$readonlys,$preserv,1);
		}
		else
		{
			// initial content
			$GLOBALS['egw']->js->set_onload("xajax_eT_wrapper_init();");
			$GLOBALS['egw']->js->set_onload("disable_button('exec[button][previous]');");

			$sel_options = $readonlys = $preserv = array();
			if($content['edit'])
				unset ($content['edit']);

			$this->wizard_content_template = $this->wizard_step10($content, $sel_options, $readonlys, $preserv);
			$html = $this->etpl->exec(self::_appname.'.importexport_definitions_ui.wizard',$content,$sel_options,$readonlys,$preserv,1);
		}

		if(class_exists('xajaxResponse'))
		{
			$this->response = new xajaxResponse();

			if ($content['closewindow'])
			{
				$this->response->addScript("window.close();");
				$this->response->addScript("opener.location.reload();");
				// If Browser can't close window we display a "close" buuton and
				// need to disable normal buttons
				$this->response->addAssign('exec[button][previous]','style.display', 'none');
				$this->response->addAssign('exec[button][next]','style.display', 'none');
				$this->response->addAssign('exec[button][finish]','style.display', 'none');
				$this->response->addAssign('exec[button][cancel]','style.display', 'none');
			}
			$this->response->addAssign('contentbox', 'innerHTML', $html);
			if (($onload = $GLOBALS['egw']->js->set_onload('')))
			{
				$this->response->addScript($onload);
			}
			$this->response->addAssign('picturebox', 'style.display', 'none');
			$this->response->addScript("set_style_by_class('div','popupManual','display','inline');
				popup_resize();
			");

			return $this->response->getXML();
		}
		else
		{
			$GLOBALS['egw']->js->set_onload("document.getElementById('picturebox').style.display = 'none';");
			egw_framework::validate_file('.', 'etemplate', 'etemplate');
			common::egw_header();
			echo '<div id="divMain">'."\n";
			echo '<div><h3>{Im|Ex}port Wizard</h3></div>';
			// adding a manual icon to every popup
			if ($GLOBALS['egw_info']['user']['apps']['manual'])
			{
				$manual = new etemplate('etemplate.popup.manual');
				echo $manual->exec(self::_appname.'.importexport_definitions_ui.wizard',$content,$sel_options,$readonlys,$preserv,1);
				unset($manual);
			}

			echo '<div id="contentbox">';
			echo $html;
			echo '</div></div>'."\n";
			echo '<style type="text/css">#picturebox { position: absolute; right: 27px; top: 24px; }</style>'."\n";
			echo '<div id="picturebox">'. $this->clock. '</div>';
			return;
		}
	}

	/**
	 * gets name of next step
	 *
	 * @param string  $curr_step
	 * @param int $step_width
	 * @return string containing function name of next step
	 */
	function get_step ($curr_step, $step_width)
	{
		/*if($content['plugin'] && $content['application']&& !is_object($this->plugin))
		{
			$plugin_definition =  $this->plugins[$content['application']][$content['plugin']]['definition'];
			if($plugin_definition) $this->plugin = new $plugin_definition;
		}*/
		if(is_object($this->plugin) && is_array($this->plugin->steps))
		{
			$steps = array_merge($this->steps,$this->plugin->steps);
			$steps = array_flip($steps); asort($steps);	$steps = array_flip($steps);
		}
		else
		{
			$steps = $this->steps;
		}
		$step_keys = array_keys($steps);
		$nn = array_search($curr_step,$step_keys)+(int)$step_width;
		return (key_exists($nn,$step_keys)) ? $step_keys[$nn] : 'wizard_finish';
	}


	function wizard_step10(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if(self::_debug) error_log('importexport.importexport_definitions_ui::wizard_step10->$content '.print_r($content,true));

		// return from step10
		if ($content['step'] == 'wizard_step10')
		{
			switch (array_search('pressed', $content['button']))
			{
				case 'next':
					return $this->get_step($content['step'],1);
				case 'finish':
					return 'wizard_finish';
				default :
					return $this->wizard_step10($content,$sel_options,$readonlys,$preserv);
			}

		}
		// init step10
		else
		{
			$content['msg'] = $this->steps['wizard_step10'];
			foreach ($this->plugins as $appname => $options)
			{
				if($GLOBALS['egw_info']['user']['apps'][$appname] || $GLOBALS['egw_info']['user']['apps']['admin']) {
					$sel_options['application'][$appname] = lang($appname);
				}
			}
			$GLOBALS['egw']->js->set_onload("disable_button('exec[button][previous]');");
			$content['step'] = 'wizard_step10';
			$preserv = $content;
			unset ($preserv['button']);
			return 'importexport.wizard_chooseapp';
		}

	}

	// get plugin
	function wizard_step20(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if(self::_debug) error_log('importexport.' . get_class($this) . '::wizard_step20->$content '.print_r($content,true));

		// return from step20
		if ($content['step'] == 'wizard_step20')
		{
			switch (array_search('pressed', $content['button']))
			{
				case 'next':
					// There's no real reason the plugin has to come from any of these, as long as it has a $steps variable
					if($this->plugin instanceof importexport_iface_import_plugin || $this->plugin instanceof importexport_wizard_basic_import_csv) {
						$content['type'] = 'import';
					} elseif($this->plugin instanceof importexport_iface_export_plugin || $this->plugin instanceof importexport_wizard_basic_export_csv) {
						$content['type'] = 'export';
					} else {
						throw new egw_exception('Invalid plugin');
					}
					return $this->get_step($content['step'],1);
				case 'previous' :
					unset ($content['plugin']);
					if(is_object($this->response)) {
						$this->response->addScript("disable_button('exec[button][previous]');");
					}
					return $this->get_step($content['step'],-1);
				case 'finish':
					return 'wizard_finish';
				default :
					return $this->wizard_step20($content,$sel_options,$readonlys,$preserv);
			}
		}
		// init step20
		else
		{
			$content['msg'] = $this->steps['wizard_step20'];
			$config = config::read('phpgwapi');
			foreach ($this->plugins[$content['application']] as $type => $plugins) {
				if($config['export_limit'] == 'no' && !$GLOBALS['egw_info']['user']['apps']['admin'] && $type == 'export') continue;
				foreach($plugins as $plugin => $name) {
					$sel_options['plugin'][$plugin] = $name;
				}
			}
			$content['step'] = 'wizard_step20';
			$preserv = $content;
			unset ($preserv['button']);
			return 'importexport.wizard_chooseplugin';
		}


	}

	// name
	function wizard_step21(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if(self::_debug) error_log('importexport.importexport_definitions_ui::wizard_step21->$content '.print_r($content,true));

		// Check for duplicate name
		$duplicate = isset($content['duplicate_error']);
		if($content['name'] && !$duplicate)
		{
			try {
				$check_definition = new importexport_definition($content['name']);
				if($check_definition && $check_definition->definition_id != $content['definition_id'])
				{
					throw new Exception('Already exists');
				}
			} catch (Exception $e) {
			//		throw new Exception('Already exists');
				$content['duplicate_error'] = lang('Duplicate name, please choose another.');
				
				// Try some suggestions
				$suggestions = array(
					$content['name'] .'-'. $GLOBALS['egw_info']['user']['account_lid'],
					$content['name'] .'-'. $GLOBALS['egw_info']['user']['account_id'],
					$content['name'] .'-'. egw_time::to('now', true),
					//$content['name'] .'-'. rand(0,100),
				);
				foreach($suggestions as $key => $suggestion) {
					$sug_definition = new importexport_definition($suggestion);
					if($sug_definition->definition_id) {
						unset($suggestions[$key]);
					}
				}
				if($suggestions) {
					$content['duplicate_error'] .= '  '. lang('Try')." \n" . implode("\n", $suggestions);
				}
				return $this->get_step($content['step'],0);
			}
		}

		// return from step21
		if ($content['step'] == 'wizard_step21' && !$duplicate)
		{
			switch (array_search('pressed', $content['button']))
			{
				case 'next':
					return $this->get_step($content['step'],1);
				case 'previous' :
					return $this->get_step($content['step'],-1);
				case 'finish':
					return 'wizard_finish';
				default :
					return $this->wizard_step21($content,$sel_options,$readonlys,$preserv);
			}
		}
		// init step21
		else
		{
			$content['msg'] = $this->steps['wizard_step21'] . ($duplicate ? "\n".$content['duplicate_error'] : '');
			$content['step'] = 'wizard_step21';
			unset($content['duplicate_error']);
			$preserv = $content;
			unset ($preserv['button']);
			return 'importexport.wizard_choosename';
		}
	}

	// allowed users
	function wizard_step90(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if(self::_debug) error_log('importexport.importexport_definitions_ui::wizard_step90->$content '.print_r($content,true));

		// return from step90
		if ($content['step'] == 'wizard_step90')
		{
			$content['owner'] = $content['just_me'] || !$GLOBALS['egw']->acl->check('share_definitions', EGW_ACL_READ,'importexport') ?
				($content['owner'] ? $content['owner'] : $GLOBALS['egw_info']['user']['account_id']) :
				null;
			$content['allowed_users'] = $content['just_me'] ? '' : implode(',',$content['allowed_users']);
			unset($content['just_me']);

			// workaround for some ugly bug related to readonlys;
			switch (array_search('pressed', $content['button']))
			{
				case 'previous' :
					return $this->get_step($content['step'],-1);
				case 'next':
				case 'finish':
					return 'wizard_finish';
				default :
					return $this->wizard_step90($content,$sel_options,$readonlys,$preserv);
			}
		}
		// init step90
		else
		{
			$content['msg'] = $this->steps['wizard_step90'];
			$content['step'] = 'wizard_step90';
			$preserv = $content;

			// Set owner for non-admins
			$content['just_me'] = ((!$content['allowed_users'] || !$content['allowed_users'][0] && count($content['allowed_users']) ==1) && $content['owner']);
			//if(!$GLOBALS['egw_info']['user']['apps']['admin'] && !$GLOBALS['egw']->acl->check('share_definition', EGW_ACL_READ, 'importexport')) {
			if(!$GLOBALS['egw']->acl->check('share_definition', EGW_ACL_READ, 'importexport') && !$GLOBALS['egw_info']['user']['apps']['admin']) {
				$content['allowed_users'] = array();
				$readonlys['allowed_users'] = true;
				$readonlys['just_me'] = true;
				$content['just_me'] = true;
			}

			unset ($preserv['button']);
			$GLOBALS['egw']->js->set_onload("disable_button('exec[button][next]');");
			if(is_object($this->response)) {
				$this->response->addAssign('exec[button][next]','style.display', 'none');
			}
			return 'importexport.wizard_chooseallowedusers';
		}
	}

	function wizard_finish(&$content)
	{
		if(self::_debug) error_log('importexport.importexport_definitions_ui::wizard_finish->$content '.print_r($content,true));
		// Take out some UI leavings
		unset($content['msg']);
		unset($content['step']);
		unset($content['button']);

		$bodefinitions = new importexport_definitions_bo();
		$bodefinitions->save($content);
		// This message is displayed if browser cant close window
		$content['msg'] = lang('ImportExport wizard finished successfully!');
		$content['closewindow'] = true;
		return 'importexport.wizard_close';
	}

	function import_definition($content='')
	{
		$bodefinitions = new importexport_definitions_bo();
		if (is_array($content))
		{
			if($content['import_file']['tmp_name'])
			{
				$bodefinitions->import($content['import_file']['tmp_name']);
				// TODO make redirect here!
			}
			if($content['update'])
			{
				$applist = importexport_helper_functions::get_apps('all', true);
				foreach($applist as $appname) {
					importexport_helper_functions::load_defaults($appname);
				}
			}
			return $this->index();
		}
		else
		{
			$etpl = new etemplate(self::_appname.'.import_definition');
			return $etpl->exec(self::_appname.'.importexport_definitions_ui.import_definition',$content,array(),$readonlys,$preserv);
		}
	}

	/**
	 * Site configuration
	 */
	public function site_config($content = array())
	{
		if(!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			egw::redirect_link('/home');
		}
		if($content['save'])
		{
			unset($content['save']);

			// ACL
			$GLOBALS['egw']->acl->delete_repository(self::_appname, 'definition',false);
			$GLOBALS['egw']->acl->delete_repository(self::_appname, 'share_definition',false);
			if($content['share_definition'])
			{
				$GLOBALS['egw']->acl->add_repository(self::_appname, 'share_definition', $content['share_definition'],
					EGW_ACL_READ
				);
			}
			unset($content['share_definition']);

			// Other config
			foreach($content as $key=>$value)
			{
				config::save_value($key, $value, 'importexport');
			}
		} elseif (isset($content['cancel'])) {
			$GLOBALS['egw']->redirect_link('/admin/index.php');
		}

		$data = config::read(self::_appname);
		$data['share_definition'] = $GLOBALS['egw']->acl->get_ids_for_location('share_definition', EGW_ACL_READ, self::_appname);

		// Folder stuff should really be part of etemplate (merge base is in there) 
		// but the sidebox link works best if they're together
		if(!array_key_exists('export_spreadsheet_folder', $data)) $data['export_spreadsheet_folder'] = 'user,stylite';
		$sel_options = array(
			'export_spreadsheet_folder' => array(
				'user'	=>	lang('User template folder'),
			)
		);
		if($GLOBALS['egw_info']['apps']['stylite']) {
			$sel_options['export_spreadsheet_folder'] += array(
				'stylite'	=> lang('Stylite template folder'),
			);
		}
		
		if(!$data['update']) $data['update'] = 'request';

		$GLOBALS['egw_info']['flags']['app_header'] = lang('Site configuration') . ' - ' . lang(self::_appname);
		$etpl = new etemplate(self::_appname.'.config');
		$etpl->exec(self::_appname.'.importexport_definitions_ui.site_config',$data,$sel_options,$readonlys,$preserv);
	}
}
