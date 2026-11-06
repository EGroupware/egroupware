<?php
/**
 * Tests for Tracking
 *
 * @package api
 * @subpackage tests
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2018 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Storage;

require_once __DIR__ . '/../LoggedInTest.php';
require_once __DIR__ . '/TestTracking.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest as LoggedInTest;

class TrackingTest extends LoggedInTest
{
	const APP = 'test';

	protected $simple_field = array(
		'app'     => self::APP,
		'name'    => 'test_field',
		'label'   => 'Custom field',
		'type'    => 'text',
		'type2'   => array(),
		'help'    => 'Custom field created for automated testing by CustomfieldsTest',
		'values'  => null,
		'len'     => null,
		'rows'    => null,
		'order'   => null,
		'needed'  => null,
		'private' => array()
	);

	/**
	 * Test the access control on private custom fields
	 */
	public function testSanitizeCustomMessage()
	{
		// Create field
		$field = array_merge(
			$this->simple_field,
			array(
				'private' => array($GLOBALS['egw_info']['user']['account_id'])
			)
		);
		Customfields::update($field);

		$fields = Customfields::get(self::APP);

		$dirty_message = "This custom message contains {{#test_field}}, which only user {$GLOBALS['egw_info']['user']['account_id']} can access.";
		$clean_message = "This custom message contains , which only user {$GLOBALS['egw_info']['user']['account_id']} can access.";

		// Get another user
		$accounts = $GLOBALS['egw']->accounts->search(array(
														  'type' => 'accounts'
													  ));
		unset($accounts[$GLOBALS['egw_info']['user']['account_id']]);
		if(count($accounts) == 0)
		{
			$this->markTestSkipped('Need more than one user to check private');
		}
		$other_account = key($accounts);

		$tracking = new TestTracking(self::APP);
		$cleaned = $tracking->sanitize_custom_message($dirty_message, $other_account);

		$this->assertEquals($clean_message, $cleaned);

		// Clean up
		unset($fields[$field['name']]);
		Customfields::save(self::APP, $fields);
	}

	/**
	 * Test the html notification: a card with the message as header, the details as table with modified fields marked
	 * in words, sections for headings and comments, plus a dark skin keeping the inline light colors as default
	 */
	public function testHtmlBody()
	{
		$tracking = new TestTracking(self::APP);
		$tracking->message = array('Reply text', '---', 'Entry modified by Admin');
		$tracking->details = array(
			'status'  => array('label' => 'Status', 'value' => 'Open'),
			'cc'      => array('label' => 'CC', 'value' => 'cc@example.org'),
			'title'   => array('label' => 'Summary', 'value' => '#1 - <b>Title</b>', 'type' => 'summary'),
			array('value' => 'Description', 'type' => 'summary'),
			'description' => array('value' => "Line 1\nLine 2", 'type' => 'multiline'),
			'replies' => array('value' => 'Comment by Admin:', 'type' => 'reply'),
			array('value' => 'New comment', 'type' => 'multiline'),
			array('value' => 'Comment by User:', 'type' => 'reply'),
			array('value' => 'Old comment', 'type' => 'multiline'),
		);
		$data = array('t_id' => 1, 'status' => 'open', 'cc' => 'cc@example.org', 'replies' => 2);
		$old = array('t_id' => 1, 'status' => 'open', 'replies' => 1);
		$tracking->config['link'] = 'https://example.org/index.php?menuaction=test.ui.view';

		$body = $tracking->get_body(true, $data, $old, false);

		$this->assertStringStartsWith(Tracking::html_dark_style().'<table class="egwNotification" role="presentation" lang="'.Api\Translation::$userlang.'"', $body);
		// header with the message, the last line being the heading
		$this->assertMatchesRegularExpression('#<td class="egwNotificationHeader"[^>]*><p [^>]*>Reply text</p><hr [^>]*/><h1 [^>]*>Entry modified by Admin</h1></td>#', $body);
		// details table, only CC is modified
		$this->assertMatchesRegularExpression('#<tr class="egwNotificationRow"><th scope="row" class="egwNotificationLabel"[^>]*>Status</th><td [^>]*>Open</td></tr>#', $body);
		$this->assertMatchesRegularExpression('#<tr class="egwNotificationRow egwNotificationModified"><th scope="row"[^>]*>CC</th><td [^>]*>cc@example.org<span class="egwNotificationBadge"[^>]*>'.lang('changed').'</span></td></tr>#', $body);
		// "What changed" repeats the modified CC row and the new comment, followed by the button to open the entry
		$sections = explode('<td class="egwNotificationSection"', $body);
		$this->assertStringContainsString('>'.lang('What changed').'</h2>', $sections[1]);
		$this->assertEquals(1, substr_count($sections[1], 'egwNotificationModified'));
		$this->assertStringContainsString('>cc@example.org<span class="egwNotificationBadge"', $sections[1]);
		$this->assertStringContainsString('<div>New comment</div>', $sections[1]);
		$this->assertStringNotContainsString('Old comment', $sections[1]);
		$this->assertMatchesRegularExpression('#<td class="egwNotificationButton"[^>]*><a href="https://example.org/index.php\?menuaction=test.ui.view&amp;t_id=1" target="_blank"[^>]*>'.lang('Open').'</a></td>#', $sections[1]);
		$this->assertEquals(2, substr_count(substr($body, strlen(Tracking::html_dark_style())), 'egwNotificationModified'));
		// summary row with a heading, html is escaped
		$this->assertMatchesRegularExpression('#<th scope="row"[^>]*><h2 [^>]*>Summary</h2></th><td [^>]*>\#1 - &lt;b&gt;Title&lt;/b&gt;</td>#', $body);
		// description section with its text
		$this->assertMatchesRegularExpression('#<h2 class="egwNotificationHeading"[^>]*>Description</h2>\s*<div class="egwNotificationText" [^>]*>Line 1<br />\s*Line 2</div>#', $body);
		// comments: the modified one is new
		$this->assertMatchesRegularExpression('#<td class="egwNotificationComment egwNotificationNew"[^>]*><h3 [^>]*><span class="egwNotificationBadge egwNotificationNew"[^>]*>'.lang('new').'</span>Comment by Admin:</h3><div>New comment</div></td>#', $body);
		$this->assertMatchesRegularExpression('#<td class="egwNotificationComment"[^>]*><h3 [^>]*>Comment by User:</h3><div>Old comment</div></td>#', $body);
		// dark skin applies by media query only, EGroupware's popup notifications get it from kdots' dark theme
		$style = Tracking::html_dark_style();
		$this->assertStringStartsWith("<style>\n@media (prefers-color-scheme: dark) {\n\ttable.egwNotification { background-color:#22252b !important; ", $style);
		$this->assertStringContainsString("\ttr.egwNotificationModified > * { background-color:#3b3018 !important; }\n", $style);
		$this->assertStringNotContainsString('sl-theme', $style);

		// text mails mark modified fields in words, no html
		$text = $tracking->get_body(false, $data, $old, false);
		$this->assertStringNotContainsString('egwNotification', $text);
		$this->assertStringContainsString("Comment by Admin: (".lang('new').")\n", $text);
		$this->assertStringContainsString("CC: cc@example.org (".lang('changed').")\n", $text);
		$this->assertStringContainsString("Status: Open\n", $text);
	}

	/**
	 * Test a multiline value does not switch off escaping for the labels and values following it
	 */
	public function testHtmlEscapedAfterMultiline()
	{
		$tracking = new TestTracking(self::APP);
		$tracking->details = array(
			'description' => array('label' => 'Description', 'value' => "<i>Line 1</i>\nLine 2", 'type' => 'multiline'),
			'title'   => array('label' => '<b>Summary</b>', 'value' => '<b>Title</b>', 'type' => 'summary'),
			array('value' => 'Comment by <b>Admin</b>:', 'type' => 'reply'),
			array('value' => '<b>Comment</b>', 'type' => 'multiline'),
		);

		$body = $tracking->get_body(true, array('t_id' => 1), null, false);

		$this->assertStringContainsString('&lt;i&gt;Line 1&lt;/i&gt;<br />', $body);
		$this->assertStringNotContainsString('<b>', $body);
		$this->assertStringContainsString('&lt;b&gt;Summary&lt;/b&gt;', $body);
		$this->assertStringContainsString('&lt;b&gt;Title&lt;/b&gt;', $body);
		$this->assertStringContainsString('Comment by &lt;b&gt;Admin&lt;/b&gt;:', $body);
		$this->assertStringContainsString('&lt;b&gt;Comment&lt;/b&gt;', $body);
		$this->assertFalse($tracking->html_content_allow);
	}
}
