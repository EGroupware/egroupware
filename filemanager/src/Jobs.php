<?php
/**
 * EGroupware - Filemanager - Jobs
 *
 * @link https://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker
 * @copyright (c) 2025 Ralf Becker <rb@egroupware.org>
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Filemanager;

use EGroupware\Api;
use EGroupware\Api\Vfs\Sharing;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Framework;
use EGroupware\Api\Link;
use EGroupware\Api\Vfs;

/**
 * Filemanager jobs allow monitoring a directory and start actions for files in it:
 * - Create an InfoLog entry with the file attached
 */
class Jobs
{
	const APP = 'filemanager';

	// These functions are allowed to be called via menuaction GET parameter
	public $public_functions = [
		'index' => true,
		'edit' => true,
	];

	public static $supported_apps = ['infolog' => 'infolog'];

	/**
	 * @var \infolog_bo
	 */
	protected $info_bo;

	public function __construct()
	{
		$this->info_bo = new \infolog_bo();
		Api\Translation::add_app('infolog');
	}

	/**
	 * Create or edit a job
	 *
	 * @param array|null $content
	 * @return void
	 */
	public function edit(?array $content=null)
	{
		if (!is_array($content))
		{
			if (empty($_GET['id']) || !($content = $this->read($_GET['id'])))
			{
				$content = ['app' => 'infolog', 'info_type' => 'task'];
			}
			// fix old custom-fields
			elseif(!empty($content['cf']))
			{
				foreach ($content['cf'] as $cf)
				{
					if (!empty($cf['value']))
					{
						$content['#'.$cf['name']] = $cf['value'];
					}
				}
				unset($content['cf']);
			}
			$content['tabs'] = empty($content['error']) ? 'general' : 'error';
		}
		elseif (!empty($content['button']))
		{
			try {
				$button = key($content['button']);
				unset($content['button']);
				switch ($button)
				{
					case 'save':
					case 'apply':
						$this->checkFolder($content['directory']);
						if (!empty($content['target_dir']))
						{
							$this->checkFolder($content['target_dir']);
						}
						// remove empty custom-fields before storing
						foreach($content as $name => $value)
						{
							if ($name[0] === '#' && empty($value) && (string)$value !== '0')
							{
								unset($content[$name]);
							}
						}
						$type = empty($content['id']) ? 'add' : 'edit';
						unset($content['error']);   // unset error to restart job
						$content = self::save($content);
						$msg = lang('Job saved.');
						Framework::refresh_opener($msg, self::APP, $content['id'], $type);
						if ($button === 'apply')
						{
							Framework::message($msg, 'success');
							break;
						}
						// fall-through for save
					case 'cancel':
						Framework::window_close();
						break;

					case 'delete':
						$this->delete($content['id']);
						Framework::refresh_opener(lang('Job deleted.'), self::APP, $content['id'], 'delete');
						Framework::window_close();
						break;
				}
			}
			catch (\Exception $e) {
				Framework::message($e->getMessage(), 'error');
			}
		}
		$sel_options = [
			'app' => self::$supported_apps,
			'info_type' => $this->info_bo->enums['type'],
			'name' => array_map(function ($cf) {
				return $cf['label'];
			}, Api\Storage\Customfields::get($content['app'] ?? 'infolog', true, $content['info_type'] ?? '')),
		];
		$sel_options['file_created'] = $sel_options['name'];
		$content['no_cfs'] = empty($sel_options['name']);
		$readonlys = [
			'button[delete]' => empty($content['id']),
			'tabs' => [
				'custom' => $content['no_cfs'],
				'error' => empty($content['error']),
			],
		];
		$tpl = new Api\Etemplate('filemanager.job');
		$tpl->exec(self::APP.'.'.self::class.'.edit', $content, $sel_options, $readonlys, $content, 2);
	}

