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

use Egroupware\Api\Contacts;

class ContactTest extends \EGroupware\Api\AppTest
{

	protected $ui;
	protected $bo;

	// Infolog under test
	protected $info_id = null;

	public function setUp()
	{
		$this->ui = new \infolog_ui();

		$this->ui->tmpl = $this->getMockBuilder('\\Egroupware\\Api\\Etemplate')
			->disableOriginalConstructor()
			->setMethods(array('exec', 'read'))
			->getMock($this->ui);
		$this->bo = $this->ui->bo;

		$this->mockTracking($this->bo, 'infolog_tracking');
	}

	public function tearDown()
	{
		// Double delete to make sure it's gone, not preserved due to history setting
		$this->bo->delete($this->info_id);
		$this->bo->delete($this->info_id);

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
