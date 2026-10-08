<?php
/**
 * EGroupware Api: test Mail\Account::read_identity() self-heals a stale webdav.php signature
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Mail;

require_once realpath(__DIR__.'/../LoggedInTest.php');

/**
 * Ticket #125961 follow-up: a signature saved before embedSignatureImages() existed (or by a
 * client that never re-triggers a save) can still carry a '/webdav.php' image reference that
 * only works for whoever has that exact account's own session.
 *
 * Deliberately self-healed on READ (read_identity()), not via a one-off DB migration: a bulk
 * migration has no reliable per-identity user/VFS-ACL context (different identities can be
 * owned by, or shared with, different users, and the same-origin check needs the right host) -
 * reading runs inside the actual owning user's real session, which a migration can't reproduce.
 */
class AccountReadIdentitySelfHealsWebdavSignatureTest extends Api\LoggedInTest
{
	private array $identIds = [];
	private array $vfsPaths = [];
	private $originalHttpHost;

	protected function setUp() : void
	{
		parent::setUp();
		$this->originalHttpHost = $_SERVER['HTTP_HOST'] ?? null;
		$_SERVER['HTTP_HOST'] = Api\Header\Http::host();
	}

	protected function tearDown() : void
	{
		foreach ($this->identIds as $ident_id)
		{
			$GLOBALS['egw']->db->delete('egw_ea_identities', ['ident_id' => $ident_id], __LINE__, __FILE__, 'api');
		}
		foreach ($this->vfsPaths as $path)
		{
			Api\Vfs::remove($path);
		}
		if ($this->originalHttpHost === null)
		{
			unset($_SERVER['HTTP_HOST']);
		}
		else
		{
			$_SERVER['HTTP_HOST'] = $this->originalHttpHost;
		}
		parent::tearDown();
	}

	private function seedIdentity(string $signature) : int
	{
		$db = $GLOBALS['egw']->db;
		$db->insert('egw_ea_identities', [
			'ident_realname' => 'phpunit-readheal-test',
			'ident_email' => 'phpunit-readheal-test@example.invalid',
			'acc_id' => 0,
			'account_id' => $GLOBALS['egw_info']['user']['account_id'],
			'ident_signature' => $signature,
		], false, __LINE__, __FILE__, 'api');
		$ident_id = (int)$db->get_last_insert_id('egw_ea_identities', 'ident_id');
		$this->identIds[] = $ident_id;
		return $ident_id;
	}

	private function readStoredSignature(int $ident_id) : string
	{
		$db = $GLOBALS['egw']->db;
		$db->select('egw_ea_identities', 'ident_signature', ['ident_id' => $ident_id], __LINE__, __FILE__, false, '', 'api');
		return $db->row(true)['ident_signature'];
	}

	private function seedVfsImage(string $path) : void
	{
		$image = imagecreatetruecolor(10, 10);
		imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
		ob_start();
		imagepng($image);
		$bytes = ob_get_clean();
		imagedestroy($image);

		$backup = Api\Vfs::$is_root;
		Api\Vfs::$is_root = true;
		file_put_contents(Api\Vfs::PREFIX.$path, $bytes);
		Api\Vfs::$is_root = $backup;
		$this->vfsPaths[] = $path;
	}

	public function testReadingAStaleWebdavSignatureConvertsAndPersistsItImmediately()
	{
		$path = '/home/'.$GLOBALS['egw_info']['user']['account_lid'].'/phpunit-'.uniqid().'.png';
		$this->seedVfsImage($path);
		$ident_id = $this->seedIdentity('<p>Best regards</p><img src="/webdav.php'.$path.'">');

		$data = Mail\Account::read_identity($ident_id);

		$this->assertStringNotContainsString('/webdav.php', $data['ident_signature'],
			'the returned value must already be converted for the caller using it right now');
		$this->assertStringContainsString('data:image/png;base64,', $data['ident_signature']);

		$stored = $this->readStoredSignature($ident_id);
		$this->assertSame($data['ident_signature'], $stored,
			'the conversion must be persisted immediately, not just returned once');
	}

	public function testReadingAnAlreadyCleanSignatureTwiceDoesNotRewriteTheRow()
	{
		$ident_id = $this->seedIdentity('<p>No image here at all</p>');

		Mail\Account::read_identity($ident_id);
		$firstRead = $this->readStoredSignature($ident_id);
		Mail\Account::read_identity($ident_id);
		$secondRead = $this->readStoredSignature($ident_id);

		$this->assertSame('<p>No image here at all</p>', $firstRead);
		$this->assertSame($firstRead, $secondRead);
	}
}
