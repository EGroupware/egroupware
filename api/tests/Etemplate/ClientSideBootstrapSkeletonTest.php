<?php
/**
 * Etemplate::clientSideBootstrap() tells the client whether the form shows a skeleton while it is built
 *
 * A form built client-side (mail compose) never goes through Etemplate::exec(), which is where the decision is made
 * for everything else, so it has to come with the bootstrap.
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage etemplate
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate;

require_once __DIR__.'/../LoggedInTest.php';

use EGroupware\Api\Etemplate;
use EGroupware\Api\LoggedInTest;

class ClientSideBootstrapSkeletonTest extends LoggedInTest
{
	protected function setUp() : void
	{
		parent::setUp();
		Etemplate::reset_request();
	}

	public function testComposeHasOneByConvention() : void
	{
		$bootstrap = Etemplate::clientSideBootstrap('mail.compose');
		$this->assertTrue($bootstrap['skeleton']);
		$this->assertSame('mail.compose', $bootstrap['name']);
	}

	public function testTheCallerCanTurnItOffOrOn() : void
	{
		$this->assertFalse(Etemplate::clientSideBootstrap('mail.compose', false)['skeleton']);
		$this->assertTrue(Etemplate::clientSideBootstrap('infolog.index', true)['skeleton']);
	}

	public function testNotForAnythingThatIsNotAForm() : void
	{
		$this->assertFalse(Etemplate::clientSideBootstrap('infolog.index')['skeleton']);
	}
}
