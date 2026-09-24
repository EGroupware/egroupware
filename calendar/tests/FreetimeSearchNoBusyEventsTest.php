<?php
/**
 * EGroupware calendar: regression test for freetime search finding nothing when a participant
 * has no conflicting events at all in the searched window
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * Regression coverage for the "Freetime Search" popup showing NO time slots at all after
 * clicking "New search", reported via https://help.egroupware.org/t/.../80008.
 *
 * calendar_uiforms::freetime() appends a synthetic "end of search" event to $busy, purely to
 * give the loop below a boundary to compute the trailing freetime slot against ("to cope with
 * empty search and get freetime til that date", per its own comment). Commit ca8a6328d5 later
 * added a per-event "does at least one wanted participant have a non-rejected status in this
 * event" filter to stop rejected meeting-requests being reported as busy/conflicting - but that
 * filter runs on the synthetic event too, which has no 'participants' key at all, so it never
 * passes and gets skipped via the loop's own `continue`. When there is no OTHER (real) busy
 * event in the search window either, this was the ONLY entry in $busy, so the loop produced
 * nothing and freetime() returned an empty array - even though the entire window is free.
 *
 * Pass criterion: searching for freetime for a participant with zero conflicting events in the
 * window must return that whole window as one free slot, not an empty result.
 */
class FreetimeSearchNoBusyEventsTest extends \EGroupware\Api\AppTest
{
	/**
	 * @var \calendar_uiforms
	 */
	protected $uiforms;

	protected function setUp() : void
	{
		parent::setUp();
		$this->uiforms = new \calendar_uiforms();
	}

	/**
	 * Uses the logged-in test user as the sole participant, so no admin session / new account
	 * is needed - just a search window far enough in the future that this shared dev/test
	 * instance cannot already have a conflicting event there.
	 */
	public function testEmptyCalendarReturnsWholeWindowAsFree()
	{
		$me = $GLOBALS['egw_info']['user']['account_id'];

		$start = strtotime('2099-01-05 09:00:00');	// a Monday, far in the future
		$end = strtotime('2099-01-05 17:00:00');

		$freetime = $this->uiforms->freetime([$me], $start, $end, 3600);

		$this->assertNotEmpty($freetime,
			'freetime() returned no slots at all for a participant with zero conflicting events - '.
			'this is the "New search shows no freetime" regression');
		$slot = reset($freetime);
		$this->assertSame($start, $slot['start']);
		$this->assertSame($end, $slot['end']);
	}
}
