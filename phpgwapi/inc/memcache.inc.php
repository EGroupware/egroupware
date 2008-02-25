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
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

define('DEBUG',0);
define('DEBUGALL',0);

function egw_memcache_open($save_path, $session_name)
{
	global $egw_memcache_obj;
	$egw_memcache_obj = new Memcache;
	foreach(explode(',',ini_get('session.save_path')) as $path)
	{
		$parts = parse_url($path);
		$egw_memcache_obj->addServer($parts['host'],$parts['port']);	// todo parse query
	}
	return(true);
}

function egw_memcache_close()
{
	global $egw_memcache_obj;
	
	return is_object($egw_memcache_obj) ? $egw_memcache_obj->close() : false;
}

define('MEMCACHED_MAX_JUNK',1024*1000);	// 1024*1024 is too big, maybe some account-info needs to be added

function egw_memcache_read($id)
{
	global $egw_memcache_obj;

	if (DEBUG > 0 && DEBUGALL>0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." READ start $id:");
	
	if (!_acquire_and_wait($id)) return false;
	
	for($data=false,$n=0; ($read = $egw_memcache_obj->get($id.($n?'-'.$n:''))); ++$n)
	{
		if (DEBUG > 0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." read $id:$n:".print_r(_bytes($read),true));
		$data .= $read;
	}
	_release($id);
	return $data;
}

function egw_memcache_write($id, $sess_data)
{
	global $egw_memcache_obj;
	
	$lifetime = (int)ini_get('session.gc_maxlifetime');
	// give anon sessions only a lifetime of 10min
	if (is_object($GLOBALS['egw']->session) && $GLOBALS['egw']->session->session_flags == 'A')
	{
		$lifetime = 600;
	}
	if (DEBUG > 0 && DEBUGALL>0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." WRITE start $id:");
	
	if (!_acquire_and_wait($id)) return false;
	
	for($n=$i=0,$len=_bytes($sess_data); $i < $len; $i += MEMCACHED_MAX_JUNK,++$n)
	{
		if (DEBUG > 0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." in :$n write $id:$i:".print_r(_bytes($sess_data),true));
		
		if (!$egw_memcache_obj->set($id.($n?'-'.$n:''),_cut_bytes($sess_data,$i,MEMCACHED_MAX_JUNK),0,$lifetime)) {
			_release($id);
			return false;
		}
	}
	if (DEBUG > 0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." DELETE :$n");
	for($n=$n; $egw_memcache_obj->delete($id.($n?'-'.$n:'')); ++$n) ;
	
	_release($id);
	return true;
}

function _acquire_and_wait($id)
{
	global $egw_memcache_obj;

	if (DEBUG > 0 && DEBUGALL>0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." ACQUIRE :$id");
	
	$i=0;
	// Acquire lock for 3 seconds, after that i should have done my job
	
	while(!$egw_memcache_obj->add($id.'-lock',1,0,3)) {
		if (DEBUG > 0 && DEBUGALL>0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." ACQUIRE Lock Loop :$id:$i");
		usleep(100000);
		$i++;
		if ($i > 40) { 
			if (DEBUG > 0) error_log("\n memcache ".print_r(getenv('HOSTNAME'),true).$_SERVER["REQUEST_TIME"]." blocked :$id");
			// Could not acquire lock after 3 seconds, Continue, and pretend the locking process get stuck
			// return false;A
			break;
		}
	}
	if($i>1) {
		if (DEBUG > 0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." ACQUIRE LOOP $i :$id");
	}
	if (DEBUG > 0) error_log("\n memcache ".print_r(getenv('HOSTNAME'),true).$_SERVER["REQUEST_TIME"]." Lock ACQUIRED $i:$id");
	return true;
}

function _release($id)
{
	global $egw_memcache_obj;

	if (DEBUG > 0) error_log("\n memcache ".$_SERVER["REQUEST_TIME"]." RELEASE :$id");
	return $egw_memcache_obj->delete($id.'-lock');
}

function _test_mbstring_func_overload()
{
	return @extension_loaded('mbstring') && (ini_get('mbstring.func_overload') & 2);
}

function _bytes(&$data)
{
	global $mbstring_func_overload;
	
	if (is_null($mbstring_func_overload)) $mbstring_func_overload=_test_mbstring_func_overload();
	
	return $mbstring_func_overload ? mb_strlen($data,'ascii') : strlen($data);
}

function _cut_bytes(&$data,$offset,$len=null)
{
	global $mbstring_func_overload;
	
	if (is_null($mbstring_func_overload)) $mbstring_func_overload=_test_mbstring_func_overload();
	if (DEBUG > 0 && DEBUGALL>0) error_log("\n memcache in cutbyte mb $id:$n:".print_r(mb_substr($data,$offset,$len,'ascii'),true));
	if (DEBUG > 0 && DEBUGALL>0) error_log("\n memcache in cutbyte norm $id:$n:".print_r(substr($data,$offset,$len),true));
	
	return $mbstring_func_overload ? mb_substr($data,$offset,$len,'ascii') : substr($data,$offset,$len);
}

function egw_memcache_destroy($id)
{
	global $egw_memcache_obj;
	
	if (!_acquire_and_wait($id)) return false;

	error_log("\n memcache destroy  $id:$n:");
	for($n=0; $egw_memcache_obj->delete($id.($n?'-'.$n:'')); ++$n) ;
	
	_release($id);
	return $n > 0;
}

function egw_memcache_gc($maxlifetime)
{
}

session_set_save_handler("egw_memcache_open", "egw_memcache_close", "egw_memcache_read", "egw_memcache_write", "egw_memcache_destroy", "egw_memcache_gc");
