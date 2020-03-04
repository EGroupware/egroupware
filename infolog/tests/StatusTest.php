<?php

/**
 * Test the status / completion combinations on an infolog entry
 *
 * Some statuses changes will also change the percent completed value.
 * For example, closed statuses will set completed to 100%, subsequently changing
 * to an open status will change completed to less than 100%.  Changing to new
 * (not-started) will set completion to 0%.
 *
 * The list of status and percentage changes is stored in a JSON file
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package infolog
 * @copyright (c) 2017 by Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */


namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use Egroupware\Api;

class StatusTest extends \EGroupware\Api\AppTest
{

	private static $map_file = '/status_map.json';

	protected $bo;

	// Infolog under test
	protected $info_id = null;

	/**
	 * Create a custom status we can use to test
	 */
	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		// Create custom status
		$bo = new \infolog_bo();
		$bo->status['task']['custom'] = 'custom';

		Api\Config::save_value('status',$bo->status,'infolog');
	}
	public static function tearDownAfterClass() : void
	{
		// Remove custom status
		$bo = new \infolog_bo();
		unset($bo->status['task']['custom']);
		Api\Config::save_value('status',$bo->status,'infolog');

		// Have to remove custom status first, before the DB is gone
		parent::tearDownAfterClass();
	}

	protected function setUp() : void
	{
		$this->bo = new \infolog_bo();

		$this->mockTracking($this->bo, 'infolog_tracking');
	}

	protected function tearDown() : void
	{
		$this->bo = null;
	}

	/**
	 * Step through the map and check each one
	 */
	public function testStatusChange()
	{
		$json = file_get_contents(realpath(__DIR__.static::$map_file));
		// Strip the comments out of the json file, they're not valid
		$json = preg_replace("#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t]//.*)|(^//.*)#", '', $json);
		$map = json_decode($json, true);

		$this->assertNotEquals(0, count($map), 'Could not read status map ' . static::$map_file);

		foreach($map as $test => $map)
		{
			$this->checkOne($map['from'], $map['to'], $map['expected']);
		}
	}

	/**
	 * Check a change
	 *
	 * @param Array $from
	 * @param Array $to
	 * @param Array $expected
	 */
	protected function checkOne($from, $to, $expected)
	{
		$info = $this->getTestInfolog($from);

		// Skipping notifications - save initial state
		$this->info_id = $this->bo->write($info, true, true, true, true);

		foreach($to as $field => $value)
		{
			$info["info_{$field}"] = $value;
		}

		// Skipping notifications
		$this->bo->write($info, true, true, true, true);

		// Read it back to check
		$saved = $this->bo->read($this->info_id);

		$test_name = $this->getTestName($from, $to);

		foreach($expected as $field => $value)
		{
			$this->assertEquals($value, $saved["info_{$field}"],
					"$test_name failed on '$field' field");
		}

		// Remove infolog under test
		if($this->info_id)
		{
			$this->bo->delete($this->info_id, False, False, True);
			$this->bo->delete($this->info_id, False, False, True);
		}
	}

	/**
	 * Get a text representation of the change so we can tell which one went
	 * wrong
	 *
	 * @param Array $from
	 * @param Array $to
	 * @return String
	 */
	protected function getTestName($from, $to)
	{
		$name = array();
		foreach($from as $field =>  $value)
		{
			$name[] = $field . ': ' . $from[$field] . (array_key_exists($field, $to) ? ' => ' . $to[$field] : '');
		}
		return implode(', ', $name);
	}

	/**
	 * Set up a basic infolog entry for testing with the specified fields
	 * set.
	 *
	 * @param Array $from Fields to be set for initial conditions
	 * @return Array
	 */
	protected function getTestInfolog($from)
	{
		$info = array(
			'info_subject'     =>	'Test Infolog Entry for ' . $this->getName()
		);

		foreach($from as $field => $value)
		{
			$info["info_{$field}"] = $value;
		}

		return $info;
	}

	/**
	 * Check status changes with custom status
	 */
	public function testCustomStatus()
	{

		$this->checkOne(
				array('status' => 'custom', 'percent' => 10),
				array('status' => 'ongoing'),
				array('status' => 'ongoing', 'percent' => 10)
		);


	}
}
