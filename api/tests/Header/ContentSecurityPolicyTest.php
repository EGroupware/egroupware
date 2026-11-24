<?php
/**
 * EGroupware API: tests for the sources of our Content-Security-Policy
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Header;

use PHPUnit\Framework\TestCase;

/**
 * default-src does not cover every directive: base-uri has to be set explicitly,
 * or a <base> in user content resolves all relative urls of the page against another server.
 */
class ContentSecurityPolicyTest extends TestCase
{
	/**
	 * Sources of a directive, as send() uses them
	 *
	 * @param string $directive
	 * @return ?array
	 */
	protected function sources(string $directive) : ?array
	{
		$sources = (new \ReflectionProperty(ContentSecurityPolicy::class, 'sources'))->getValue();

		return $sources[$directive] ?? null;
	}

	public function testBaseUriIsLimitedToSelf() : void
	{
		$this->assertSame(["'self'"], $this->sources('base-uri'));
	}

	public function testDefaultIsNone() : void
	{
		$this->assertSame(["'none'"], $this->sources('default-src'));
	}
}
