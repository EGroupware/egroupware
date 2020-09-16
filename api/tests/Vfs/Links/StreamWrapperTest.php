<?php

/**
 * Test the basic Vfs::StreamWrapper
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2020  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs\Links;

require_once __DIR__ . '/../StreamWrapperBase.php';

use EGroupware\Api;
use EGroupware\Api\Vfs;


class StreamWrapperTest extends Vfs\StreamWrapperBase
{
	protected $entries = [];

	protected function setUp() : void
	{
		parent::setUp();

		$this->mountLinks('/apps');

		$info_id = $this->make_infolog();
		$this->files[] = $this->test_file = $this->getFilename(null, $info_id);

		// Check that the file is not there
		$pre_start = Vfs::stat($this->test_file);
		$this->assertEquals(null,$pre_start,
				"File '$this->test_file' was there before we started, check clean up"
		);

	}

	protected function tearDown() : void
	{
		// Do local stuff first, parent will remove stuff that is needed

		$bo = new \infolog_bo();
		foreach($this->entries as $entry)
		{
			$bo->delete($entry);
		}

		parent::tearDown();
	}

	/**
	 * Make an infolog entry
	 */
	protected function make_infolog()
	{
		$bo = new \infolog_bo();
		$element = array(
				'info_subject' => "Test infolog for #{$this->getName()}",
				'info_des' => 'Test element for ' . $this->getName() . "\n" . Api\DateTime::to(),
				'info_status' => 'open'
		);

		$element_id = $bo->write($element, true, true, true, true);
		$this->entries[] = $element_id;
		return $element_id;
	}
	/**
	 * Make a filename that reflects the current test
	 */
	protected function getFilename($path, $info_id)
	{
		if(is_null($path)) $path = '/apps/infolog/';
		if(substr($path,-1,1) !== '/') $path = $path . '/';
		$reflect = new \ReflectionClass($this);
		return $path .$info_id .'/'. $reflect->getShortName() . '_' . $this->getName() . '.txt';
	}

}