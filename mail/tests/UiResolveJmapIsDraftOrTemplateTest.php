<?php
/**
 * EGroupware Mail: Ui::resolveJmapIsDraftOrTemplate()'s real-JMAP Templates-folder fallback
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Mail;
use EGroupware\Api\Mail\Jmap\Http;
use EGroupware\Mail\Ui;

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

/**
 * Ticket-driven regression (Nordenham, 2026-11-25, addjan01/ralspe01): opening a message stored in
 * the account's Templates folder always showed it read-only instead of redirecting to compose -
 * Ui::displayMessage()'s is_jmap branch asked a real JMAP server (Stalwart) for the folder's own
 * "role", but Stalwart never tags Templates/Outbox with a role at all (no IMAP SPECIAL-USE
 * attribute or JMAP role exists for either - already documented client-side, see mail/js/jmap.ts's
 * own templatesFolder docblock and applyTemplateOutboxRoles()), so $role was unconditionally null
 * for that folder and only 'drafts' (a real role) ever triggered the compose redirect.
 *
 * resolveJmapIsDraftOrTemplate() was factored out of displayMessage() specifically so this is
 * testable without going through Egw::redirect_link()'s own exit() - the fake JMAP session here is
 * the same fake-Http-subclass technique ImportHandlerImportMessageToFolderTest.php already
 * established (Http's own constructor does a real network bootstrap, so a no-op override is needed
 * either way).
 */
class UiResolveJmapIsDraftOrTemplateTest extends Api\LoggedInTest
{
	/** @param ?array $mailboxGetList the canned 'Mailbox/get' response's own 'list' entry (null = not found) */
	private function fakeHttp(?array $mailboxGetList) : Http
	{
		return new class($mailboxGetList) extends Http
		{
			public function __construct(private ?array $mailboxGetList) { $this->accountId = 'acc1'; }
			public function call(string $method, array $args) : array
			{
				if ($method === 'Mailbox/get')
				{
					return ['list' => $this->mailboxGetList !== null ? [$this->mailboxGetList] : []];
				}
				throw new \Exception("unexpected call $method");
			}
		};
	}

	private function fakeJmapIcServer(Http $fakeSession, string $acc_folder_template='') : Mail\Imap\Jmap
	{
		return new class($fakeSession, $acc_folder_template) extends Mail\Imap\Jmap
		{
			public function __construct(private Http $fakeSession, private string $acc_folder_template) {}
			public function jmapClient() { return $this->fakeSession; }
			public function __get($name) { return $name === 'acc_folder_template' ? $this->acc_folder_template : null; }
		};
	}

	private function call(?string $folderID, object $icServer, string $icServerID='1') : array
	{
		$ui = new Ui(false);
		$method = new ReflectionMethod(Ui::class, 'resolveJmapIsDraftOrTemplate');
		$method->setAccessible(true);
		return $method->invoke($ui, $folderID, $icServer, $icServerID);
	}

	public function testStalwartNeverReturnsATemplatesRoleButNameMatchFallsBackCorrectly()
	{
		$icServer = $this->fakeJmapIcServer(
			$this->fakeHttp(['id' => 'm1', 'role' => null, 'name' => 'Vorlagen']),
			'Vorlagen'
		);
		[$isDraftOrTemplate, $isDraft] = $this->call('m1', $icServer);

		$this->assertTrue($isDraftOrTemplate);
		$this->assertFalse($isDraft);
	}

	public function testNameMatchIsCaseInsensitiveAndDefaultsToTemplatesWhenUnconfigured()
	{
		$icServer = $this->fakeJmapIcServer(
			$this->fakeHttp(['id' => 'm1', 'role' => null, 'name' => 'TEMPLATES']),
			'' // unconfigured - falls back to the literal default 'Templates'
		);
		[$isDraftOrTemplate] = $this->call('m1', $icServer);

		$this->assertTrue($isDraftOrTemplate);
	}

	public function testRealRoleStillWinsForDrafts()
	{
		$icServer = $this->fakeJmapIcServer(
			$this->fakeHttp(['id' => 'm1', 'role' => 'drafts', 'name' => 'Drafts']),
			'Templates'
		);
		[$isDraftOrTemplate, $isDraft] = $this->call('m1', $icServer);

		$this->assertTrue($isDraftOrTemplate);
		$this->assertTrue($isDraft);
	}

	public function testAnOrdinaryFolderWithNoMatchingNameIsNeitherDraftNorTemplate()
	{
		$icServer = $this->fakeJmapIcServer(
			$this->fakeHttp(['id' => 'm1', 'role' => null, 'name' => 'Sterbefälle']),
			'Vorlagen'
		);
		[$isDraftOrTemplate, $isDraft] = $this->call('m1', $icServer);

		$this->assertFalse($isDraftOrTemplate);
		$this->assertFalse($isDraft);
	}

	public function testEmptyFolderIdNeverCallsTheServerAndIsNeitherDraftNorTemplate()
	{
		$icServer = $this->fakeJmapIcServer($this->fakeHttp(null));
		[$isDraftOrTemplate, $isDraft] = $this->call(null, $icServer);

		$this->assertFalse($isDraftOrTemplate);
		$this->assertFalse($isDraft);
	}
}
