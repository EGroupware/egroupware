<?php
/**
 * EGroupware Api: Application test base class
 *
 * @link http://www.stylite.de
 * @package api
 * @subpackage test
 * @author Nathan Gray
 * @copyright (c) 2016 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api;

// test base providing Egw environment
require_once 'LoggedInTest.php';

use EGroupware\Api;

/**
 * Base class for application tests, loads the egroupware environment and provides
 * some handy helpers.
 *
 * Extend this class into <appname>/tests/ to test one
 * small aspect of an application.  For more basic (actual unit) tests that deal
 * with a single function, consider extending TestCase directly instead of this
 * class to avoid the overhead of creating the session.
 */
abstract class AppTest extends LoggedInTest
{
	/**
	 * Sets the tracking object to a mock object, so we don't try to send real
	 * notifications while testing.
	 *
	 * After calling this to mock the tracking object, you can set expectations
	 * for tracking:
	 * <code>
	 * $this->mockTracking($this->bo, 'app_tracker');
	 *
	 * // we do not expect track to get called for a new entry
	 * $this->bo->tracking->expects($this->never())
	 *		->method('track');
	 *
	 * $this->bo->save($entry);
	 * </code>
	 * @param Object $bo_object Instance of the BO object
	 * @param String $tracker_class The name of the tracker class to mock
	 */
	protected function mockTracking(&$bo_object, $tracker_class)
	{
		if(!is_object($bo_object))
		{
			throw new \BadMethodCallException('Invalid BO object');
		}
		if(!property_exists($bo_object, 'tracking'))
		{
			throw new \BadMethodCallException('Invalid BO object - needs tracking property');
		}
		$bo_object->tracking = $this->getMockBuilder($tracker_class)
			->disableOriginalConstructor()
			->setMethods(['track'])
			->getMock($bo_object);
	}
}