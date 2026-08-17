<?php


namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use Egroupware\Api;
use Egroupware\Api\Etemplate;
use Egroupware\Api\Link;

/**
 * Test setting a project manager project on an infolog entry
 *
 * Tests adding, loading, clearing the project to make sure field is set
 * correctly, and checks to make sure the infolog entry is properly added / removed
 * from the project.
 *
 * Since projectmanager is involved, there are frequent calls to Api\Link::run_notifies()
 * to keep its elements up to date.  Normally this is not needed, as it is run
 * at the end of every execution.
 */
class SetProjectManagerTest extends \EGroupware\Api\AppTest
{

	protected $ui;
	protected $bo;
	protected $pm_bo;

	// Infolog under test
	protected $info_id = null;

	// Project used to test
	protected $pm_id = null;

	/**
	 * Force-purge a stray projectmanager project by id, bypassing ACL, IF it's still there
	 * after a normal projectmanager_bo delete attempt.
	 *
	 * projectmanager_bo::delete() calls projectmanager_bo::read() first, which enforces an ACL
	 * check against the CURRENT session user. Observed (but not yet root-caused) in the full
	 * PHPUnit suite: that check can return false for a project this same test just created a
	 * moment earlier, apparently after enough other tests have run before it in the same
	 * process. When that happens, delete() silently no-ops (returns 0, no exception) and the
	 * row survives, later colliding with the next test's fixture INSERT under the same
	 * pm_number. This is test-fixture cleanup, where "get rid of it no matter what" is the
	 * correct semantics - projectmanager_so's delete() is a plain, ACL-free row delete, used
	 * here ONLY as a last-resort safety net *after* the normal, cascade-aware bo-level delete
	 * has already had its chance to clean up members/elements/links properly.
	 *
	 * @param int|string|null $pm_id
	 */
	protected function forcePurgeProjectIfStillThere($pm_id)
	{
		if (!$pm_id)
		{
			return;
		}
		$so = new \projectmanager_so();
		if ($so->read($pm_id))
		{
			$so->delete($pm_id);
		}
	}

	/**
	 * Make sure no stray projectmanager project with the given pm_number is left over from an
	 * earlier, interrupted test run, before a fresh fixture gets created under the same number.
	 *
	 * Looks the project up via projectmanager_so, NOT projectmanager_bo: a stray row can be
	 * ACL-invisible to the ACL-gated bo::read() used for the lookup in the original version of
	 * this pre-cleanup, for the same not-yet-root-caused reason documented on
	 * forcePurgeProjectIfStillThere() - which would otherwise skip cleanup entirely rather than
	 * just no-op the delete. If found, attempts the normal cascade-aware bo-level delete first
	 * (proper member/element/link cleanup when the ACL check happens to succeed), then falls
	 * back to a forced purge if the row is still there afterwards.
	 *
	 * @param \projectmanager_bo $bo used for the normal (cascade-aware) delete attempt;
	 *   its history property is forced to '' so a single delete() call always purges rather
	 *   than soft-deleting
	 * @param string $pm_number
	 */
	protected function purgeStaleProjectFixture(\projectmanager_bo $bo, $pm_number)
	{
		$so = new \projectmanager_so();
		$project = $so->read(array('pm_number' => $pm_number));
		if (!$project || !$project['pm_id'])
		{
			return;
		}
		$bo->history = '';
		$bo->delete($project['pm_id'], true);
		$this->forcePurgeProjectIfStillThere($project['pm_id']);
	}

	protected function setUp() : void
	{
		$this->ui = new \infolog_ui();

		$this->ui->tmpl = $this->createPartialMock(Etemplate::class, array('exec', 'read'));

		$this->bo = $this->ui->bo;
		$this->pm_bo = new \projectmanager_bo();

		$this->mockTracking($this->bo, 'infolog_tracking');
		// Unlike $this->bo, this wasn't mocked before - projectmanager_bo::save() lazily
		// creates a real projectmanager_tracking and calls its (real, unmocked) track(), which
		// can fail under heavy load and make save() return a truthy error string instead of 0,
		// failing makeProject()'s assertFalse() check. DeleteTest.php/TemplateTest.php already
		// mock this for their own projectmanager_bo - do the same here.
		$this->mockTracking($this->pm_bo, 'projectmanager_tracking');

		// Make sure projects are not there first
		foreach(array('TEST', 'SUB-TEST') as $number)
		{
			$this->purgeStaleProjectFixture($this->pm_bo, $number);
		}


		$this->makeProject();
	}

