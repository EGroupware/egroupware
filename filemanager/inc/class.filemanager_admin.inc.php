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
		'fsck' => true,
	);

	/**
	 * Autheticated user is setup config user
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
			//_debug_array($content);
			if ($content['sudo'])
			{
				$msg = $this->sudo($content['user'],$content['password'],self::$is_setup) ?
					lang('Root access granted.') : lang('Wrong username or password!');
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
					lang('Successful mounted %1 on %2.',$url,$path) : lang('Error mounting %1 on %2!',$url,$path);
				Vfs::$is_root = $backup;
			}
			elseif (Vfs::$is_root)
			{
				if ($content['logout'])
				{
					$msg = $this->sudo('','',self::$is_setup) ? 'Logout failed!' : lang('Root access stopped.');
					$msg_type = !Vfs::$is_root ? 'success' : 'error';
				}
				if ($content['mounts']['disable'] || self::$is_setup && $content['mounts']['umount'])
				{
					if (($unmount = $content['mounts']['umount']))
					{
						$path = @key($content['mounts']['umount']);
					}
					else
					{
						$path = @key($content['mounts']['disable']);
					}
					if (!in_array($path, self::$protected_path) && $path != '/')
					{
						$msg = Vfs::umount($path) ?
							lang('%1 successful unmounted.',$path) : lang('Error unmounting %1!',$path);
					}
					else	// re-mount / with sqlFS, to disable versioning
					{
						$msg = Vfs::mount($url=Vfs\Sqlfs\StreamWrapper::SCHEME.'://default'.$path,$path) ?
							lang('Successful mounted %1 on %2.',$url,$path) : lang('Error mounting %1 on %2!',$url,$path);
					}
				}
				if (($path = $content['mounts']['path']) &&
					($content['mounts']['enable'] || self::$is_setup && $content['mounts']['mount']))
				{
					$url = str_replace('$path',$path,$content['mounts']['url']);
					if (empty($url) && $this->versioning) $url = Versioning\StreamWrapper::PREFIX.$path;

					if ($content['mounts']['enable'] && !$this->versioning)
					{
						$msg = lang('Versioning requires <a href="http://www.egroupware.org/products">Stylite EGroupware Enterprise Line (EPL)</a>!');
						$msg_type = 'info';
					}
					elseif (!Vfs::file_exists($path) || !Vfs::is_dir($path))
					{
						$msg = lang('Path %1 not found or not a directory!',$path);
						$msg_type = 'error';
					}
					// dont allow to change mount of /apps or /templates (eg. switching on versioning)
					elseif (in_array($path, self::$protected_path))
					{
						$msg = lang('Permission denied!');
						$msg_type = 'error';
					}
					else
					{
						$msg = Vfs::mount($url,$path) ?
							lang('Successful mounted %1 on %2.',$url,$path) : lang('Error mounting %1 on %2!',$url,$path);
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
					$msg = lang('Permission denied')."\n\n".lang('You are NOT allowed to finally delete older versions and deleted files!');
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
							)+(!(int)$content['mtime'] ? array() : array(
								'mtime' => ($content['mtime']<0?'-':'+').(int)$content['mtime'],
							)), function($path) use (&$deleted, &$errors)
							{
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
						$time = number_format(microtime(true)-$starttime, 1);
						$msg = ($errors ? lang('%1 errors deleting!', $errors)."\n\n" : '').
							lang('%1 files or directories deleted in %2 seconds.', $deleted, $time);
						$msg_type = $errors ? 'error' : 'info';
					}
					Vfs::$is_root = false;
				}
			}
		}
		else
		{
			// defaults for deleting of older versions
			$content['versionedpath'] = '/';
			$content['mtime'] = 100;
		}
		if (true) $content = array(
			'versionedpath' => $content['versionedpath'],
			'mtime' => $content['mtime'],
		);
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
		$content['mounts'] = array();
		foreach(Vfs::mount() as $path => $url)
		{
			$content['mounts'][$n++] = array(
				'path' => $path,
				'url'  => $url,
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

		if (!($msgs = Vfs\Sqlfs\Utils::fsck($check_only)))
		{
			$msgs = lang('Filesystem check reported no problems.');
		}
		$content = '<p>'.implode("</p>\n<p>", (array)$msgs)."</p>\n";

		$content .= Api\Html::form('<p>'.($check_only&&is_array($msgs) ?
			Api\Html::submit_button('fix', lang('Fix reported problems')) : '').
			Api\Html::submit_button('cancel', lang('Cancel')).'</p>',
			'','/index.php',array('menuaction'=>'filemanager.filemanager_admin.fsck'));

		$GLOBALS['egw']->framework->render($content, lang('Admin').' - '.lang('Check virtual filesystem'), true);
	}
}