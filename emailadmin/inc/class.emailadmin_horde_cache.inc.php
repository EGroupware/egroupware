<?php
/**
 * EGroupware EMailAdmin: Horde_Cache compatible class using egw_cache
 *
 * @link http://www.stylite.de
 * @package emailadmin
 * @author Ralf Becker <rb-AT-stylite.de>
 * @copyright (c) 2013 by Ralf Becker <rb-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Horde_Cache compatible class using egw_cache
 */
class emailadmin_horde_cache
{
	/**
	 * App to use
	 */
	const APP = 'mail';
	/**
	 * How to cache: instance-specific
	 */
	const LEVEL = egw_cache::INSTANCE;

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
		$ret = egw_cache::getCache(self::LEVEL, 'mail', $key);
		
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
		egw_cache::setCache(self::LEVEL, 'mail', $key, $data, $lifetime);
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
		return !is_null(egw_cache::getCache(self::LEVEL, 'mail', $key));
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
		egw_cache::unsetCache(self::LEVEL, 'mail', $key);
	}

    /**
     * Clears all data from the cache.
     *
     * @throws Horde_Cache_Exception
     */
    public function clear()
	{
		egw_cache::flush(self::LEVEL, self::APP);
	}
}
