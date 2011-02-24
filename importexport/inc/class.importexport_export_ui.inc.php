<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

/**
 * userinterface for exports
 *
 */
class importexport_export_ui {
	const _appname = 'importexport';

	public $public_functions = array(
		'export_dialog' =>	true,
		'download' 		=>	true,
	);

	private $js;
	private $user;

	/**
	 * holds all export plugins from all apps
	 *
	 * @var array
	 */
	private $export_plugins;

	public function __construct() {
		$this->js = $GLOBALS['egw']->js = is_object($GLOBALS['egw']->js) ? $GLOBALS['egw']->js : CreateObject('phpgwapi.javascript');
		$this->js->validate_file('.','export_dialog','importexport');
		$this->js->validate_file('.','importexport','importexport');
		$this->user = $GLOBALS['egw_info']['user']['user_id'];
		$this->export_plugins = importexport_helper_functions::get_plugins('all','export');
		$GLOBALS['egw_info']['flags']['include_xajax'] = true;

	}

	public function export_dialog($_content=array()) {
		$tabs = 'general_tab|selection_tab|options_tab';
		$sel_options = array();
		$readonlys = array();
		$preserv = array();

		// Check global setting
		if(!$GLOBALS['egw_info']['user']['apps']['admin']) {
			$config = config::read('phpgwapi');
			if($config['export_limit'] == 'no') {
				die(lang('Admin disabled exporting'));
			}
		}

		$et = new etemplate(self::_appname. '.export_dialog');
		$_appname = $_content['appname'] ? $_content['appname'] : $_GET['appname'];
		$_definition = $_content['definition'] ? $_content['definition'] : $_GET['definition'];
		$_plugin = $_content['plugin'] ? $_content['plugin'] : $_GET['plugin'];
		$_selection = $_content['selection'] ? $_content['selection'] : $_GET['selection'];

			//error_log(__FILE__.__FUNCTION__. '::$_GET[\'appname\']='. $_appname. ',$_GET[\'definition\']='. $_definition. ',$_GET[\'plugin\']='.$_plugin. ',$_GET[\'selection\']='.$_selection);
		// if appname is given and valid, list available definitions (if no definition is given)
		$readonlys['appname'] = (!empty($_appname) && $GLOBALS['egw']->acl->check('run',1,$_appname));
		$content['appname'] = $_appname;
		$preserv['appname'] = $_appname;
		if(empty($_appname)) {
			$this->js->set_onload("set_style_by_class('tr','select_definition','display','none');");
		}

		// fill definitions
		$sel_options['definition'] = array('' => lang('Select'));
		$definitions = new importexport_definitions_bo(array(
			'type' => 'export',
			'application' => isset($content['appname']) ? $content['appname'] : '%'
		));
		foreach ((array)$definitions->get_definitions() as $identifier) {
			try {
				$definition = new importexport_definition($identifier);
			} catch (Exception $e) {
				// permission error
				continue;
			}
				if ($title = $definition->get_title()) {
					$sel_options['definition'][$title] = $title;
				}
				unset($definition);
		}
		if(count($sel_options['definition']) == 2 && !$content['definition']) {
			$content['definition'] = end($sel_options['definition']);
		}
		unset($definitions);
		//$sel_options['definition']['expert'] = lang('Expert options');

		if(isset($_definition) && array_key_exists($_definition,$sel_options['definition'])) {
			$content['definition'] = $_definition;
		}

		// fill plugins
		$sel_options['plugin'] = $this->export_plugins[$_appname]['export'];

		// show definitions or plugins in ui?
		if($content['definition'] == 'expert') {
			if(isset($_plugin) && array_key_exists($_plugin,$sel_options['plugin'])) {
				$content['plugin'] = $_plugin;
				$selected_plugin = $_plugin;
			}
			else {
/*
					$plugins_classnames = array_keys($sel_options['plugin']);
					$selected_plugin = $plugins_classnames[0];
					$sel_options['plugin'] = $plugins;
*/
			}
			//$this->js->set_onload("set_style_by_class('tr','select_definition','display','none');");
		}
		else {

			$this->js->set_onload("set_style_by_class('tr','select_plugin','display','none');");
			$this->js->set_onload("set_style_by_class('tr','save_definition','display','none');");

			$definition = new importexport_definition($content['definition']);
			if($definition) {
				$content += (array)$definition->plugin_options;
				$selected_plugin = $definition->plugin;
				$content['description'] = $definition->description;
			}
		}

		// handle selector
		if($selected_plugin) {
			$plugin_object = new $selected_plugin;

			$content['description'] = $plugin_object->get_description();

			// fill options tab
 			if(method_exists($plugin_object, 'get_selectors_html')) {
				$content['plugin_options_html'] = $plugin_object->get_options_html();
			} else {
				$options = $plugin_object->get_options_etpl();
				if(is_array($options)) {
					$content['plugin_options_template'] = $options['name'];
					$content += (array)$options['content'];
					$sel_options += (array)$options['sel_options'];
					$readonlys += (array)$options['readonlys'];
					$preserv += (array)$options['preserv'];
				} else {
					$content['plugin_options_template'] = $options;
				}
			}
			if(!$content['plugin_options_html'] && !$content['plugin_options_template']) {
				$readonlys[$tabs]['options_tab'] = true;
			}
		}

		// fill selection tab
		if($definition && $definition->plugin_options['selection']) {
			$_selection = $definition->plugin_options['selection'];
		}
		if ($_selection) {
			$readonlys[$tabs]['selection_tab'] = true;
			$content['selection'] = $_selection;
			$preserv['selection'] = $_selection;
		}
		elseif ($plugin_object) {
 			if(method_exists($plugin_object, 'get_selectors_html')) {
				$content['plugin_selectors_html'] = $plugin_object->get_selectors_html();
			} else {
				$options = $plugin_object->get_selectors_etpl();
				if(is_array($options)) {
					$content['selection'] = (array)$options['content'];
					$sel_options += (array)$options['sel_options'];
					$readonlys['selection'] = (array)$options['readonlys'];
					$preserv['selection'] = (array)$options['preserv'];
					$content['plugin_selectors_template'] = $options['name'];
				} else {
					$content['plugin_selectors_template'] = $options;
				}
			}
			if(!$content['plugin_selectors_html'] && !$content['plugin_selectors_template']) {
				$readonlys[$tabs]['selection_tab'] = true;
			}
		} elseif (!$_selection) {
			$this->js->set_onload("
				disable_button('exec[preview]');
				disable_button('exec[export]');
			");
		}
		if (($prefs = $GLOBALS['egw_info']['user']['preferences']['importexport'][$definition->definition_id]) &&
			($prefs = unserialize($prefs)))
		{
			$content = array_merge($content,$prefs);
		}
		unset ($plugin_object);
		(array)$apps = importexport_helper_functions::get_apps('export');
		$sel_options['appname'] = array('' => lang('Select one')) + array_combine($apps,$apps);
		$this->js->set_onload("set_style_by_class('tr','select_plugin','display','none');");
		if(!$_application && !$selected_plugin) {
			$content['plugin_selectors_html'] = $content['plugin_options_html'] =
					lang('You need to select an app and format first!');
			$this->js->set_onload("document.getElementById('importexport.export_dialog.options_tab-tab').style.visibility='hidden';");
			$this->js->set_onload("document.getElementById('importexport.export_dialog.selection_tab-tab').style.visibility='hidden';");
		}

		// disable preview box
		$this->js->set_onload("set_style_by_class('tr','preview-box','display','none');");

		//xajax_eT_wrapper submit
		if(class_exists('xajaxResponse'))
		{
			//error_log(__LINE__.__FILE__.'$_content: '.print_r($_content,true));
			$response = new xajaxResponse();

			if ($_content['definition'] == 'expert') {
				$definition = new importexport_definition();
				$definition->definition_id	= $_content['definition_id'] ? $_content['definition_id'] : '';
				$definition->name		= $_content['name'] ? $_content['name'] : '';
				$definition->application	= $_content['appname'];
				$definition->plugin		= $_content['plugin'];
				$definition->type		= 'export';
				$definition->allowed_users	= $_content['allowed_users'] ? $_content['allowed_users'] : $this->user;
				$definition->owner		= $_content['owner'] ? $_content['owner'] : $this->user;
			}
			else {
				$definition = new importexport_definition($_content['definition']);
			}

			if(!is_array($definition->plugin_options)) {
				$definition->plugin_options = array(
					'mapping'	=>	array()
				);
			}
			$definition->plugin_options = array_merge(
				$definition->plugin_options,
				$_content
			);

			if(!$definition->plugin_options['selection']) {
				$response->addScript('alert("' . lang('No records selected') . '");');
				return $response->getXML();
			}

			$tmpfname = tempnam($GLOBALS['egw_info']['server']['temp_dir'],'export');
			$file = fopen($tmpfname, "w+");
			if (! $charset = $definition->plugin_options['charset']) {
				$charset = $GLOBALS['egw']->translation->charset();
			}
			$plugin_object = new $definition->plugin;
			$plugin_object->export( $file, $definition );

			// Keep settings
			$keep = array_diff_key($_content, array_flip(array('appname', 'definition', 'plugin', 'preview', 'export', $tabs)));
			$GLOBALS['egw']->preferences->add('importexport',$definition->definition_id,serialize($keep));
			// save prefs, but do NOT invalid the cache (unnecessary)
			$GLOBALS['egw']->preferences->save_repository(false,'user',false);


			if($_content['export'] == 'pressed') {
				fclose($file);
				$filename = pathinfo($tmpfname, PATHINFO_FILENAME);
				$response->addScript("xajax_eT_wrapper();");
				$response->addScript("opener.location.href='". $GLOBALS['egw']->link('/index.php','menuaction=importexport.importexport_export_ui.download&_filename='. $filename.'&_appname='. $definition->application). "&_suffix=". $plugin_object->get_filesuffix(). "&_type=".$plugin_object->get_mimetype() ."';");
				$response->addScript('window.setTimeout("window.close();", 100);');
				return $response->getXML();
			}
			elseif($_content['preview'] == 'pressed') {
				fseek($file, 0);
				$item_count = 1;
				$preview = '';
				$search = array('[\016]','[\017]',
								'[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
								'[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');
				$replace = $preview = '';

				while(!feof($file) && $item_count < 10) {
					$preview .= preg_replace($search,$replace,fgets($file,1024));
					$item_count++;
				}

				fclose($file);
				unlink($tmpfname);

				// NOTE: $definition->plugin_options['charset'] may not be set,
				// but it's the best guess atm.
				$preview = $GLOBALS['egw']->translation->convert( $preview,
					$definition->plugin_options['charset'],
					$GLOBALS['egw']->translation->charset()
				);

				$response->addAssign('exec[preview-box]','innerHTML',nl2br($preview));
				$response->jquery('.preview-box','show');
				$response->jquery('.preview-box-buttons','show');

				$response->addScript("xajax_eT_wrapper();");
				return $response->getXML();
			}
			//nothing else expected!
			throw new Exception('Error: unexpected submit in export_dialog!');
		} else {
			$readonlys[$tabs]['selection'] = true;
			$readonlys[$tabs]['selection'] = false;
		}
		//error_log(print_r($content,true));
		return $et->exec(self::_appname. '.importexport_export_ui.export_dialog',$content,$sel_options,$readonlys,$preserv,2);
	}

	public function ajax_get_definitions($_appname, xajaxResponse &$response = null) {
		if(is_null($response)) {
			$response = new xajaxResponse();
		} else {
			$no_return = true;
		}
		if (!$_appname) {
			$response->addScript("set_style_by_class('tr','select_definition','display','none');");
			return $no_return ? '' : $response->getXML();
		}

		$definitions = new importexport_definitions_bo(array(
			'type' => 'export',
			'application' => $_appname
		));
		$response->addScript("clear_options('exec[definition]');");
		foreach ((array)$definitions->get_definitions() as $identifier) {
			try {
				$definition = new importexport_definition($identifier);
			} catch (Exception $e) {
				// Permission error
				continue;
			}
				if ($title = $definition->get_title()) {
					if (!$selected_plugin) $selected_plugin = $title;
					$response->addScript("selectbox_add_option('exec[definition]','$title', '$value',".($selected_plugin == $title ? 'true' : 'false').");");
				}
				unset($definition);
		}
		unset($definitions);
		$response->addScript("selectbox_add_option('exec[definition]','" . lang('Expert options') . "', 'expert',".($selected_plugin == $title ? 'true' : 'false').");");

		if($selected_plugin == 'expert') {
			$this->ajax_get_plugins($_appname, $response);
		} else {
			$response->addScript("set_style_by_class('tr','select_plugin','display','none');");
		}
		$response->addScript('export_dialog.change_definition(document.getElementById("exec[definition]"));');
		$response->addScript("set_style_by_class('tr','select_definition','display','table-row');");
		return $no_return ? '' : $response->getXML();
	}

	public function ajax_get_plugins($_appname, xajaxResponse &$response = null) {
		if(!is_null($response)) {
			$no_return = true;
		} else {
			$response = new xajaxResponse();
		}
		if (!$_appname) {
			$response->addScript("set_style_by_class('tr','select_plugin','display','none');");
			return $no_return ? '' : $response->getXML();
		}

		(array)$plugins = importexport_helper_functions::get_plugins($_appname,'export');
		$sel_options['plugin'] = '';
		$response->addScript("clear_options('exec[plugin]');");
		foreach ($plugins[$_appname]['export'] as $plugin => $plugin_name) {
			if (!$selected_plugin) $selected_plugin = $plugin;
			$response->addScript("selectbox_add_option('exec[plugin]','$plugin_name', '$plugin',".($selected_plugin == $plugin ? 'true' : 'false').");");
		}

		$this->ajax_get_plugin_description($selected_plugin,$response);
		$this->ajax_get_plugin_options($selected_plugin, $response, $_definition);
		$this->ajax_get_plugin_selectors($selected_plugin, $response, $_definition);
		$response->addScript("set_style_by_class('tr','select_plugin','display','table-row');");
		return $no_return ? '' : $response->getXML();
	}

	public function ajax_get_definition_description($_definition, xajaxResponse &$response=null) {
		$no_return = !is_null($response);
		if(is_null($response)) {
			$response = new xajaxResponse();
		}
		if (!$_definition) return $response->getXML();
		$_object = new importexport_definition($_definition);
		if (is_a($_object, 'importexport_definition')) {
			$description = $_object->description;
			$response->assign('exec[plugin_description]','innerHTML',$description);
		}
		unset ($_object);

		return $no_return ? '' : $response->getXML();
	}

	public function ajax_get_plugin_description($_plugin,&$_response=false) {
		$no_return = !is_null($_response);
		if(is_null($_response)) {
			$_response = new xajaxResponse();
		}
		if (!$_plugin) return $no_return ? '' : $response->getXML();

		$plugin_object = new $_plugin;
		if (is_a($plugin_object, 'importexport_iface_export_plugin')) {
			$description = $plugin_object->get_description();
			$_response->addAssign('exec[plugin_description]','innerHTML',$description);

			if (isset($definition->plugin_options['selection'])) {
				$_response->addScript("document.getElementById('importexport.export_dialog.selection_tab-tab').style.visibility='hidden';");
			}
			$this->ajax_get_plugin_options($_plugin, $_response);
		}
		unset ($plugin_object);

		return $no_return ? '' : $response->getXML();
	}

	public function ajax_get_plugin_options($_plugin,&$response=false, $definition = '') {
		$no_return = !is_null($response);
		if(is_null($response)) {
			$response = new xajaxResponse();
		}
		if (!$_plugin) return $no_return ? '' : $response->getXML();

		$plugin_object = new $_plugin;
		if (is_a($plugin_object, 'importexport_iface_export_plugin')) {
			$options = $plugin_object->get_options_etpl();
			ob_start();
			$template = new etemplate($options);
/*
			$template->exec('importexport.importexport_export_ui.dialog', array(), array(), array(), array(), 2);
			$html = ob_get_clean();
			ob_end_clean();
*/
			$html = $template->exec('importexport.importexport_export_ui.dialog', array(), array(), array(), array(), 1);
			$html = preg_replace('|<input.+id="etemplate_exec_id".*/>|',
				'',
				$html
			);
			$response->addAssign('importexport.export_dialog.options_tab', 'innerHTML', $html);
		}

		unset ($plugin_object);

		return $no_return ? '' : $response->getXML();
	}

	/**
	 * downloads file to client and deletes it.
	 *
	 * @param sting $_tmpfname
	 * @todo we need a suffix atibute in plugins e.g. .csv
	 */
	public function download($_tmpfname = '') {
		$tmpfname = $_tmpfname ? $_tmpfname : $_GET['_filename'];
		$tmpfname = $GLOBALS['egw_info']['server']['temp_dir'] .'/'. $tmpfname;
		if (!is_readable($tmpfname)) die();

		$appname = $_GET['_appname'];
		$nicefname = 'egw_export_'.$appname.'-'.date('Y-m-d');

		// Turn off all output buffering
		while (@ob_end_clean());

		header('Content-type: ' . $_GET['_type'] ? $_GET['_type'] : 'application/text');
		header('Content-Disposition: attachment; filename="'.$nicefname.'.'.$_GET['_suffix'].'"');
		$file = fopen($tmpfname,'rb');
		fpassthru($file);

		unlink($tmpfname);

		// Try to avoid any extra finishing output
		common::egw_exit();
	}

	public function ajax_get_plugin_selectors($_plugin,&$response=false, $definition = '') {
		$no_return = !is_null($response);
		if(is_null($response)) {
			$response = new xajaxResponse();
		}
		if (!$_plugin) return $no_return ? '' : $response->getXML();

		$plugin_object = new $_plugin;
		if (is_a($plugin_object, 'importexport_iface_export_plugin')) {
			$options = $plugin_object->get_selectors_etpl();
			ob_start();
			etemplate::$name_vars='exec';
			$template = new etemplate($options);
			$html = $template->exec('importexport.importexport_export_ui.dialog', array(), array(), array(), array(), 1);
			//$html = ob_get_clean();
			ob_end_clean();
			$pattern = array(
				'|<input.+id="etemplate_exec_id".*/>|',
				'|<input(.+)name="exec[0-9]*\[|'
			);
			$html = preg_replace($pattern,
				array('', '<input\\1name="exec['),
				$html
			);
			$response->addAssign('importexport.export_dialog.selection_tab', 'innerHTML', $html);
		}

		unset ($plugin_object);

		return $no_return ? '' : $response->getXML();
	}

	public function ajax_get_template($_name) {

	}
} // end class uiexport
