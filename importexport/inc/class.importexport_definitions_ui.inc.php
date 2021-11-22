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

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Acl;
use EGroupware\Api\Etemplate;

/**
 * Userinterface to define {im|ex}ports
 *
 * @package importexport
 */
class importexport_definitions_ui
{
	const _debug = false;

	const _appname = 'importexport';

	// To skip a step, step returns this
	const SKIP = '-skip-';

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

	function __construct()
	{
		// we cant deal with notice and warnings, as we are on ajax!
		error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
		Api\Translation::add_app(self::_appname);
		$GLOBALS['egw_info']['flags']['currentapp'] = self::_appname;

		$this->etpl = new Etemplate();
		$this->clock = Api\Html::image(self::_appname,'clock');
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
			$config = Api\Config::read('phpgwapi');
			if($config['export_limit'] == 'no' && !Api\Storage\Merge::is_export_limit_excepted()) {
				$filter['type'] = 'import';
			}
		}

		if (is_array($content))
		{
			// Handle legacy actions
			if (isset($content['nm']['rows']['delete']))
			{
				$content['nm']['action'] = 'delete';
				$content['nm']['selected'] = array_keys($content['nm']['rows']['delete'],'pressed');
			}
			elseif(($button = array_search('pressed',$content['nm']['rows'])) !== false)
			{
				$selected = $content['nm']['rows']['selected'];
				if(count($selected) < 1 || !is_array($selected)) exit();
				switch ($button)
				{
					case 'delete_selected' :
						$content['nm']['selected'] = $selected;
						$content['nm']['action'] = 'delete';
						break;
					case 'export_selected':
						$content['nm']['selected'] = $selected;
						$content['nm']['action'] = 'export';
						break;
				}
			}
			if ($content['nm']['action'])
			{
				if (!count($content['nm']['selected']) && !$content['nm']['select_all'])
				{
					$msg = lang('You need to select some entries first!');
				}
				else
				{
					// Action has an additional parameter
					if(in_array($content['nm']['action'], array('owner', 'allowed')))
					{
						$action = $content['nm']['action'];
						if($content['nm']['action'] == 'allowed')
						{
							$content['allowed'] = $content['allowed_popup']['allowed_private'] == 'true' ? null : (
								$content['allowed_popup']['all_users']=='true' ? 'all' : implode(',',$content['allowed_popup']['allowed'])
							);
						}
						else
						{
							$content['owner'] = $content['owner_popup']['owner'];
						}
						if(is_array($content[$content['nm']['action']]))
						{
							$content[$content['nm']['action']] = implode(',',$content[$content['nm']['action']]);
						}
						$content['nm']['action'] .= '_' . $content[$action];
						unset($content[$action]);
						unset($content['allowed_popup']);
					}
					if ($this->action($content['nm']['action'],$content['nm']['selected'],$content['nm']['select_all'],
						$success,$failed,$action_msg,'index',$msg))
					{
						$msg .= lang('%1 definition(s) %2',$success,$action_msg);
					}
					elseif(empty($msg))
					{
						$msg .= lang('%1 definition(s) %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed);
					}
				}
			}
		}

		$content['nm'] = array(
			'get_rows'	=> 'importexport.importexport_definitions_ui.get_rows',
			'no_cat'	=> true,
			'no_filter'	=> true,
			'no_filter2'	=> true,
			'csv_fields'	=> false,	// Disable CSV export, uses own export
			'default_cols'  => '!actions',  // switch legacy actions column and row off by default
			'row_id'	=> 'definition_id',
			'placeholder_actions' => array('add')
		);
		if($_GET['application']) $content['nm']['col_filter']['application'] = $_GET['application'];

		if(Api\Cache::getSession('importexport', 'index'))
		{
			$content['nm'] = array_merge($content['nm'], Api\Cache::getSession('importexport', 'index'));
		}
		$content['nm']['actions'] = $this->get_actions();
		$sel_options = array(
			'type'	=> array(
				'import'	=> lang('import'),
				'export'	=> lang('export'),
			),
			'allowed_users' => array(
				array('value' => 'private', 'label' => lang('Private')),
				array('value' => 'all', 'label' => lang('all'))
			)
		);
		foreach ($this->plugins as $appname => $options)
		{
			if($GLOBALS['egw_info']['user']['apps'][$appname] || $GLOBALS['egw_info']['user']['apps']['admin']) {
				$sel_options['application'][$appname] = lang($appname);
			}
		}
		if($msg) $content['msg'] = $msg;

		$etpl = new Etemplate(self::_appname.'.definition_index');
		return $etpl->exec( self::_appname.'.importexport_definitions_ui.index', $content, $sel_options, $readonlys, $preserv );
	}