	protected function tearDown() : void
	{
		// Nested try/finally: if deleting the infolog entry throws, the project must still
		// get a cleanup attempt, and the bo's must still get unset either way - otherwise a
		// stuck 'TEST'-numbered project or global bo silently breaks unrelated tests running
		// later in the same PHPUnit process.
		try
		{
			// Remove infolog under test
			if($this->info_id)
			{
				$this->bo->delete($this->info_id, False, False, True);
				// One more time for history
				$this->bo->delete($this->info_id, False, False, True);
			}
		}
		finally
		{
			try
			{
				// Remove the test project
				$this->deleteProject();
			}
			finally
			{
				$this->bo = null;
				$this->pm_bo = null;
			}
		}
	}

	/**
	 * Create a new infolog entry, with project set
	 *
	 * This one goes via the ui, mostly to see how it would work
	 */
	public function testAddProjectToNew()
	{
		// Saving the infolog should try to send a notification
		$this->bo->tracking->expects($this->atLeastOnce())
                ->method('track')
				->with($this->callback(function($subject) { return $subject['pm_id'] == $this->pm_id;}));

		// Mock the etemplate call to check the results
		$this->ui->tmpl->expects($this->once())
				->method('exec')
				->with($this->stringContains('infolog.infolog_ui.edit'),
					$this->callback(function($info) {
						$this->assertNotNull($info['info_id']);
						$this->info_id = $info['info_id'];
						return $info['pm_id'] == $this->pm_id;
					})
				);

		$info = $this->getTestInfolog();

		// Set up the test - just set pm_id
		$info['pm_id'] = $this->pm_id;

		// Set button 'apply' to save, but not try to close the window since
		// that would fail
		$info['button'] = array('apply' => true);

		// Make a call to edit, looks like user set pm_id and clicked Apply
		$this->ui->edit($info);

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check project
		$this->checkElements();
	}

	public function testAddProjectToExisting()
	{
		// Saving the infolog should try to send a notification
		$track_call = 0;
		$this->bo->tracking->expects($this->exactly(2))
			->method('track')
			->willReturnCallback(function ($subject) use (&$track_call)
			{
				if($track_call === 0)
				{
					$this->assertNull($subject['pm_status']);
				}
				elseif($track_call === 1)
				{
					$this->assertEquals($this->pm_id, $subject['pm_id']);
				}
				$track_call++;
				return true;
			});

		$info = $this->getTestInfolog();

		$this->info_id = $this->bo->write($info);
		$this->assertIsInt($this->info_id);
		$this->assertGreaterThan(0, $this->info_id);

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Now load it again
		$info = $this->bo->read($this->info_id);

		// Set project by pm_id
		$info['pm_id'] = $this->pm_id;
		$this->bo->write($info);

		// Now load it again
		$info = $this->bo->read($this->info_id);

		// Check pm_id is there
		$this->assertNotNull($info['pm_id'], 'Project was not set');

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check project
		$this->checkElements();
	}

	/**
	 * Add a project by only adding it as a link.  First linked project gets
	 * taken as _the_ project.
	 */
	public function testAddProjectViaLink()
	{
		// Saving the infolog should try to send a notification
		$track_call = 0;
		$this->bo->tracking->expects($this->exactly(2))
			->method('track')
			->willReturnCallback(function ($subject) use (&$track_call)
			{
				if($track_call === 0)
				{
					$this->assertNull($subject['pm_status']);
				}
				elseif($track_call === 1)
				{
					$this->assertEquals($this->pm_id, $subject['pm_id']);
				}
				$track_call++;
				return true;
			});

		$info = $this->getTestInfolog();

		$this->info_id = $this->bo->write($info);
		$this->assertIsInt($this->info_id);
		$this->assertGreaterThan(0, $this->info_id);

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Now load it again
		$info = $this->bo->read($this->info_id);

		// Set project by link
		Link::link('infolog', $this->info_id, 'projectmanager', $this->pm_id);
		Api\Link::run_notifies();

		$this->bo->write($info);

		// Now load it again
		$info = $this->bo->read($this->info_id);

		// Check pm_id is there
		$this->assertNotNull($info['pm_id'], 'Project was not set');

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check project
		$this->checkElements();
	}

