<?php

/**
 * Test the contact field - If there is a free-text in info_contact, its content
 * would be stored in info_from. If info_link_id is greater then 0, link-title
 * of that id would be stored in info_from allowing regular search to find the
 * entry.
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package infolog
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use Egroupware\Api\Etemplate;

class ContactTest extends \EGroupware\Api\AppTest
{

	protected $ui;
	protected $bo;

	// Infolog under test
	protected $info_id = null;

	protected function setUp() : void
	{
		$this->ui = new \infolog_ui();

		$this->ui->tmpl = $this->createPartialMock(Etemplate::class, array('exec', 'read'));

		$this->bo = $this->ui->bo;

		$this->mockTracking($this->bo, 'infolog_tracking');
	}

	protected function tearDown() : void
	{
		// Double delete to make sure it's gone, not preserved due to history setting
		if($this->info_id)
		{
			$this->bo->delete($this->info_id);
			$this->bo->delete($this->info_id);
		}
		$this->bo = null;
	}

	/**
	 * Test that free text in the info_contact field winds up in info_from, and
	 * when loaded again it is put into the search of info_contact for display.
	 */
	public function testFreeText()
	{
		$content = array(
			'contact' => array(
				'app'   =>	'addressbook',
				'id'    =>	Null,
				'search'=>	'Free text'
			)
		);

		$info = $this->getTestInfolog($content);

		// Skipping notifications - save initial state
		$this->info_id = $this->bo->write($info, true, true, true, true);

		// Read it back to check
		$saved = $this->bo->read($this->info_id);

		$this->assertEquals($content['contact']['search'], $saved['info_from']);
		$this->assertEquals(0, $saved['info_link_id']);

		// Mock the etemplate call to check the results
		$this->ui->tmpl->expects($this->once())
			->method('exec')
			->will(
				$this->returnCallback(function($method, $info) {
					$this->assertNotNull($info['info_id']);
					$this->assertEquals('Free text', $info['info_contact']['title']);
					return true;
				})
			);

		// Make a call to edit, looks like initial load
		$_REQUEST['info_id'] = $this->info_id;
		$this->ui->edit();

		// Change it
		$saved['info_contact']['search'] =  'Totally different';

		// Skipping notifications - save initial state
		$this->bo->write($saved, true, true, true, true);

		// Read it back to check
		$resaved = $this->bo->read($this->info_id);
		$this->assertEquals('Totally different', $resaved['info_from'], 'Did not change free text');
		$this->assertEquals(0, $resaved['info_link_id']);

		// Now clear it
		$saved = $resaved;
		$saved['info_contact']['search'] = '';

		// Skipping notifications - save initial state
		$this->bo->write($saved, false, false);

		// Read it back to check
		$resaved = $this->bo->read($this->info_id);
		$this->assertEquals('', $resaved['info_from'], 'Did not clear free text');
		$this->assertEquals(0, $resaved['info_link_id']);
	}

	/**
	 * Test that a selected entry is put into info_link_id, and its link title
	 * is put into info_from (not the search text)
	 */
	public function testLinkedEntry()
	{
		$content = array(
			'contact' => array(
				'app'   =>	'addressbook',
				// Linking to current user's contact
				'id'    =>	$GLOBALS['egw_info']['user']['person_id'],
				'search'=>	'Free text'
			)
		);
		$link_title = $GLOBALS['egw']->contacts->link_title($content['contact']['id']);
		$info = $this->getTestInfolog($content);

		// Skipping notifications - save initial state
		$this->info_id = $this->bo->write($info, true, true, true, true);

		// Read it back to check
		$saved = $this->bo->read($this->info_id);

		$this->assertEquals($link_title, $saved['info_contact']['title'], 'Link title was missing');
		$this->assertNotEquals(0, $saved['info_link_id']);

		// Mock the etemplate call to check the results
		$this->ui->tmpl->expects($this->once())
			->method('exec')
			->will(
				$this->returnCallback(function($method, $info) use($link_title) {
					$this->assertNotNull($info['info_id']);
					$this->assertEquals('', $info['contact']['search']);
					$this->assertEquals($GLOBALS['egw_info']['user']['person_id'], $info['info_contact']['id']);
					$this->assertEquals($link_title, $info['info_contact']['title']);
				})
			);

		// Make a call to edit, looks like initial load
		$_REQUEST['info_id'] = $this->info_id;
		$this->ui->edit();
		unset($_REQUEST['info_id']);
	}

	/**
	 * Test that creating a sub-infolog keeps info_contact on the parent
	 *
	 * @ticket 24920
	 */
	public function testSubEntry()
	{
		// Parent needs a project & contact for this
		$content = array(
			'contact' => array(
				'app'   =>	'addressbook',
				'id'    =>	Null,
				'search'=>	'Free text'
			)
		);
		$parent = $this->getTestInfolog($content);

		// Skipping notifications - save initial state
		$parent_id = $this->bo->write($parent, true, true, true, true);

		// Mock the etemplate call to check sub gets parent's contact
		$sub = array();
		$this->ui->tmpl->expects($this->once())
			->method('exec')
			->will(
				$this->returnCallback(function($method, $info) use($parent, &$sub) {
					$this->assertNull($info['info_id']);
					$this->assertEquals($parent['info_id'], $info['info_id_parent']);
					$this->assertEquals($parent['info_contact']['id'], $info['info_contact']['id']);
					$this->assertEquals($parent['info_contact']['app'], $info['info_contact']['app']);
					$this->assertEquals($parent['info_from'], $info['info_from']);
					$sub = $info;
					return true;
				})
			);

		// Make a sub-entry
		$_REQUEST['action'] = 'sp';
		$_REQUEST['action_id'] = $parent['info_id'];
		$this->ui->edit();

		// Skipping notifications - save initial state
		$this->info_id = $this->bo->write($sub, true, true, true, true);

		// Read it back to check
		$saved = $this->bo->read($this->info_id);

		$this->assertEquals($parent['pm_id'], $saved['pm_id']);
		$this->assertEquals($parent['info_from'], $saved['info_from']);
		$this->assertEquals(json_encode($parent['info_contact']), json_encode($saved['info_contact']));
		$this->assertEquals($parent_id, $saved['info_id_parent']);

		// Check parent
		$parent_reload = $this->bo->read($parent_id);

		$this->assertEquals($parent['pm_id'], $parent_reload['pm_id']);
		$this->assertEquals($parent['info_from'], $parent_reload['info_from']);
		$this->assertEquals($parent['info_contact'], $parent_reload['info_contact']);

		// Remove parent (twice, for history preservation)
		$this->bo->delete($parent_id);
		$this->bo->delete($parent_id);
	}

	/**
	 * Set up a basic infolog entry for testing with the specified fields
	 * set.
	 *
	 * @param Array $fields Fields to be set for initial conditions
	 * @return Array
	 */
	protected function getTestInfolog($fields)
	{
		$info = array(
			'info_subject'     =>	'Test Infolog Entry for ' . $this->getName()
		);

		foreach($fields as $field => $value)
		{
			$info["info_{$field}"] = $value;
		}

		return $info;
	}

}