	private function get_actions() {
		$group = 0;
		$actions = array(
			'edit' => array(
				'caption' => 'Open',
				'default' => true,
				'allowOnMultiple' => false,
				'url' => 'menuaction=importexport.importexport_definitions_ui.edit&definition=$id',
				'popup' => '500x500',
				'group' => $group,
			),
			'add' => array(
				'caption' => 'Add',
				'url' => 'menuaction=importexport.importexport_definitions_ui.edit',
				'popup' => '500x500',
				'group' => $group,
			),
			'execute' => array(
				'caption' => 'Execute',
				'icon' => 'importexport/navbar',
				'allowOnMultiple' => false,
				'group' => $group,
				'onExecute' => 'javaScript:app.importexport.run_definition'
			),
			'schedule' => array(
				'caption' => 'Schedule',
				'icon' => 'timesheet/navbar',
				'allowOnMultiple' => false,
				'url' => 'menuaction=importexport.importexport_schedule_ui.edit&definition=$id',
				'popup' => '500x500',
				'group' => $group,
			),
			'change' => array(
				'caption' => 'Change',
				'icon' => 'edit',
				'children' => array(
					'owner' => array(
						'caption' => 'Owner',
						'group' => 1,
						'icon' => 'addressbook/accounts',
						'nm_action' => 'open_popup',
					),
					'allowed' => array(
						'caption' => 'Allowed users',
						'group' => 1,
						'icon' => 'addressbook/group',
						'nm_action' => 'open_popup',
					)
				),
				'disableClass' => 'rowNoEdit',
			),
			'select_all' => array(
				'caption' => 'Whole query',
				'checkbox' => true,
				'hint' => 'Apply the action on the whole query, NOT only the shown definitions!!!',
				'group' => ++$group,
			),
			'copy' => array(
				'caption' => 'Copy',
				'group' => ++$group,
			),
			'createexport' => array(
				'caption' => 'Create export',
				'hint' => 'Create a matching export definition based on this import definition',
				'icon' => 'export',
				'group' => $group,
				'allowOnMultiple' => false,
				'disableClass' => 'export'
			),

			'export' => array(
				'caption' => 'Export',
				'group' => $group,
				'icon' => 'filesave',
				'postSubmit' => true
			),
			'delete' => array(
				'caption' => 'Delete',
				'confirm' => 'Delete this entry',
				'confirm_multiple' => 'Delete these entries',
				'group' => ++$group,
				'disableClass' => 'rowNoDelete',
			),
		);

		// Unset admin actions
		if(!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			unset($actions['schedule']);
		}
		return $actions;
	}

