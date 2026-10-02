<?php
/**
 * EGroupware API: tests for Header\Authenticate::hasCredentials()
 *
 * groupdav.php rejects requests without credentials with 401 before any session handling. It used to only look at
 * (REDIRECT_)HTTP_AUTHORIZATION, while autocreate_session_callback() also takes PHP_AUTH_USER/PW and PHP_AUTH_DIGEST,
 * so setups providing credentials only that way (eg. Apache with PHP-FPM/CGI, depending on CGIPassAuth / rewrite rule)
 * could no longer log in with DAV clients.
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Header;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AuthenticateHasCredentialsTest extends TestCase
{
	public static function providerCredentials() : array
	{
		return [
			'nothing' => [false, []],
			'HTTP_AUTHORIZATION Basic' => [true, ['HTTP_AUTHORIZATION' => 'Basic dXNlcjpwdw==']],
			'REDIRECT_HTTP_AUTHORIZATION Basic (Apache rewrite rule)' => [true, ['REDIRECT_HTTP_AUTHORIZATION' => 'Basic dXNlcjpwdw==']],
			'HTTP_AUTHORIZATION Bearer' => [true, ['HTTP_AUTHORIZATION' => 'Bearer abc.def.ghi']],
			'Bearer without token still reaches the real check' => [true, ['HTTP_AUTHORIZATION' => 'Bearer']],
			'Digest' => [true, ['HTTP_AUTHORIZATION' => 'Digest username="user", realm="x", nonce="y", response="z"']],
			'Negotiate (SSO)' => [true, ['HTTP_AUTHORIZATION' => 'Negotiate YIIB']],
			'unknown scheme' => [true, ['HTTP_AUTHORIZATION' => 'Custom whatever']],
			'REDIRECT_HTTP_AUTHORIZATION Bearer' => [true, ['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer abc.def.ghi']],
			'PHP_AUTH_USER only (no Authorization variable)' => [true, ['PHP_AUTH_USER' => 'user', 'PHP_AUTH_PW' => 'pw']],
			'PHP_AUTH_DIGEST' => [true, ['PHP_AUTH_DIGEST' => 'username="user", realm="x"']],
			'empty Basic header' => [false, ['HTTP_AUTHORIZATION' => 'Basic ']],
			'empty Basic header without trailing space' => [false, ['HTTP_AUTHORIZATION' => 'Basic']],
			'empty basic header, other case and whitespace' => [false, ['HTTP_AUTHORIZATION' => " basic \t"]],
			'whitespace only' => [false, ['HTTP_AUTHORIZATION' => '  ']],
			'empty REDIRECT Basic header' => [false, ['REDIRECT_HTTP_AUTHORIZATION' => 'Basic ']],
			'empty strings' => [false, ['HTTP_AUTHORIZATION' => '', 'PHP_AUTH_USER' => '', 'PHP_AUTH_DIGEST' => '']],
			'empty Basic header, but PHP_AUTH_USER' => [true, ['HTTP_AUTHORIZATION' => 'Basic ', 'PHP_AUTH_USER' => 'user']],
		];
	}

	/**
	 * Pass criteria: credentials are recognized wherever the callback would find them, an empty "Basic " is not
	 */
	#[DataProvider('providerCredentials')]
	public function testHasCredentials(bool $expected, array $server)
	{
		$this->assertSame($expected, Authenticate::hasCredentials($server, [], []));
	}

	/**
	 * Pass criteria: a bearer token in the oauth_id_token cookie counts, as autocreate_session_callback() uses it
	 */
	public function testOauthCookieCounts()
	{
		$this->assertTrue(Authenticate::hasCredentials([], ['oauth_id_token' => 'abc'], []));
		$this->assertFalse(Authenticate::hasCredentials([], ['oauth_id_token' => ''], []));
	}

	/**
	 * Pass criteria: defaults to the current request ($_SERVER / $_COOKIE)
	 */
	public function testDefaultsToRequest()
	{
		$backup = [$_SERVER, $_COOKIE];
		try
		{
			unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_DIGEST']);
			$_COOKIE = [];
			$this->assertFalse(Authenticate::hasCredentials());
			$_SERVER['PHP_AUTH_USER'] = 'user';
			$this->assertTrue(Authenticate::hasCredentials());
		}
		finally
		{
			[$_SERVER, $_COOKIE] = $backup;
		}
	}

	public static function providerBearer() : array
	{
		return [
			'HTTP_AUTHORIZATION' => ['abc.def', ['HTTP_AUTHORIZATION' => 'Bearer abc.def'], []],
			'REDIRECT_HTTP_AUTHORIZATION (rewrite rule)' => ['abc.def', ['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer abc.def'], []],
			'scheme in other case' => ['abc.def', ['HTTP_AUTHORIZATION' => 'bearer abc.def'], []],
			'HTTP_AUTHORIZATION wins over REDIRECT' => ['one', ['HTTP_AUTHORIZATION' => 'Bearer one', 'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer two'], []],
			'Basic in HTTP_AUTHORIZATION, Bearer in REDIRECT' => ['two', ['HTTP_AUTHORIZATION' => 'Basic dXNlcjpwdw==', 'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer two'], []],
			'cookie' => ['cookie-token', [], ['oauth_id_token' => 'cookie-token']],
			'header wins over cookie' => ['abc.def', ['HTTP_AUTHORIZATION' => 'Bearer abc.def'], ['oauth_id_token' => 'cookie-token']],
			'Bearer without token' => [null, ['HTTP_AUTHORIZATION' => 'Bearer'], []],
			'Bearer with spaces only' => [null, ['HTTP_AUTHORIZATION' => 'Bearer   '], []],
			'Basic only' => [null, ['HTTP_AUTHORIZATION' => 'Basic dXNlcjpwdw=='], []],
			'nothing' => [null, [], []],
		];
	}

	/**
	 * Pass criteria: a bearer token is found in HTTP_AUTHORIZATION, REDIRECT_HTTP_AUTHORIZATION or the cookie
	 */
	#[DataProvider('providerBearer')]
	public function testBearerToken(?string $expected, array $server, array $cookie)
	{
		$this->assertSame($expected, Authenticate::bearerToken($server, $cookie, []));
	}

	public static function providerHeaders() : array
	{
		return [
			'only in request headers (Apache not writing it to $_SERVER)' => [true, [], ['Authorization' => 'Basic dXNlcjpwdw==']],
			'header name in other case' => [true, [], ['authorization' => 'Bearer abc']],
			'empty Basic in request headers' => [false, [], ['Authorization' => 'Basic']],
			'other headers are ignored' => [false, [], ['X-Authorization' => 'Basic dXNlcjpwdw==', 'Accept' => 'text/xml']],
			'empty $_SERVER value, real one in request headers' => [true, ['HTTP_AUTHORIZATION' => ''], ['Authorization' => 'Bearer abc']],
		];
	}

	/**
	 * Pass criteria: an Authorization header only available via getallheaders() / apache_request_headers() counts
	 */
	#[DataProvider('providerHeaders')]
	public function testRequestHeaders(bool $expected, array $server, array $headers)
	{
		$this->assertSame($expected, Authenticate::hasCredentials($server, [], $headers));
	}

	/**
	 * Pass criteria: a bearer token is also found in the request headers
	 */
	public function testBearerTokenFromRequestHeaders()
	{
		$this->assertSame('abc', Authenticate::bearerToken([], [], ['Authorization' => 'Bearer abc']));
	}

	public static function providerBasic() : array
	{
		$ok = base64_encode('user:pa:ss');
		return [
			'HTTP_AUTHORIZATION' => [['user', 'pa:ss'], ['HTTP_AUTHORIZATION' => 'Basic '.$ok], []],
			'REDIRECT_HTTP_AUTHORIZATION' => [['user', 'pa:ss'], ['REDIRECT_HTTP_AUTHORIZATION' => 'Basic '.$ok], []],
			'request headers' => [['user', 'pa:ss'], [], ['Authorization' => 'Basic '.$ok]],
			'scheme in other case' => [['user', 'pa:ss'], ['HTTP_AUTHORIZATION' => 'basic '.$ok], []],
			'empty password' => [['user', ''], ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode('user:')], []],
			'no colon' => [[null, null], ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode('nocolon')], []],
			'bare Basic' => [null, ['HTTP_AUTHORIZATION' => 'Basic'], []],
			'Bearer is not basic' => [null, ['HTTP_AUTHORIZATION' => 'Bearer abc'], []],
			'nothing' => [null, [], []],
		];
	}

	/**
	 * Pass criteria: basic credentials are decoded from whichever source has them, malformed ones give [null, null]
	 */
	#[DataProvider('providerBasic')]
	public function testBasicCredentials(?array $expected, array $server, array $headers)
	{
		$this->assertSame($expected, Authenticate::basicCredentials($server, $headers));
	}

	public static function providerRedactAuthorization() : array
	{
		return [
			'basic shows user and as many * as the password has chars' => ['Basic user:*****', 'Basic '.base64_encode('user:12345')],
			'basic password containing colon' => ['Basic user:****', 'Basic '.base64_encode('user:a:bc')],
			'basic empty password' => ['Basic user:', 'Basic '.base64_encode('user:')],
			'basic malformed' => ['Basic ********', 'Basic '.base64_encode('nocolon')],
			'basic not base64' => ['Basic ********', 'Basic !!!'],
			'short secret shows less than 4 chars' => ['Bearer a********', 'Bearer abcd'],
			'very short secret shows nothing' => ['Bearer ********', 'Bearer ab'],
			'basic scheme other case' => ['basic user:**', 'basic '.base64_encode('user:pw')],
			'bearer: first 4 chars, no length' => ['Bearer abc.********', 'Bearer abc.def.ghijklmnopqrstuvwxyz'],
			'digest: username only' => ['Digest username="user", ********', 'Digest username="user", realm="x", nonce="n", response="r"'],
			'unknown scheme' => ['Negotiate YIIB********', 'Negotiate YIIBsecretsecret'],
			'bare scheme' => ['Basic', 'Basic'],
		];
	}

	/**
	 * Pass criteria: a header shows what is needed to debug (scheme, user, password length for basic), no secrets
	 */
	#[DataProvider('providerRedactAuthorization')]
	public function testRedactAuthorization(string $expected, string $header)
	{
		$this->assertSame($expected, Authenticate::redactAuthorization($header));
	}

	/**
	 * Pass criteria: phpinfo() output has credentials redacted in all places, the rest is unchanged
	 */
	public function testRedactPhpinfo()
	{
		$basic = base64_encode('admin:secret!');
		$row = static fn($k, $v) => '<tr><td class="e">'.$k.' </td><td class="v">'.$v.' </td></tr>';
		$html = '<table>'.
			$row('$_SERVER[\'HTTP_AUTHORIZATION\']', 'Basic '.$basic).
			$row('$_SERVER[\'REDIRECT_HTTP_AUTHORIZATION\']', 'Basic '.$basic).
			$row('$_SERVER[\'PHP_AUTH_USER\']', 'admin').
			$row('$_SERVER[\'PHP_AUTH_PW\']', 'secret!').
			$row('Authorization', 'Bearer abc.def.ghi.jkl.mnopq').
			$row('HTTP_AUTHORIZATION', 'Basic '.$basic).
			$row('$_SERVER[\'HTTP_COOKIE\']', 'sessionid=abc123; kp3=def456; last_loginid=admin').
			$row('$_COOKIE[\'sessionid\']', 'abc123').
			$row('$_SERVER[\'HTTP_USER_AGENT\']', 'curl/8').
			$row('memory_limit', '128M').
			'</table>';

		$out = Authenticate::redactPhpinfo($html);

		foreach(['secret', $basic, 'def.ghi', 'jkl.mnopq', 'abc123', 'def456'] as $secret)
		{
			$this->assertStringNotContainsString($secret, $out, "$secret must be redacted");
		}
		$this->assertSame(3, substr_count($out, 'Basic admin:*******'), 'Basic shows user and one * per password char');
		$this->assertStringContainsString('** length=7 **', $out, 'PHP_AUTH_PW shows only its length');
		$this->assertStringContainsString('Bearer abc.********', $out, 'other schemes show scheme and first 4 chars');
		$this->assertStringContainsString('sessionid=***; kp3=***; last_loginid=***', $out, 'cookies show only names');
		$this->assertStringContainsString('<td class="v">XDEBUG=*** </td>', Authenticate::redactPhpinfo($row('$_SERVER[\'HTTP_COOKIE\']', 'XDEBUG=PHPSTORM; ')), 'trailing cookie separator gives no empty cookie');
		$this->assertStringContainsString($row('$_SERVER[\'PHP_AUTH_USER\']', 'admin'), $out, 'username stays visible');
		$this->assertStringContainsString($row('$_SERVER[\'HTTP_USER_AGENT\']', 'curl/8'), $out, 'unrelated rows are unchanged');
		$this->assertStringContainsString($row('memory_limit', '128M'), $out);
	}

	public static function providerTextFormat() : array
	{
		return [
			'text/plain' => ['text/plain', 'text/plain'],
			'text/markdown' => ['text/markdown', 'text/markdown'],
			'markdown wins over plain at equal q' => ['text/markdown', 'text/plain, text/markdown'],
			'plain with higher q' => ['text/plain', 'text/markdown;q=0.5, text/plain;q=0.9'],
			'text/plain first' => ['text/plain', 'text/plain, text/html;q=0.5'],
			'text/html wins' => [null, 'text/html, text/plain;q=0.5'],
			'browser' => [null, 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'],
			'curl default */* stays HTML' => [null, '*/*'],
			'nothing' => [null, ''],
			'text/plain refused' => [null, 'text/plain;q=0'],
			'other case and spaces' => ['text/plain', ' TEXT/PLAIN ; q=1 '],
		];
	}

	/**
	 * Pass criteria: text is only chosen, if the client explicitly prefers text/markdown or text/plain over HTML
	 */
	#[DataProvider('providerTextFormat')]
	public function testPreferredTextFormat(?string $expected, string $accept)
	{
		$this->assertSame($expected, Authenticate::preferredTextFormat($accept));
	}

	/**
	 * Pass criteria: phpinfo() HTML is converted to markdown headings and tables, incl. our own rows
	 */
	public function testPhpinfoToMarkdown()
	{
		$html = '<!DOCTYPE html><html><head><style>td.e {color: red}</style><title>phpinfo()</title></head><body>'."\n".
			'<div class="center"><table><tr class="h"><td><h1 class="p">PHP Version 8.5.11</h1></td></tr></table>'."\n".
			'<table><tr><td class="e">EGroupware caching provider </td><td class="v">Redis </td></tr></table>'."\n".
			'<table>'."\n".'<tr><td class="e">System </td><td class="v">Linux host 6.1 #1 SMP </td></tr>'."\n".
			'<tr><td class="e">Build Date </td><td class="v">Sep&nbsp;1 2026 </td></tr>'."\n".'</table>'."\n".
			'<h2><a name="module_core">Core</a></h2>'."\n".
			'<table>'."\n".'<tr class="h"><th>Directive</th><th>Local Value</th><th>Master Value</th></tr>'."\n".
			'<tr><td class="e">memory_limit </td><td class="v">128M </td><td class="v">256M </td></tr>'."\n".
			'<tr><td class="e">open_basedir </td><td class="v"><i>no value</i> </td><td class="v"><i>no value</i> </td></tr>'."\n".
			'<tr><td class="e">$_SERVER[\'HTTP_ACCEPT\'] </td><td class="v">text/plain, a &amp; b&lt;c&gt; <br />second | line </td></tr>'."\n".
			'<tr><td class="e">$_SERVER[\'HTTP_AUTHORIZATION\'] </td><td class="v">Basic user:**** </td></tr>'."\n".'</table>'."\n".
			'<h2>Environment</h2><table><tr><td class="e">PATH </td><td class="v">/usr/bin </td></tr></table>'."\n".
			'</div></body></html>';

		$md = Authenticate::phpinfoToMarkdown($html);

		$this->assertStringNotContainsString('color: red', $md, 'head/style is dropped');
		$this->assertStringStartsWith("# PHP Version 8.5.11\n\n", $md, 'a table containing only a heading is just a heading');
		$this->assertStringContainsString("| Name | Value |\n| --- | --- |\n| EGroupware caching provider | Redis |\n", $md);
		$this->assertStringContainsString("| System | Linux host 6.1 #1 SMP |\n| Build Date | Sep 1 2026 |\n", $md, 'entities are decoded, rows contiguous');
		$this->assertStringContainsString("\n\n## Core\n\n| Directive | Local Value | Master Value |\n| --- | --- | --- |\n| memory_limit | 128M | 256M |\n", $md);
		$this->assertStringContainsString("| open_basedir | no value | no value |\n", $md);
		$this->assertStringContainsString('| $_SERVER[\'HTTP_ACCEPT\'] | text/plain, a & b<c> second \| line |  |'."\n", $md, 'pipes are escaped, <c> survives, short rows are padded');
		$this->assertStringContainsString('| $_SERVER[\'HTTP_AUTHORIZATION\'] | `Basic user:****` |  |'."\n", $md, 'values with asterisks are shown as code');
		$this->assertSame("| Name | Value |\n| --- | --- |\n| x | `a\\|b**` |\n", Authenticate::phpinfoToMarkdown('<body><table><tr><td>x</td><td>a|b**</td></tr></table></body>'), 'pipe is still escaped in code');
		$this->assertSame("| Name | Value |\n| --- | --- |\n| x | a`b\\*\\* |\n", Authenticate::phpinfoToMarkdown('<body><table><tr><td>x</td><td>a`b**</td></tr></table></body>'), 'backtick in value: escape asterisks instead');
		$this->assertStringContainsString("\n\n## Environment\n\n| Name | Value |\n| --- | --- |\n| PATH | /usr/bin |\n", $md);
		$this->assertStringNotContainsString("\n\n\n", $md, 'no runs of blank lines');
		$this->assertStringNotContainsString('</', str_replace('b<c>', '', $md), 'no tags left');
	}

	/**
	 * Pass criteria: a table with uneven row lengths is padded to the widest row
	 */
	public function testPhpinfoToMarkdownPadsRows()
	{
		$md = Authenticate::phpinfoToMarkdown('<body><table><tr><td>a</td><td>b</td></tr><tr><td>c</td><td>d</td><td>e</td></tr></table></body>');
		$this->assertSame("| Name | Value | Master |\n| --- | --- | --- |\n| a | b |  |\n| c | d | e |\n", $md);
	}
}
