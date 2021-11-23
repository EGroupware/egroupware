<?php
/**
 * EGroupware - ImportExport
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
use EGroupware\Api\Vfs;
use EGroupware\Api\Etemplate;

/**
 * userinterface for admins to schedule imports or exports using async services
 *
*/

class importexport_schedule_ui
{
	public $public_functions = array(
		'index'	=>	true,
		'edit'	=>	true,
	);

	protected static $template;

	public function __construct()
	{
		$this->template = new Etemplate();
	}

	public function index($content = array())
	{
		$async = new Api\Asyncservice();
		if(is_array($content['scheduled']))
		{
			foreach($content['scheduled'] as $row)
			{
				if($row['delete'])
				{
					$key = urldecode(key($row['delete']));
					$async->cancel_timer($key);
				}
			}
		}
		$async_list = $async->read('importexport%');
		$data = array();
		if(is_array($async_list))
		{
			foreach($async_list as $id => $async)
			{
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
				if(is_numeric($async['data']['record_count']))
				{
					$async['data']['record_count'] = lang('%1 records processed', $async['data']['record_count']);
				}
				$data['scheduled'][] = array_merge($async['data'], array(
					'id'	=>	urlencode($id),
					'next'	=>	Api\DateTime::server2user($async['next']),
					'times'	=>	str_replace("\n", '', print_r($async['times'], true)),
					'last_run' =>	$async['data']['last_run'] ? Api\DateTime::server2user($async['data']['last_run']) : ''
				));
			}
			array_unshift($data['scheduled'], false);
		}
		$sel_options = self::get_select_options($data);
		$this->template->read('importexport.schedule_index');

		$GLOBALS['egw_info']['flags']['app_header'] = lang('Schedule import / export');
		$this->template->exec('importexport.importexport_schedule_ui.index', $data, $sel_options);
	}

	public function edit($content = array())
	{
		$id = $_GET['id'] ? urldecode($_GET['id']) : $content['id'];
		$definition_id = $_GET['definition'];
		$async = new Api\Asyncservice();

		unset($content['id']);

		$data = $content;

		// Deal with incoming
		if($content['save'] && self::check_target($content) === true)
		{
			unset($content['save']);
			$async->cancel_timer($id);
			$id = self::generate_id($content);
			$schedule = $content['schedule'];
			// Async sometimes changes minutes to an array - keep what user typed
			$content['min'] = $schedule['min'];
			unset($content['schedule']);

			// Remove any left blank
			foreach($schedule as $key => &$value)
			{
				if($value == '') unset($schedule[$key]);
			}
			$result = $async->set_timer(
				$schedule,
				$id,
				'importexport.importexport_schedule_ui.exec',
				$content
			);
			if($result)
			{
				Framework::refresh_opener('', 'admin',$id,'update','admin');
				Framework::window_close();
			}
			else
			{
				$data['message'] = lang('Unable to schedule');
				unset($id);
			}
		}

		if($id)
		{

			$preserve['id'] = $id;
			$async = $async->read($id);
			if(is_array($async[$id]['data']))
			{
				$data += $async[$id]['data'];
				$data['schedule'] = $async[$id]['times'];
				unset($data['times']);

				// Async sometimes changes minutes to an array - show user what they typed
				if(is_array($data['schedule']['min']))
				{
					$data['schedule']['min'] = $data['min'];
				}
			}
			else
			{
				$data['message'] = lang('Schedule not found');
			}
		}
		else
		{
			$data['type'] = $content['type'] ? $content['type'] : 'import';

			if((int)$definition_id)
			{
				$bo = new importexport_definitions_bo();
				$definition = $bo->read($definition_id);
				if($definition['definition_id'])
				{
					$data['type'] = $definition['type'];
					$data['appname'] = $definition['application'];
					$data['plugin'] = $definition['plugin'];
					$data['definition'] = $definition['name'];
				}
			}
		}

		if($data['target'] && $data['type'])
		{
			$file_check = self::check_target($data);
			if($file_check !== true)
			{
				$data['message'] .= ($data['message'] ? "\n" . $file_check : $file_check);
			}
		}

		$data['no_delete_files'] = $data['type'] != 'import';

		// Current server time for nice message
		$data['current_time'] = time();

		$sel_options = self::get_select_options($data);
		Framework::includeJS('.','importexport','importexport');

		$GLOBALS['egw_info']['flags']['app_header'] = lang('Schedule import / export');
		$this->template->read('importexport.schedule_edit');
		$this->template->exec('importexport.importexport_schedule_ui.edit', $data, $sel_options, array(), $preserve, 2);
	}

