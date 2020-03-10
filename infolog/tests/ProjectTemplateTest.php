<?php


namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../projectmanager/tests/TemplateTest.php');

use EGroupware\Api\Link;

/**
 * Test creating a project from a template, with some extra testing for various
 * infolog special cases to make sure info_from and contact are correctly handled.
 *
 */
class ProjectTemplateTest extends \EGroupware\Projectmanager\TemplateTest
{

	protected $debug = false;

	// List of extra customizations to check
	protected $customizations = array();

	/**
	 * Make a project so we can test with it
	 */
	protected function makeProject($status = 'active')
	{
		$project = array(
			'pm_number'         =>	'TEST Template',
			'pm_title'          =>	'Auto-test for ' . $this->getName(),
			'pm_status'         =>	$status,
			'pm_description'    =>	'Test project for ' . $this->getName()
		);

		// Save & set modifier, no notifications
		try
		{
			$result = true;
			$result = $this->bo->save($project, true, false);
		}
		catch (\Exception $e)
		{
			// Something went wrong, we'll just fail
			$this->fail($e);
		}

		$this->assertFalse((boolean)$result, 'Error making test project');
		$this->assertArrayHasKey('pm_id', $this->bo->data, 'Could not make test project');
		$this->assertThat($this->bo->data['pm_id'],
			$this->logicalAnd(
				$this->isType('integer'),
				$this->greaterThan(0)
			)
		);
		$this->pm_id = $this->bo->data['pm_id'];

		// Add some elements
		$this->assertGreaterThan(0, count($GLOBALS['egw_info']['apps']),
			'No apps found to use as projectmanager elements'
		);

		// Make an infolog with a contact
		$contact_id = $GLOBALS['egw_info']['user']['person_id'];
		$title = Link::title('addressbook', $contact_id);
		$this->make_infolog(array(
			'info_contact' => array('app' => 'addressbook', 'id' => $contact_id, 'title' => $title )
		));

		// Make one with a custom from
		$this->make_infolog(array(
				'info_from' => 'Custom from'
		));

		// Need to do this from parent to keep IDs where expected
		$this->make_projectmanager();

		// We got this far, there should be elements
		$this->assertGreaterThan(0, count($this->elements), "No project elements created");
		if ($this->debug)
		{
			echo __METHOD__ . " Created test elements: \n";
			print_r($this->elements);
		}

		// Force links to run notification now, or we won't get elements since it
		// usually waits until Egw::on_shutdown();
		Link::run_notifies();

		$elements = new \projectmanager_elements_bo($this->bo);
		$elements->sync_all($this->pm_id);

		// Make sure all elements are created
		$this->checkOriginalElements(false, count($this->elements), "Unable to create all project elements");
	}

	/**
	 * Make an infolog entry and add it to the project
	 */
	protected function make_infolog($custom = false)
	{
		$bo = new \infolog_bo();
		$element = array(
			'info_subject' => "Test infolog for #{$this->pm_id}",
			'info_des'     => 'Test element as part of the project for test ' . $this->getName(),
			'info_status'  => 'open',
			'pm_id'	=> $this->pm_id
		);

		if ($custom)
		{
			$element['info_subject'] .= "\tCustomized:\t" . \array2string($custom);
			$element['info_des'] .= "\nCustomized:\n" . \array2string($custom);
			$element += $custom;
		}

		$element_id = $bo->write($element, true, true, true, true);
		$this->assertIsNumeric($element_id, "Problem creating test infolog entry");
		$this->assertNotEquals(false, $element_id, "Problem creating test infolog entry");

		$this->elements[] = 'infolog:' . $element_id;

		if ($custom)
		{
			$this->customizations['infolog:' . $element_id] = $custom;
		}
	}

	/**
	 * Check that the project elements are present, and have the provided status.
	 *
	 * @param String $status
	 */
	protected function checkClonedElements($clone_id)
	{
		$element_bo = new \projectmanager_elements_bo();
		$element_bo->pm_id = $clone_id;
		$indexed_elements = array();
		$unmatched_elements = $this->elements;

		if ($this->debug)
		{
			echo "\n" . __METHOD__ . "\n";
			echo "Checking on (copied) PM ID $clone_id\n";
		}

		$elements = $element_bo->search(array('pm_id' => $clone_id), false, 'pe_id ASC');
		// Expect 1 sub-project, 2 infologs
		$this->assertIsArray($elements, "Did not find any project elements in copy");
		$this->assertCount(3, $elements, "Incorrect number of project elements");

		foreach ($elements as $element)
		{
			if ($this->debug)
			{
				echo "\tPM:" . $element['pm_id'] . ' ' . $element['pe_id'] . "\t" . $element['pe_app'] . ':' . $element['pe_app_id'] . "\t" . $element['pe_title'] . "\n" . Link::title($element['pe_app'], $element['pe_app_id']) . "\n";
			}
			$indexed_elements[$element['pe_app']][] = $element;
		}
		foreach ($this->elements as $key => $_id)
		{
			list($app, $id) = explode(':', $_id);

			// Don't care about other apps here
			if ($app !== 'infolog')
			{
				unset($unmatched_elements[$key]);
				continue;
			}

			$copied = array_shift($indexed_elements[$app]);

			if ($this->debug)
			{
				echo "$_id:\tCopied element - PM:" . $copied['pm_id'] . ' ' . $copied['pe_app'] . ':' . $copied['pe_app_id'] . "\t" . $copied['pe_title'] . "\n";
			}

			$this->assertNotNull($copied, "$app entry $_id did not get copied");

			// Also check pm_id & info_from
			$info_bo = new \infolog_bo();
			$entry = $info_bo->read($copied['pe_app_id']);
			$this->assertEquals($clone_id, $entry['pm_id']);

			// Make sure ID is actually different - copied, not linked
			$this->assertNotEquals($id, $copied['pe_app_id']);

			unset($unmatched_elements[$key]);

			if($this->customizations[$_id])
			{
				$this->assertNotNull($entry);
				foreach ($this->customizations[$_id] as $custom_key => $custom_value)
				{
					$this->assertArrayHasKey($custom_key, $entry);
					$this->assertEquals($custom_value, $entry[$custom_key]);
				}
			}
		}

		// Check that we found them all
		$this->assertEmpty($unmatched_elements, 'Missing copied elements ' . \array2string($unmatched_elements));
	}
}