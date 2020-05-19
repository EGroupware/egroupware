<?php

/**
 * Tests for resetting participant status if the event changes
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package calendar
 * @copyright (c) 2019  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use Egroupware\Api;

class ResetParticipantStatusTest extends \EGroupware\Api\AppTest
{
	// Another user we can use for testing
	protected $account_id;

	// Method under test with modified access
	private $check_method = null;

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

	}
	public static function tearDownAfterClass() : void
	{
		parent::tearDownAfterClass();
	}

	protected function setUp() : void
	{
		$this->bo = new \calendar_boupdate();

		// Add another user
		$this->account_id = $this->make_test_user();

		// Make check_reset_status method accessable
		$class = new \ReflectionClass($this->bo);
        $this->check_method = $class->getMethod('check_reset_statuses');
        $this->check_method->setAccessible(true);
	}

	protected function tearDown() : void
	{

		// Clean up user
		$GLOBALS['egw']->accounts->delete($this->account_id);
	}

	/**
	 * Test that various participant strings are not changed if nothing changes
	 *
	 * @param array $participant Participant to test
	 *
	 * @dataProvider participantProvider
	 */
	public function testNoChange($participant)
	{
		$this->fix_id($participant);

		// Get test event
		$event = $old_event = $this->get_event();

		// No change
		$event['participant'] = $old_event['participant'] = $participant;

		// Check & reset status
        $reset = $this->check_method->invokeArgs($this->bo, array(&$event, $old_event));

		// Verify
		$this->assertFalse($reset);
	}


	/**
	 * Test that moving the event forward 1 hour works correctly.
	 * - For current user, no change to status
	 * - For other users, respect the preference
	 * - For non-users, always reset status.
	 *
	 * @param type $participant
	 *
	 * @dataProvider participantProvider
	 */
	public function testForwardOneHour($participant)
	{
		$this->fix_id($participant);
		$participant[$this->account_id] = 'A';

		// Get test event
		$event = $old_event = $this->get_event();
		$event['participants'] = $old_event['participants'] = $participant;

		// Forward 1 hour
		$event['start'] += 3600;
		$event['end'] += 3600;

		// Set preference to only if start day changes, so no reset is done
		$pref = array(
			'account' => $this->account_id,
			'pref' => 'user',
			'app' => 'calendar',
			'set' => array('reset_stati' => 'startday')
		);
		$pref_command = new \admin_cmd_edit_preferences($pref);
		$pref_command->run();


		// Check & reset status
        $reset = $this->check_method->invokeArgs($this->bo, array(&$event, $old_event));

		// Verify change as expected
		foreach($event['participants'] as $id => $status)
		{
			if($id == $this->bo->user)
			{
				// Current user, no change
				$this->assertEquals($participant[$id], $status, "Participant $id was changed");
			}
			if(is_int($id))
			{
				// Users respect preference, in this case no change
				$this->assertEquals($participant[$id], $status, "Participant $id was changed");
			}
			else
			{
				// Non-user gets reset
				$this->assertEquals('U', $status[0], "Participant $id did not get reset");
			}
		}
	}

	/**
	 * Test that moving the event to a different day resets or keeps status for
	 * user accounts according to that account's preference
	 *
	 * @param Array $change_preference One of the allowed preference values
	 *
	 * @dataProvider statiPreferenceProvider
	 */
	public function testChangeUsesPreference($change_preference)
	{
		// Get test event
		$event = $old_event = $this->get_event();
		$participant = $event['participants'] = $old_event['participants'] = array(
			// Current user is never changed
			$this->bo->user => 'A',
			// Other user will use preference
			$this->account_id => 'A'
		);

		// Set preference
		$pref = array(
			'account' => $this->account_id,
			'pref' => 'user',
			'app' => 'calendar',
			'set' => array('reset_stati' => $change_preference)
		);
		$pref_command = new \admin_cmd_edit_preferences($pref);
		$pref_command->run();

		// Forward 1 day
		$event['start'] += 24*3600;
		$event['end'] += 24*3600;

		// Check & reset status
        $reset = $this->check_method->invokeArgs($this->bo, array(&$event, $old_event));

		// Verify no change in current user
		$this->assertEquals($participant[$this->bo->user], $event['participants'][$this->bo->user]);

		// Other user may change though
		switch($pref)
		{
			case 'no':
				$this->assertFalse($reset);
				$this->assertEquals($participant[$this->account_id], $event['participants'][$this->account_id]);
				break;
			case 'all':
			case 'sameday':
				$this->assertTrue($reset);
				$this->assertEquals('U', $event['participants'][$this->account_id][0]);
				break;
		}
	}

	protected function get_event()
	{
		return array(
			'title' => 'Test event for ' . $this->getName(),
			'owner' => $GLOBALS['egw_info']['user']['account_id'],
			'start' => 1602324600,
			'end'   => 1602328200
		);
	}

	protected function make_test_user()
	{
		$account = array(
			'account_lid' => 'user_test',
			'account_firstname' => 'Test',
			'account_lastname' => 'Test'
		);
		$command = new \admin_cmd_edit_user(false, $account);
		$command->comment = 'Needed for unit test ' . $this->getName();
		$command->run();
		return $command->account;
	}

	/**
	 * Account ID is unknown when participantProvider is run, so we swap in
	 * the ID
	 *
	 * @param Array $participants
	 */
	protected function fix_id(&$participants)
	{
		// Provider didn't know test user's ID, so swap it in
		foreach($participants as $id => $status)
		{
			if($id == $GLOBALS['egw_info']['user']['account_lid'])
			{
				unset($participants[$id]);
				$participants[$GLOBALS['egw_info']['user']['account_id']] = $status;
			}
		}
	}

	/**
	 * Different values for the status change on event change preference
	 * 
	 * @see calendar_hooks::settings()
	 */
	public function statiPreferenceProvider()
	{
		return array(
			array('no'), // Never change status
			array('all'),// Always change status
			array('startday') // Change status of start date changes
		);
	}

	public function participantProvider()
	{
		return array(
			// Participant ID => status

			// User - 'demo' will get changed for real ID later
			array(array('demo' => 'A')),
			array(array('demo' => 'R')),
			array(array('demo' => 'T')),
			array(array('demo' => 'U')),
			array(array('demo' => 'D')),

			// These don't have to be real IDs since we're not actually doing
			// anything with the entry itself, just checking its type

			// Contact
			array(array('c1' => 'A')),
			array(array('c1' => 'R')),
			array(array('c1' => 'T')),
			array(array('c1' => 'U')),
			array(array('c1' => 'D')),

			// Resource
			array(array('r1' => 'A')),
			array(array('r1' => 'R')),
			array(array('r1' => 'T')),
			array(array('r1' => 'U')),
			array(array('r1' => 'D')),

			// All together, just for fun
			array(array('demo' => 'A', 'c1' => 'D', 'r1' => 'T'))
		);
	}
}