	/**
	 * Show jobs
	 *
	 * @param array|null $content
	 */
	public function index(?array $content=null)
	{
		if (!is_array($content) || empty($content['nm']))
		{
			$content = [
				'nm' => [
					'get_rows'       =>	self::APP.'.'.self::class.'.get_rows',
					'no_filter'      => true,	// disable the diverse filters we not (yet) use
					'no_filter2'     => true,
					'no_cat'         => true,
					'order'          =>	'modified',// IO name of the column to sort after (optional for the sortheaders)
					'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
					'row_id'         => 'id',
					'row_modified'   => 'modified',
					'actions'        => $this->get_actions(),
					'placeholder_actions' => array('add')
				]
			];
		}
		elseif(!empty($content['nm']['action']))
		{
			try {
				Api\Framework::message($this->action($content['nm']['action'],
					$content['nm']['selected'], $content['nm']['select_all']));
			}
			catch (\Exception $ex) {
				Api\Framework::message($ex->getMessage(), 'error');
			}
		}
		$sel_options = [
			'app' => self::$supported_apps,
		];
		$tmpl = new Api\Etemplate('filemanager.jobs');
		$tmpl->exec(self::APP.'.'.self::class.'.index', $content, $sel_options, [], ['nm' => $content['nm']]);
	}

	/**
	 * Run an action
	 *
	 * @param string $action
	 * @param array $ids
	 * @param bool $select_all
	 * @return mixed
	 * @throws \Exception
	 */
	protected function action(string $action, array $ids, bool $select_all)
	{
		switch ($action)
		{
			case 'delete':
				foreach($ids as $id)
				{
					$this->delete($id);
				}
				return lang('Job deleted.');

			default:
				throw new \Exception('Unknown action');
		}
	}

	/**
	 * Return actions
	 *
	 * @return array
	 */
	protected function get_actions()
	{
		return [
			'edit' => [
				'caption' => 'Edit',
				'allowOnMultiple' => false,
				'url' => 'menuaction='.self::APP.'.'.self::class.'.edit&id=$id',
				'default' => true,
				'popup' => '800x600',
				'group' => $group=1,
			],
			'add' => [
				'caption' => 'Add',
				'url' => 'menuaction='.self::APP.'.'.self::class.'.edit',
				'popup' => '800x600',
				'group' => $group,
			],
			'delete' => [
				'caption' => 'Delete',
				'confirm' => 'Are you sure?',
				'group' => $group=5,
			],
		];
	}

	/**
	 * Save a given job
	 *
	 * @param array $data
	 * @return array
	 * @throws Api\Exception\WrongParameter
	 */
	protected static function save(array $data)
	{
		$jobs = Api\Config::read(self::APP)['jobs'] ?? [];
		if (empty($data['id']))
		{
			$data['id'] = Vfs\WebDAV::_new_uuid();
			$data['created'] = new Api\DateTime();
			$data['creator'] = $GLOBALS['egw_info']['user']['account_id'];
		}
		$data['modified'] = new Api\DateTime();
		$data['modifier'] = $GLOBALS['egw_info']['user']['account_id'];

		$jobs[$data['id']] = $data;
		Api\Config::save_value('jobs', $jobs, self::APP);

		self::installAsyncJob();

		return $data;
	}

	/**
	 * Read a job specified by its ID
	 *
	 * @param string $id
	 * @return array|null
	 */
	protected function read(string $id)
	{
		return Api\Config::read(self::APP)['jobs'][$id] ?? null;
	}

	/**
	 * Delete a job
	 *
	 * @param string $id
	 * @return void
	 */
	protected function delete(string $id)
	{
		$jobs = Api\Config::read(self::APP)['jobs'] ?? [];
		unset($jobs[$id]);
		Api\Config::save_value('jobs', $jobs, self::APP);
	}

	/**
	 * Fetch jobs to display
	 *
	 * @param ?array $query
	 * @param ?array& $rows =null
	 * @param ?array& $readonlys =null
	 */
	public function get_rows(?array $query, array &$rows=null, array &$readonlys=null)
	{
		$rows = Api\Config::read(self::APP)['jobs'] ?? [];
		// filter and sort
		foreach($query['col_filter'] ?? [] as $name => $value)
		{
			if (($value ?? '') !== '')
			{
				$rows = array_filter($rows, function ($row) use ($name, $value)
				{
					return $row[$name] === $value;
				});
			}
		}
		$rows = array_values($rows);
		return count($rows);
	}

