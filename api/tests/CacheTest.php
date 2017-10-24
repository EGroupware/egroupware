<?php
/**
 * EGroupware Api: Caching tests
 *
 * @link http://www.stylite.de
 * @package api
 * @subpackage cache
 * @author Ralf Becker <rb-AT-stylite.de>
 * @copyright (c) 2016 by Ralf Becker <rb-AT-stylite.de>
 * @author Stylite AG <info@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api;

use EGroupware\Api;
use PHPUnit\Framework\TestCase as TestCase;
use ReflectionClass;

/**
 * Mail account credentials tests
 *
 * Only testing en&decryption of mail passwords so far.
 * Further tests would need database.
 */
class CacheTest extends TestCase
{
	/**
	 * Test a caching provider
	 *
	 * @param string $class
	 * @param string $params
	 * @dataProvider cachingProvider
	 */
	public function testCache($class, $params=array())
	{
		// set us up as provider for Api\Cache class
		$GLOBALS['egw_info']['server']['install_id'] = md5(microtime(true).__FILE__);
		unset($GLOBALS['egw_info']['server']['cache_provider_instance']);
		unset($GLOBALS['egw_info']['server']['cache_provider_tree']);
		Api\Cache::$default_provider = $class;

		try {
			$provider = new $class($params);
			$refclass = new ReflectionClass($class);
			$methods = array();
			foreach(array('get','set','add','mget','delete') as $name)
			{
				if ($name != 'mget' || is_a($provider, 'EGroupware\Api\Cache\ProviderMultiple'))
				{
					$methods[$name] = $refclass->getMethod($name);
					$methods[$name]->setAccessible(true);
				}
			}

			foreach(array(
				Api\Cache::TREE => 'tree',
				Api\Cache::INSTANCE => 'instance',
			) as $level => $label)
			{
				$locations = array();
				foreach(array('string',123,true,false,null,array(),array(1,2,3)) as $data)
				{
					$location = md5(microtime(true).$label.serialize($data));
					$this->assertNull($methods['get']->invokeArgs($provider, array(array($level,__CLASS__,$location))),
						"$class: $label: get_before_set");

					$this->assertTrue($methods['set']->invokeArgs($provider, array(array($level,__CLASS__,$location), $data, 10)),
						"$class: $label: set");

					$this->assertEquals($data, $methods['get']->invokeArgs($provider, array(array($level,__CLASS__,$location))),
						"$class: $label: get_after_set");

					if (is_a($provider, 'EGroupware\Api\Cache\ProviderMultiple'))
					{
						$this->assertEquals(array($location => $data),
							$methods['mget']->invokeArgs($provider, array(array($level,__CLASS__,array($location)))),
							"$class: $label: mget_after_set");
					}
					$this->assertNotTrue($methods['add']->invokeArgs($provider, array(array($level,__CLASS__,$location), 'other-data')),
						"$class: $label: add_after_set");

					$this->assertTrue($methods['delete']->invokeArgs($provider, array(array($level,__CLASS__,$location))),
						"$class: $label: delete");

					$this->assertNull($methods['get']->invokeArgs($provider, array(array($level,__CLASS__,$location))),
						"$class: $label: get_after_delete");

					// prepare for mget of everything
					if (is_a($provider, 'EGroupware\Api\Cache\ProviderMultiple'))
					{
						$locations[$location] = $data;
						$mget_after_delete = $methods['mget']->invokeArgs($provider, array(array($level,__CLASS__,array($location))));
						$this->assertNotTrue(isset($mget_after_delete[$location]),
							"$class: $label: mget_after_delete['$location']");
					}
					elseif (!is_null($data))	// emulation can NOT distinquish between null and not set
					{
						$locations[$location] = $data;
					}
					$this->assertTrue($methods['add']->invokeArgs($provider, array(array($level,__CLASS__,$location), $data, 10)),
						"$class: $label: add_after_delete");

					$this->assertEquals($data, $methods['get']->invokeArgs($provider, array(array($level,__CLASS__,$location))),
						"$class: $label: get_after_add");
				}
				// get all above in one request
				$keys = array_keys($locations);
				$keys_bogus = array_merge(array('not-set'),array_keys($locations),array('not-set-too'));
				if (is_a($provider, 'EGroupware\Api\Cache\ProviderMultiple'))
				{
					$this->assertEquals($locations, $methods['mget']->invokeArgs($provider, array(array($level,__CLASS__,$keys))),
						"$class: $label: mget_all");
					$this->assertEquals($locations, $methods['mget']->invokeArgs($provider, array(array($level,__CLASS__,$keys_bogus))),
						"$class: $label: mget_with_bogus_key");
				}
			}
		}
		catch (\Exception $e) {
			$this->markTestSkipped($e->getMessage());
		}
	}

	/**
	 * Caching provides to set with constructor parameters
	 *
	 * @return array of array
	 */
	public static function cachingProvider()
	{
		// create empty temp. directory
		unlink($tmp_dir = tempnam('/tmp', 'tmp'));
		mkdir($tmp_dir);

		return array(
			array(__NAMESPACE__.'\\Cache\\Apcu'),
			array(__NAMESPACE__.'\\Cache\\Apc'),
			array(__NAMESPACE__.'\\Cache\\Memcache', array('localhost')),
			array(__NAMESPACE__.'\\Cache\\Memcached', array('localhost')),
			array(__NAMESPACE__.'\\Cache\\Files', array($tmp_dir)),
		);
	}
}
