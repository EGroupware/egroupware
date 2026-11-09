<?php
/**
 * EGroupware Api: Framework::skeleton() - the stand-in shown while an add / edit form loads
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage framework
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Framework;

use EGroupware\Api\Framework;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SkeletonTest extends TestCase
{
	protected function tearDown() : void
	{
		unset($_COOKIE['skeleton']);
	}

	#[DataProvider('decisions')]
	public function testWhoDecides(string $template, $override, $attribute, bool $expected) : void
	{
		$this->assertSame($expected, Framework::skeletonWanted($template, $override, $attribute));
	}

	public static function decisions() : array
	{
		return [
			// the convention: add / edit / compose forms
			'edit'               => ['infolog.edit', null, null, true],
			'add'                => ['timesheet.add', null, null, true],
			'compose'            => ['mail.compose', null, null, true],
			'part of a form'     => ['infolog.edit.description', null, null, false],
			'index'              => ['infolog.index', null, null, false],
			'other dialog'       => ['api.about', null, null, false],
			'no app'             => ['edit', null, null, false],
			'edit in the middle' => ['infolog.edit.links', null, null, false],
			// the template says so itself, skeleton="true" / "false" on its root
			'attribute on'       => ['timesheet.add_material', null, 'true', true],
			'attribute off'      => ['infolog.edit', null, 'false', false],
			'attribute empty'    => ['infolog.edit', null, '', true],
			// the application sets Etemplate::$skeleton, and has the last word
			'property on'        => ['timesheet.add_material', true, null, true],
			'property off'       => ['infolog.edit', false, 'true', false],
			'property over attr' => ['timesheet.add_material', true, 'false', true],
		];
	}

	public function testMarkupForAForm() : void
	{
		$html = Framework::skeleton('infolog.edit');
		$this->assertStringContainsString('id="egw-skeleton"', $html);
		$this->assertStringContainsString('egw-skeleton--page', $html, 'covers the window by default (popup)');
		$this->assertStringNotContainsString('egw-skeleton--page', Framework::skeleton('infolog.edit', false));
	}

	public function testNothingWhenTurnedOff() : void
	{
		$_COOKIE['skeleton'] = '0';
		$this->assertSame('', Framework::skeleton('infolog.edit'));
	}

	public function testCssHasBothLayoutsAndTheSmallWindowRule() : void
	{
		$css = Framework::skeletonCss();
		$this->assertStringContainsString('--egw-skeleton-mask:url("data:image/svg+xml,', $css);
		$this->assertSame(2, substr_count($css, 'data:image/svg+xml,'), 'desktop and phone layout');
		$this->assertStringContainsString('@media (max-width:800px)', $css);
		$this->assertStringContainsString('min-width:32em', $css, 'a small window is not a form');
		$this->assertStringNotContainsString("\n", $css);
		// nothing the browser would end the url() or the rule on
		$this->assertStringNotContainsString('<', $css);
	}
}
