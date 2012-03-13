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
			if(is_array($content['scheduled']))
			{
				foreach($content['scheduled'] as $row)
				{
					if($row['delete']) {
						$key = key($row['delete']);
						ExecMethod('phpgwapi.asyncservice.cancel_timer', $key);
					}
				}
			}
			$async_list = ExecMethod('phpgwapi.asyncservice.read', 'importexport%');
			$data = array();
			if(is_array($async_list)) {
				foreach($async_list as $id => $async) {
					foreach(array('errors', 'warnings', 'result') as $messages)
					{
						if(is_array($async['data'][$messages]))
						{
							$list = array();
							foreach($async['data'][$messages] as $target => $message)
							{
								$list[] = array(
									'target' => (is_numeric($target) ? '' : $target),
									'message' =>  implode("\n", (array)$message)
								);
							}
							$async['data'][$messages] = $list;
						}
					}
					if($results)
					{
						$async['data']['result'] = $results;
					}
					if(is_numeric($async['data']['record_count'])) {
						$async['data']['record_count'] = lang('%1 records processed', $async['data']['record_count']);
					}
					$data['scheduled'][] = array_merge($async['data'], array(
						'id'	=>	$id,
						'next'	=>	egw_time::server2user($async['next']),
						'times'	=>	str_replace("\n", '', print_r($async['times'], true)),
						'last_run' =>	$async['data']['last_run'] ? egw_time::server2user($async['data']['last_run']) : ''
					));
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
			$definition_id = $_GET['definition'];

			unset($content['id']);

			$data = $content;

			// Deal with incoming
			if($content['save'] && self::check_target($content) === true) {
				unset($content['save']);
				ExecMethod('phpgwapi.asyncservice.cancel_timer', $id);
				$id = self::generate_id($content);
				$schedule = $content['schedule'];
				// Async sometimes changes minutes to an array - keep what user typed
				$content['min'] = $schedule['min'];
				unset($content['schedule']);

				// Remove any left blank
				foreach($schedule as $key => &$value) {
					if($value == '') unset($schedule[$key]);
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
					unset($data['times']);

					// Async sometimes changes minutes to an array - show user what they typed
					if(is_array($data['schedule']['min'])) $data['schedule']['min'] = $data['min'];
				} else {
					$data['message'] = lang('Schedule not found');
				}
			} else {
				$data['type'] = $content['type'] ? $content['type'] : 'import';

				if((int)$definition_id) {
					$bo = new importexport_definitions_bo();
					$definition = $bo->read($definition_id);
					if($definition['definition_id']) {
						$data['type'] = $definition['type'];
						$data['appname'] = $definition['application'];
						$data['plugin'] = $definition['plugin'];
						$data['definition'] = $definition['name'];
					}
				}
			}

			if($data['target'] && $data['type']) {
				$file_check = self::check_target($data);
				if($file_check !== true) $data['message'] .= ($data['message'] ? "\n" . $file_check : $file_check);
			}

			$data['no_delete_files'] = $data['type'] != 'import';

			// Current server time for nice message
			$data['current_time'] = time();

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

			$plugins = importexport_helper_functions::get_plugins($data['appname'] ? $data['appname'] : 'all', $data['type']);
			if(is_array($plugins)) {
				foreach($plugins as $appname => $types) {
					if(!is_array($types[$data['type']])) continue;
					foreach($types[$data['type']] as $key => $title) {
						$options['plugin'][$key] = $title;
					}
				}
			}

			$options['definition'] = array();

			if($data['file'] && !is_array($data['file'])) {
				$extension = substr($data['file'], -3);
			}

			// If the query isn't started with something, bodefinitions won't load the definitions
			$query = array();
			$query['type'] = $data['type'];
			$query['application'] = $data['application'];
			$query['plugin'] = $data['plugin'];
			$definitions = new importexport_definitions_bo($query);
			foreach ((array)$definitions->get_definitions() as $identifier) {
				try {
					$definition = new importexport_definition($identifier);
				} catch (Exception $e) {
					// permission error
					continue;
				}
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
			$response->addScript("selectbox_add_option('exec[plugin]','".lang('Select...')."', '',false);");
			if(is_array($options['plugin'])) {
				foreach ($options['plugin'] as $value => $title) {
					$response->addScript("selectbox_add_option('exec[plugin]','$title', '$value',false);");
				}
			}
			if(count($options['plugin']) == 1) {
				$this->ajax_get_definitions($appname, $value, $response);
				$response->assign('exec[plugin]','value',$value);
			} else {
				$response->addScript("xajax_doXMLHTTP('importexport.importexport_schedule_ui.ajax_get_definitions', '$appname', document.getElementById('exec[plugin]').value);");
			}

			return $response->getXML();
		}

		/**
		* Get definitions via ajax
		*/
		public function ajax_get_definitions($appname, $plugin, &$response = null) {
			$options = self::get_select_options(array('appname'=>$appname, 'plugin'=>$plugin));
			if($response) {
				$return = false;
			} else {
				$response = new xajaxResponse();
			}
			$response->addScript("clear_options('exec[definition]');");
			$response->addScript("selectbox_add_option('exec[definition]','".lang('Select...')."', '',false);");
			if(is_array($options['definition'])) {
				foreach ($options['definition'] as $value => $title) {
					$response->addScript("selectbox_add_option('exec[definition]','$title', '$value',false);");
				}
			}
			if(count($options['definition']) == 1) {
				$response->assign('exec[definition]','value',$value);
			} else {
				$response->addScript("document.getElementById('exec[definition]').value = ''");
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

			if ($data['type'] == 'import' && ($scheme == egw_vfs::SCHEME && !egw_vfs::is_readable($data['target'])))
			{
				return lang('%1 is not readable',$data['target']);
			}
			elseif ($data['type'] == 'import' && in_array($scheme, array('http','https')))
			{
				// Not supported by is_readable, try headers...
				$options = array();
				stream_context_set_default(array('http'=>array(
					'method'	=> 'HEAD',
					'ignore_errors'	=> 1
				)));
				$headers = get_headers($data['target'],1);

				// Reset...
				stream_context_set_default(array('http'=>array(
					'method'	=> 'GET',
					'ignore_errors'	=> 0
				)));
				// Response code has an integer key, but redirects may add more responses
				for($i = 0; $i < count($headers); $i++)
				{
					if(!$headers[$i]) break;
					if(strpos($headers[$i],'200') !== false) return true;
				}
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
			$f = @fopen($path, 'a');

			if ($f===false)
				return false;

			fclose($f);
			if (!$rm)
				@unlink($path);
			return true;
		}

		/**
		* Execute a scheduled import or export
		*/
		public static function exec($data)
		{
			ob_start();

			$data['record_count'] = 0;
			unset($data['errors']);
			unset($data['warnings']);
			unset($data['result']);

			if($data['lock'])
			{
				// Lock expires
				if($data['lock'] < time())
				{
					unset($data['lock']);
					$data['warnings'][][] = lang('Lock expired on previous run');
				}
				else
				{
					// Still running
					ob_end_flush();
					return;
				}
			}

			$data['last_run'] = time();

			// Lock job for an hour to prevent multiples overlapping
			$data['lock'] = time() + 3600;
			self::update_job($data, true);

			// check file
			$file_check = self::check_target($data);
			if($file_check !== true) {
				$data['errors'] = array($file_check=>'');
				// Update job with results
				self::update_job($data);

				ob_end_flush();
				fwrite(STDERR,'importexport_schedule: ' . date('c') . ": $file_check \n");
				return;
			}

			$definition = new importexport_definition($data['definition']);
			if( $definition->get_identifier() < 1 ) {
				$data['errors'] = array('Definition not found!');
				// Update job with results
				self::update_job($data);

				fwrite(STDERR,'importexport_schedule: ' . date('c') . ": Definition not found! \n");
				return;
			}
			$GLOBALS['egw_info']['flags']['currentapp'] = $definition->application;

			$po = new $definition->plugin;

			$type = $data['type'];

			if(is_dir($data['target']))
			{
				$targets = array();
				foreach(scandir($data['target']) as $target)
				{
					if ($target == '.' || $target == '..') continue;
					$target = $data['target'].(substr($data['target'],-1) == '/' ? '' : '/').$target;

					// Check modification time, make sure it's not currently being written
					// Skip files modified in the last 10 seconds
					$mod_time = filemtime($target);
					if($mod_time >= time() - 10)
					{
						$data['result'][$target] = lang('Skipped');
						continue;
					}
					$targets[$mod_time.$target] = $target;
				}
				if($targets)
				{
					ksort($targets);
				}
			}
			else
			{
				$targets = array($data['target']);
			}

			foreach($targets as $target)
			{
				// Update lock timeout
				$data['lock'] = time() + 3600;
				self::update_job($data, true);

				$resource = null;
				try {
					if($resource = @fopen( $target, $data['type'] == 'import' ? 'rb' : 'wb' )) {
						$result = $po->$type( $resource, $definition );

						fclose($resource);
					} else {
						fwrite(STDERR,'importexport_schedule: ' . date('c') . ": File $target not readable! \n");
						$data['errors'][$target][] = lang('%1 is not readable',$target);
					}
				}
				catch (Exception $i_ex)
				{
					fclose($resource);
					$data['errors'][$target][] = $i_ex->getMessage();
				}


				if(method_exists($po, 'get_warnings') && $po->get_warnings()) {
					fwrite(STDERR, 'importexport_schedule: ' . date('c') . ": Import warnings:\n#\tWarning\n");
					foreach($po->get_warnings() as $record => $msg) {
						$data['warnings'][$target][] = "#$record: $msg";
						fwrite(STDERR, "$record\t$msg\n");
					}
				} else {
					unset($data['warnings'][$target]);
				}
				if(method_exists($po, 'get_errors') && $po->get_errors()) {
					fwrite(STDERR, 'importexport_schedule: ' . date('c') . ": Import errors:\n#\tError\n");
					foreach($po->get_errors() as $record => $error) {
						$data['errors'][$target][] = "#$record: $error";
						fwrite(STDERR, "$record\t$error\n");
					}
				} else {
					unset($data['errors'][$target]);
				}

				if($po instanceof importexport_iface_import_plugin) {
					if(is_numeric($result)) {
						$data['record_count'] += $result;
						$data['result'][$target][] = lang('%1 records processed', $result);
					}
					$data['result'][$target] = array();
					foreach($po->get_results() as $action => $count) {
						$data['result'][$target][] = lang($action) . ": $count";
					}
				} else {
					$data['result'][$target] = $result;
				}
			}

			// Delete file?
			if($data['delete_files'] && $type == 'import' && !$data['errors'])
			{
				foreach($targets as $target)
				{
					if(unlink($target))
					{
						$data['result'][$target][] .= "\n..." . lang('deleted');
					}
					else
					{
						$data['errors'][$target][] .= "\n..." . lang('Unable to delete');
					}
				}
			}

			// Run time in minutes
			$data['run_time'] = round((time() - $data['last_run']) / 60,1);

			// Clear lock
			$data['lock'] = 0;

			// Update job with results
			self::update_job($data);

			$contents = ob_get_contents();

			// Log to cron log
			if($contents)
			{
				fwrite(STDOUT,'importexport_schedule: ' . date('c') . ": \n".$contents);
			}

			ob_end_clean();
		}

		/**
		 * Update the async job with current status, and send a notification
		 * to user if there were any errors.
		 */
		private static function update_job($data, $no_notification = false) {
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
			if($no_notification) return $result;

			// Send notification to user
			if($data['warnings'] || $data['errors'])
			{
				$notify = new notifications();
				$notify->set_sender($data['account_id']);
				$notify->add_receiver($data['account_id']);
				$notify->set_subject(lang('Schedule import | export'). ' ' . lang('errors'));
				$contents = '';

				if($data['warnings'])
				{
					$contents .= lang($data['type']) . ' ' . lang('Warnings') . ' ' . egw_time::to() . ':';
					foreach($data['warnings'] as $target => $message)
					{
						$contents .= "\n". (is_numeric($target) ? '' : $target."\n");
						$contents .= is_array($message) ? implode("\n",$message) : $message;
					}
					$contents .= "\n";
				}
				if($data['errors'])
				{
					$contents .= lang($data['type']) . ' ' . lang('Errors') . ' ' . egw_time::to() . ':';
					foreach($data['errors'] as $target => $errors)
					{
						$contents .= "\n". (is_numeric($target) ? '' : $target."\n");
						$contents .= is_array($errors) ? implode("\n",$errors) : $errors;
					}
					$contents .= "\n";
				}
				$notify->set_message($contents);
				$notify->send();
			}
			return $result;
		}
	}
?>
