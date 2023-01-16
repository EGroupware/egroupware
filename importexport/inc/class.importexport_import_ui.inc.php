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

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Etemplate;

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
		}

		/**
		*  Step user through importing their file
		*/
		public function import_dialog($content = array()) {
			$appname = $_GET['appname'] ? $_GET['appname'] : $content['appname'];
			$definition = $_GET['definition'] ? $_GET['definition'] : $content['definition'];

			// We use some inline js in preview results if it fails
			Api\Header\ContentSecurityPolicy::add('script-src', "unsafe-inline");

			$template = new Etemplate('importexport.import_dialog');
			$preserve = array();

			if($definition)
			{
				$definition_obj = new importexport_definition($definition);
			}

			// Load application's translations
			if($appname)
			{
				Api\Translation::add_app($appname);
			}
			if ($content['import'] && $definition && !empty($content['file']) && file_exists($content['file']['tmp_name'])) {
				try {
					if($content['dry-run']) {
						// Set this so plugin doesn't do any data changes
						$definition_obj->plugin_options = (array)$definition_obj->plugin_options + array('dry_run' => true);
					}
					$options =& $definition_obj->plugin_options;
					$options['no_notification'] = $content['no_notifications'];
					if($content['delimiter']) {
						$options['fieldsep'] =
							$content['delimiter'] == 'other' ? $content['other_delimiter'] : $content['delimiter'];
					}
					$options = array_merge($options, array_diff_key($content, array_flip(array('dry-run','file','import'))));
					$definition_obj->plugin_options = $options;

					$plugin = new $definition_obj->plugin;

					// Check file encoding matches import
					$sample = mb_substr(file_get_contents($content['file']['tmp_name'], false, null, 0, 2048), 1024);

					if($appname == 'addressbook' && $definition_obj->plugin == 'addressbook_import_vcard')
					{
						$preference = $GLOBALS['egw_info']['user']['preferences']['addressbook']['vcard_charset'];
					}
					else
					{
						$preference = $GLOBALS['egw_info']['user']['preferences']['common']['csv_charset'];
					}
					$required = $options['charset'] == 'user' || !$options['charset'] ? $preference : $options['charset'];
					$encoding = Api\Translation::detect_encoding($sample, $required);
					if($encoding && strtoupper($required) != strtoupper($encoding))
					{
						$this->message = lang("Encoding mismatch.  Expected %1 file, you uploaded %2.<br />\n",
							$required,
							$encoding
						);
					}

					$file = fopen($content['file']['tmp_name'], 'rb');
					$count = 0;

					// Some of the translation, conversion, etc look here
					$GLOBALS['egw_info']['flags']['currentapp'] = $appname;

					// Destination if we need to hold the file
					if($file)
					{
						$cachefile = new Api\Cache\Files(array());
						$dst_file = $cachefile->filename(Api\Cache::keys(Api\Cache::INSTANCE, 'importexport',
							'import_'.md5($content['file']['name'].$GLOBALS['egw_info']['user']['account_id']), true),true);
						// Keep file
						if($dst_file)
						{
							if($content['file']['name'] && copy($content['file']['tmp_name'],$dst_file)) {
								$preserve['file']['tmp_name'] = $dst_file;
							}
						}

						// Check on matching columns
						$check_message = array();
						if(!self::check_file($file, $definition_obj, $check_message, $dst_file))
						{
							// Set this so plugin doesn't do any data changes
							$content['dry-run'] = true;
							importexport_helper_functions::$dry_run = true;
							$this->message .= '<b>' . lang('Import aborted').":</b><br />\n";
							$definition_obj->plugin_options = (array)$definition_obj->plugin_options + array('dry_run' => true);
						}
						if(count($check_message))
						{
							$this->message .= implode("<br />\n", $check_message) . "<br />\n";
						}
						if($content['dry-run'])
						{
							$preview = $this->preview($plugin, $file, $definition_obj);
							if(trim($this->message) == '')
							{
								// Clear first, to prevent request from being collected if the result is the same
								$template->setElementAttribute('preview', 'value', '');
								$template->setElementAttribute('preview', 'value', $preview);
								return;
							}
						}
						else
						{
							importexport_helper_functions::$dry_run = false;
							$count = $plugin->import($file, $definition_obj);
						}
					}
					else
					{
						$this->message .= lang('please select file to import'."<br />\n");
					}

					if($content['dry-run'])
					{
						$this->message .= '<b>' . lang('test only').":</b><br />\n";
					}
					$this->message .= lang('%1 records processed', $count);

					// Refresh opening window
					if(!$content['dry-run'])
					{
						Framework::refresh_opener(lang('%1 records processed',$count), $appname, null,null,$appname);
					}
					$total_processed = 0;
					foreach($plugin->get_results() as $action => $a_count) {
						$this->message .= "<br />\n" . lang($action) . ": $a_count";
						$total_processed += $a_count;
					}
					if(count($plugin->get_warnings())) {
						$this->message .= "<br />\n".lang('Warnings').':';
						foreach($plugin->get_warnings() as $record => $message) {
							$this->message .= "<br />\n$record: $message";
						}
					}
					if(count($plugin->get_errors())) {
						$this->message .= "<br />\n".lang('Problems during import:');
						foreach($plugin->get_errors() as $record => $message) {
							$this->message .= "<br />\n$record: $message";
						}
						if($count != $total_processed) $this->message .= "<br />\n".lang('Some records may not have been imported');
					}
					if ($dst_file && $content['file']['tmp_name'] == $dst_file) {
						// Remove file
						$cachefile->delete(Api\Cache::keys(Api\Cache::INSTANCE, 'importexport',
							'import_'.md5($content['file']['name'].$GLOBALS['egw_info']['user']['account_id'])));
						unset($dst_file);
					}

				} catch (Api\Db\Exception $db_ex) {
					$this->message .= lang('Database error');
				} catch (Exception $e) {
					$this->message .= $e->getMessage();
				}
			}
			elseif($content['cancel'])
			{
				Api\Framework::window_close();
			}
			elseif ($GLOBALS['egw_info']['user']['apps']['admin'])
			{
				$this->message .= lang('You may want to <a href="%1" target="_new">backup</a> first.',
					Egw::link('/index.php',
						array('menuaction' => 'admin.admin_db_backup.index')
					)
				);
			}

			if(!array_key_exists('dry-run',$content))
			{
				$data['dry-run'] = true;
			}

			$data['appname'] = $preserve['appname'] = $appname ? $appname : ($definition_obj ? $definition_obj->application : '');
			$data['definition'] = is_numeric($definition) ?  ($definition_obj ? $definition_obj->get_title() : '') : $definition;
			$data['delimiter'] = $definition_obj->plugin_options['delimiter'];
			$data['no_notifications'] = true;	// switch notifications off by default

			$sel_options = self::get_select_options($data);
			$readonlys = array();

			if(!$definition_obj && $sel_options['definition'] && !$data['definition'])
			{
				$definition_obj = new importexport_definition(key($sel_options['definition']));
			}
			if($definition_obj)
			{
				$plugin = new $definition_obj->plugin;
				$options = $plugin->get_options_etpl($definition_obj);
				if(is_array($options))
				{
					$data['plugin_options_template'] = $options['name'];
					$data += (array)$options['content'];
					$sel_options += (array)$options['sel_options'];
					$readonlys += (array)$options['readonlys'];
					$preserve += (array)$options['preserv'];
				}
				else
				{
					// Fallback for not returning anything - CSV
					$data['plugin_options_template'] = "importexport.import_dialog.csv";
					$data['file_type'] = 'csv';
				}
			}

			$data['message'] = $this->message;
			Framework::includeJS('.', 'importexport', 'importexport');
			Framework::includeJS('.', 'app', $data['appname']);

			if($_GET['appname'])
			{
				$readonlys['appname'] = true;
			}

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
		 * Return an interpretation of the file for dry runs
		 *
		 * If the plugin has a preview, use that.  Otherwise, try a simple CSV => HTML table
		 *
		 * @param importexport_iface_import_plugin $plugin Instance of plugin to be used
		 * @param resource $stream
		 * @param importexport_definition $definition
		 * @return String HTML fragment illustrating how the data will be understood by Egw
		 */
		protected function preview(importexport_iface_import_plugin &$plugin, &$stream, importexport_definition &$definition_obj)
		{
			if(method_exists($plugin, 'preview'))
			{
				$preview = $plugin->preview($stream, $definition_obj);
			}
			elseif($definition_obj->plugin_options['csv_fields'])
			{
				$import_csv = new importexport_import_csv( $stream, array(
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
				$preview = Api\Html::table($rows);
				rewind($stream);
			}
			else
			{
				$preview = lang('Preview not supported by %1', $plugin->get_name());
			}
			if(count($plugin->get_warnings())) {
				$this->message .= "<br />\n".lang('Warnings').':';
				foreach($plugin->get_warnings() as $record => $message) {
					$this->message .= "\n$record: $message";
				}
				$this->message .= "<br />\n";
			};
			if(count($plugin->get_errors())) {
				$this->message .= "<br />\n".lang('Problems during import:');
				foreach($plugin->get_errors() as $record => $message) {
					$this->message .= "<br />\n$record: $message";
				}
				$this->message .= "<br />\n".lang('Some records may not have been imported');
				$this->message .= "<br />\n";
			}
			return '<div class="header">' . lang('Preview') . ' - ' . $plugin->get_name() . '</div>' . $preview;
		}

		/**
		 * Simple check to see if the file at least matches the definition
		 *
		 * Checks that column headers match
		 * @note Currently only works for CSV
		 *
		 * @param resource $file
		 * @param importexport_definition $definition
		 * @param Array message Will be filled with any warnings or errors detected
		 * @param String Temporary file location, so user doesn't have to keep uploading
		 *
		 * @return boolean Ok to import
		 */
		public static function check_file(&$file, &$definition, &$message = array(), $dst_file = false)
		{
			$options =& $definition->plugin_options;
			$message_count = count($message);

			// Only CSV files
			if(!$options['csv_fields']) return true;

			// Can't check if definition has no header
			if($options['num_header_lines'] == 0) return true;

			$preference = $GLOBALS['egw_info']['user']['preferences']['common']['csv_charset'];
			$charset = $options['charset'] == 'user' || !$options['charset'] ? $preference : $options['charset'];

			$data = fgetcsv($file, 8000, $options['fieldsep']);
			rewind($file);
			$data = Api\Translation::convert($data,$charset);

			$ok = true;
			if(count($data) != count($options['csv_fields']) && max(array_keys($data)) != max(array_keys($options['csv_fields'])))
			{
				$message[] = lang("Column mismatch.  Expected %1 columns, your file has %2.",
								  count($options['csv_fields']),
								  count($data)
				);
				$ok = false;
			}
			foreach($data as $index => $header)
			{
				if($index < count($options['csv_fields']) && !$options['field_mapping'][$index])
				{
					// Skipped column in definition
					continue;
				}
				else if ($index > count($options['csv_fields']))
				{
					// File has extra columns - already warned, above
					break;
				}

				// Check for matching headers
				if($options['csv_fields'][$index] == $header)
				{
					// Simple match
					continue;
				}
				// Check column headers, taking into account different translations - make sure no *
				$lang_defn = mb_strtoupper(Api\Translation::translate($options['csv_fields'][$index],false,''));
				$lang_file = mb_strtoupper(Api\Translation::translate($header,false,''));

				if($lang_defn == $lang_file ||
					$lang_defn == mb_strtoupper($header) ||
					$lang_file == mb_strtoupper($options['csv_fields'][$index])
				)
				{
					continue;
				}

				// Try to go back to Api\Translation message ID for a match
				$file_message_id = Api\Translation::get_message_id($header, $definition->application);
				$defn_message_id = Api\Translation::get_message_id($options['csv_fields'][$index], $definition->application);

				if($file_message_id && $defn_message_id && $file_message_id == $defn_message_id)
				{
					continue;
				}
				//error_log("Raw[Defn: {$options['csv_fields'][$index]} File: $header] Lang[Defn: $lang_defn File: $lang_file] MSG_ID[Defn: $defn_message_id File: $file_message_id]");

				// Problem
				$message[] = lang("Column mismatch: %1 should be %2, not %3",
					$index,$options['csv_fields'][$index], $header);
				// But can still continue
				// $ok = false;
			}
			if(!$ok || count($message) != $message_count)
			{
				// Add links for new / edit definition
				$config = Api\Config::read('importexport');
				if($GLOBALS['egw_info']['user']['apps']['admin'] || $config['users_create_definitions'])
				{
					$actions = [];
					// New definition
					$add_link = Egw::link('/index.php',array(
						'menuaction' => 'importexport.importexport_definitions_ui.edit',
						'application' => $definition->application,
						'plugin' => $definition->plugin,
						// Jump to name step
						'step' => 'wizard_step21'
					));
					$add_link = "
						javascript:this.window.location = '" . Egw::link('/index.php', array(
							'menuaction' => 'importexport.importexport_import_ui.import_dialog',
							// Don't set appname, or user won't be able to select & see their new definition
							//'appname' => $definition->application,
						)) . "';
						egw_openWindowCentered2('$add_link','_blank',500,500,'yes');
					";
					$actions[] = lang('Create a <a href="%1">new definition</a> for this file', $add_link);

					// Edit selected definition, if allowed
					if($definition->owner == $GLOBALS['egw_info']['user']['account_id'] ||
						!$definition->owner && $GLOBALS['egw_info']['user']['apps']['admin'])
					{
						$edit_link = array(
							'menuaction' => 'importexport.importexport_definitions_ui.edit',
							'definition' => $definition->name,
							// Jump to file step
							'step' => 'wizard_step21'
						);
						if($dst_file)
						{
							// Still have uploaded file, jump there
							Api\Cache::setSession($definition->application, 'csvfile', $dst_file);
							$edit_link['step'] = 'wizard_step30';
						}
						$edit_link = Egw::link('/index.php', $edit_link);
						$edit_link = "javascript:egw_openWindowCentered2('$edit_link','_blank',500,500,'yes')";
						$actions[] = lang('Edit definition <a href="%1">%2</a> to match your file',
										  $edit_link, $definition->name
						);
					}
					$actions[] = lang('Edit your file to match the definition:') . ' '
						. implode(', ', array_map('lang', array_intersect_key($options['csv_fields'], $options['field_mapping'])));
					$message[] = "\n<li>" . implode("\n<li>", $actions);
				}
			}
			return $ok;
		}
	}
?>
