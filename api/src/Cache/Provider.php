<?php
/**
 * EGroupware API: Caching provider interface
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage cache
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2009-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\Cache;

/**
 * Interface for a caching provider for tree and instance level
 *
 * The provider can eg. create subdirs under /tmp for each key
 * to store data as a file or concat them with a separator to
 * get a single string key to eg. store data in memcached
 */
interface Provider
{
	/**
	 * Constructor, eg. opens the connection to the backend
	 *
	 * @throws Exception if connection to backend could not be established
	 * @param array $params eg. array(host,port) or array(directory) depending on the provider
	 */
	function __construct(array $params);

	/**
	 * Stores some data in the cache, if it does NOT already exists there
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @param mixed $data
	 * @param int $expiration =0
	 * @return boolean true on success, false on error, incl. key already exists in cache
	 */
	function add(array $keys,$data,$expiration=0);

	/**
	 * Stores some data in the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @param mixed $data
	 * @param int $expiration =0
	 * @return boolean true on success, false on error
	 */
	function set(array $keys,$data,$expiration=0);

	/**
	 * Get some data from the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @return mixed data stored or NULL if not found in cache
	 */
	function get(array $keys);

	/**
	 * Increments value in cache
	 *
	 * @param array $keys
	 * @param int $offset =1 how much to increment by
	 * @param int $intial_value =0 value to use if not in cache
	 * @param int $expiration =0
	 * @return false|int new value on success, false on error
	 */
	function increment(array $keys, int $offset=1, int $intial_value=0, int $expiration=0);

	/**
	 * Decrements value in cache, but never below 0
	 *
	 * If new value would be below 0, 0 will be set as new value!
	 *
	 * @param array $keys
	 * @param int $offset =1 how much to increment by
	 * @param int $intial_value =0 value to use if not in cache
	 * @param int $expiration =0
	 * @return false|int new value on success, false on error
	 */
	function decrement(array $keys, int $offset=1, int $intial_value=0, int $expiration=0);

	/**
	 * Delete some data from the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @return boolean true on success, false on error (eg. $key not set)
	 */
	function delete(array $keys);

	/**
	 * Delete all data under given keys
	 *
	 * Providers can return false, if they do not support flushing part of the cache (eg. memcache)
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @return boolean true on success, false on error (eg. $key not set)
	 */
	function flush(array $keys);
}
