<?php

namespace EGroupware\Infolog;


use EGroupware\Api\Categories;
use EGroupware\Api\Etemplate;
use Egroupware\Api\Link;
use EGroupware\Api\TestEtemplate;

require_once realpath(__DIR__ . '/../../api/tests/EtemplateTest.php');

/**
 * Test having an infolog that is part of a project, and linking it to another project
 *
 * Tests to make sure that the original project stays as _the_ project (pm_id), but that
 * both projects get linked to the infolog, and the infolog is an element of both projects.
 *
 * Since projectmanager is involved, there are frequent calls to Api\Link::run_notifies()
 * to keep its elements up to date.  Normally this is not needed, as it is run
 * at the end of every execution.
 */
class DoubleLinkPMTest extends \EGroupware\Api\EtemplateTest
{

	protected $ui;
	protected $bo;
	protected $pm_bo;

	// Infolog under test
	protected $info_id = null;

	// Project(s) used to test
	protected $pm_id = [];

	protected function setUp() : void
	{
		parent::setUp();

		$_GET = $_POST = $_REQUEST = array();
		$this->ui = new \infolog_ui();

		$this->ui->tmpl = $this->createPartialMock(Etemplate::class, array('exec'));
		$this->ui->tmpl->expects($this->any())
					   ->method('exec')
					   ->will($this->returnCallback([$this, 'mockExec']));

		$this->bo = $this->ui->bo;
		$this->pm_bo = new \projectmanager_bo();

		$this->bo->tracking = $this->createStub(\infolog_tracking::class);
		$this->bo->tracking->method('track')->willReturn(0);

		// Make sure projects are not there first
		$pm_numbers = array(
			'TEST 1',
			'TEST 2',
			'TEST 3'
		);
		foreach($pm_numbers as $number)
		{
			$project = $this->pm_bo->read(array('pm_number' => $number));
			if($project && $project['pm_id'])
			{
				$this->pm_bo->delete($project);
			}
		}

		$this->makeProject("1");

		// Make another project, we need 2
		$this->makeProject("2");
	}

	protected function tearDown() : void
	{
		// Remove infolog under test
		if($this->info_id)
		{
			$this->bo->delete($this->info_id, False, False, True);
			// One more time for history
			$this->bo->delete($this->info_id, False, False, True);
		}

		// Remove the test projects
		$this->deleteProject();

		$this->bo = null;
		$this->pm_bo = null;

		// Clean up the request
		$_GET = $_POST = $_REQUEST = array();

		parent::tearDown();
	}


	/**
	 * Have an infolog that is part of a project, but then add it into another project.
	 * We expect both projects to stay linked, but _the_ project to be unchanged.
	 * It works correctly if you debug it but not if you run it
	 */
	public function testInProjectLinkAnother()
	{
		$first_project = $this->pm_id[0];
		$second_project = $this->pm_id[1];

		// Create our test infolog
		$info = $this->getTestInfolog();
		$this->info_id = $this->bo->write($info);
		$this->assertIsInt($this->info_id);
		$this->assertGreaterThan(0, $this->info_id);

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Link::run_notifies();

		// Fake opening the edit dialog, important not to pass an array to accurately copy normal behaviour
		// Set the initial project via the select
		$this->ui->edit($this->info_id);
		$content = self::$mocked_exec_result;
		$content['pm_id'] = $first_project;
		// pm_id has an on change submit, so submit without button
		$this->ui->edit($content);

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Link::run_notifies();

		// Then they click apply
		// Set button 'apply' to save, but not try to close the window since
		// that would fail
		$content = self::$mocked_exec_result;
		$content['button'] = ['apply' => true];
		$this->ui->edit($content);
		Link::run_notifies();

		// Now load the infolog entry
		$info = $this->bo->read($this->info_id);

		// Check original pm_id is there
		$this->assertNotNull($info['pm_id'], 'Project was not set');
		$this->assertEquals($first_project, $info['pm_id'], 'Project went missing');

		// Now link another project
		Link::link('infolog', $this->info_id, 'projectmanager', $second_project, "This is the second project");

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Link::run_notifies();
		$this->check_links($this->info_id, $first_project, $second_project);

		// Make a call to edit, looks like user updated subject and clicked Apply
		// Ticket #63114 says an edit will remove the second project
		$this->loadAndCheckLinks($info, $first_project, $second_project);

		// Check infolog is in original project
		$this->checkElements($first_project);
		// Check infolog is in second project
		$this->checkElements($second_project);

		// Check changing project
		$this->checkWeCanChangeTheProject();
	}