	/**
	 * apply an action to multiple entries
	 *
	 * @param string/int $action 'delete', 'export', etc.
	 * @param array $selected id's to use if !$use_all
	 * @param boolean $use_all if true use all entries of the current selection (in the session)
	 * @param int &$success number of succeded actions
	 * @param int &$failed number of failed actions (not enought permissions)
	 * @param string &$action_msg translated verb for the actions, to be used in a message like %1 entries 'deleted'
	 * @param string/array $session_name 'index' or 'email', or array with session-data depending if we are in the main list or the popup
	 * @return boolean true if all actions succeded, false otherwise
	 */
	function action($action,$selected,$use_all,&$success,&$failed,&$action_msg,$session_name,&$msg)
	{
		//error_log( __METHOD__."('$action', ".array2string($selected).', '.array2string($use_all).",,, '$session_name')");
		if ($use_all)
		{
			// get the whole selection
			$old_query = $query = is_array($session_name) ? $session_name : Api\Cache::getSession('importexport', $session_name);

			@set_time_limit(0);				// switch off the execution time limit, as it's for big selections to small
			$query['num_rows'] = -1;		// all
			$query['csv_export'] = true;	// so get_rows method _can_ produce different content or not store state in the session
			$this->get_rows($query,$rows,$readonlys);

			$selected = array();
			foreach($rows as $row) {
				$selected[] = $row['definition_id'];
			}
			if(!is_array($session_name))
			{
				// Restore old query
				Api\Cache::setSession('importexport', $session_name, $old_query);
			}
		}

		// Dialogs to get options
		list($action, $settings) = explode('_', $action, 2);

		$bodefinitions = new importexport_definitions_bo(false, true);

		switch($action) {
			case 'allowed':
				// Field is allowed_users, popup doesn't like _
				$action = 'allowed_users';
				// Fall through
			case 'owner':
				$action_msg = lang('changed'. ' ' . $action);
				foreach($selected as $id) {
					$definition = $bodefinitions->read((int)$id);
					if($definition['definition_id']) {
						// Prevent private with no owner
						if(!$definition['owner'] && !$settings) $definition['owner'] = $GLOBALS['egw_info']['user']['account_id'];

						$definition[$action] = $settings;
						$bodefinitions->save($definition);
						$success++;
					}
				}
				break;
			case 'delete':
				$bodefinitions->delete($selected);
				$action_msg = lang('deleted');
				break;

			case 'export' :
				$action_msg = lang('exported');
				$mime_type = ($GLOBALS['egw']->html->user_agent == 'msie' || $GLOBALS['egw']->html->user_agent == 'opera') ?
					'application/octetstream' : 'application/octet-stream';
				$name = 'importexport_definition.xml';
				header('Content-Type: ' . $mime_type);
				header('Content-Disposition: attachment; filename="'.$name.'"');
				echo $bodefinitions->export($selected);
				exit();
				break;

			case 'copy':
				$action_msg = lang('copied');
				// Should only be one selected
				foreach($selected as $id) {
					$definition = $bodefinitions->read((int)$id);
					if($definition['definition_id']) {
						unset($definition['definition_id']);
						$definition['name'] = $settings ? $settings : $definition['name'] . ' copy';
						try {
							$bodefinitions->save($definition);
						} catch (Exception $e) {
							try {
								$definition['name'] .= ' ' . $GLOBALS['egw_info']['user']['account_lid'];
								$bodefinitions->save($definition);
							} catch (Exception $ex) {
								$failed++;
								$msg .= lang('Duplicate name, please choose another.');
								continue;
							}
						}
						$success++;
					}
				}
				break;
			case 'createexport':
				$action_msg = lang('created');
				// Should only be one selected
				foreach($selected as $id) {
					$definition = new importexport_definition($id);
					try {
						$export = $bodefinitions->export_from_import($definition);
						$export->save($definition->get_title());
					} catch (Exception $e) {
						if($export)
						{
							try {
								$export->name = $export->name.' ' . $GLOBALS['egw_info']['user']['account_lid'];
								$export->save($export->name);
							} catch (Exception $ex) {
								$failed++;
								$msg .= lang('Duplicate name, please choose another.');
								continue;
							}
						}
						else
						{
							$failed++;
						}
					}
					$success++;
				}
				break;
		}
		return !$failed;
	}

	public function get_rows(&$query, &$rows, &$readonlys) {
		$rows = array();
		Api\Cache::setSession('importexport', 'index', array_intersect_key($query, array_flip(array(
			'col_filter', 'search', 'filter', 'filter2'
		))));

		// Special handling for allowed users 'private'
		if($query['col_filter']['allowed_users'] == 'private')
		{
			unset($query['col_filter']['allowed_users']);
			$query['col_filter'][] = 'allowed_users = ' . $GLOBALS['egw']->db->quote(',,');
		}
		$bodefinitions = new importexport_definitions_bo($query['col_filter'], true);
		// We don't care about readonlys for the UI
		return $bodefinitions->get_rows($query, $rows, $discard);
	}

	/**
	 * Edit a definition
	 *
	 * To jump to a certain step, pass the previous step in the URL step=wizard_stepXX
	 * The wizard will validate that step, then display the _next_ step..
	 */
	function edit()
	{
		if(!$_definition = $_GET['definition'])
		{
			$content = array(
				'edit'		=> true,
				'application'	=> $_GET['application'],
				'plugin'	=> $_GET['plugin']
			);

			// Jump to a step
			if($_GET['step'])
			{
				$content['edit'] = false;
				// Wizard will process previous step, then advance
				$content['step'] = $this->get_step($_GET['step'],-1);
				$content['button']['next'] = 'pressed';
				$this->wizard($content);
			}
			else
			{
				// Initial form
				$this->wizard($content);
			}
			return;
		}
		if(is_numeric($_GET['definition']))
		{
			$definition = (int)$_GET['definition'];
		}
		else
		{
			$definition = array('name' => $_definition);
		}
		$bodefinitions = new importexport_definitions_bo();
		$definition = $bodefinitions->read($definition);
		$definition['edit'] = true;
		// Jump to a step
		if($_GET['step'])
		{
			$definition['edit'] = false;
			// Wizard will process previous step, then advance
			$definition['step'] = $_GET['step'];;
			$definition['button'] = array('next' => 'pressed');
		}
		$this->wizard($definition);
	}

