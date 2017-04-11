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
use Horde_Crypt_Smime;

/**
 * EMailAdmin generic base class for SMTP
 */
class Smime extends Horde_Crypt_Smime
{
	/*
	 * SMIME types
	 */
	static $SMIME_TYPES = array (
		'application/pkcs8',
		'application/pkcs7',
		'application/pkcs10',
		'application/pkcs8',
		'multipart/signed',
		'application/x-pkcs7-signature',
		'application/x-pkcs7-mime',
		'application/pkcs7-mime',
		'application/pkcs7-signature',
	);

	/**
	 * SMIME signature only types
	 * @var type
	 */
	static $SMIME_SIGNATURE_ONLY_TYPES = array (
		'application/x-pkcs7-signature',
		'application/pkcs7-signature',
		'multipart/signed'
	);

	/**
	 * SMIME public key regular expression
	 */
	static public $pubkey_regexp = '/-----BEGIN PUBLIC KEY-----.*-----END PUBLIC KEY-----\r?\n/s';

	/**
	 * SMIME encrypted private key regular expresion
	 */
	static public $privkey_encrypted_regexp = '/-----BEGIN ENCRYPTED PRIVATE KEY-----.*-----END ENCRYPTED PRIVATE KEY-----\r?\n/s';

	/**
	 * SMIME private key regular expression
	 */
	static public $privkey_regexp = '/-----BEGIN PRIVATE KEY-----.*-----END PRIVATE KEY-----\r?\n/s';

	/**
	 * SMIME certificate regular expression
	 */
	static public $certificate_regexp = '/-----BEGIN CERTIFICATE-----.*-----END CERTIFICATE-----\r?\n/s';

	/**
	* Encryption type of sign
	*
	* @var String;
	*/
	const TYPE_SIGN = 'smime_sign';

	/**
	 * Encryption type of encrypt
	 *
	 * @var string
	 */
	const TYPE_ENCRYPT = 'smime_encrypt';

	/**
	 * Encryption type of sign and encrypt
	 *
	 * @var string
	 */
	const TYPE_SIGN_ENCRYPT = 'smime_sign_encrypt';

	/**
     * Constructor.
     *
     * @param Horde_Crypt_Smime $smime  S/MIME object.
     */
    public function __construct($params = array())
    {
		parent::__construct($params);
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
	 * Check if a given mime type is smime type of signature only
	 *
	 * @param string $_mime mimetype
	 *
	 * @return type
	 */
	public static function isSmimeSignatureOnly ($_mime)
	{
		return in_array($_mime, self::$SMIME_SIGNATURE_ONLY_TYPES);
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
	 * Extract public key from certificate
	 *
	 * @param string $cert content of certificate in PEM format
	 *
	 * @return string returns public key
	 */
	public function get_publickey ($cert)
	{
		$handle = openssl_get_publickey($cert);
		$keyData = openssl_pkey_get_details($handle);
		return $keyData['key'];
	}

	/**
	 * Extract certificates info from a p12 file
	 *
	 * @param string $pkcs12 content of p12 file in string
	 * @param string $passphrase = '', passphrase to unlock the p12 file
	 *
	 * @return boolean|array returns array of certs info or false if not successful
	 */
	public function extractCertPKCS12 ($pkcs12, $passphrase = '')
	{
		$certs = array ();
		if (openssl_pkcs12_read($pkcs12, $certs, $passphrase))
		{
			return $certs;
		}
		else
		{
			return false;
		}
	}

	/**
     * Verify a signature using via S/MIME.
     *
     * @param string $text  The multipart/signed data to be verified.
     * @param mixed $certs  Either a single or array of root certificates.
     *
     * @return stdClass  Object with the following elements:
     * <pre>
     * cert - (string) The certificate of the signer stored in the message (in
     *        PEM format).
     * email - (string) The email of the signing person.
     * msg - (string) Status string.
     * verify - (boolean) True if certificate was verified.
     * </pre>
     * @throws Horde_Crypt_Exception
	 *
	 * 
	 * @TODO: This method is overridden in order to extract content
	 * from the signed message. There's a pull request opened for this
	 * modification on horde github, https://github.com/horde/horde/pull/218
	 * which in case that gets merged we need to remove this implementation.
     */
    public function verify($text, $certs)
    {
        /* Check for availability of OpenSSL PHP extension. */
        $this->checkForOpenSSL();

        /* Create temp files for input/output. */
        $input = $this->_createTempFile('horde-smime');
        $output = $this->_createTempFile('horde-smime');
		$content = $this->_createTempFile('horde-smime');

        /* Write text to file */
        file_put_contents($input, $text);
        unset($text);

        $root_certs = array();
        if (!is_array($certs)) {
            $certs = array($certs);
        }
        foreach ($certs as $file) {
            if (file_exists($file)) {
                $root_certs[] = $file;
            }
        }

        $ob = new stdClass;

        if (!empty($root_certs) &&
            (openssl_pkcs7_verify($input, 0, $output) === true)) {
            /* Message verified */
            $ob->msg = Horde_Crypt_Translation::t("Message verified successfully.");
            $ob->verify = true;
        } else {
            /* Try again without verfying the signer's cert */
            $result = openssl_pkcs7_verify($input, PKCS7_NOVERIFY, $output);

            if ($result === -1) {
                throw new Horde_Crypt_Exception(Horde_Crypt_Translation::t("Verification failed - an unknown error has occurred."));
            } elseif ($result === false) {
                throw new Horde_Crypt_Exception(Horde_Crypt_Translation::t("Verification failed - this message may have been tampered with."));
            }

            $ob->msg = Horde_Crypt_Translation::t("Message verified successfully but the signer's certificate could not be verified.");
            $ob->verify = false;
        }
		if (openssl_pkcs7_verify($input, PKCS7_NOVERIFY, $output, array(), $output, $content))
		{
			$ob->content = file_get_contents($content);
		}
        $ob->cert = file_get_contents($output);
        $ob->email = $this->getEmailFromKey($ob->cert);

        return $ob;
    }
}