	/**
	 * Test that after messing with the links in testInProjectLinkAnother(),
	 * we can just change the pm_id and have it stored correctly
	 */
	protected function checkWeCanChangeTheProject()
	{
		// Make another project
		$this->makeProject('3');
		$new_project = $this->pm_id[2];

		// Sleep for a bit to make the modified time different, or it will fail
		sleep(1);

		// Fake opening the edit dialog, important not to pass an array to accurately copy normal behaviour
		// New BO to make sure we get a clean load, no caching
		$this->ui->bo = $this->bo = new \infolog_bo();
		$this->ui->edit($this->info_id);
		$content = self::$mocked_exec_result;

		$content['pm_id'] = $new_project;
		$content['button'] = array('apply' => true);

		// Set button 'apply' to save, but not try to close the window since
		// that would fail
		$this->ui->edit($content);

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Link::run_notifies();

		// Now load it again
		$info = $this->bo->read($this->info_id);

		// Check new pm_id is there
		$this->assertNotNull($info['pm_id'], 'Project was not set');
		$this->assertNotEquals((string)$this->pm_id[0], (string)$info['pm_id'], 'Project did not get changed');
		$this->assertEquals($new_project, $info['pm_id'], 'Project went missing');
	}

	public function mockExec($method, $content, $sel_options, $readonlys, $preserve)
	{
		$tmpl = new Etemplate('infolog.edit');
		$GLOBALS['egw']->categories = new Categories('', 'infolog');
		$result = parent::mockedExec($tmpl, $content, $sel_options, $readonlys, $preserve);
		// Check for the load
		$data = array();
		foreach($result as $command)
		{
			if($command['type'] == 'et2_load')
			{
				$data = $command['data'];
				break;
			}
		}

		Etemplate::ajax_process_content($data['data']['etemplate_exec_id'], $data['data']['content'], true);

		$content = static::$mocked_exec_result;

		return $content;
	}

	/**
	 * Load infolog via etemplate & check links & pm_id are still there
	 *
	 * @param $pm_id_1
	 * @param $pm_id_2
	 */
	protected function loadAndCheckLinks($info, $pm_id_1, $pm_id_2)
	{
		// Fake opening the edit dialog, important not to pass an array to accurately copy normal behaviour
		$this->ui->edit($info['info_id']);

		// Set button 'apply' to save, but not try to close the window since
		// that would fail
		$content = self::$mocked_exec_result;

		$content['info_subject'] .= " save #1";
		$content['button'] = array('apply' => true);
		$this->ui->edit($content);

		// Now do it again
		// Ticket #63114 says a second edit will remove the second project
		$content = self::$mocked_exec_result;

		$content['info_subject'] .= " save #2";
		$content['button'] = array('apply' => true);
		$this->ui->edit($content);

		$info = $this->bo->read($this->info_id);

		// Check original pm_id is there
		$this->assertNotNull($info['pm_id'], 'Original project was not set');
		$this->assertEquals($pm_id_1, $info['pm_id'], 'Original project went missing');

		$this->check_links($this->info_id, $pm_id_1, $pm_id_2);
	}

	protected function check_links($info_id, $pm_id_1, $pm_id_2)
	{
		// Check links
		$links = Link::get_links('infolog', $info_id);
		$all_there = count(array_filter($links, function ($link) use ($pm_id_1)
						   {
							   return $link['id'] == $pm_id_1;
						   })
			) == 1;
		$this->assertTrue($all_there, "First project went missing");
		$all_there = count(array_filter($links, function ($link) use ($pm_id_2)
						   {
							   return $link['id'] == $pm_id_2;
						   })
			) == 1;
		$this->assertTrue($all_there, "Second project went missing");
	}

	protected function getTestInfolog()
	{
		return array(
			'info_subject' => 'Test Infolog Entry for ' . $this->getName()
		);
	}

	/**
	 * Make a project so we can test deleting it
	 */
	protected function makeProject($pm_number = '')
	{
		$project = array(
			'pm_number'      => 'TEST' . ($pm_number ? " $pm_number" : ''),
			'pm_title'       => 'Auto-test for ' . $this->getName(),
			'pm_status'      => 'active',
			'pm_description' => 'Test project for ' . $this->getName()
		);

		// Save & set modifier, no notifications
		try
		{
			$result = true;
			$this->pm_bo->data = array();
			$result = $this->pm_bo->save($project, true, false);
		}
		catch (\Exception $e)
		{
			// Something went wrong, we'll just fail
			$this->fail($e);
		}

		$this->assertFalse((boolean)$result, 'Error making test project');
		$this->assertArrayHasKey('pm_id', $this->pm_bo->data, 'Could not make test project');
		$this->assertThat($this->pm_bo->data['pm_id'],
						  $this->logicalAnd(
							  $this->isType('integer'),
							  $this->greaterThan(0)
						  )
		);
		$this->pm_id[] = $this->pm_bo->data['pm_id'];
	}

	/**
	 * Check that the project element is present
	 *
	 */
	protected function checkElements($pm_id, $expected_count = 1)
	{
		$element_bo = new \projectmanager_elements_bo();
		$element_count = 0;

		foreach((array)$element_bo->search(array('pm_id' => $pm_id), false) as $element)
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
		Link::run_notifies();

		// Force to ignore setting
		$this->pm_bo->history = '';
		foreach($this->pm_id as $pm_id)
		{
			$this->pm_bo->delete($pm_id, true);
		}

		// Force links to run notification now, or elements might stay
		// usually waits until Egw::on_shutdown();
		Link::run_notifies();
	}

}