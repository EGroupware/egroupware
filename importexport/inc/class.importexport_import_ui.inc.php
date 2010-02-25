<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray
 * @version $Id: class.importexport_import_ui.inc.php 27222 2009-06-08 16:21:14Z ralfbecker $
 */

require_once(EGW_INCLUDE_ROOT. '/importexport/inc/class.import_export_helper_functions.inc.php');
require_once(EGW_INCLUDE_ROOT. '/importexport/inc/class.bodefinitions.inc.php');
require_once(EGW_INCLUDE_ROOT. '/importexport/inc/class.definition.inc.php');


/**
 * userinterface for imports
 *
*/

	class importexport_import_ui {

		const _appname = 'importexport';

		public $public_functions = array(
			'import_dialog'	=>	true,
			'download'	=>	true,
		);

		/**
		 * holds all import plugins from all apps
		 *
		 * @var array
		 */
		private $plugins;

		public function __construct() {
			$GLOBALS['egw']->js->validate_file('.','import_dialog','importexport');
			$this->plugins = import_export_helper_functions::get_plugins('all','import');
			$GLOBALS['egw_info']['flags']['include_xajax'] = true;
		}

		/**
		*  Step user through importing their file
		*/
		public function import_dialog($content = array()) {
			$appname = $_GET['appname'] ? $_GET['appname'] : $content['appname'];
			$definition = $_GET['definition'] ? $_GET['definition'] : $content['definition'];

			if($content['import'] && $definition) {
				try {
					$definition_obj = new definition($content['definition']);
					if($content['dry-run']) {
						$definition_obj->plugin_options = $definition_obj->plugin_options + array('dry_run' => true);
					}
					$plugin = new $definition_obj->plugin;
					$file = fopen($content['file']['tmp_name'], 'r');

					// Some of the translation, conversion, etc look here
					$GLOBALS['egw_info']['flags']['currentapp'] = $appname;
					$count = $plugin->import($file, $definition_obj);

					$this->message = lang('%1 records imported successfully', $count);
					if(count($plugin->get_errors())) {
						$this->message .= "\n".lang('Unable to import:');
						foreach($plugin->get_errors() as $record => $message) {
							$this->message .= "\n$record: $message";
						}
					}
				} catch (Exception $e) {
					$this->message = $e->getMessage();
				}
			}
			$data['appname'] = $appname;
			$data['definition'] = $definition;
			$sel_options = self::get_select_options($data);

			$data['message'] = $this->message;

			$template = new etemplate('importexport.import_dialog');
			$template->exec('importexport.importexport_import_ui.import_dialog', $data, $sel_options, $readonlys, $preserve, 2);
		}

		/**
		* Get options for select boxes
		*/
		public static function get_select_options(Array $data) {
			$options = array();

			(array)$apps = import_export_helper_functions::get_apps('import');
			$options['appname'] = array('' => lang('Select one')) + array_combine($apps,$apps);

			if($data['appname']) {
				$options['definition'] = array();

				if($data['file'] && !is_array($data['file'])) {
					$extension = substr($data['file'], -3);
				}
				$definitions = new bodefinitions(array(
					'type' => 'import',
					'application' => $data['appname']
				));
				foreach ((array)$definitions->get_definitions() as $identifier) {
						$definition = new definition($identifier);
						if ($title = $definition->get_title()) {
							$options['definition'][$title] = $title;
						}
						unset($definition);
				}
				unset($definitions);
			}

			return $options;
		}

		/**
		* Get definitions via ajax
		*/
		public function ajax_get_definitions($appname, $file=null) {
			$options = self::get_select_options(array('appname'=>$appname, 'file'=>$file));
			if(is_array($options['definition'])) {
				foreach ($options['definition'] as $value => $title) {
					$sel_options['definition'] .= '<option value="'. $value. '" >'. $title. '</option>';
				}
			}
			$response = new xajaxResponse();
			$response->addScript('import_dialog.change_definition(document.getElementId(\'exec[definition]\'));');
			$response->addAssign('exec[definition]','innerHTML',$sel_options['definition']);
			return $response->getXML();
		}
	}
?>
