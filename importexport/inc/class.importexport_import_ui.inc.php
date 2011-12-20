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


/**
 * userinterface for imports
 *
*/

	class importexport_import_ui {

		const _appname = 'importexport';

		public $public_functions = array(
			'import_dialog'	=>	true,
		);

		/**
		 * holds all import plugins from all apps
		 *
		 * @var array
		 */
		private $plugins;

		public function __construct() {
			$this->plugins = importexport_helper_functions::get_plugins('all','import');
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
					$definition_obj = new importexport_definition($content['definition']);
					if($content['dry-run']) {
						// Set this so plugin doesn't do any data changes
						$definition_obj->plugin_options = (array)$definition_obj->plugin_options + array('dry_run' => true);
					}
					if($content['delimiter']) {
						$options =& $definition_obj->plugin_options;
						$options['fieldsep'] =
							$content['delimiter'] == 'other' ? $content['other_delimiter'] : $content['delimiter'];
						$definition_obj->plugin_options = $options;
					}
					$plugin = new $definition_obj->plugin;
					$file = fopen($content['file']['tmp_name'], 'r');

					// Some of the translation, conversion, etc look here
					$GLOBALS['egw_info']['flags']['currentapp'] = $appname;

					if($content['dry-run'])
					{
						echo $this->preview($file, $definition_obj);
					}
					
					$count = $plugin->import($file, $definition_obj);

					$this->message = lang('%1 records processed', $count);
					$total_processed = 0;
					foreach($plugin->get_results() as $action => $a_count) {
						$this->message .= "\n" . lang($action) . ": $a_count";
						$total_processed += $a_count;
					}
					if(count($plugin->get_warnings())) {
						$this->message .= "\n".lang('Warnings').':';
						foreach($plugin->get_warnings() as $record => $message) {
							$this->message .= "\n$record: $message";
						}
					}
					if(count($plugin->get_errors())) {
						$this->message .= "\n".lang('Problems during import:');
						foreach($plugin->get_errors() as $record => $message) {
							$this->message .= "\n$record: $message";
						}
						if($count != $total_processed) $this->message .= "\n".lang('Some records may not have been imported');
					}
				} catch (Exception $e) {
					$this->message = $e->getMessage();
				}
			} elseif($content['cancel']) {
				$GLOBALS['egw']->js->set_onload('window.close();');
			}

			$data['appname'] = $appname;
			$data['definition'] = $definition;
			$data['delimiter'] = $definition_obj->plugin_options['delimiter'];

			$sel_options = self::get_select_options($data);

			$data['message'] = $this->message;
			$GLOBALS['egw']->js->validate_file('.','importexport','importexport');

			if($_GET['appname']) $readonlys['appname'] = true;

			$template = new etemplate('importexport.import_dialog');
			$template->exec('importexport.importexport_import_ui.import_dialog', $data, $sel_options, $readonlys, $preserve, 2);
		}

		/**
		* Get options for select boxes
		*/
		public static function get_select_options(Array $data) {
			$options = array(
				'delimiter' => array(
					''	=>	lang('From definition'),
					';'     =>      ';',
					','     =>      ',',
					'\t'    =>      'Tab',
					' '     =>      'Space',
					'|'     =>      '|',
					'other'      =>      lang('Other')
				)
			);

			(array)$apps = importexport_helper_functions::get_apps('import');
			$options['appname'] = array('' => lang('Select one')) + array_combine($apps,$apps);

			if($data['appname']) {
				$options['definition'] = array();

				if($data['file'] && !is_array($data['file'])) {
					$extension = substr($data['file'], -3);
				}
				$definitions = new importexport_definitions_bo(array(
					'type' => 'import',
					'application' => $data['appname']
				));
				foreach ((array)$definitions->get_definitions() as $identifier) {
					try
					{
						$definition = new importexport_definition($identifier);
					}
					catch (Exception $e)
					{
						// Permission error
						continue;
					}
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
			$response = new xajaxResponse();
			$response->addScript("clear_options('exec[definition]');");
			if(is_array($options['definition'])) {
				foreach ($options['definition'] as $value => $title) {
					$response->addScript("selectbox_add_option('exec[definition]','$title', '$value',false);");
				}
			}
			return $response->getXML();
		}

		/**
		 * Display the contents of the file for dry runs
		 */
		protected function preview(&$_stream, &$definition_obj)
		{
			$import_csv = new importexport_import_csv( $_stream, array(
				'fieldsep' => $definition_obj->plugin_options['fieldsep'],
				'charset' => $definition_obj->plugin_options['charset'],
			));
			// set FieldMapping.
			$import_csv->mapping = $definition_obj->plugin_options['field_mapping'];

			$rows = array('h1'=>array(),'f1'=>array(),'.h1'=>'class=th');
			for($row = 0; $row < $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs']; $row++)
			{
				$row_data = $import_csv->get_record();
				if($row_data === false) break;
				$rows[$import_csv->get_current_position() <= $definition_obj->plugin_options['num_header_lines'] ? 'h1' : $row] = $row_data;
				if($import_csv->get_current_position() <= $definition_obj->plugin_options['num_header_lines']) $row--;
			}

			// Rewind
			rewind($_stream);
			return html::table($rows);
		}
	}
?>
