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

require_once(EGW_INCLUDE_ROOT. '/etemplate/inc/class.etemplate.inc.php');
require_once(EGW_INCLUDE_ROOT. '/importexport/inc/class.import_export_helper_functions.inc.php');
require_once(EGW_INCLUDE_ROOT. '/importexport/inc/class.bodefinitions.inc.php');
require_once(EGW_INCLUDE_ROOT. '/importexport/inc/class.definition.inc.php');


/**
 * userinterface for exports
 *
 */
class uiexport {
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
		$this->user = $GLOBALS['egw_info']['user']['user_id'];
		$this->export_plugins = import_export_helper_functions::get_plugins('all','export');
		$GLOBALS['egw_info']['flags']['include_xajax'] = true;
		
	}
	
	public function export_dialog($_content=array()) {
		$tabs = 'general_tab|selection_tab|options_tab|templates_tab';
		$content = array();
		$sel_options = array();
		$readonlys = array();
		$preserv = array();
		
		if(empty($_content)) {
			$et = new etemplate(self::_appname. '.export_dialog');
			$_appname = $_GET['appname'];
			$_plugin = $_GET['plugin'];
			$_selection = $_GET['selection'];

			// if appname is given and valid, list available plugins (if no plugin is given)
			if (!empty($_appname) && $GLOBALS['egw']->acl->check('run',1,$_appname)) {
				$content['appname'] = $_appname;
				$preserv['appname'] = $_appname;
				$readonlys['appname'] = true;
				$this->js->set_onload("set_style_by_class('tr','select_appname','display','none');");

				(array)$plugins = $this->export_plugins[$_appname]['export'];
				if(isset($plugins[$_plugin])) {
					$content['plugin'] = $_plugin;
					$selected_plugin = $_plugin;
					$this->js->set_onload("set_style_by_class('tr','select_plugin','display','none');");
				}
				else {
					$plugins_classnames = array_keys($plugins);
					$selected_plugin = $plugins_classnames[0];
					$sel_options['plugin'] = $plugins;
				}
				
				if (!empty($selected_plugin)) {
					$plugin_object = new $selected_plugin;
					
					$content['plugin_description'] = $plugin_object->get_description();
					
					// fill options tab
					// TODO: do we need all options templates online?
					// NO, we can manipulate the session array of template id on xajax request
					// however, there might be other solutions... we solve this in 1.3
					$content['plugin_options_html'] = $plugin_object->get_options_etpl();
					
					// fill selection tab
					if ($_selection) {
						$readonlys[$tabs]['selection_tab'] = true;
						$content['selection'] = $_selection;
						$preserv['selection'] = $_selection;
					}
					else {
						// ToDo: I need to think abaout it...
						// are selectors abstracted in the iface_egw_record_entity ?
						// if so, we might not want to have html here ?
						$content['plugin_selectors_html'] = $plugin_object->get_selectors_html();
					}
					unset ($plugin_object);
				}
			}
			// if no appname is supplied, list apps which can export
			else {
				(array)$apps = import_export_helper_functions::get_apps('export');
				$sel_options['appname'] = array('' => lang('Select one')) + array_combine($apps,$apps);
				$this->js->set_onload("set_style_by_class('tr','select_plugin','display','none');");
				$content['plugin_selectors_html'] = $content['plugin_options_html'] = 
					lang('You need to select an app and format first!');
			}
			
			if (!$_selection) {
				$this->js->set_onload("
					disable_button('exec[preview]');
					disable_button('exec[export]');
				");
			}
						
			// fill templates_tab
			$sel_options['templates'] = array();
			$definitions = new bodefinitions(array(
				'type' => 'export',
				'application' => isset($content['appname']) ? $content['appname'] : '%'
			));
			foreach ((array)$definitions->get_definitions() as $identifier) {
					$definition = new definition($identifier);
					if ($title = $definition->get_title()) {
						$sel_options['templates'][$title] = $title;
					}
					unset($definition);
			}
			unset($definitions);
			if (empty($sel_options['templates'])) {
				$readonlys[$tabs]['templates_tab'] = true;
			}
			// disable preview box
			$this->js->set_onload("set_style_by_class('tr','preview-box','display','none');");
		}
		//xajax_eT_wrapper submit
		elseif(class_exists('xajaxResponse'))
		{
			//error_log(__LINE__.__FILE__.'$_content: '.print_r($_content,true));
			$response =& new xajaxResponse();
			
			$definition = new definition();
			$definition->definition_id	= $_content['definition_id'] ? $_content['definition_id'] : '';
			$definition->name			= $_content['name'] ? $_content['name'] : '';
			$definition->application	= $_content['appname'];
			$definition->plugin			= $_content['plugin'];
			$definition->type			= 'export';
			$definition->allowed_users	= $_content['allowed_users'] ? $_content['allowed_users'] : $this->user;
			$definition->owner			= $_content['owner'] ? $_content['owner'] : $this->user;
			
			//$definition->options		= parse(...)
			$definition->options		= array( 
				'selection'	=> $_content['selection'],
			);
			$tmpfname = tempnam('/tmp','export');
			$file = fopen($tmpfname, "w+");
			if (! $charset = $definition->options['charset']) {
				$charset = $GLOBALS['egw']->translation->charset();
			}
			$plugin_object = new $_content['plugin'];
			$plugin_object->export($file, $charset, $definition);

			if($_content['export'] == 'pressed') {
				fclose($file);
				$response->addScript("xajax_eT_wrapper();");
				$response->addScript("opener.location.href='". $GLOBALS['egw']->link('/index.php','menuaction=importexport.uiexport.download&_filename='. $tmpfname.'&_appname='. $definition->application). "&_suffix=". $plugin_object->get_filesuffix(). "';");
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
				$response->addAssign('exec[preview-box]','innerHTML',$preview);
				$response->addAssign('divPoweredBy','style.display','none');
				$response->addAssign('exec[preview-box]','style.display','inline');
				$response->addAssign('exec[preview-box-buttons]','style.display','inline');
				
				$response->addScript("xajax_eT_wrapper();");
				return $response->getXML();
			}
			//nothing else expected!
			throw new Exception('Error: unexpected submit in export_dialog!');
		}
		return $et->exec(self::_appname. '.uiexport.export_dialog',$content,$sel_options,$readonlys,$preserv,2);
	}
	
	public function ajax_get_plugins($_appname) {
		$response = new xajaxResponse();
		if (!$_appname) {
			$response->addScript("set_style_by_class('tr','select_plugin','display','none');");
			return $response->getXML();
		}
		
		(array)$plugins = import_export_helper_functions::get_plugins($_appname,'export');
		$sel_options['plugin'] = '';
		foreach ($plugins[$_appname]['export'] as $plugin => $plugin_name) {
			if (!$selected_plugin) $selected_plugin = $plugin;
			$sel_options['plugin'] .= '<option value="'. $plugin. '" >'. $plugin_name. '</option>';
		}
		
		$this->ajax_get_plugin_description($selected_plugin,$response);
		$response->addAssign('exec[plugin]','innerHTML',$sel_options['plugin']);
		$response->addScript("set_style_by_class('tr','select_plugin','display','table-row');");
		return $response->getXML();
	}
	
	public function ajax_get_plugin_description($_plugin,$_response=false) {
		$response = $_response ? $_response : new xajaxResponse();
		if (!$_plugin) return $response->getXML();

		$plugin_object = new $_plugin;
		if (is_a($plugin_object, 'iface_export_plugin')) {
			$description = $plugin_object->get_description();
			$_response->addAssign('plugin_description','style.width','300px');
			$_response->addAssign('plugin_description','innerHTML',$description);
		}
		unset ($plugin_object);
		
		return $_response ? '' : $response->getXML();
	}
	
	public function ajax_get_plugin_options($_plugin,$_response=false) {
		$response = $_response ? $_response : new xajaxResponse();
		if (!$_plugin) return $response->getXML();
		
		$plugin_object = new $_plugin;
		if (is_a($plugin_object, 'iface_export_plugin')) {
			$options = $plugin_object->get_options_etpl();
		}
		unset ($plugin_object);
		
		return $_response ? '' : $response->getXML();
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
	}
	
	public function ajax_get_plugin_selectors() {
		
	}
	
	public function ajax_get_template($_name) {
		
	}
} // end class uiexport