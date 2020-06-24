<?php

/**
 * Test the save to VFS functions
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package mail
 * @copyright (c) 2018 by Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */


namespace EGroupware\Mail;

class SaveToVfsTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * Create a custom status we can use to test
	 */
	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

	}
	public static function tearDownAfterClass() : void
	{

		// Have to remove custom status first, before the DB is gone
		parent::tearDownAfterClass();
	}

	protected function setUp() : void
	{
	}

	protected function tearDown() : void
	{
	}

	/**
	 * Test that we make nice filenames for the VFS
	 *
	 * Under Windows the characters < > ? " : | \ / * are not allowed.
	 * % causes problems with VFS UI
	 *
	 * @param String $filename
	 * @dataProvider filenameProvider
	 */
	public function testVfsFilename($filename, $replacements)
	{
		$cleaned = VfsTestMail::clean_subject_for_filename($filename);

		$this->assertStringNotContainsString('<', $cleaned);
		$this->assertStringNotContainsString('>', $cleaned);
		$this->assertStringNotContainsString('"', $cleaned);
		$this->assertStringNotContainsString('#', $cleaned);
		$this->assertStringNotContainsString(':', $cleaned);
		$this->assertStringNotContainsString('|', $cleaned);
		$this->assertStringNotContainsString('\\', $cleaned);
		$this->assertStringNotContainsString('*', $cleaned);
		$this->assertStringNotContainsString('/', $cleaned);
		$this->assertStringNotContainsString('?', $cleaned);
		$this->assertStringNotContainsString('\x0b', $cleaned);
		$this->assertStringNotContainsString('😂', $cleaned);

		// Check if the filename is not empty
		$this->assertGreaterThan(0, strlen($cleaned), 'File name is empty');

		if (!$replacements)
		{
			$this->assertEquals($filename, $cleaned);
		}
		else
		{
			$this->assertNotEquals($filename, $cleaned);
		}
	}

	public function filenameProvider()
	{
		return array(
			array('Normal! All allowed (!@$^&) {\'} []', false),
			array('Contains a >', true),
			array('Contains a <', true),
			array('Contains a "', true),
			array('Contains a #', true),
			array('Contains a :', true),
			array('Contains a |', true),
			array('Contains a \\', true),
			array('Contains a *', true),
			array('Contains a /', true),
			array('Contains a ?', true),
			array('Contains a %', true),
			array('Contains a \x0b', true),
			array('Contains a 😂', true),
			array('This one contains them all < > " : | \ * / ? % 😂 are not allowed', true)
		);
	}
}


class VfsTestMail extends \EGroupware\Api\Mail
{
	// Expose for testing
	public static function clean_subject_for_filename($filename)
	{
		return parent::clean_subject_for_filename($filename);
	}
}