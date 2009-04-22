<?php
/**
 * eGroupWare API - memcache session handler
 *
 * Fixes a problem of the buildin session handler of the memcache pecl extension,
 * which can NOT work with sessions > 1MB. This handler splits the session-data
 * in 1MB chunk, so memcache can handle them. For the first chunk we use an identical
 * key (just the session-id) as the original memcache session handler. For the further
 * chunks we add -2, -3, ... so other code (eg. the SyncML code from Horde) can
 * open the session, if it's size is < 1MB.
 *
 * To enable it, you need to set session.save_handler to 'memcache',
 * session.save_path to 'tcp://host:port[,tcp://host2:port,...]',
 * as you have to do it with the original handler PLUS adding the following
 * to your header.inc.php:
 *
 * $GLOBALS['egw_info']['server']['session_handler'] = 'egw_session_memcache';
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @subpackage session
 * @copyright (c) 2007-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

// needed for check_load_extension (session-handler gets included before regular include via the header.inc.php)
require_once(EGW_API_INC.'/common_functions.inc.php');

/**
 * File based php sessions or all other build in handlers configures via session_module_name() or php.ini: session.save_handler
 *
 * Contains static methods to list or count 'files' sessions (does not work under Debian, were session.save_path is not searchable!)
 */
class egw_session_memcache
{
	/**
	 * Debug level: 0 = none, 1 = some, 2 = all
	 */
	const DEBUG = 0;
	/**
	 * Instance of Memcache
	 *
	 * @var Memcache
	 */
	private static $memcache;

	/**
	 * are the string functions overloaded by their mbstring variants
	 *
	 * @var boolean
	 */
	private static $mbstring_func_overload;

	/**
	 * Initialise the session-handler (session_set_save_handler()), if necessary
	 */
	public static function init_session_handler()
	{
		self::$mbstring_func_overload = @extension_loaded('mbstring') && (ini_get('mbstring.func_overload') & 2);

		// session needs to be closed before objects get destroyed, as this session-handler is an object ;-)
		register_shutdown_function('session_write_close');
	}

	/**
	 * Open session
	 *
	 * @param string $save_path
	 * @param string $session_name
	 * @return boolean
	 */
	public static function open($save_path, $session_name)
	{
		check_load_extension('memcache',true);	// true = throw exception if not loadable

		self::$memcache = new Memcache;
		foreach(explode(',',$save_path) as $path)
		{
			$parts = parse_url($path);
			self::$memcache->addServer($parts['host'],$parts['port']);	// todo parse query
		}
		return true;
	}

	/**
	 * Close session
	 *
	 * @return boolean
	 */
	public static function close()
	{
		return is_object(self::$memcache) ? self::$memcache->close() : false;
	}

	/**
	 * Size of a single memcache junk
	 *
	 * 1024*1024 is too big, maybe some account-info needs to be added
	 */
	const MEMCACHED_MAX_JUNK = 1024000;

	/**
	 * Read session data
	 *
	 * According to a commentary on php.net (session_set_save_handler) this function has to return
	 * a string, if the session-id is NOT found, not false. Returning false terminates the script
	 * with a fatal error!
	 *
	 * @param string $id
	 * @return string|boolean false on error, '' for not found session otherwise session data
	 */
	public static function read($id)
	{
		if (self::DEBUG > 0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." READ start $id:");

		if (!self::_acquire_and_wait($id)) return false;

		for($data='',$n=0; ($read = self::$memcache->get($id.($n?'-'.$n:''))); ++$n)
		{
			if (self::DEBUG > 0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." read $id:$n:".print_r(self::_bytes($read),true));
			$data .= $read;
		}
		self::_release($id);

		return $data;
	}