	/**
	* Get options for select boxes
	*/
	public static function get_select_options(Array $data)
	{
		$options = array(
			'type'	=>	array(
				'import'	=> lang('import'),
				'export'	=> lang('export')
			)
		);

		(array)$apps = importexport_helper_functions::get_apps($data['type'] ? $data['type'] : 'all');
		if(count($apps))
		{
			$options['appname'] = array('' => lang('Select one')) + array_combine($apps,$apps);
		}

		$plugins = importexport_helper_functions::get_plugins($data['appname'] ? $data['appname'] : 'all', $data['type']);
		if(is_array($plugins))
		{
			foreach($plugins as $types)
			{
				if(!is_array($types[$data['type']]))
				{
					continue;
				}
				foreach($types[$data['type']] as $key => $title)
				{
					$options['plugin'][$key] = $title;
				}
			}
		}

		$options['definition'] = array();

		// If the query isn't started with something, bodefinitions won't load the definitions
		$query = array();
		$query['type'] = $data['type'];
		$query['application'] = $data['application'];
		$query['plugin'] = $data['plugin'];
		$definitions = new importexport_definitions_bo($query);
		foreach ((array)$definitions->get_definitions() as $identifier)
		{
			try
			{
				$definition = new importexport_definition($identifier);
			}
			catch (Exception $e)
			{
				unset($e);
				// permission error
				continue;
			}
			if (($title = $definition->get_title()))
			{
				$options['definition'][] = array(
					'value' => $definition->get_identifier(),
					'label' => $title
				);
			}
			unset($definition);
		}
		unset($definitions);

		return $options;
	}

	/**
	* Generate a async key
	*/
	public static function generate_id($data)
	{

		$query = array(
			'name' => $data['definition']
		);

		$definitions = new importexport_definitions_bo($query);
		$definition_list = ((array)$definitions->get_definitions());

		$id = 'importexport.'.$definition_list[0].'.'.$data['target'];
		return $id;
	}