	/**
	 * Check folder exists and is writable
	 *
	 * @param string $folder
	 * @throws \Exception
	 */
	protected function checkFolder(string $folder)
	{
		if (!Api\Vfs::file_exists($folder) || !Api\Vfs::is_dir($folder) || !Api\Vfs::is_writable($folder))
		{
			throw new \Exception(lang('Job folder %1 does not exist or is not writable', $folder));
		}
	}

	/**
	 * Run all jobs
	 *
	 * @param ?array $data
	 * @return void
	 */
	public function asyncCallback(?array $data=null)
	{
		foreach(Api\Config::read(self::APP)['jobs'] ?? [] as $job)
		{
			try {
				// stop running an errored job, as it might create a high number of entries e.g. because file can not be deleted
				if (!empty($job['error'])) continue;

				$this->checkFolder($job['directory']);

				foreach (Api\Vfs::scandir($job['directory']) as $file)
				{
					$file = $job['directory'] . '/' . $file;
					// only run on files not directories, allows also placing archive-/target-folder inside
					if (!Api\Vfs::is_dir($file) && !Api\Vfs::is_link($file))
					{
						$this->runJob($job, $file);
					}
				}
			}
			catch(\Throwable $e) {
				$job['error'] = [
					'message' => $e->getMessage(),
					'time' => new Api\DateTime(),
					'file' => $file ?? null,
				];
				self::save($job);
			}
		}
	}

	/**
	 * Run job for a file
	 *
	 * @param array $job
	 * @param string $file
	 * @return void
	 * @throws Api\Exception\WrongUserinput
	 */
	protected function runJob(array $job, string $file)
	{
		switch ($job['app'])
		{
			case 'infolog':
				$entry = array_filter($job, static function ($name) {
					return str_starts_with($name, 'info_');
				}, ARRAY_FILTER_USE_KEY)+ [
					'info_subject' => Api\Vfs::basename($file),
					'link_to' => ['to_id' => null],
				];
				if (empty($job['file_created']))
				{
					$entry['info_startdate'] = new Api\DateTime(filemtime(Api\Vfs::PREFIX.$file), new \DateTimeZone('UTC'));
				}
				break;

			default:
				throw new \Exception('Not implemented application: '.$job['app']);
		}
		// add custom-fields and optional file creation date
		$entry += array_filter($job, static function ($name) {
			return $name[0] === '#';
		}, ARRAY_FILTER_USE_KEY);
		if (!empty($job['file_created']))
		{
			$entry['#'.$job['file_created']] = (new Api\DateTime(filemtime(Api\Vfs::PREFIX.$file), new \DateTimeZone('UTC')))
				->format(Api\DateTime::ET2);
		}
		// link file with entry
		Api\Link::link($job['app'], $entry['link_to']['to_id'], Api\Link::VFS_APPNAME, Api\Vfs::PREFIX.$file);

		switch ($job['app'])
		{
			case 'infolog':
				$this->info_bo->write($entry, true, true, true, $job['no_notifications']??false, true, false, true);
				break;
		}
		// if a target_dir is specified, move the file there, otherwise delete it
		if (!empty($job['target_dir']) && (Api\Vfs::file_exists($job['target_dir']) || Api\Vfs::mkdir($job['target_dir'])))
		{
			if (!Api\Vfs::rename($file, $job['target_dir'].'/'.Api\Vfs::basename($file)))
			{
				throw new \Exception("Could NOT move $file to ".$job['target_dir']);
			}
		}
		else
		{
			if (!Api\Vfs::unlink($file))
			{
				throw new \Exception("Could NOT remove $file");
			}
		}
	}

	const ID = 'filemanager:jobs';

	/**
	 * Install the async job to run filemanager jobs
	 *
	 * @return void
	 * @throws \Exception
	 */
	protected static function installAsyncJob()
	{
		$async = new Api\Asyncservice();
		if (!$async->read(self::ID))
		{
			if (!$async->set_timer(['min' => '*/5'], self::ID, self::APP.'.'.self::class.'.asyncCallback'))
			{
				throw new \Exception(lang("Could not install async job"));
			}
		}
	}
}