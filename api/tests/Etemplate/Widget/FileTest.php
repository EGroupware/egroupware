<?php

/**
 * Test for File widget's chunked-upload temp dir scoping
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage etemplate
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

require_once realpath(__DIR__.'/../../AppTest.php');

/**
 * Before the fix, the chunked-upload temp directory was keyed only by the client-supplied
 * resumableIdentifier (predictable: the client's default generator is just size+filename,
 * no randomness or session binding) - a shared namespace across all users. This checks
 * that the resolved temp dir now also depends on the current user's account_id.
 */
class FileTest extends \EGroupware\Api\AppTest
{
	private function callChunkTempDir($resumable_identifier)
	{
		$reflection = new \ReflectionMethod(File::class, 'chunkTempDir');
		$reflection->setAccessible(true);

		return $reflection->invoke(null, $resumable_identifier);
	}

	/**
	 * Pass criteria: two different account ids must resolve to two different temp dirs
	 * for the identical, attacker-guessable resumableIdentifier.
	 */
	public function testTempDirIsScopedPerAccount()
	{
		$original_account_id = $GLOBALS['egw_info']['user']['account_id'];
		$identifier = '12345-shared_filename.txt';

		try
		{
			$GLOBALS['egw_info']['user']['account_id'] = 111;
			$dir_a = $this->callChunkTempDir($identifier);

			$GLOBALS['egw_info']['user']['account_id'] = 222;
			$dir_b = $this->callChunkTempDir($identifier);
		}
		finally
		{
			$GLOBALS['egw_info']['user']['account_id'] = $original_account_id;
		}

		$this->assertNotSame($dir_a, $dir_b,
			'the same resumableIdentifier must resolve to different temp dirs for different accounts');
	}
}
