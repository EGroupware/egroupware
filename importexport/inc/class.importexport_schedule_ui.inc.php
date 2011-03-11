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
 * userinterface for admins to schedule imports or exports using async services
 *
*/

	class importexport_schedule_ui {

		public $public_functions = array(
			'index'	=>	true,
			'edit'	=>	true,
		);

		protected static $template;

		public function __construct() {
			$this->template = new etemplate();
		}

		public function index($content = array()) {

			if($content['scheduled']['delete']) {
				$key = key($content['scheduled']['delete']);
				ExecMethod('phpgwapi.asyncservice.cancel_timer', $key);
			}
			$async_list = ExecMethod('phpgwapi.asyncservice.read', 'importexport%');
			$data = array();
			if(is_array($async_list)) {
				foreach($async_list as $id => $async) {
					if(is_array($async['data']['errors'])) {
						$async['data']['errors'] = implode("\n", $async['data']['errors']);
					}
					if(is_numeric($async['data']['record_count'])) {
						$async['data']['record_count'] = lang('%1 records processed', $async['data']['record_count']);
					}
					$data['scheduled'][] = $async['data'] + array(
						'id'	=>	$id,
						'next'	=>	$async['next'],
						'times'	=>	str_replace("\n", '', print_r($async['times'], true)),
					);
				}
				array_unshift($data['scheduled'], false);
			}
			$sel_options = self::get_select_options($data);
			$this->template->read('importexport.schedule_index');

			$GLOBALS['egw_info']['flags']['app_header'] = lang('Schedule import / export');
			$this->template->exec('importexport.importexport_schedule_ui.index', $data, $sel_options, $readonlys, $preserve);
		}

		public function edit($content = array()) {
			$id = $_GET['id'] ? $_GET['id'] : $content['id'];
			unset($content['id']);

			$data = $content;

			// Deal with incoming
			if($content['save'] && self::check_target($content) === true) {
				unset($content['save']);
				ExecMethod('phpgwapi.asyncservice.cancel_timer', $id);
				$id = self::generate_id($content);
				$schedule = $content['schedule'];
				unset($content['schedule']);

				// Fill in * for any left blank
				foreach($schedule as $key => &$value) {
					if($value == '') $value = '*';
				}
				$result = ExecMethod2('phpgwapi.asyncservice.set_timer', 
					$schedule,
					$id,
					'importexport.importexport_schedule_ui.exec',
					$content
				);
				if($result) {
					$GLOBALS['egw']->js->set_onload('window.opener.location.reload(); self.close();');
				} else {
					$data['message'] = lang('Unable to schedule');
					unset($id);
				}
			}

			if($id) {
				$preserve['id'] = $id;
				$async = ExecMethod('phpgwapi.asyncservice.read', $id);
				if(is_array($async[$id]['data'])) {
					$data += $async[$id]['data'];
					$data['schedule'] = $async[$id]['times'];
				} else {
					$data['message'] = lang('Schedule not found');
				}
			} else {
				$data['type'] = $content['type'] ? $content['type'] : 'import';
			}

			if($data['target'] && $data['type']) {
				$file_check = self::check_target($data);
				if($file_check !== true) $data['message'] .= ($data['message'] ? "\n" . $file_check : $file_check);
			}

			$sel_options = self::get_select_options($data);
			$GLOBALS['egw']->js->validate_file('.','importexport','importexport');

			$GLOBALS['egw_info']['flags']['app_header'] = lang('Schedule import / export');
			$this->template->read('importexport.schedule_edit');
			$this->template->exec('importexport.importexport_schedule_ui.edit', $data, $sel_options, $readonlys, $preserve, 2);
		}

		/**
		* Get options for select boxes
		*/
		public static function get_select_options(Array $data) {
			$options = array(
				'type'	=>	array(
					'import'	=> lang('import'),
					'export'	=> lang('export')
				)
			);

			(array)$apps = importexport_helper_functions::get_apps($data['type'] ? $data['type'] : 'all');
			if(count($apps)) {
				$options['appname'] = array('' => lang('Select one')) + array_combine($apps,$apps);
			}

			if($data['appname']) {
				$plugins = importexport_helper_functions::get_plugins($data['appname'], $data['type']);
				if(is_array($plugins[$data['appname']][$data['type']])) {
					foreach($plugins[$data['appname']][$data['type']] as $key => $title) {
						$options['plugin'][$key] = $title;
					}
				}
			} else {
				$plugins = importexport_helper_functions::get_plugins('all', $data['type'] ? $data['type'] : 'all');
				if(is_array($plugins)) {
					foreach($plugins as $appname => $_types) {
						foreach($_types as $type => $plugins) {
							foreach($plugins as $key => $title) {
								$options['plugin'][$key] = $title;
							}
						}
					}
				}
			}
				
			$options['definition'] = array();

			if($data['file'] && !is_array($data['file'])) {
				$extension = substr($data['file'], -3);
			}

			// If the query isn't started with something, bodefinitions won't load the definitions
			$query = array('definition_id');
			if($data['type']) $query['type'] = $data['type'];
			if($data['application']) $query['application'] = $data['application'];
			if($data['plugin']) $query['plugin'] = $data['plugin'];
			$definitions = new importexport_definitions_bo($query);
			foreach ((array)$definitions->get_definitions() as $identifier) {
					$definition = new importexport_definition($identifier);
					if ($title = $definition->get_title()) {
						$options['definition'][$title] = $title;
					}
					unset($definition);
			}
			unset($definitions);

			return $options;
		}

		/**
		* Generate a async key
		*/
		public static function generate_id($data) {

			$query = array(
				'name' => $data['definition']
			);

			$definitions = new importexport_definitions_bo($query);
			$definition_list = ((array)$definitions->get_definitions());
			
			$id = 'importexport.'.$definition_list[0].'.'.$data['target'];
			return $id;
		}

		/**
		* Get plugins via ajax
		*/
		public function ajax_get_plugins($type, $appname, &$response = null) {
			if($response) {
				$return = false;
			} else {
				$response = new xajaxResponse();
			}
			$options = self::get_select_options(array('type' => $type, 'appname'=>$appname));
			$response->addScript("clear_options('exec[plugin]');");
			if(is_array($options['plugin'])) {
				foreach ($options['plugin'] as $value => $title) {
					$response->addScript("selectbox_add_option('exec[plugin]','$title', '$value',false);");
				}
			}
			return $response->getXML();
		}

		/**
		* Get definitions via ajax
		*/
		public function ajax_get_definitions($appname, $plugin) {
			$options = self::get_select_options(array('appname'=>$appname, 'plugin'=>$plugin));
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
		* Check that the target is valid for the type (readable or writable)
		* and that they're not trying to write directly to the filesystem
		* 
		* $data should contain target & type
		*/
		public static function check_target(Array $data) {
			$scheme = parse_url($data['target'], PHP_URL_SCHEME);
			if($scheme == '' || $scheme == 'file') {
				return 'Direct file access not allowed';
			}
			if($scheme == vfs_stream_wrapper::SCHEME && !in_array(vfs_stream_wrapper::SCHEME, stream_get_wrappers())) {
				stream_wrapper_register(vfs_stream_wrapper::SCHEME, 'vfs_stream_wrapper', STREAM_IS_URL);
			}

			if ($data['type'] == 'import' && ($scheme == egw_vfs::SCHEME && !egw_vfs::is_readable($data['target']) ||
			    $scheme != egw_vfs::SCHEME && !is_readable($data['target'])))
			{
				return lang('%1 is not readable',$data['target']);
			} 
			elseif ($data['type'] == 'export' && !self::is__writable($data['target'])) {
				return lang('%1 is not writable',$data['target']);
			}

			return true;
		}

		/**
		* Writable that checks the folder too, in case the file does not exist yet
		* http://ca3.php.net/manual/en/function.is-writable.php#73596
		*
		* @param path Path to check
		*/
		private static function is__writable($path) {
			if ($path{strlen($path)-1}=='/') // recursively return a temporary file path
				return is__writable($path.uniqid(mt_rand()).'.tmp');
			else if (is_dir($path))
				return is__writable($path.'/'.uniqid(mt_rand()).'.tmp');
			// check tmp file for read/write capabilities
			$rm = file_exists($path);
			$f = fopen($path, 'a');

			if ($f===false)
				return false;

			fclose($f);
			if (!$rm)
				unlink($path);
			return true;
		}

		/**
		* Execute a scheduled import or export
		*/
		public static function exec($data) {
			ob_start();

			// check file
			$file_check = self::check_target($data);
			if($file_check !== true) {
				fwrite(STDERR,'importexport_schedule: ' . date('c') . ": $file_check \n");
				exit();
			}

			$definition = new importexport_definition($data['definition']);
			if( $definition->get_identifier() < 1 ) {
				fwrite(STDERR,'importexport_schedule: ' . date('c') . ": Definition not found! \n");
				exit();
			}
			$GLOBALS['egw_info']['flags']['currentapp'] = $definition->application;

			$po = new $definition->plugin;

			$type = $data['type'];
			if($resource = fopen( $data['target'], $data['type'] == 'import' ? 'r' : 'w' )) {
				$result = $po->$type( $resource, $definition );

				fclose($resource);
			} else {
				fwrite(STDERR,'importexport_schedule: ' . date('c') . ": Definition not found! \n");
			}

			if(method_exists($po, 'get_errors') && $po->get_errors()) {
				$data['errors'] = $po->get_errors();
				fwrite(STDERR, 'importexport_schedule: ' . date('c') . ": Import errors:\n#\tError\n");
				foreach($po->get_errors() as $record => $error) {
					fwrite(STDERR, "$record\t$error\n");
				}
			} else {
				unset($data['errors']);
			}

			if($po instanceof importexport_iface_import_plugin) {
				if(is_numeric($result)) {
					$data['record_count'] = $result;
				}
				$data['result'] = '';
				foreach($po->get_results() as $action => $count) {
					$data['result'] .= "\n" . lang($action) . ": $count";
				}
			} else {
				$data['result'] = $result;
			}
			$data['last_run'] = time();

			// Update job with results
			$id = self::generate_id($data);
			$async = ExecMethod('phpgwapi.asyncservice.read', $id);
			$async = $async[$id];
			if(is_array($async)) {
				ExecMethod('phpgwapi.asyncservice.cancel_timer', $id);
				$result = ExecMethod2('phpgwapi.asyncservice.set_timer', 
					$async['times'],
					$id,
					'importexport.importexport_schedule_ui.exec',
					$data
				);
			}

			$contents = ob_get_contents();
			if($contents) {
				fwrite(STDOUT,'importexport_schedule: ' . date('c') . ": \n".$contents);
			}
			ob_end_clean();
		}
	}
?>
