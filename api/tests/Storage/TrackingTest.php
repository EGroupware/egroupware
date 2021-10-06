<?php
/**
 * Tests for Tracking
 *
 * @package api
 * @subpackage tests
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2018 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Storage;

require_once __DIR__ . '/../LoggedInTest.php';
require_once __DIR__ . '/TestTracking.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest as LoggedInTest;

class TrackingTest extends LoggedInTest
{
	const APP = 'test';

	protected $simple_field = array(
		'app'     => self::APP,
		'name'    => 'test_field',
		'label'   => 'Custom field',
		'type'    => 'text',
		'type2'   => array(),
		'help'    => 'Custom field created for automated testing by CustomfieldsTest',
		'values'  => null,
		'len'     => null,
		'rows'    => null,
		'order'   => null,
		'needed'  => null,
		'private' => array()
	);

	/**
	 * Test the access control on private custom fields
	 */
	public function testSanitizeCustomMessage()
	{
		// Create field
		$field = array_merge(
			$this->simple_field,
			array(
				'private' => array($GLOBALS['egw_info']['user']['account_id'])
			)
		);
		Customfields::update($field);

		$fields = Customfields::get(self::APP);

		$dirty_message = "This custom message contains {{#test_field}}, which only user {$GLOBALS['egw_info']['user']['account_id']} can access.";
		$clean_message = "This custom message contains , which only user {$GLOBALS['egw_info']['user']['account_id']} can access.";

		// Get another user
		$accounts = $GLOBALS['egw']->accounts->search(array(
														  'type' => 'accounts'
													  ));
		unset($accounts[$GLOBALS['egw_info']['user']['account_id']]);
		if(count($accounts) == 0)
		{
			$this->markTestSkipped('Need more than one user to check private');
		}
		$other_account = key($accounts);

		$tracking = new TestTracking(self::APP);
		$cleaned = $tracking->sanitize_custom_message($dirty_message, $other_account);

		$this->assertEquals($clean_message, $cleaned);

		// Clean up
		unset($fields[$field['name']]);
		Customfields::save(self::APP, $fields);
	}
}
