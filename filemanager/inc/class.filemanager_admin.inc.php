<?php
/**
 * EGroupware Filemanager: mounting GUI
 *
 * @link http://www.egroupware.org/
 * @package filemanager
 * @author Ralf Becker <rb-AT-stylite.de>
 * @copyright (c) 2010-16 by Ralf Becker <rb-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Etemplate;
use EGroupware\Stylite\Vfs\Versioning;
use EGroupware\Api\Vfs;

/**
 * Filemanager: mounting GUI
 */
class filemanager_admin extends filemanager_ui
{
	/**
	 * Functions callable via menuaction
	 *
	 * @var array
	 */
	public $public_functions = array(
		'index' => true,
		'fsck'  => true,
		'quota' => true,
	);

	/**
	 * Authenticated user is setup config user
	 *
	 * @var boolean
	 */
	static protected $is_setup = false;

	/**
	 * Do we have versioning (Versioning\StreamWrapper class) available and with which schema
	 *
	 * @var string
	 */
	protected $versioning;

	/**
	 * Do not allow to (un)mount these
	 *
	 * @var array
	 */
	protected static $protected_path = array('/apps', '/templates');

	/**
	 * Constructor
	 */
	function __construct()
	{
		// make sure user has admin rights
		if (!isset($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			throw new Api\Exception\NoPermission\Admin();
		}
		// sudo handling
		parent::__construct();
		self::$is_setup = Api\Cache::getSession('filemanager', 'is_setup');

		if (class_exists('EGroupware\Stylite\Vfs\Versioning\StreamWrapper'))
		{
			$this->versioning = Versioning\StreamWrapper::SCHEME;
		}
		Api\Translation::add_app('filemanager');
	}

	/**
	 * Mount GUI
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	public function index(array $content=null, $msg='', $msg_type=null)
	{
		if (is_array($content))
		{
			try
			{
				if ($content['sudo'])
				{
					$this->sudo($content['user'], $content['password'], $msg, true, self::$is_setup);
					$msg_type = Vfs::$is_root ? 'success' : 'error';
				}
				elseif ($content['etemplates'] && $GLOBALS['egw_info']['user']['apps']['admin'])
				{
					$path = '/etemplates';
					$url = 'stylite.merge://default/etemplates?merge=.&lang=0&level=1&extension=xet&url=egw';
					$backup = Vfs::$is_root;
					Vfs::$is_root = true;
					Vfs::mkdir($path);
					Vfs::chgrp($path, 'Admins');
					Vfs::chmod($path, 075);
					$msg = Vfs::mount($url, $path) ?
						lang('Successful mounted %1 on %2.', $url, $path) : lang('Error mounting %1 on %2!', $url, $path);
					Vfs::$is_root = $backup;
				}
				elseif (Vfs::$is_root)
				{
					if ($content['logout'])
					{
						$this->sudo('', '', $msg);
						$msg_type = 'success';
					}
					if ($content['mounts']['disable'] || Vfs::$is_root && $content['mounts']['umount'])
					{
						if (!empty($content['mounts']['umount']))
						{
							$path = @key($content['mounts']['umount']);
						}
						else
						{
							$path = @key($content['mounts']['disable']);
						}
						// set unmounted url for a (changed) remount
						$mounts = Vfs::mount();
						$content['mounts']['path'] = $path;
						$content['mounts']['url'] = Vfs::parse_url($mounts[$path]);
						if (!empty($content['mounts']['url']['query']))
						{
							$content['mounts']['url']['path'] .= '?'.$content['mounts']['url']['query'];
						}
						if (!in_array($path, self::$protected_path) && $path != '/')
						{
							$msg = Vfs::umount($path) ?
								lang('%1 successful unmounted.', $path) : lang('Error unmounting %1!', $path);
						}
						else    // re-mount / with sqlFS, to disable versioning
						{
							$msg = Vfs::mount($url = Vfs\Sqlfs\StreamWrapper::SCHEME . '://default' . $path, $path) ?
								lang('Successful mounted %1 on %2.', $url, $path) : lang('Error mounting %1 on %2!', $url, $path);
						}
					}
					if (($path = $content['mounts']['path']) &&
						($content['mounts']['enable'] || Vfs::$is_root && $content['mounts']['mount']))
					{
						if (empty($content['mounts']['url']['path']) && $this->versioning)
						{
							$content['mounts']['url'] = [
								'scheme' => Versioning\StreamWrapper::SCHEME,
								'path' => $path,
							];
						}
						if (empty($content['mounts']['url']['scheme']) || $content['mounts']['url']['scheme'] === 'filesystem' && !self::$is_setup)
						{
							throw new Api\Exception\NoPermission();
						}
						$url = $content['mounts']['url']['scheme'] . '://';
						if (in_array($content['mounts']['url']['scheme'], ['smb', 'vfs']) || !empty(trim($content['mounts']['url']['user'])))
						{
							if (in_array($content['mounts']['url']['scheme'], ['smb', 'vfs']) && empty(trim($content['mounts']['url']['user'])))
							{
								throw new Api\Exception\WrongUserinput(lang('SMB, WebDAVs and VFS require a username!'));
							}
							$url .= str_replace(urlencode('$user'), '$user', urlencode(trim($content['mounts']['url']['user'])));
							if (!empty($content['mounts']['url']['pass']))
							{
								$url .= ':' . ($content['mounts']['url']['pass'] === '$pass' ? '$pass' : urlencode(trim($content['mounts']['url']['pass'])));
							}
							$url .= '@';
						}
						$url .= $content['mounts']['url']['host'] ?: 'default';
						$url .= $content['mounts']['url']['path'] ?: $path;

						// WebDAV needs a trailing slash and while EGroupware redirects, NextCloud e.g. gives an error
						if (preg_match('#^webdavs?://#', $url) && !substr($url, -1) !== '/')
						{
							$url .= '/';
						}

						if (($content['mounts']['enable'] || substr($content['mounts']['url']['scheme'], 0, 8) === 'stylite.') && !$this->versioning)
						{
							throw new Api\Exception\WrongUserinput(lang('Versioning requires EGroupware EPL'));
						}
						elseif (Vfs::file_exists($path) && !Vfs::is_dir($path))
						{
							throw new Api\Exception\WrongUserinput(lang('Path %1 not found or not a directory!', $path));
						}
						// don't allow changing mount of /apps or /templates (eg. switching on versioning)
						elseif (in_array($path, self::$protected_path))
						{
							throw new Api\Exception\NoPermission();
						}
						else
						{
							$msg = Vfs::mount($url, $path, true) ?
								lang('Successful mounted %1 on %2.', str_replace('%5C', '\\', $url), $path) :
								lang('Error mounting %1 on %2!', str_replace('%5C', '\\', $url), $path);
						}
					}
					if ($content['allow_delete_versions'] != $GLOBALS['egw_info']['server']['allow_delete_versions'])
					{
						Api\Config::save_value('allow_delete_versions', $content['allow_delete_versions'], 'phpgwapi');
						$GLOBALS['egw_info']['server']['allow_delete_versions'] = $content['allow_delete_versions'];
						$msg = lang('Configuration changed.');
					}
				}
				// delete old versions and deleted files
				if ($content['delete-versions'])
				{
					if (!Versioning\StreamWrapper::check_delete_version(null))
					{
						$msg = lang('Permission denied') . "\n\n" . lang('You are NOT allowed to finally delete older versions and deleted files!');
						$msg_type = 'error';
					}
					else
					{
						// we need to be root to delete files independent of permissions and ownership
						Vfs::$is_root = true;
						if (!Vfs::file_exists($content['versionedpath']) || !Vfs::is_dir($content['versionedpath']))
						{
							$msg = lang('Directory "%1" NOT found!', $content['versionedpath']);
							$msg_type = 'error';
						}
						else
						{
							@set_time_limit(0);
							$starttime = microtime(true);
							$deleted = $errors = 0;

							// shortcut to efficently delete every old version and deleted file
							if ($content['versionedpath'] == '/')
							{
								$deleted = Versioning\StreamWrapper::purge_all_versioning($content['mtime']);
							}
							else
							{
								Vfs::find($content['versionedpath'], array(
										'show-deleted' => true,
										'hidden' => true,
										'depth' => true,
										'path_preg' => '#/\.(attic|versions)/#',
									) + (!(int)$content['mtime'] ? array() : array(
										'mtime' => ($content['mtime'] < 0 ? '-' : '+') . (int)$content['mtime'],
									)), function ($path) use (&$deleted, &$errors) {
									if (($is_dir = Vfs::is_dir($path)) && Vfs::rmdir($path) ||
										!$is_dir && Vfs::unlink($path))
									{
										++$deleted;
									}
									else
									{
										++$errors;
									}
								});
							}
							$time = number_format(microtime(true) - $starttime, 1);
							$msg = ($errors ? lang('%1 errors deleting!', $errors) . "\n\n" : '') .
								lang('%1 files or directories deleted in %2 seconds.', $deleted, $time);
							$msg_type = $errors ? 'error' : 'info';
						}
						Vfs::$is_root = false;
					}
				}
			}
			catch (\Exception $e) {
				$msg = $e->getMessage();
				$msg_type = 'error';
			}
		}
		else
		{
			// defaults for deleting of older versions
			$content['versionedpath'] = '/';
			$content['mtime'] = 100;
		}
		$content = [
			'versionedpath' => $content['versionedpath'],
			'mtime' => $content['mtime'],
			'mounts' => $content['mounts'],
		];
		if ($this->versioning)
		{
			// statistical information
			$content += Versioning\StreamWrapper::summary();
			if ($content['total_files']) $content['percent_files'] = number_format(100.0*$content['version_files']/$content['total_files'],1).'%';
			if ($content['total_size']) $content['percent_size'] = number_format(100.0*$content['version_size']/$content['total_size'],1).'%';
		}
		if (!($content['is_root']=Vfs::$is_root))
		{
			if (empty($msg))
			{
				$msg = lang('You need to become root, to enable or disable versioning on a directory!');
				$msg_type = 'info';
			}
			$readonlys['logout'] = $readonlys['enable'] = $readonlys['allow_delete_versions'] = true;
		}
		$content['is_setup'] = self::$is_setup;
		$content['versioning'] = $this->versioning;
		$content['allow_delete_versions'] = $GLOBALS['egw_info']['server']['allow_delete_versions'];
		Framework::message($msg, $msg_type);

		$n = 2;
		$content['mounts']['at'] = '@';
		foreach(Vfs::mount() as $path => $url)
		{
			$content['mounts'][$n++] = array(
				'path' => $path,
				'url'  => preg_replace('#://([^:@/]+):((?!\$pass)[^@/]+)@#', '://$1:****@',
					str_replace('%5c', '\\', $url)),
			);
			$readonlys["disable[$path]"] = !$this->versioning || !Vfs::$is_root ||
				Vfs::parse_url($url,PHP_URL_SCHEME) != $this->versioning;
		}
		$readonlys['umount[/]'] = $readonlys['umount[/apps]'] = true;	// do not allow to unmount / or /apps
		$readonlys['url'] = !self::$is_setup;

		$sel_options['allow_delete_versions'] = array(
			'root'     => lang('Superuser (root)'),
			'admins'   => lang('Administrators'),
			'everyone' => lang('Everyone'),
		);
		$sel_options['scheme'] = [
			"webdavs" => "WebDAVs",
			"smb" => "SMB",
			"filesystem" => lang("Filesystem"),
			"sqlfs" => "SQLfs",
			"links" => lang("Links"),
			"stylite.versioning" => lang("Versioning"),
			"stylite.links" => lang("Links").'+'.lang("Versioning"),
			"vfs" => "VFS",
		];
		foreach($sel_options['scheme'] as $scheme => &$label)
		{
			$label = ['label' => $label, 'title' => $scheme.'://...'];
			if (!Vfs::load_wrapper($scheme) || !self::$is_setup && $scheme === 'filesystem')
			{
				unset($sel_options['scheme'][$scheme]);
			}
		}
		// show [Mount /etemplates] button for admin, if not already mounted and available
		$readonlys['etemplates'] = !class_exists('\EGroupware\Stylite\Vfs\Merge\StreamWrapper') ||
			($fs_tab=Vfs::mount($url)) && isset($fs_tab['/etemplates']) ||
			!isset($GLOBALS['egw_info']['user']['apps']['admin']);
		//_debug_array($content);

		$tpl = new Etemplate('filemanager.admin');
		$GLOBALS['egw_info']['flags']['app_header'] = lang('VFS mounts and versioning');
		$tpl->exec('filemanager.filemanager_admin.index',$content,$sel_options,$readonlys);
	}

	/**
	 * Run fsck on sqlfs
	 */
	function fsck()
	{
		if ($_POST['cancel'])
		{
			Framework::redirect_link('/admin/index.php', null, 'admin');
		}
		$check_only = !isset($_POST['fix']);

		if(!($msgs = Vfs\Sqlfs\Utils::fsck($check_only)))
		{
			$msgs = lang('Filesystem check reported no problems.');
		}
		$content = '<p>' . implode("</p>\n<p>", (array)$msgs) . "</p>\n";

		$content .= Api\Html::form('<p>' . ($check_only && is_array($msgs) ?
									   Api\Html::submit_button('fix', lang('Fix reported problems')) : '') .
								   Api\Html::submit_button('cancel', lang('Cancel')) . '</p>',
								   '', '/index.php', array('menuaction' => 'filemanager.filemanager_admin.fsck')
		);

		$GLOBALS['egw']->framework->render($content, lang('Admin') . ' - ' . lang('Check virtual filesystem'), true);
	}

	/**
	 * Admin tasks related to quota
	 *
	 * Manually trigger a directory size recalculation
	 *
	 * @param array $content
	 * @return void
	 * @throws Api\Exception\AssertionFailed
	 */
	public function quota(array $content = null)
	{
		if(is_array($content))
		{
			$button = key($content['button']);
			unset($content['button']);
			switch($button)
			{
				case  'recalculate':
					Framework::message($this->quotaRecalc());
					break;
				case 'save':
				case 'apply':


					if($button == 'apply')
					{
						break;
					}
				// fall-through for save
				case 'cancel':

					// Reload tracker app
					if(Api\Json\Response::isJSONResponse())
					{
						Api\Json\Response::get()->apply('app.admin.load');
					}
					Framework::redirect_link('/index.php', array(
						'menuaction' => 'admin.admin_ui.index',
						'ajax'       => 'true'
					),                       'admin');
					break;
			}
		}

		$content = $content ?: [];
		if($button == 'recalculate')
		{
			$content['check_oversize'] = true;
		}
		
		$tpl = new Etemplate('filemanager.quota');
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Quota');
		$tpl->exec('filemanager.filemanager_admin.quota', $content, $sel_options, $readonlys);
	}

	/**
	 * Recalculate directory sizes
	 */
	function quotaRecalc()
	{
		list($dirs, $iterations, $time) = Vfs\Sqlfs\Utils::quotaRecalc();

		return lang("Recalculated %1 directories in %2 iterations and %3 seconds", $dirs, $iterations, number_format($time, 1)) . "\n";
	}
}