<?php

/**
 * Test the basic Vfs::StreamWrapper
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2020  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

require_once __DIR__ . '/StreamWrapperBase.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest as LoggedInTest;
use EGroupware\Api\Vfs;
use EGroupware\Stylite\Vfs\Versioning;


class StreamWrapperTest extends StreamWrapperBase
{
	protected function setUp() : void
	{
		parent::setUp();

		$this->files[] = $this->test_file = $this->getFilename();

		// Check that the file is not there
		$pre_start = Vfs::stat($this->test_file);
		$this->assertEquals(null,$pre_start,
				"File '$this->test_file' was there before we started, check clean up"
		);
	}

	protected function tearDown() : void
	{
		// Do local stuff first, parent will remove stuff

		parent::tearDown();
	}

}