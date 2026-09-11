<?php
/**
 * EGroupware Mail: Test Ui\MessageActionHandler::statusCriteria()
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail\Ui;

/**
 * doc/ai/projects/mail-test-coverage.md's priority-4 entry: MessageActionHandler.php's own
 * methods are almost entirely real mailbox-mutating logic needing a live IMAP connection
 * (overlaps priority 1, deliberately deprioritized), but statusCriteria() is a small, pure helper
 * with none of that - combines a "select all matching filter" request's status filter and the
 * app-header flagFilter (nextmatch's col_filter[flagFilter]) into the criteria list
 * Mail::createIMAPFilter() consumes. No IMAP/DB dependency at all, tested directly via
 * ReflectionMethod (private static).
 */
class MessageActionHandlerStatusCriteriaTest extends \PHPUnit\Framework\TestCase
{
	private function invoke(array $query) : array
	{
		$method = new \ReflectionMethod(MessageActionHandler::class, 'statusCriteria');
		$method->setAccessible(true);
		return $method->invoke(null, $query);
	}

	public function testCombinesBothCriteriaWhenBothArePresentFilterFirst()
	{
		$result = $this->invoke(['filter' => 'unseen', 'col_filter' => ['flagFilter' => 'flagged']]);

		$this->assertSame(['unseen', 'flagged'], $result);
	}

	public function testReturnsOnlyTheStatusFilterWhenFlagFilterIsAbsent()
	{
		$result = $this->invoke(['filter' => 'unseen']);

		$this->assertSame(['unseen'], $result);
	}

	public function testReturnsOnlyTheFlagFilterWhenTheStatusFilterIsAbsent()
	{
		$result = $this->invoke(['col_filter' => ['flagFilter' => 'flagged']]);

		$this->assertSame(['flagged'], $result);
	}

	public function testReturnsAnEmptyArrayWhenNeitherIsSet()
	{
		$this->assertSame([], $this->invoke([]));
	}

	public function testReturnsAnEmptyArrayWhenBothAreEmptyStrings()
	{
		$result = $this->invoke(['filter' => '', 'col_filter' => ['flagFilter' => '']]);

		$this->assertSame([], $result);
	}

	public function testDoesNotErrorWhenColFilterKeyIsEntirelyMissing()
	{
		$this->assertSame(['unseen'], $this->invoke(['filter' => 'unseen']));
	}
}
