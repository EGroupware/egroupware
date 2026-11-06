<?php
/**
 * EGroupware Api: a link keeps the last-modification it was created with
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Link;

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

require_once realpath(__DIR__.'/../LoggedInTest.php');

/**
 * Link::link() takes a $lastmod, and used to drop it on its way to storage - every link was
 * stamped with the time it was created instead. Migrating an install's links depends on it: without
 * it they all arrive stamped with the time of the migration, rather than when they were made.
 */
class LinkLastmodTest extends LoggedInTest
{
	/**
	 * @var int[]
	 */
	protected $entries = array();

	protected function tearDown() : void
	{
		$bo = new \infolog_bo();
		foreach($this->entries as $id)
		{
			Api\Link::unlink(0, 'infolog', $id);
			// first delete() only flags an entry deleted and keeps the row, a second removes it
			$bo->delete($id);
			$bo->delete($id);
		}
		$this->entries = array();

		parent::tearDown();
	}

	/**
	 * @return int
	 */
	protected function makeEntry($n)
	{
		$bo = new \infolog_bo();
		$entry = array(
			'info_subject' => 'LinkLastmod '.$n.' #'.$this->name(),
			'info_status'  => 'open',
		);
		$this->entries[] = $id = $bo->write($entry, true, true, true, true);

		return $id;
	}

	public function testLinkKeepsTheLastmodItWasGiven()
	{
		$from = $this->makeEntry(1);
		$to = $this->makeEntry(2);
		$lastmod = mktime(9, 30, 0, 3, 7, 2019);

		$link_id = Api\Link::link('infolog', $from, 'infolog', $to, '', 0, $lastmod);
		$this->assertNotEmpty($link_id);

		$link = Storage::get_link($link_id);
		$this->assertEquals($lastmod, $link['link_lastmod']);
	}

	/**
	 * Without one it is stamped now, as it always was
	 */
	public function testWithoutALastmodItIsStampedNow()
	{
		$from = $this->makeEntry(3);
		$to = $this->makeEntry(4);
		$before = time();

		$link_id = Api\Link::link('infolog', $from, 'infolog', $to);
		$this->assertNotEmpty($link_id);

		$link = Storage::get_link($link_id);
		$this->assertGreaterThanOrEqual($before, (int)$link['link_lastmod']);
		$this->assertLessThanOrEqual(time(), (int)$link['link_lastmod']);
	}
}
