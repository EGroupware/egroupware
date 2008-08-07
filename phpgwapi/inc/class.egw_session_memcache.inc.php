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
 * To enable it, you need to set session.save_handler to 'memcache' and
 * session.save_path to 'tcp://host:port[,tcp://host2:port,...]',
 * as you have to do it with the original handler.
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @subpackage session
 * @copyright (c) 2007-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

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

		session_set_save_handler(
			array(__CLASS__,'open'),
			array(__CLASS__,'close'),
			array(__CLASS__,'read'),
			array(__CLASS__,'write'),
			array(__CLASS__,'destroy'),
			array(__CLASS__,'gc'));

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
		self::$memchache = new Memcache;
		foreach(explode(',',ini_get('session.save_path')) as $path)
		{
			$parts = parse_url($path);
			self::$memchache->addServer($parts['host'],$parts['port']);	// todo parse query
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
		return is_object(self::$memchache) ? self::$memchache->close() : false;
	}

	/**
	 * Size of a single memcache junk
	 *
	 * 1024*1024 is too big, maybe some account-info needs to be added
	 */
	const MEMCACHED_MAX_JUNK = 1024000;

	/**
	 * Enter description here...
	 *
	 * @param string $id
	 * @return string|boolean
	 */
	public static function read($id)
	{
		if (self::DEBUG > 0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." READ start $id:");

		if (!self::_acquire_and_wait($id)) return false;

		for($data=false,$n=0; ($read = self::$memchache->get($id.($n?'-'.$n:''))); ++$n)
		{
			if (self::DEBUG > 0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." read $id:$n:".print_r(_bytes($read),true));
			$data .= $read;
		}
		self::_release($id);

		return $data;
	}

	/**
	 * Write session
	 *
	 * @param string $id
	 * @param string $sess_data
	 * @return boolean
	 */
	public static function write($id, $sess_data)
	{
		$lifetime = (int)ini_get('session.gc_maxlifetime');

		// give anon sessions only a lifetime of 10min
		if (is_object($GLOBALS['egw']->session) && $GLOBALS['egw']->session->session_flags == 'A')
		{
			$lifetime = 600;
		}
		if (self::DEBUG > 0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." WRITE start $id:");

		if (!self::_acquire_and_wait($id)) return false;

		for($n=$i=0,$len=_bytes($sess_data); $i < $len; $i += self::MEMCACHED_MAX_JUNK,++$n)
		{
			if (self::DEBUG > 1) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." in :$n write $id:$i:".print_r(_bytes($sess_data),true));

			if (!self::$memchache->set($id.($n?'-'.$n:''),self::_cut_bytes($sess_data,$i,self::MEMCACHED_MAX_JUNK),0,$lifetime)) {
				self::_release($id);
				return false;
			}
		}
		if (self::DEBUG > 0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." DELETE :$n");
		for($n=$n; self::$memchache->delete($id.($n?'-'.$n:'')); ++$n) ;

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
		while(!self::$memchache->add($id.'-lock',1,0,3))
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

		return self::$memchache->delete($id.'-lock');
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

		error_log("\n memcache destroy  $id:$n:");
		for($n=0; self::$memchache->delete($id.($n?'-'.$n:'')); ++$n) ;

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
