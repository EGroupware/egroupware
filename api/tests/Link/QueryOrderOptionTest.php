<?php
/**
 * EGroupware Api: regression test for Api\Link::query()'s 'order' option validation
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

require_once realpath(__DIR__.'/../AppTest.php');

/**
 * Api\Link::query() (called from Etemplate\Widget\Link::ajax_link_search(), the search box on
 * every app's "Links" tab) validates a caller-supplied 'order' option against
 * '/^[a-z0-9_]+$/' before handing it to the app's own search backend - to reject anything that
 * isn't a bare column name before it can reach a SQL ORDER BY clause.
 *
 * That check was `!preg_match(preg_match('/.../ , $options['order']))` - the inner call's
 * return value (0/1) was passed as the *pattern* argument to the outer call, which requires at
 * least 2 arguments. Whenever a link-search request included an 'order' option at all (regardless
 * of its value), this threw an uncaught ArgumentCountError, turning every such search into a
 * request that still queued its results to the client (Widget\Link::ajax_link_search() runs
 * fine, since it does not set 'order') but fataled with a 500 elsewhere any code path that
 * populates 'order' before calling query() directly.
 */
class LinkQueryOrderOptionTest extends \EGroupware\Api\AppTest
{
	/**
	 * Pass criteria: a bare column name is accepted and reaches the search backend unchanged, and
	 * no exception is thrown.
	 */
	public function testValidOrderIsKept()
	{
		$options = array('order' => 'info_subject', 'num_rows' => 1);

		Api\Link::query('infolog', 'phpunit_no_such_entry', $options);

		$this->assertSame('info_subject', $options['order']);
	}

	/**
	 * Pass criteria: an invalid 'order' (anything a SQL ORDER BY shouldn't see verbatim) is
	 * stripped instead of throwing - this is what the doubled preg_match() call broke.
	 */
	public function testInvalidOrderIsStrippedNotFatal()
	{
		$options = array('order' => 'info_subject; DROP TABLE egw_infolog', 'sort' => 'DESC', 'num_rows' => 1);

		Api\Link::query('infolog', 'phpunit_no_such_entry', $options);

		$this->assertArrayNotHasKey('order', $options);
		$this->assertArrayNotHasKey('sort', $options);
	}
}
