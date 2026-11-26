<?php
/**
 * EGroupware home: regression test for home_favorite_portlet's stored-favorite state merge
 *
 * @link http://www.egroupware.org
 * @package home
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * get_rows must always be decided server-side, never read back from a stored favorite's
 * 'state'. Tested on the base class directly, so the merge itself is covered regardless of
 * what any particular app's portlet subclass sets up around it.
 */
class FavoritePortletStateGetRowsTest extends \EGroupware\Api\AppTest
{
	const APP = 'phpunitfixture_favorite_state';

	protected function tearDown() : void
	{
		unset($GLOBALS['egw_info']['user']['preferences'][self::APP]);
		parent::tearDown();
	}

	public function testPoisonedFavoriteStateCannotInjectGetRows()
	{
		$GLOBALS['egw_info']['user']['preferences'][self::APP]['favorite_poisoned'] = array(
			'name'  => 'Poisoned',
			'group' => false,
			'state' => array(
				// must never survive into nm_settings
				'get_rows' => 'some.other.callback',
				// other state content must still be applied
				'search'   => 'from-favorite',
			),
		);
		$context = array('appname' => self::APP, 'favorite' => 'poisoned');
		$need_reload = false;

		$portlet = new home_favorite_portlet($context, $need_reload);

		$ref = new ReflectionProperty('home_favorite_portlet', 'nm_settings');
		$ref->setAccessible(true);
		$nm_settings = $ref->getValue($portlet);

		$this->assertArrayNotHasKey('get_rows', $nm_settings,
			"a 'get_rows' key from a stored favorite's state must never reach nm_settings here");
		$this->assertSame('from-favorite', $nm_settings['search'] ?? null,
			"the favorite's other state content must still be applied");
	}
}