	/**
	 * Create a new infolog entry, set project via info_contact
	 */
	public function testContact()
	{
		// Saving the infolog should try to send a notification
		$this->bo->tracking->expects($this->once())
                ->method('track')
				->with($this->callback(function($subject) { return $subject['pm_id'] == $this->pm_id;}));

		$info = $this->getTestInfolog();

		// Set up the test - just set info_contact
		$info['info_contact'] = 'projectmanager:'.$this->pm_id;

		$this->info_id = $this->bo->write($info);
		$this->assertArrayHasKey('info_id', $info, 'Could not make test project');
		$this->assertThat($this->info_id,
			$this->logicalAnd(
				$this->isType('integer'),
				$this->greaterThan(0)
			)
		);

		// Check infolog has pm_id properly set
		$this->assertEquals($this->pm_id, $info['pm_id']);

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check project
		$this->checkElements();
	}

	/**
	 * Test adding a project to an infolog that has a contact (link to addressbook) set
	 */
	public function testLinkContact()
	{
		// Saving the infolog should try to send a notification
		$this->bo->tracking->expects($this->once())
                ->method('track')
				->with($this->callback(function($subject) { return $subject['pm_id'] == $this->pm_id;}));

		$info = $this->getTestInfolog();

		// Set up the test - just set info_contact
		$info['info_contact'] = array(
			'app'    =>	'addressbook',
			// Linking to current user's contact
			'id'     =>	$GLOBALS['egw_info']['user']['person_id'],
		);

		// Set project by pm_id
		$info['pm_id'] = $this->pm_id;

		$this->info_id = $this->bo->write($info);
		$this->assertArrayHasKey('info_id', $info, 'Could not make test entry');
		$this->assertThat($this->info_id,
			$this->logicalAnd(
				$this->isType('integer'),
				$this->greaterThan(0)
			)
		);

		// Now load it again
		$info = $this->bo->read($this->info_id);

		// Check infolog still has pm_id
		$this->assertEquals($this->pm_id, $info['pm_id'], 'Project went missing');

		// Check that infolog still has contact
		$keys = array('app' => 'addressbook', 'id' => $GLOBALS['egw_info']['user']['person_id']);
		foreach ($keys as $check_key => $check_value)
		{
			$this->assertArrayHasKey($check_key, $info['info_contact'], 'Infolog lost contact');
			$this->assertEquals($check_value, $info['info_contact'][$check_key], 'Infolog lost contact');
		}

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check project
		$this->checkElements();
	}

	/**
	 * Test free text in the contact field
	 */
	public function testFreeContact()
	{
		$info = $this->getTestInfolog();

		// Set up the test - just set info_contact
		$info['info_contact'] = array(
			'app'     =>	null,
			'id'      =>	null,
			'search'  =>	'Free text'
		);
		// Set project by pm_id
		$info['pm_id'] = $this->pm_id;

		$this->info_id = $this->bo->write($info);
		$this->assertArrayHasKey('info_id', $info, 'Could not make test infolog');
		$this->assertThat($this->info_id,
			$this->logicalAnd(
				$this->isType('integer'),
				$this->greaterThan(0)
			)
		);

		// Check infolog has pm_id properly set
		$this->assertEquals($this->pm_id, $info['pm_id']);

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check project
		$this->checkElements();
	}

	/**
	 * Test that loading a project set in the contact gets loaded with pm_id
	 * set.
	 */
	public function testLoadWithProject()
	{
		// Saving the infolog should try to send a notification
		$this->bo->tracking->expects($this->once())
			->method('track')
			->willReturnCallback(function ($subject)
			{
				return $subject['pm_id'] == $this->pm_id;
			});

		$info = $this->getTestInfolog();

		// Set up the test - just set info_contact
		$info['info_contact'] = 'projectmanager:'.$this->pm_id;

		$this->info_id = $this->bo->write($info);
		$this->assertThat($this->info_id,
			$this->logicalAnd(
				$this->isType('integer'),
				$this->greaterThan(0)
			)
		);

		// Check infolog has pm_id properly set
		$this->assertEquals($this->pm_id, $info['pm_id']);

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Now load it again
		$info = $this->bo->read($this->info_id);

		// Check infolog still has pm_id
		$this->assertEquals($this->pm_id, $info['pm_id'], 'Project went missing');

		// Check project
		$this->checkElements();
	}

