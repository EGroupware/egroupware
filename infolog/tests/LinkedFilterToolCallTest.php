<?php

/**
 * Infolog REST API: regression test for filters[linked] empty-value and composite-id handling
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api;

/**
 * infolog_groupdav's filters[linked] validation (identical to addressbook_groupdav and timesheet's
 * ApiHandler) used to reject an empty value outright, instead of treating it as "not filtering by
 * link" - a local/small model's grammar-constrained tool-calling often can't express "omit this
 * unused optional property" and sends it as "" instead, which broke every listTasks call that
 * happened to include it. It also only ever accepted a plain numeric ID, which mail's own
 * linked-entry ids (a colon-separated composite string, see Mail\Ui::generateRowID()) could never
 * satisfy.
 *
 * Reported live via AiTools against a local llama.cpp endpoint, see
 * https://help.egroupware.org/t/aitools-mehrere-reproduzierbare-probleme-bei-tool-calls-und-openai-kompatiblen-apis/80042
 * (2026-09-24).
 */
class LinkedFilterToolCallTest extends \EGroupware\Api\AppTest
{
	public function testEmptyLinkedFilterIsIgnoredNotRejected()
	{
		$result = Api\CalDAV\OpenAPI::toolCall('listTasks', ['filters[linked]' => '']);

		$this->assertSame(200, $result['status'] ?? null, 'an empty filters[linked] must be ignored, not rejected');
		$this->assertTrue($result['success'] ?? null);
	}

	public function testMailStyleCompositeLinkedIdIsAccepted()
	{
		$result = Api\CalDAV\OpenAPI::toolCall('listTasks',
			['filters[linked]' => 'mail:1:1:'.base64_encode('INBOX').':12345']);

		$this->assertSame(200, $result['status'] ?? null,
			'a non-numeric, colon-separated composite id (eg. mail\'s own linked-entry id format) '.
			'must be accepted, not rejected as "invalid"');
		$this->assertTrue($result['success'] ?? null);
	}

	public function testInvalidLinkedFilterReports400NotServerError()
	{
		$result = Api\CalDAV\OpenAPI::toolCall('listTasks', ['filters[linked]' => 'not-a-valid-format']);

		$this->assertFalse($result['success'] ?? null);
		$this->assertSame(400, $result['status'] ?? null,
			'a malformed filters[linked] value is a bad-arguments error, not a server failure');
	}
}
