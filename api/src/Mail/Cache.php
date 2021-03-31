<?php
/**
 * EGroupware Api: Horde_Cache compatible class using Api\Cache
 *
 * @link http://www.stylite.de
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb-AT-stylite.de>
 * @copyright (c) 2013-16 by Ralf Becker <rb-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Mail;

use EGroupware\Api;

/**
 * Horde_Cache compatible class using egw_cache
 */
class Cache
{
	/**
	 * App to use
	 */
	const APP = 'mail';
	/**
	 * How to cache: instance-specific
	 */
	const LEVEL = Api\Cache::INSTANCE;

    /**
     * Retrieve cached data.
     *
     * @param string $key        Object ID to query.
     * @param integer $lifetime  Lifetime of the object in seconds.
     *
     * @return mixed  Cached data, or false if none was found.
     */
    public function get($key, $lifetime = 0)
	{
		unset($lifetime);	// not (yet) used, but required by function signature

		$ret = Api\Cache::getCache(self::LEVEL, 'mail', $key);

		return !is_null($ret) ? $ret : false;
	}

    /**
     * Store an object in the cache.
     *
     * @param string $key        Object ID used as the caching key.
     * @param mixed $data        Data to store in the cache.
     * @param integer $lifetime  Object lifetime - i.e. the time before the
     *                           data becomes available for garbage
     *                           collection. If 0 will not be GC'd.
     */
    public function set($key, $data, $lifetime = 0)
	{
		Api\Cache::setCache(self::LEVEL, 'mail', $key, $data, $lifetime);
	}

    /**
     * Checks if a given key exists in the cache, valid for the given
     * lifetime.
     *
     * @param string $key        Cache key to check.
     * @param integer $lifetime  Lifetime of the key in seconds.
     *
     * @return boolean  Existence.
     */
    public function exists($key, $lifetime = 0)
	{
		unset($lifetime);	// not (yet) used, but required by function signature

		return !is_null(Api\Cache::getCache(self::LEVEL, 'mail', $key));
	}

    /**
     * Expire any existing data for the given key.
     *
     * @param string $key  Cache key to expire.
     *
     * @return boolean  Success or failure.
     */
    public function expire($key)
	{
		Api\Cache::unsetCache(self::LEVEL, 'mail', $key);
	}

    /**
     * Clears all data from the cache.
     */
    public function clear()
	{
		Api\Cache::flush(self::LEVEL, self::APP);
	}
}
