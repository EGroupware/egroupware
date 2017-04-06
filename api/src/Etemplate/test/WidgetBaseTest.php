<?php

/**
 * Base test file for all widget tests
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package api
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate;

// test base providing Egw environment, since we need the DB
require_once realpath(__DIR__.'/../../test/LoggedInTest.php');

/**
 * Base class for all widget tests
 *
 * Widget scans the apps for widgets, which needs the app list, pulled from the
 * database, so we need to log in.
 */
class WidgetBaseTest extends \EGroupware\Api\LoggedInTest {
	//put your code here
}
