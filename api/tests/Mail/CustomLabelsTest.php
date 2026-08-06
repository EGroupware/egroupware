<?php
/**
 * EGroupware API: custom Mail label tests
 *
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CustomLabelsTest extends TestCase
{
	private array $originalCustomLabels;

	protected function setUp(): void
	{
		$this->originalCustomLabels = Mail::$customLabels;
		Mail::$customLabels = array(
			'project' => array(
				'name' => 'Project',
				'color' => '#ff8000',
			),
			'unusual' => array(
				'name' => 'Unusual',
				'color' => '#4b0082',
			),
		);
	}

	protected function tearDown(): void
	{
		Mail::$customLabels = $this->originalCustomLabels;
	}

	/**
	 * Mail categories map their names to label IDs and their configured presentation.
	 *
	 * The fixture covers description/name fallback and passes when color and icon
	 * are copied from category data.
	 */
	public function testCategoriesMapToCustomLabels()
	{
		$method = (new ReflectionClass(Mail::class))->getMethod('categoriesToCustomLabels');
		$labels = $method->invoke(null, array(
			array(
				'id' => 123,
				'name' => 'HausMails',
				'description' => '',
				'data' => array('color' => '#4615E7', 'icon' => 'charts'),
			),
			array(
				'id' => 124,
				'name' => 'MailCustomLabelIndigo',
				'description' => 'Description',
				'data' => array('color' => '#0B6702', 'icon' => 'finance'),
			),
		));

		$this->assertSame(array(
			'HausMails' => array(
				'name' => 'HausMails',
				'color' => '#4615E7',
				'icon' => 'charts',
			),
			'MailCustomLabelIndigo' => array(
				'name' => 'Description',
				'color' => '#0B6702',
				'icon' => 'finance',
			),
		), $labels);
	}

	/**
	 * Category names remain the UI label IDs while IMAP keywords are normalized.
	 *
	 * The fixture uses mixed case and passes when returned flags keep that ID and
	 * searches use the case-insensitive lowercase IMAP keyword.
	 */
	public function testCategoryNameLabelIdUsesNormalizedImapKeyword()
	{
		Mail::$customLabels = array(
			'HausMails' => array(
				'name' => 'Mail from home',
				'color' => '#0B6702',
				'icon' => 'finance',
			),
		);

		$flags = Mail::prepareFlagsArray(array('FLAGS' => array('$hausmails')));
		$this->assertTrue($flags['keywords']['HausMails']);
		$this->assertSame(
			array('keyword' => 'hausmails', 'set' => true),
			Mail::labelSearchCriterion('HausMails', true)
		);
	}

	/**
	 * Custom labels are independent IMAP keywords.
	 *
	 * The fixture contains two non-prefixed keywords and passes when both are
	 * returned in the generic keyword map.
	 */
	public function testPrepareFlagsArrayKeepsMultipleCustomLabels()
	{
		$flags = Mail::prepareFlagsArray(array(
			'FLAGS' => array('$project', '$unusual'),
		));

		$this->assertTrue($flags['keywords']['project'], 'project should be returned as set');
		$this->assertTrue($flags['keywords']['unusual'], 'unusual should be returned as set');
	}

	/**
	 * Configured custom-label statuses search for their IMAP keyword.
	 *
	 * The query passes when the configured project ID becomes KEYWORD $PROJECT
	 * and an unconfigured ID is not interpreted as a status.
	 */
	public function testConfiguredCustomLabelStatusIsSearchable()
	{
		$mail = (new ReflectionClass(Mail::class))->newInstanceWithoutConstructor();

		$positive = $mail->createIMAPFilter('INBOX', array('status' => 'project'))->build();
		$this->assertStringContainsString(
			'KEYWORD $PROJECT',
			(string)$positive['query'],
			'The configured status should search for its IMAP keyword'
		);

		$unknown = $mail->createIMAPFilter('INBOX', array('status' => 'missing'))->build();
		$this->assertStringNotContainsString(
			'MISSING',
			(string)$unknown['query'],
			'An unconfigured ID must not be inferred to be a keyword status'
		);
	}

}
