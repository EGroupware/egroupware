<?php
/**
 * Symfony polyfill for php < 5.5
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 * @link https://github.com/symfony/polyfill-php55/blob/master/Php55.php
 */

if (!function_exists('hash_pbkdf2'))
{
	function hash_pbkdf2($algorithm, $password, $salt, $iterations, $length = 0, $rawOutput = false)
	{
		// Number of blocks needed to create the derived key
		$blocks = ceil($length / strlen(hash($algorithm, null, true)));
		$digest = '';
		for ($i = 1; $i <= $blocks; ++$i) {
			$ib = $block = hash_hmac($algorithm, $salt.pack('N', $i), $password, true);
			// Iterations
			for ($j = 1; $j < $iterations; ++$j) {
				$ib ^= ($block = hash_hmac($algorithm, $block, $password, true));
			}
			$digest .= $ib;
		}
		if (!$rawOutput) {
			$digest = bin2hex($digest);
		}
		return substr($digest, 0, $length);
	}
}