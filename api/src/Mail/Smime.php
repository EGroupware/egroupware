<?php
/**
 * EGroupware Api: generic base class for SMIME
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage mail
 * @author Hadi Nategh <hn@egrupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 * @version $Id$
 */

namespace EGroupware\Api\Mail;

use EGroupware\Api;
use Horde_Crypt_Exception;
use Horde_Crypt_Translation;
use Horde_Crypt_Smime;

/**
 * EMailAdmin generic base class for SMTP
 */
class Smime extends Horde_Crypt_Smime
{

	/**
	 * openssl binary path
	 *
	 * @var string
	 */
	protected $sslpath;

	static $SMIME_TYPES = array (
		'application/pkcs8',
		'application/pkcs7',
		'application/pkcs10',
		'application/pkcs8',
		'multipart/signed',
		'application/x-pkcs7-signature',
		'application/x-pkcs7-mime'
	);
	/**
     * Constructor.
     *
     * @param Horde_Crypt_Smime $smime  S/MIME object.
     */
    public function __construct($params = array())
    {
		parent::__construct($params);
		$mailconfig = Api\Config::read('mail');
		$this->sslpath = $mailconfig['opensslpath']? $mailconfig['opensslpath']: '';
    }

	/**
	 * Check if a given mime type is smime type
	 *
	 * @param string $_mime mime type
	 *
	 * @return boolean returns TRUE if the given mime is smime
	 */
	public static function isSmime ($_mime)
	{
		return in_array($_mime, self::$SMIME_TYPES);
	}

	/**
	 * Check if the openssl is supported
	 *
	 * @return boolean returns True if openssl is supported
	 */
	public function enabled ()
	{
		try
		{
			$this->checkForOpenSSL();
		} catch (Exception $ex) {
			return false;
		}
		return true;
	}

	/**
	 * Extract Certificates from given PKCS7 data
	 *
	 * @param string $pkcs7
	 * @return string returns
	 * @throws Horde_Crypt_Exception
	 */
	public function extractCerticatesFromPKCS7 ($pkcs7)
	{
		$this->checkForOpenSSL();
		
		// Create temp file for input
        $input = $this->_createTempFile('smime-pkcs7');
		$output = $this->_createTempFile('smime-pkcs7-out');
        /* Write text to file. */
        file_put_contents($input, $pkcs7);

		exec($this->sslpath . ' pkcs7 -print_certs -inform der -in ' . $input . ' -outform PEM -out ' . $output);

		$ret = file_get_contents($output);

		if ($ret) return $ret;
		throw new Horde_Crypt_Exception(Horde_Crypt_Translation::t("OpenSSL error: Could not extract certificates from pkcs7 part."));
	}

	/**
     * Extract the contents from signed S/MIME data.
     *
     * @param string $data     The signed S/MIME data.
	 *
     * @return string  The contents embedded in the signed data.
     * @throws Horde_Crypt_Exception
     */
	public function extractSignedContents ($data) {
		return parent::extractSignedContents($data, $this->sslpath);
	}
}