	/**
	 * Test that you can change from one project to another without overwriting
	 * the set info_contact.
	 */
	public function testChangeProjectWithContactSet()
	{
		// Saving the infolog should try to send a notification
		$this->bo->tracking->expects($this->exactly(2))
			->method('track')
			->willReturnCallback(function ($subject)
			{
				return $subject['pm_id'] == $this->pm_id;
			});

		$info = $this->getTestInfolog();

		// Set up the test - set info_contact
		$info['info_contact'] = array(
			'app'    =>	'addressbook',
			// Linking to current user's contact
			'id'     =>	$GLOBALS['egw_info']['user']['person_id'],
		);
		// Set up the test - set pm_id
		$info['pm_id'] = $this->pm_id;

		$this->info_id = $this->bo->write($info);
		$this->assertThat($this->info_id,
			$this->logicalAnd(
				$this->isType('integer'),
				$this->greaterThan(0)
			)
		);

		// Check infolog has pm_id properly set
		$this->assertEquals($this->pm_id, $info['pm_id']);

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Now load it again
		$info = $this->bo->read($this->info_id);

		// Check infolog still has pm_id
		$this->assertEquals($this->pm_id, $info['pm_id'], 'Project went missing');

		// Now create a new project
		$first_pm_id = $this->pm_id;
		$this->pm_bo->data = array();
		$this->makeProject('2');
		$info['old_pm_id'] = $first_pm_id;
		$info['pm_id'] = $this->pm_id;
		$this->bo->write($info);

		// Check infolog has pm_id properly set
		$this->assertEquals($this->pm_id, $info['pm_id']);

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Now load it again
		$info = $this->bo->read($this->info_id);

		try {
			// Check infolog has pm_id properly set
			$this->assertEquals($this->pm_id, $info['pm_id'], 'Project did not change');

			// Check project
			$this->checkElements();

			// Check links (should be only 1)
			$pm_links = Api\Link::get_links('infolog',$this->info_id,'projectmanager');
			$this->assertEquals(1, count($pm_links));
		}
		finally
		{
			// Delete new
			$this->deleteProject();

			// Reset for cleanup
			$this->pm_id = $first_pm_id;
		}
	}

	/**
	 * Test free text in the contact field
	 */
	public function testChangeContactWithProjectStillSet()
	{
		$info = $this->getTestInfolog();

		// Set up the test - just set info_contact to the project
		$info['info_contact'] = array(
			'app'     =>	'projectmanager',
			'id'      =>	$this->pm_id
		);

		$this->info_id = $this->bo->write($info);
		$this->assertArrayHasKey('info_id', $info, 'Could not make test infolog');
		$this->assertThat($this->info_id,
			$this->logicalAnd(
				$this->isType('integer'),
				$this->greaterThan(0)
			)
		);

		// Check infolog has pm_id properly set
		$this->assertEquals($this->pm_id, $info['pm_id']);

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		$info = $this->bo->read($this->info_id);
		// Check project
		$this->checkElements();

		// Now set info_contact to be a contact
		$info['info_contact'] = array(
			'app'    =>	'addressbook',
			// Linking to current user's contact
			'id'     =>	$GLOBALS['egw_info']['user']['person_id'],
		);
		$this->bo->write($info);

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Now load it again
		$info = $this->bo->read($this->info_id);

		// Check infolog still has pm_id properly set
		$this->assertNotNull($info['pm_id'], 'Project got lost');
		$this->assertEquals($this->pm_id, $info['pm_id'], 'Project got changed');

		// Check project
		$this->checkElements();

		// Check pm links (should be only 1)
		$pm_links = Api\Link::get_links('infolog',$this->info_id,'projectmanager');
		$this->assertEquals(1, count($pm_links));

		// Check all links (should be contact & project)
		$links = Api\Link::get_links('infolog',$this->info_id);
		$this->assertEquals(2, count($links));

	}

	public function testClearProject()
	{
		// Saving the infolog should try to send a notification
		$track_call = 0;
		$this->bo->tracking->expects($this->exactly(2))
			->method('track')
			->willReturnCallback(function ($subject) use (&$track_call)
			{
				if($track_call === 0)
				{
					$this->assertEquals($this->pm_id, $subject['pm_id']);
				}
				elseif($track_call === 1)
				{
					$this->assertNull($subject['pm_status']);
				}
				$track_call++;
				return true;
			});

		$info = $this->getTestInfolog();

		// Set up the test - just set info_contact
		$info['info_contact'] = 'projectmanager:'.$this->pm_id;

		$this->info_id = $this->bo->write($info);
		$this->assertThat($this->info_id,
			$this->logicalAnd(
				$this->isType('integer'),
				$this->greaterThan(0)
			)
		);

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Now load it again
		$info = $this->bo->read($this->info_id);

		// Clear it
		unset($info['pm_id']);
		$this->bo->write($info);

		// Now load it again
		$info = $this->bo->read($this->info_id);

		// Check pm_id is gone
		$this->assertNull($info['pm_id'], 'Project was not removed');

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check project
		$this->checkElements(0);
	}