	function wizard($content = null, $msg='')
	{
		$this->etpl->read('importexport.wizardbox');

		if(is_array($content) &&! $content['edit'])
		{
			if(self::_debug) error_log('importexport.wizard->$content '. print_r($content,true));
			//foreach($content as $key => $val) error_log(" $key : ".array2string($val));
			// fetch plugin object
			if($content['plugin'] && $content['application'])
			{
				$wizard_name = $content['application'] . '_wizard_' . str_replace($content['application'] . '_', '', $content['plugin']);

				// we need to deal with the wizard object if exists
				if (file_exists(EGW_SERVER_ROOT . '/'. $content['application']."/inc/class.$wizard_name.inc.php"))
				{
					$wizard_plugin = $wizard_name;
				}
				else
				{
					$wizard_plugin = $content['plugin'];
				}
				// App translations
				if($content['application']) Api\Translation::add_app($content['application']);

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

			// post process submitted step
			if($content['step'])
			{
				if(!$this->can_edit($content))
				{
					// Each step changes definition, reload it
					$bodefinitions = new importexport_definitions_bo();
					$definition = $bodefinitions->read($content);
					$content = $definition + array('step' => $content['step'], 'button' => $content['button']);
				}
				if(!key_exists($content['step'],$this->steps))
				{
					$next_step = $this->plugin->{$content['step']}($content,$sel_options,$readonlys,$preserv);
				}
				else
				{
					$next_step = $this->{$content['step']}($content,$sel_options,$readonlys,$preserv);
				}
			}
			else
			{
				die('Cannot find next step');
			}

			// pre precess next step
			$sel_options = $readonlys = $preserv = array();

			// Disable finish button if required fields are missing
			if(!$content['name'] || !$content['type'] || !$content['plugin'])
			{
				$readonlys['button[finish]'] = true;
			}
			do
			{
				if(!key_exists($next_step,$this->steps))
				{
					$this->wizard_content_template = $this->plugin->$next_step($content,$sel_options,$readonlys,$preserv);
				}
				else
				{
					$this->wizard_content_template = $this->$next_step($content,$sel_options,$readonlys,$preserv);
				}
				if($this->wizard_content_template == self::SKIP)
				{
					if(!key_exists($content['step'],$this->steps))
					{
						$next_step = $this->plugin->{$content['step']}($content);
					}
					else
					{
						$next_step = $this->{$content['step']}($content);
					}
				}
			} while($this->wizard_content_template == self::SKIP);

			if(!$this->can_edit($content))
			{
				$readonlys[$this->wizard_content_template] = true;
				$preserve = $content;
				$readonlys['button[finish]'] = true;
			}

			unset($content['button']);
			$content['wizard_content'] = $this->wizard_content_template;
			$this->etpl->exec(self::_appname.'.importexport_definitions_ui.wizard',$content,$sel_options,$readonlys,$preserv,2);

			// Make sure JS is loaded - Framework won't send it
			Api\Framework::include_css_js_response();
		}
		else
		{
			// initial content
			$sel_options = $readonlys = $preserv = array();
			$readonlys['button[previous]'] = true;
			if($content['edit'])
			{
				unset ($content['edit']);
			}

			$this->wizard_content_template = $this->wizard_step10($content, $sel_options, $readonlys, $preserv);

			if(!$this->can_edit($content))
			{
				$readonlys[$this->wizard_content_template] = true;
				$preserve = $content;
				$readonlys['button[finish]'] = true;
			}

			$content['wizard_content'] = $this->wizard_content_template;
			$this->etpl->exec(self::_appname.'.importexport_definitions_ui.wizard',$content,$sel_options,$readonlys,$preserv,2);
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
			$content['text'] = $this->steps['wizard_step10'];
			foreach ($this->plugins as $appname => $options)
			{
				if($GLOBALS['egw_info']['user']['apps'][$appname] || $GLOBALS['egw_info']['user']['apps']['admin']) {
					$sel_options['application'][$appname] = lang($appname);
				}
			}
			$readonlys['button[previous]'] = true;
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
					if($this->plugin instanceof importexport_iface_import_plugin || $this->plugin instanceof importexport_wizard_basic_import_csv || strpos(get_class($this->plugin), 'import') !== false) {
						$content['type'] = 'import';
					} elseif($this->plugin instanceof importexport_iface_export_plugin || $this->plugin instanceof importexport_wizard_basic_export_csv || strpos(get_class($this->plugin),'export') !== false) {
						$content['type'] = 'export';
					} else {
						throw new Api\Exception('Invalid plugin');
					}
					return $this->get_step($content['step'],1);
				case 'previous' :
					$readonlys['button[previous]'] = true;
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
			$content['text'] = $this->steps['wizard_step20'];
			$config = Api\Config::read('phpgwapi');
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
				if($check_definition && $check_definition->definition_id && $check_definition->definition_id != $content['definition_id'])
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
					$content['name'] .'-'. Api\DateTime::to('now', true),
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
			$content['text'] = $this->steps['wizard_step21'] . ($duplicate ? "\n".$content['duplicate_error'] : '');
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
			if($this->can_edit($content))
			{
				$content['owner'] = $content['just_me'] || !$GLOBALS['egw']->acl->check('share_definitions', Acl::READ,'importexport') ?
					($content['owner'] ?: $GLOBALS['egw_info']['user']['account_id']) :
					null;
				$content['allowed_users'] = $content['just_me'] ? '' : ($content['all_users'] ? 'all' : implode(',', (array)$content['allowed_users']));
				unset($content['just_me']);
			}

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
			$content['text'] = $this->steps['wizard_step90'];
			$content['step'] = 'wizard_step90';
			$preserv = $content;

			// Set owner for non-admins
			$content['just_me'] = ((!$content['allowed_users'] || !$content['allowed_users'][0] && count($content['allowed_users']) ==1) && $content['owner']);
			$content['all_users'] = is_array($content['allowed_users']) && array_key_exists('0',$content['allowed_users']) && $content['allowed_users'][0] == 'all' ||
			$content['allowed_users'] == 'all';
			if(!$GLOBALS['egw']->acl->check('share_definition', Acl::READ, 'importexport') && !$GLOBALS['egw_info']['user']['apps']['admin'])
			{
				$content['allowed_users'] = array();
				$readonlys['allowed_users'] = true;
				$readonlys['just_me'] = true;
				$readonlys['all_users'] = true;
				$content['just_me'] = true;
			}

			$sel_options = array(
				'allowed_users' => array(
					array('value' => null, 'label' => lang('Just me')),
					array('value' => 'all', 'label' => lang('all users'))
				)
			);
			// Hide 'just me' checkbox, users get confused by read-only
			if($readonlys['just_me'] || !$this->can_edit($content))
			{
				$content['no_just_me'] = true;
			}
			if($readonlys['all_users'] || !$this->can_edit($content))
			{
				$content['no_all_users'] = true;
			}
			unset ($preserv['button']);

			$readonlys['button[next]'] = true;
			return 'importexport.wizard_chooseallowedusers';
		}
	}

	function wizard_finish(&$content)
	{
		if(self::_debug) error_log('importexport.importexport_definitions_ui::wizard_finish->$content '.print_r($content,true));
		// Take out some UI leavings
		unset($content['text']);
		unset($content['step']);
		unset($content['button']);

		$bodefinitions = new importexport_definitions_bo();
		$bodefinitions->save($content);
		// This message is displayed if browser cant close window
		$content['msg'] = lang('ImportExport wizard finished successfully!');
		Framework::refresh_opener('','importexport');
		Framework::window_close();
		return 'importexport.wizard_close';
	}

	function import_definition($content='')
	{
		$bodefinitions = new importexport_definitions_bo();
		if (is_array($content))
		{
			if($content['import_file']['tmp_name'])
			{
				$result = $bodefinitions->import($content['import_file']['tmp_name']);
				$msg = lang('%1 definitions %2', count($result), lang('imported')) ."\n". implode("\n", array_keys($result));
				return $this->index(null, $msg);
			}
			if($content['update'])
			{
				$applist = importexport_helper_functions::get_apps('all', true);
				foreach($applist as $appname) {
					importexport_helper_functions::load_defaults($appname);
				}
				return $this->index();
			}
		}
		else
		{
			$content = array();
			$etpl = new etemplate(self::_appname.'.import_definition');
			return $etpl->exec(self::_appname.'.importexport_definitions_ui.import_definition',$content,array(),$readonlys,$preserv);
		}
	}

	/**
	 * Determine if the user is allowed to edit the definition
	 *
	 */
	protected function can_edit(Array $definition)
	{
		if($definition['owner'] && $definition['owner'] == $GLOBALS['egw_info']['user']['account_id'])
		{
			// Definition belongs to user
			return true;
		}
		elseif($definition['definition_id'] && !$definition['owner'] && $GLOBALS['egw_info']['user']['apps']['admin'])
		{
			// Definition is unowned, and user is an admin
			return true;
		}
		elseif(!$definition['definition_id'])
		{
			// Definition is in-progress, not saved yet
			return true;
		}
		return false;
	}
}
