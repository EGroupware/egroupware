<?php
/**
 * Tests for the actions calendar adds to its popup notifications
 *
 * The client runs an action's onExecute as javascript (notifications/js/app.ts),
 * so everything put into it has to be encoded as a javascript literal.
 * The videoconference url of an alarm contains the room id of the event,
 * which can be set by whoever created the event, eg. via CalDAV.
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

use EGroupware\Api\LoggedInTest;

require_once realpath(__DIR__ . '/../../api/tests/LoggedInTest.php');

class NotificationsActionsTest extends LoggedInTest
{
	/**
	 * Url with characters ending a javascript string literal
	 */
	const URL = 'https://meet.example.org/room");window.injected=1;//\\';

	/**
	 * Apps of the test user, to restore them
	 *
	 * @var mixed
	 */
	protected $apps;

	protected function setUp() : void
	{
		// MSG_* constants
		class_exists(\calendar_boupdate::class);

		// the Join action needs the status app, independent of the rights of the test user
		$this->apps = $GLOBALS['egw_info']['user']['apps'] ?? null;
		$GLOBALS['egw_info']['user']['apps']['status'] = true;
	}

	protected function tearDown() : void
	{
		$GLOBALS['egw_info']['user']['apps'] = $this->apps;
	}

	/**
	 * The Join action of an alarm must pass the url as one string literal
	 */
	public function testAlarmJoinActionEncodesUrl() : void
	{
		if (\calendar_hooks::isVideoconferenceDisabled())
		{
			$this->markTestSkipped('Videoconference is disabled, alarms have no Join action');
		}
		$actions = \calendar_hooks::notifications_actions(['data' => [
			'type' => MSG_ALARM,
			'videoconference' => self::URL,
		]]);

		$this->assertCount(1, $actions);
		$this->assertSame('app.status.openCall('.json_encode(self::URL).');', $actions[0]['onExecute']);
	}

	/**
	 * The answer actions of an invitation must stay encoded too
	 */
	public function testInvitationActionsEncodeIds() : void
	{
		$id = '1"]);window.injected=1;//';
		$actions = \calendar_hooks::notifications_actions(['data' => [
			'type' => MSG_REQUEST,
			'event_id' => $id,
			'user_id' => $id,
		]]);

		$this->assertCount(3, $actions);
		foreach($actions as $action)
		{
			$this->assertStringStartsWith('egw().request("calendar.calendar_uiforms.ajax_status",'.
				json_encode([$id, $id, $action['id']]).')', $action['onExecute']);
		}
	}
}