	/**
	 * Write session data
	 *
	 * @param string $id
	 * @param string $sess_data
	 * @return boolean
	 */
	public static function write($id, $sess_data)
	{
		$lifetime = (int)ini_get('session.gc_maxlifetime');

		if ($id == 'no-session')
		{
			return true;	// no need to save
		}
		// give anon sessions only a lifetime of 10min
		if (is_object($GLOBALS['egw']->session) && $GLOBALS['egw']->session->session_flags == 'A' ||
			$GLOBALS['egw_info']['flags']['currentapp'] == 'groupdav')
		{
			$lifetime = 600;
		}
		if (self::DEBUG > 0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." WRITE start $id:");

		if (!self::_acquire_and_wait($id)) return false;

		for($n=$i=0,$len=self::_bytes($sess_data); $i < $len; $i += self::MEMCACHED_MAX_JUNK,++$n)
		{
			if (self::DEBUG > 1) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." in :$n write $id:$i:".print_r(self::_bytes($sess_data),true));

			if (!self::$memcache->set($id.($n?'-'.$n:''),self::_cut_bytes($sess_data,$i,self::MEMCACHED_MAX_JUNK),0,$lifetime)) {
				self::_release($id);
				return false;
			}
		}
		if (self::DEBUG > 0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." DELETE :$n");
		for($n=$n; self::$memcache->delete($id.($n?'-'.$n:'')); ++$n) ;

		self::_release($id);

		return true;
	}

	/**
	 * semaphore to gard against conflicting writes, destroying the session-data
	 *
	 * @param string $id
	 * @return boolean
	 */
	private static function _acquire_and_wait($id)
	{
		if (self::DEBUG > 0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." ACQUIRE :$id");

		$i=0;
		// Acquire lock for 3 seconds, after that i should have done my job
		while(!self::$memcache->add($id.'-lock',1,0,3))
		{
			if (self::DEBUG > 0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." ACQUIRE Lock Loop :$id:$i");
			usleep(100000);
			$i++;
			if ($i > 40)
			{
				if (self::DEBUG > 1) error_log("\n memcache ".print_r(getenv('HOSTNAME'),true).$_SERVER["REQUEST_TIME"]." blocked :$id");
				// Could not acquire lock after 3 seconds, Continue, and pretend the locking process get stuck
				// return false;A
				break;
			}
		}
		if($i > 1)
		{
			if (self::DEBUG > 0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." ACQUIRE LOOP $i :$id");
		}
		if (self::DEBUG > 0) error_log("\n memcache ".print_r(getenv('HOSTNAME'),true).$_SERVER["REQUEST_TIME"]." Lock ACQUIRED $i:$id");

		return true;
	}

	/**
	 * Release semaphore
	 *
	 * @param string $id
	 * @return boolean
	 */
	private static function _release($id)
	{
		if (self::DEBUG > 0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." RELEASE :$id");

		return self::$memcache->delete($id.'-lock');
	}

	/**
	 * mbstring.func_overload safe strlen
	 *
	 * @param string $data
	 * @return int
	 */
	private static function _bytes(&$data)
	{
		return self::$mbstring_func_overload ? mb_strlen($data,'ascii') : strlen($data);
	}

	/**
	 * mbstring.func_overload safe substr
	 *
	 * @param string $data
	 * @param int $offset
	 * @param int $len
	 * @return string
	 */
	private static function _cut_bytes(&$data,$offset,$len=null)
	{
		if (self::DEBUG > 1) error_log("\n memcache in cutbyte mb $id:$n:".print_r(mb_substr($data,$offset,$len,'ascii'),true));
		if (self::DEBUG > 1) error_log("\n memcache in cutbyte norm $id:$n:".print_r(substr($data,$offset,$len),true));

		return self::$mbstring_func_overload ? mb_substr($data,$offset,$len,'ascii') : substr($data,$offset,$len);
	}

	/**
	 * Destroy a session
	 *
	 * @param string $id
	 * @return boolean
	 */
	public static function destroy($id)
	{
		if (!self::_acquire_and_wait($id)) return false;

		for($n=0; self::$memcache->delete($id.($n?'-'.$n:'')); ++$n)
		{
			if (self::DEBUG > 0) error_log("******* memcache destroy  $id:$n:");
		}
		self::_release($id);

		return $n > 0;
	}

	/**
	 * Run garbade collection
	 *
	 * @param int $maxlifetime
	 */
	public static function gc($maxlifetime)
	{
		// done by memcached itself
	}
}

function init_session_handler()
{
	$ses = 'egw_session_memcache';
	$ret = session_set_save_handler(
		array($ses,'open'),
		array($ses,'close'),
		array($ses,'read'),
		array($ses,'write'),
		array($ses,'destroy'),
		array($ses,'gc'));
	if (!$ret) error_log(__METHOD__.'() session_set_save_handler(...)='.(int)$ret.', session_module_name()='.session_module_name().' *******************************');
}
init_session_handler();
