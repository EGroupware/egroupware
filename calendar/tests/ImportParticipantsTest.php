<?php

/**
 * Tests for importing participants
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package calendar
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use Egroupware\Api;

class ImportParticipantsTest extends \EGroupware\Api\AppTest
{

	// Import object
	private $import = null;

	// Method under test with modified access
	private $parse_method = null;

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
		$this->bo = new \calendar_bo();

		$this->import = new \calendar_import_csv();
		$this->import->bo = $this->bo;
		$this->import->role_map = array_flip($this->bo->roles);
		$this->import->status_map = array_flip($this->bo->verbose_status);

		// Make parse_participants method accessable
		$class = new \ReflectionClass($this->import);
        $this->parse_method = $class->getMethod('parse_participants');
        $this->parse_method->setAccessible(true);
	}

	protected function tearDown() : void
	{

	}

	/**
	 * Test that various participant strings are correctly parsed and matched
	 *
	 * @param array $expected IDs expected
	 * @param string $test_string String to be parsed
	 * @param boolean $warn Expect a warning
	 *
	 * @dataProvider participantProvider
	 */
	public function testUsers($expected, $test_string, $warn)
	{

		$warning = '';
		$record = new ParticipantRecord($test_string);

		// Parse the string
		$parsed = $this->parse_method->invokeArgs($this->import, array($record, &$warning));

		// Get numeric ID for this system
		foreach ($expected as $id => $status)
		{
			$_id = $id;
			unset($expected[$id]);
			$id = $GLOBALS['egw']->accounts->name2id($_id);
			$expected[$id] = $status;
		}

		// Verify
		$this->assertEquals($expected, $parsed);

		if($warn)
		{
			$this->assertNotEmpty($warning, 'Did not get a warning');
		}
		else
		{
			$this->assertEmpty($warning, 'Got a warning');
		}
	}

	public function participantProvider()
	{
		return array(
			// Expected resource IDs, string to be parsed


			array(array(), '', false),

			// No such user, but it looks OK - should warn about not found user
			array(array(), 'Not found (No Response)', true),
			array(array(), 'Not Found (4) (No Response) Chair', true),
			array(array(), 'Delta (de), Dan (No Response) None', true),

			// Statuses
			array(array('demo' => 'A'), 'demo (Accepted)', false),
			array(array('demo' => 'R'), 'demo (Rejected)', false),
			array(array('demo' => 'T'), 'demo (Tentative)', false),
			array(array('demo' => 'U'), 'demo (No Response)', false),
			array(array('demo' => 'D'), 'demo (Delegated)', false),

			// Status with quantity
			array(array('demo' => 'A4'), 'demo (4) (Accepted)', false),
			array(array('demo' => 'R4'), 'demo (4) (Rejected)', false),
			array(array('demo' => 'T4'), 'demo (4) (Tentative)', false),
			array(array('demo' => 'U4'), 'demo (4) (No Response)', false),
			array(array('demo' => 'D4'), 'demo (4) (Delegated)', false),
			array(array('demo' => 'A'), 'demo (Accepted) Requested', false),

			// Roles
			array(array('demo' => 'ACHAIR'), 'demo (Accepted) Chair', false),
			array(array('demo' => 'AOPT-PARTICIPANT'), 'demo (Accepted) Optional', false),
			array(array('demo' => 'ANON-PARTICIPANT'), 'demo (Accepted) None', false),
			array(array('demo' => 'AUnknown'), 'demo (Accepted) Unknown', false),

			// Quantity, status & role
			array(array('demo' => 'A2CHAIR'), 'demo (2) (Accepted) Chair', false),
			array(array('demo' => 'A2Invalid'), 'demo (2) (Accepted) Invalid', false),

			// Multiples
			array(array('demo' => 'A'), 'demo (Accepted), Found, Not (No Response)', true),
			array(array('demo' => 'A'), 'Guest, Demo (Accepted), Found (why), Not (No Response)', true),

			// Invalid - unparsable
			array(array(), 'Totally invalid', false),
			array(array(), 'demo (Invalid)', false),
			array(array(), 'demo (4) (Acepted)', false),
			array(array(), 'demo (Five) (No Response)', true),

			// TOOD: These will need matching resources created to try to match on
			/*
			array(array('de' => 'A'), 'Delta (de), Dan (No Response) None', false),
			array(array('de' => 'A'), 'Delta (de), Dan (3) (No Response) None', false)
			 */
		);
	}
}

class ParticipantRecord {
	public $participants = '';
	public function __construct($p)
	{
		$this->participants = $p;
	}
}