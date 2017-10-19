<?php

/**
 * Test for IncludeMgr
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package api
 * @subpackage framework
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

use EGroupware\Api\Framework;
use PHPUnit\Framework\TestCase as TestCase;

/**
 * Tests for IncludeMgr
 *
 */
class IncludeMgrTest extends TestCase
{
	public function testEmpty()
	{
		$mgr = new Framework\IncludeMgr();
		$this->assertEmpty($mgr->get_included_files());
	}

	/**
	 * Tests by checking api/js/jsapi/egw_config.js, which requires egw_core.js
	 */
	public function testConfig()
	{
		$mgr = new Framework\IncludeMgr();
		$mgr->include_js_file('/api/js/jsapi/egw_config.js');
		$this->assertEquals(
				array('/api/js/jsapi/egw_core.js', '/api/js/jsapi/egw_config.js'),
				$mgr->get_included_files(true)
		);
	}
}
