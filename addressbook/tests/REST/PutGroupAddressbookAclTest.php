<?php
/**
 * REST API tests: creating a contact in a GROUP addressbook via PUT (ticket #125601)
 *
 * aERP syncs contacts with "PUT /<user>/addressbook-<group>/<uid>" and a JSContact body. The group
 * addressbook is named after the group (here: "addressbook-infor" for the group "Infor") and the
 * calling user needs the rights the GROUP granted to him (acl_account = group, acl_location = user,
 * i.e. the reverse direction of Api\Acl::check()), not rights he granted to the group.
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package addressbook
 * @subpackage tests
 * @copyright (c) 2026 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\addressbook\REST;

require_once __DIR__.'/../../../api/tests/RestBase.php';

use EGroupware\Api\Acl;
use EGroupware\Api\RestBase;
use GuzzleHttp\RequestOptions;

class PutGroupAddressbookAclTest extends RestBase
{
	/**
	 * account_lid of the group, mixed case on purpose: aERP uses the lower-case name in the URL
	 *
	 * @var string
	 */
	protected static $group;

	/**
	 * account_id (negative) of the group
	 *
	 * @var int
	 */
	protected static $group_id;

	/**
	 * Users by scenario, account_lid => account_id
	 *
	 * @var int[]
	 */
	protected static $users = [];

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		$suffix = bin2hex(random_bytes(3));
		self::$group = 'Infor'.$suffix;
		$data = ['primary_group' => false];	// false: create a group, not a user
		self::$group_id = self::createUser(self::$group, $data);
		self::assertLessThan(0, self::$group_id, 'Group should have a negative account_id');

		foreach([
			'full'   => Acl::READ|Acl::ADD|Acl::EDIT|Acl::DELETE,	// as reported for "aerpegapi"
			'noadd'  => Acl::READ|Acl::EDIT|Acl::DELETE,
			'nogrant' => 0,
		] as $scenario => $rights)
		{
			$lid = 'abput'.$scenario.$suffix;
			$user = [];
			self::$users[$scenario] = self::createUser($lid, $user);
			if ($rights)
			{
				// grantor (acl_account) is the group, grantee (acl_location) the user
				self::addAcl('addressbook', (string)self::$users[$scenario], self::$group_id, $rights);
			}
			self::$users[$scenario] = $lid;
		}
	}

	/**
	 * createUser() registers the group like a user, but admin_cmd_delete_account refuses groups
	 * ("NO User") --> remove the group and the rights it granted ourselves, before the parent cleans up users.
	 */
	public static function tearDownAfterClass() : void
	{
		if (self::$group_id)
		{
			try
			{
				$GLOBALS['egw']->accounts->delete(self::$group_id);
				$GLOBALS['egw']->db->delete(Acl::TABLE, ['acl_account' => self::$group_id], __LINE__, __FILE__);
			}
			catch (\Throwable $e)
			{
				// ignore cleanup failures to avoid masking the test result
			}
		}
		parent::tearDownAfterClass();
	}

	/**
	 * Collection URL the way aERP addresses it: /<user>/addressbook-<lower-case group name>/
	 */
	protected function groupCollection(string $user) : string
	{
		return '/'.$user.'/addressbook-'.strtolower(self::$group).'/';
	}

	/**
	 * JsContact like aERP sends it (empty phone numbers / url, categories, notes, address with street parts)
	 */
	protected function aerpContact(string $uid) : array
	{
		return [
			'kind' => 'individual',
			'name' => [
				['@type' => 'NameComponent', 'type' => 'title', 'value' => 'Mr.'],
				['@type' => 'NameComponent', 'type' => 'given', 'value' => 'Greg'],
				['@type' => 'NameComponent', 'type' => 'surname', 'value' => 'Kwiat'],
				['@type' => 'NameComponent', 'type' => 'suffix', 'value' => ''],
			],
			'fullName' => 'Mr. Greg Kwiat',
			'organizations' => ['org' => ['@type' => 'Organization', 'name' => 'KWIAT Inc.']],
			'emails' => ['work' => ['@type' => 'EmailAddress', 'email' => 'greg@kwiat.example.org',
				'contexts' => ['work' => true], 'pref' => 1]],
			'online' => ['url' => ['@type' => 'Resource', 'resource' => '', 'type' => 'uri', 'contexts' => ['work' => true]]],
			'addresses' => ['work' => ['@type' => 'Address', 'locality' => 'New York', 'region' => '',
				'postcode' => '10022', 'countryCode' => 'US',
				'street' => [
					['@type' => 'StreetComponent', 'type' => 'name', 'value' => 'Suite 1400'],
					['@type' => 'StreetComponent', 'type' => 'separator', 'value' => "\n"],
					['@type' => 'StreetComponent', 'type' => 'name', 'value' => '555 Madison Avenue'],
				], 'contexts' => ['work' => true], 'pref' => 1]],
			'notes' => ['EH 20T / Ab Feb 2018', ''],
			'categories' => ['Kunden' => true],
			'phones' => [
				'tel_work' => ['@type' => 'Phone', 'phone' => ''],
				'tel_fax' => ['@type' => 'Phone', 'phone' => ''],
				'tel_cell' => ['@type' => 'Phone', 'phone' => ''],
			],
			'uid' => 'urn:uuid:'.$uid,
		];
	}

	protected function putContact(string $user, string $uid) : \Psr\Http\Message\ResponseInterface
	{
		return $this->getClient($user)->put($this->url($this->groupCollection($user).$uid), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody($this->aerpContact($uid)),
		]);
	}

	/**
	 * Sanity check of the scenario setup: the group addressbook is reachable by name for a user holding its rights.
	 *
	 * Pass criteria:
	 * - GET of the group collection as the user with full rights is not 403/404.
	 */
	public function testGroupCollectionResolvesByName()
	{
		$response = $this->getClient(self::$users['full'])->get($this->url($this->groupCollection(self::$users['full'])), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
		]);
		$this->assertHttpStatus(200, $response, 'GET '.$this->groupCollection(self::$users['full']));
	}

	/**
	 * aERP's case: PUT of a new contact (client generated uid) into a group addressbook with READ|ADD|EDIT|DELETE
	 * granted by the group to the user.
	 *
	 * Pass criteria:
	 * - HTTP 201/204, NOT 403 (ticket #125601).
	 * - the contact can be read back from the group addressbook.
	 */
	public function testPutNewContactWithAllGrants()
	{
		$user = self::$users['full'];
		$uid = $this->makeUid('abput');
		$response = $this->putContact($user, $uid);
		$this->assertHttpStatus([201, 204], $response, 'PUT new contact into group addressbook');

		$read = $this->getClient($user)->get($this->url($this->groupCollection($user).$uid), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
		]);
		$this->assertHttpStatus(200, $read, 'reading back the created contact');
		$this->assertSame('Mr. Greg Kwiat', $this->jsonDecode($read)['fullName'] ?? null);
	}

	/**
	 * Same PUT again (aERP syncs repeatedly): updating the now existing contact must work too.
	 *
	 * Pass criteria:
	 * - second PUT of the same uid returns 201/204, not 403/412.
	 */
	public function testPutExistingContactWithAllGrants()
	{
		$user = self::$users['full'];
		$uid = $this->makeUid('abput');
		$this->assertHttpStatus([201, 204], $this->putContact($user, $uid), 'create');
		$this->assertHttpStatus([200, 201, 204], $this->putContact($user, $uid), 'update');
	}

	/**
	 * Control: without the ADD grant, creating a contact must be refused.
	 *
	 * Pass criteria:
	 * - HTTP 403.
	 */
	public function testPutNewContactWithoutAddGrantIsForbidden()
	{
		$response = $this->putContact(self::$users['noadd'], $this->makeUid('abput'));
		$this->assertHttpStatus(403, $response);
	}

	/**
	 * Control: a user the group granted nothing must not be able to create contacts either.
	 *
	 * Pass criteria:
	 * - HTTP 403 (or 404 if the group addressbook is not even visible to him).
	 */
	public function testPutNewContactWithoutAnyGrantIsRefused()
	{
		$response = $this->putContact(self::$users['nogrant'], $this->makeUid('abput'));
		$this->assertHttpStatus([403, 404], $response);
	}
}
