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

	public function testSimpleReadWrite(): string
	{
		$info_id = $this->make_infolog();
		$this->files[] = $this->test_file = $this->getInfologFilename(null, $info_id);

		return parent::testSimpleReadWrite();
	}

	public function testNoReadAccess(): void
	{
		$info_id = $this->make_infolog();
		$this->files[] = $this->test_file = $this->getInfologFilename(null, $info_id);

		parent::testNoReadAccess();
	}

	public function testWithAccess(): void
	{
		$info_id = $this->make_infolog();
		$this->files[] = $this->test_file = $this->getInfologFilename(null, $info_id);

		parent::testWithAccess();
	}
	/**
	 * Test that we can work through/with a symlink
	 *
	 * @throws Api\Exception\AssertionFailed
	 */
	public function testSymlinkFromFolder($test_file = '') : void
	{
		$info_id = $this->make_infolog();
		$this->files[] = $this->test_file = $this->getInfologFilename(null, $info_id);

		parent::testSymlinkFromFolder($this->test_file);
	}

	protected function allowAccess(string $test_name, string &$test_file, int $test_user, string $needed) : void
	{
		// Make sure user has infolog run rights
		$command = new \admin_cmd_acl(true, $test_user,'infolog','run',Api\Acl::READ);
		$command->run();

		// We'll allow access by putting test user in responsible
		$so = new \infolog_so();
		$element = $so->read(Array('info_id' => $this->entries[0]));
		$element['info_responsible'] = [$test_user];
		$so->write($element);
	}

	protected function mount() : void
	{
		$this->mountLinks('/apps');
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
	 * @param $info_id
	 * @return string
	 * @throws \ReflectionException
	 */
	protected function getInfologFilename($path, $info_id)
	{
		if(is_null($path)) $path = '/apps/infolog/';
		if(substr($path,-1,1) !== '/') $path = $path . '/';
		$reflect = new \ReflectionClass($this);
		return $path .$info_id .'/'. $reflect->getShortName() . '_' . $this->getName() . '.txt';
	}

}