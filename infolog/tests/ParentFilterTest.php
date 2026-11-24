<?php
/**
 * Test the filter for the parent of sub-entries in the InfoLog list
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api;

/**
 * The list sets col_filter[info_id_parent] itself to show the sub-entries of an entry. It is
 * registered as extra attribute of the nextmatch, so no filter widget checks it:
 * infolog_ui::parentFilter() has to.
 */
class ParentFilterTest extends Api\AppTest
{
	public function testAnIdOrAListOfIdsIsKept()
	{
		$this->assertSame('1485', \infolog_ui::parentFilter('1485'));
		$this->assertSame('1485', \infolog_ui::parentFilter(1485));
		$this->assertSame('0', \infolog_ui::parentFilter('0'), 'no parent: only main entries');
		$this->assertSame([1485, 1441], \infolog_ui::parentFilter(['1485', 1441]));
	}

	public function testEverythingElseIsRemoved()
	{
		$this->assertNull(\infolog_ui::parentFilter('1485 OR 1=1'));
		$this->assertNull(\infolog_ui::parentFilter('-5'));
		$this->assertNull(\infolog_ui::parentFilter(['id' => ['nested' => 1]]));
		$this->assertSame([7], \infolog_ui::parentFilter(['7', 'x', ['8']]), 'only the ids of a list are kept');
	}

	/**
	 * The filter is registered for the nextmatch of the list, or validating its filters would drop it.
	 */
	public function testFilterIsRegisteredForTheList()
	{
		$source = file_get_contents(__DIR__.'/../inc/class.infolog_ui.inc.php');
		$this->assertMatchesRegularExpression("/'extra_attributes'\\]\\s*=\\s*\\[[^\\]]*'col_filter\\[info_id_parent\\]'/", $source);
	}
}
