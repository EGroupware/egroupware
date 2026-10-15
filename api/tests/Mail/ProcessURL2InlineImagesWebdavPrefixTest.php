<?php
/**
 * EGroupware Api: Test Mail::processURL2InlineImages()'s $allowed_webdav_prefix
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../LoggedInTest.php');

use EGroupware\Api;
use EGroupware\Api\Mail;
use EGroupware\Api\Mailer;
use EGroupware\Api\Vfs;

/**
 * Extends Api\LoggedInTest, not a bare TestCase: the vfs:// stream wrapper needs a real logged-in
 * session/ACL context even to fail gracefully on a missing file (see
 * [[feedback_bare_testcase_poisons_account_db]] and AccountEmbedSignatureImagesTest's own
 * docblock for the same reasoning applied to the sibling embedSignatureImages() helper).
 *
 * Ticket #126501: notifications_email.inc.php needs webdav.php images (eg. a tracker reply's
 * screenshot, which Et2HtmlArea[imageUpload=link_to] only ever references that way, never a
 * data: URI) embedded again - but ONLY when $allowed_webdav_prefix is given AND the url's path
 * actually starts with it, never for any other VFS path. See
 * Api\Mail::processURL2InlineImages()'s own docblock for why: that content can originate from an
 * unauthenticated-to-EGroupware source (eg. a ticket reply imported from an incoming email) and
 * gets auto-sent without review, so a caller allowing every webdav.php url the way the pre-#125961
 * code did would let anyone who can get such a url into that content trick a later, staff-
 * triggered notification into reading an arbitrary VFS path with the triggering staff member's
 * own permissions and mailing it back to them.
 *
 * The actual "real VFS read succeeds" path is NOT covered here - that needs the heavyweight
 * admin-command/DB-user VFS mount setup StreamWrapperBase itself needs, out of scope for this
 * helper (same scoping choice AccountEmbedSignatureImagesTest already makes for its sibling).
 * Covered instead: the prefix DOES match but the file genuinely doesn't exist (the one case that
 * needs vfs:// to actually run, not just string-match and bail early).
 */
class ProcessURL2InlineImagesWebdavPrefixTest extends Api\LoggedInTest
{
	public function testWebdavUrlInsideAllowedPrefixButFileMissingIsLeftUnchanged()
	{
		$mailer = new Mailer(false);
		$html = '<img src="/webdav.php/apps/tracker/999999/does-not-exist-'.uniqid().'.png">';
		$before = $html;

		$result = Mail::processURL2InlineImages($mailer, $html, null, '/apps/tracker/999999/');

		$this->assertSame($before, $html);
		$this->assertSame('', $result[0]['file']);
	}
}
