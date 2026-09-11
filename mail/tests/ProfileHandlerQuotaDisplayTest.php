<?php
/**
 * EGroupware Mail: Mail\Ui\ProfileHandler::quotaDisplay() tests
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

use EGroupware\Mail\Ui\ProfileHandler;
use PHPUnit\Framework\TestCase;

/**
 * Pure tests for ProfileHandler::quotaDisplay() - no database/session/IMAP connection required.
 */
class ProfileHandlerQuotaDisplayTest extends TestCase
{
	public function testGreenBelow80Percent()
	{
		$result = ProfileHandler::quotaDisplay(500000, 1000000);

		$this->assertSame('mail-index_QuotaGreen', $result['class']);
		$this->assertSame(50.0, $result['percent']);
		$this->assertSame(500000 * 1024, $result['freespace']);
	}

	public function testYellowAbove80Percent()
	{
		$result = ProfileHandler::quotaDisplay(850000, 1000000);

		$this->assertSame('mail-index_QuotaYellow', $result['class']);
	}

	public function testRedAbove90Percent()
	{
		$result = ProfileHandler::quotaDisplay(950000, 1000000);

		$this->assertSame('mail-index_QuotaRed', $result['class']);
	}

	public function testNoLimitIsGreenWithFullUsageAsText()
	{
		$result = ProfileHandler::quotaDisplay(500000, 0);

		$this->assertSame('mail-index_QuotaGreen', $result['class']);
		$this->assertSame(100, $result['percent']);
	}
}
