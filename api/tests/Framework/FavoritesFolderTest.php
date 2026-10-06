<?php
/**
 * EGroupware Api: Framework\Favorites folder test
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage framework
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Framework;

require_once __DIR__.'/../LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

/**
 * A favorite can be listed in a folder, which is stored with the favorite.
 *
 * Contract: Favorites::set_favorite() "add" stores the folder it is given, cleaned of markup, in the
 * favorite's preference.  Without a folder the favorite has no folder at all, so existing favorites
 * and the favorites that are not in one look the same as before.
 *
 * Setup: adds a favorite for the logged in user and reads it back from the stored preferences,
 * not from the effective ones, so it is the stored value that is checked.  The favorite is deleted
 * again afterwards.
 *
 * Pass criteria: the stored favorite has `folder` === the cleaned name, or has no `folder` key.
 *
 * The group and default ("for group") variants write the preferences of another account and need
 * an admin, so they are not covered here.
 */
class FavoritesFolderTest extends LoggedInTest
{
	const APP = 'infolog';
	const NAME = 'PHPUnit folder favorite';
	const PREF = 'favorite_PHPUnit_folder_favorite';

	protected function tearDown() : void
	{
		$this->setFavorite('delete', false);
		Api\Json\Response::get()->initResponseArray();
		parent::tearDown();
	}

	/**
	 * Favorites::set_favorite() answers through the process-wide JSON response, which takes one data
	 * answer only, so start every call with an empty one
	 */
	protected function setFavorite(string $action, $group, array $filters = [], $folder = null) : void
	{
		Api\Json\Response::get()->initResponseArray();
		Favorites::set_favorite(self::APP, self::NAME, $action, $group, $filters, $folder);
	}

	/**
	 * The favorite as stored for the user, or null
	 */
	protected function stored() : ?array
	{
		$prefs = new Api\Preferences($GLOBALS['egw_info']['user']['account_id']);
		$prefs->read_repository();
		return $prefs->user[self::APP][self::PREF] ?? null;
	}

	public function testFolderIsStoredWithTheFavorite() : void
	{
		$this->setFavorite('add', false, ['search' => 'x'], 'Work');

		$favorite = $this->stored();
		$this->assertIsArray($favorite, 'the favorite was not stored');
		$this->assertSame('Work', $favorite['folder'] ?? null, 'folder was not stored with the favorite');
		$this->assertSame(['search' => 'x'], $favorite['state'], 'the rest of the favorite was changed');
	}

	public function testFolderNameIsCleaned() : void
	{
		$this->setFavorite('add', false, [], "  <b>Work</b>\t");

		$this->assertSame('Work', $this->stored()['folder'] ?? null, 'markup or white space was stored in the folder name');
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('noFolderProvider')]
	public function testNoFolderIsNotStored($folder) : void
	{
		$this->setFavorite('add', false, ['search' => 'x'], $folder);

		$favorite = $this->stored();
		$this->assertIsArray($favorite, 'the favorite was not stored');
		$this->assertArrayNotHasKey('folder', $favorite, 'an empty folder was stored');
	}

	public static function noFolderProvider() : array
	{
		return [
			'not given' => [null],
			'empty' => [''],
			'only white space and markup' => [" <i></i> \n"],
		];
	}
}