	public function testSetProjectViaURL()
	{
		// Mock the etemplate call to check the results
		$this->ui->tmpl->expects($this->once())
				->method('exec')
				->with($this->stringContains('infolog.infolog_ui.edit'),
					$this->callback(function($info) {
						$this->assertEquals($this->pm_id, $info['pm_id']);
						return $info['pm_id'] == $this->pm_id;
					})
				);

		// Set up the test - set pm_id vi URL
		$_REQUEST['action'] = 'projectmanager';
		$_REQUEST['action_id'] = $this->pm_id;

		// Make a call to edit, looks like pm_id was set, this is initial load
		$this->ui->edit();
	}

	/**
	 * If the contact is set to a project, and the contact is cleared, that
	 * will also clear the project
	 */
	public function testClearContact()
	{
		// Saving the infolog should try to send a notification
		$track_call = 0;
		$this->bo->tracking->expects($this->exactly(2))
			->method('track')
			->willReturnCallback(function ($subject) use (&$track_call)
			{
				if($track_call === 0)
				{
					$this->assertEquals($this->pm_id, $subject['pm_id']);
				}
				elseif($track_call === 1)
				{
					$this->assertNull($subject['pm_status']);
				}
				$track_call++;
				return true;
			});

		$info = $this->getTestInfolog();

		// Set up the test - just set info_contact
		$info['info_contact'] = 'projectmanager:'.$this->pm_id;

		$this->info_id = $this->bo->write($info);
		$this->assertThat($this->info_id,
			$this->logicalAnd(
				$this->isType('integer'),
				$this->greaterThan(0)
			)
		);

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Now load it again
		$info = $this->bo->read($this->info_id);

		// Clear it
		unset($info['info_contact']);
		$this->bo->write($info);

		// Now load it again
		$info = $this->bo->read($this->info_id);

		// Check contact was cleared
		$this->assertTrue(is_null($info['info_contact']) || (
				$info['info_contact']['id'] == 'none' && !$info['info_contact']['search']),
				'Contact was not cleared'
		);

		// Check pm_id is gone
		$this->assertNull($info['pm_id'], 'Project was not removed');

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check project
		$this->checkElements(0);
	}

	protected function getTestInfolog()
	{
		return array(
			'info_subject'     =>	'Test Infolog Entry for ' . $this->name()
		);
	}

	/**
	 * Make a project so we can test deleting it
	 */
	protected function makeProject($pm_number = '')
	{
		$project = array(
			'pm_number'         =>	'TEST' . ($pm_number ? " $pm_number" : ''),
			'pm_title'          =>	'Auto-test for ' . $this->name(),
			'pm_status'         =>	'active',
			'pm_description'    =>	'Test project for ' . $this->name()
		);

		// Save & set modifier, no notifications
		try
		{
			$result = true;
			$result = $this->pm_bo->save($project, true, false);
		}
		catch (\Exception $e)
		{
			// Something went wrong, we'll just fail
			$this->fail($e);
		}

		$this->assertFalse((boolean)$result, 'Error making test project');
		$this->assertArrayHasKey('pm_id', $this->pm_bo->data, 'Could not make test project');
		// Accept int or numeric string: Storage\Base::read() never casts DB columns (they come
		// back as strings from mysqli), and any intervening read of this project - eg. via
		// notification processing - re-hydrates pm_id as a string. Only the numeric value matters.
		$this->assertTrue(is_numeric($this->pm_bo->data['pm_id']) && $this->pm_bo->data['pm_id'] > 0,
			'pm_id is not a positive number: '.var_export($this->pm_bo->data['pm_id'], true));
		$this->pm_id = $this->pm_bo->data['pm_id'];
	}

	/**
	 * Check that the project element is present
	 *
	 */
	protected function checkElements($expected_count = 1)
	{
		$element_bo = new \projectmanager_elements_bo();
		$element_count = 0;

		foreach((array)$element_bo->search(array('pm_id' => $this->pm_id), false) as $element)
		{
			$element_count++;
			$this->assertEquals($this->info_id, $element['pe_app_id']);
		}

		$this->assertEquals($expected_count, $element_count, "Incorrect number of elements");
	}

	/**
	 * Fully delete a project and its elements, no matter what state or settings
	 */
	protected function deleteProject()
	{
		// Force links to run notification now, or elements might stay
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Force to ignore setting
		$this->pm_bo->history = '';
		try
		{
			$this->pm_bo->delete($this->pm_id, true);
		}
		finally
		{
			// Force links to run notification now, or elements might stay
			// usually waits until Egw::on_shutdown();
			Api\Link::run_notifies();

			$this->forcePurgeProjectIfStillThere($this->pm_id);
		}
	}

}