	/**
	* Check that the target is valid for the type (readable or writable)
	* and that they're not trying to write directly to the filesystem
	*
	* $data should contain target & type
	*/
	public static function check_target(Array &$data) {
		$scheme = parse_url($data['target'], PHP_URL_SCHEME);
		if($scheme == 'file')
		{
			return 'Direct file access not allowed';
		}
		else if ($scheme == '')
		{
			$data['target'] = Vfs::PREFIX.$data['target'];
			return static::check_target($data);
		}

		if($scheme == Vfs::SCHEME  && !in_array(Vfs::SCHEME, stream_get_wrappers())) {
			stream_wrapper_register(Vfs::SCHEME, 'vfs_stream_wrapper', STREAM_IS_URL);
		}
		else if (!in_array($scheme, stream_get_wrappers()))
		{
			return lang("Unable to access files with '%1'",$scheme);
		}

		if ($data['type'] == 'import' && ($scheme == Vfs::SCHEME && !Vfs::is_readable($data['target'])))
		{
			return lang('%1 is not readable',$data['target']);
		}
		elseif ($data['type'] == 'import' && in_array($scheme, array('http','https')))
		{
			// Not supported by is_readable, try headers...
			stream_context_set_default(array('http'=>array(
				'method'	=> 'HEAD',
				'ignore_errors'	=> 1
			)));
			$headers = get_headers($data['target'],1) ?: [];

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
		elseif ($data['type'] == 'export' && !self::is__writable($data['target']))
		{
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
	private static function is__writable($path)
	{
		if ($path[strlen($path)-1] === '/')
		{
			// recursively return a temporary file path
			return self::is__writable($path.uniqid(mt_rand()).'.tmp');
		}
		else if (is_dir($path))
		{
			return self::is__writable($path.'/'.uniqid(mt_rand()).'.tmp');
		}

		// check tmp file for read/write capabilities
		$rm = file_exists($path);
		$f = @fopen($path, 'a');

		if ($f===false)
		{
			return false;
		}

		fclose($f);

		if (!$rm)
		{
			@unlink($path);
		}
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
	//	$data['lock'] = time() + 3600;
		self::update_job($data, true);

		// check file
		$file_check = self::check_target($data);
		if($file_check !== true)
		{
			$data['errors'] = array($file_check=>'');
			// Update job with results
			self::update_job($data);

			error_log('importexport_schedule: ' . date('c') . ": $file_check \n");
			error_log(ob_get_flush());
			return;
		}

		$definition = new importexport_definition($data['definition']);
		if( $definition->get_identifier() < 1 )
		{
			$data['errors'] = array('Definition not found!');
			// Update job with results
			self::update_job($data);

			error_log('importexport_schedule: ' . date('c') . ": Definition not found! \n");
			return;
		}
		$GLOBALS['egw_info']['flags']['currentapp'] = $definition->application;

		$po = new $definition->plugin;

		$type = $data['type'];

		if(is_dir($data['target']))
		{
			if($data['type'] == 'import')
			{
				$targets = array();
				foreach(scandir($data['target']) as $target)
				{
					if ($target == '.' || $target == '..')
					{
						continue;
					}
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
				// Create a unique file for export
				$targets = array($data['target'].uniqid($definition->name).'.'.$po->get_filesuffix());
			}
		}
		else
		{
			$targets = array($data['target']);
		}

		if($type == 'export')
		{
			// Set to export all or filter, if set
			$selection = array('selection' => 'all');
			if($definition->filter)
			{
				$fields = importexport_helper_functions::get_filter_fields($definition->application, $po);
				$selection = array('selection' => 'filter');
				$filters = array();
				foreach($definition->filter as $field => $value)
				{
					 // Handle multiple values
					if(!is_array($value) && strpos($value,',') !== false)
					{
						$value = explode(',',$value);
					}

					$filters[$field] = $value;

					// Process relative dates into the current absolute date
					if($filters[$field] && strpos($fields[$field]['type'],'date') === 0)
					{
						$filters[$field] = importexport_helper_functions::date_rel2abs($value);
					}
				}
				// Update filter to use current absolute dates
				$definition->filter = $filters;
			}
			if(!is_array($definition->plugin_options))
			{
				$definition->plugin_options = array();
			}
			$definition->plugin_options = array_merge($definition->plugin_options, $selection);
		}
		// Set some automatic admin history data, if the plugin wants it
		$definition->plugin_options = array_merge($definition->plugin_options, array('admin_cmd' => array(
			'comment' => lang('schedule import / export') . "\n" . $definition->get_title() . "\n" . $target
		)));

		foreach($targets as $target)
		{
			// Update lock timeout
			$data['lock'] = time() + 3600;
			self::update_job($data, true);

			$resource = null;
			try
			{
				if (($resource = @fopen( $target, $data['type'] == 'import' ? 'rb' : 'wb' )))
				{
					$result = $po->$type( $resource, $definition );

					fclose($resource);
				}
				else
				{
					error_log('importexport_schedule: ' . date('c') . ": File $target not readable! \n");
					$data['errors'][$target][] = lang('%1 is not readable',$target);
				}
			}
			catch (Exception $i_ex)
			{
				fclose($resource);
				$data['errors'][$target][] = $i_ex->getMessage();
			}


			if(method_exists($po, 'get_warnings') && $po->get_warnings())
			{
				$buffer = 'importexport_schedule: ' . date('c') . ": Import warnings:\n#\tWarning\n";
				foreach($po->get_warnings() as $record => $msg)
				{
					$data['warnings'][$target][] = "#$record: $msg";
					$buffer .= "$record\t$msg\n";
				}
				error_log($buffer);
			}
			if(method_exists($po, 'get_errors') && $po->get_errors())
			{
				$buffer = 'importexport_schedule: ' . date('c') . ": Import errors:\n#\tError\n";
				foreach($po->get_errors() as $record => $error)
				{
					$data['errors'][$target][] = "#$record: $error";
					$buffer .= "$record\t$error\n";
				}
				error_log($buffer);
			}

			if($po instanceof importexport_iface_import_plugin)
			{
				if(is_numeric($result))
				{
					$data['record_count'] += $result;
					$data['result'][$target][] = lang('%1 records processed', $result);
				}
				$data['result'][$target] = array();
				foreach($po->get_results() as $action => $count)
				{
					$data['result'][$target][] = lang($action) . ": $count";
				}
			}
			else
			{
				if($result instanceof importexport_iface_export_record)
				{
					$data['record_count'] += $result->get_num_of_records();
					$data['result'][$target][] = lang('%1 records processed', $result->get_num_of_records());
				}
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

		// Log to error log
		if($contents)
		{
			error_log('importexport_schedule: ' . date('c') . ": \n".$contents);
		}

		ob_end_clean();
	}

	/**
	 * Update the async job with current status, and send a notification
	 * to user if there were any errors.
	 */
	private static function update_job($data, $no_notification = false)
	{
		$id = self::generate_id($data);
		$async = new Api\Asyncservice();
		$jobs = $async->read($id);
		$job = $jobs[$id];

		if(is_array($job))
		{
			$async->cancel_timer($id);
			$result = $async->set_timer(
				$job['times'],
				$id,
				'importexport.importexport_schedule_ui.exec',
				$data
			);
		}
		if($no_notification)
		{
			return $result;
		}

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
				$contents .= lang($data['type']) . ' ' . lang('Warnings') . ' ' . Api\DateTime::to() . ':';
				foreach($data['warnings'] as $target => $message)
				{
					$contents .= "\n". (is_numeric($target) ? '' : $target."\n");
					$contents .= is_array($message) ? implode("\n",$message) : $message;
				}
				$contents .= "\n";
			}
			if($data['errors'])
			{
				$contents .= lang($data['type']) . ' ' . lang('Errors') . ' ' . Api\DateTime::to() . ':';
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
