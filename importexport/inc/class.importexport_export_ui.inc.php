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
		$content = array();
		$sel_options = array();
		$readonlys = array();
		$preserv = array();
	
		if(empty($_content)) {
			$et = new etemplate(self::_appname. '.export_dialog');
			$_appname = $_GET['appname'];
			$_definition = $_GET['definition'];
			$_plugin = $_GET['plugin']; // NOTE: definition _must_ be 'expert' if for plugin to be used!
			$_selection = $_GET['selection'];
			
			//error_log(__FILE__.__FUNCTION__. '::$_GET[\'appname\']='. $_appname. ',$_GET[\'definition\']='. $_definition. ',$_GET[\'plugin\']='.$_plugin. ',$_GET[\'selection\']='.$_selection);
			// if appname is given and valid, list available definitions (if no definition is given)
			if (!empty($_appname) && $GLOBALS['egw']->acl->check('run',1,$_appname)) {
				$content['appname'] = $_appname;
				$preserv['appname'] = $_appname;
				$readonlys['appname'] = true;
				$this->js->set_onload("export_dialog.appname = '$_appname';");
				$this->js->set_onload("set_style_by_class('tr','select_appname','display','none');");

				// fill definitions
				$sel_options['definition'] = array();
				$definitions = new importexport_definitions_bo(array(
					'type' => 'export',
					'application' => isset($content['appname']) ? $content['appname'] : '%'
				));
				foreach ((array)$definitions->get_definitions() as $identifier) {
						$definition = new importexport_definition($identifier);
						if ($title = $definition->get_title()) {
							$sel_options['definition'][$title] = $title;
						}
						unset($definition);
				}
				unset($definitions);
				$sel_options['definition']['expert'] = lang('Expert options');

				
				if(isset($_definition) && array_key_exists($_definition,$sel_options['definition'])) {
					$content['definition'] = $_definition;
				}
				else {
					$defdescs = array_keys($sel_options['definition']);
					$content['definition'] = $sel_options['definition'][$defdescs[0]];
					unset($defdescs);
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
					$this->js->set_onload("set_style_by_class('tr','select_definition','display','none');");
				}
				else {

					$this->js->set_onload("set_style_by_class('tr','select_plugin','display','none');");
					$this->js->set_onload("set_style_by_class('tr','save_definition','display','none');");
					
					$definition = new importexport_definition($content['definition']);
					$selected_plugin = $definition->plugin;
					$content['description'] = $definition->description;
				}
				
				// handle selector
				if($selected_plugin) {
					$plugin_object = new $selected_plugin;
					
					$content['description'] = $plugin_object->get_description();
					
					// fill options tab
					// TODO: do we need all options templates online?
					// NO, we can manipulate the session array of template id on xajax request
					// however, there might be other solutions... we solve this in 1.3
					$content['plugin_options_html'] = $plugin_object->get_options_etpl();
				}
				
				// fill selection tab
				if ($_selection) {
					$readonlys[$tabs]['selection_tab'] = true;
					$content['selection'] = $_selection;
					$preserv['selection'] = $_selection;
				}
				elseif ($plugin_object) {
					// ToDo: I need to think abaout it...
					// are selectors abstracted in the iface_egw_record_entity ?
					// if so, we might not want to have html here ?
					$content['plugin_selectors_html'] = $plugin_object->get_selectors_html();
				}
				unset ($plugin_object);
			}
			// if no appname is supplied, list apps which can export
			else {
				(array)$apps = importexport_helper_functions::get_apps('export');
				$sel_options['appname'] = array('' => lang('Select one')) + array_combine($apps,$apps);
				$this->js->set_onload("set_style_by_class('tr','select_plugin','display','none');");
				$content['plugin_selectors_html'] = $content['plugin_options_html'] = 
					lang('You need to select an app and format first!');
				$this->js->set_onload("document.getElementById('importexport.export_dialog.options_tab-tab').style.visibility='hidden';");
				$this->js->set_onload("document.getElementById('importexport.export_dialog.selection_tab-tab').style.visibility='hidden';");
			}
			
			if (!$_selection) {
				$this->js->set_onload("
					disable_button('exec[preview]');
					disable_button('exec[export]');
				");
			}
						
			// disable preview box
			$this->js->set_onload("set_style_by_class('tr','preview-box','display','none');");
		}
		//xajax_eT_wrapper submit
		elseif(class_exists('xajaxResponse'))
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
			if (isset($definition->plugin_options['selection'])) {
				//$definition->plugin_options		= parse(...)
			}
			else {
				$definition->plugin_options = array_merge(
					$definition->plugin_options,
					array('selection' => $_content['selection'])
				);
			}
		
			if(!$definition->plugin_options['selection']) {
				$response->addScript('alert("' . lang('No records selected') . '");');
				return $response->getXML();
			}

			$tmpfname = tempnam('/tmp','export');
			$file = fopen($tmpfname, "w+");
			if (! $charset = $definition->plugin_options['charset']) {
				$charset = $GLOBALS['egw']->translation->charset();
			}
			$plugin_object = new $definition->plugin;
			$plugin_object->export( $file, $definition );

			if($_content['export'] == 'pressed') {
				fclose($file);
				$response->addScript("xajax_eT_wrapper();");
				$response->addScript("opener.location.href='". $GLOBALS['egw']->link('/index.php','menuaction=importexport.importexport_export_ui.download&_filename='. $tmpfname.'&_appname='. $definition->application). "&_suffix=". $plugin_object->get_filesuffix(). "';");
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
				
				$response->addAssign('exec[preview-box]','innerHTML',$preview);
				//$response->addAssign('divPoweredBy','style.display','none');
				$response->addAssign('exec[preview-box]','style.display','inline');
				$response->addAssign('exec[preview-box-buttons]','style.display','inline');
				
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
				$definition = new importexport_definition($identifier);
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
		if (!is_readable($tmpfname)) die();
		
		$appname = $_GET['_appname'];
		$nicefname = 'egw_export_'.$appname.'-'.date('y-m-d');

		header('Content-type: application/text');
		header('Content-Disposition: attachment; filename="'.$nicefname.'.'.$_GET['_suffix'].'"');
		$file = fopen($tmpfname,'r');
		while(!feof($file))
			echo fgets($file,1024);
		fclose($file);

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
			$template = new etemplate($options);
			$template->exec('importexport.importexport_export_ui.dialog', array(), array(), array(), array(), 2);
			$html = ob_get_clean();
			ob_end_clean();
			$html = preg_replace('|<input.+id="etemplate_exec_id".*/>|',
				'',
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
