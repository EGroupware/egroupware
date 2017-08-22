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
use Horde_Mime_Part;
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
     * @param Horde_Crypt_Smime $params  S/MIME object.
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
			error_log(__METHOD__."() openssl extension is not enabled! $ex");
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
     * Extract the contents from signed S/MIME data.
     *
     * @param string $data     The signed S/MIME data.
     *
     * @return Horde_Mime_Part returns content of signed message as mime part object
     */
    public function extractSignedContents($data)
    {
        return Horde_Mime_Part::parseMessage(parent::extractSignedContents($data), array('forcemime' => true));
    }

	/**
	 * Verify a signature
	 *
	 * @param type $message
	 * @return type
	 */
	public function verifySignature($message)
	{
		$cert_locations = openssl_get_cert_locations();
		$certs = array();
		foreach (scandir($cert_locations['default_cert_dir']) as &$file)
		{
			if (!is_dir($cert_locations['default_cert_dir'].'/'.$file)) $certs[]= $cert_locations['default_cert_dir'].'/'.$file;
		}
		return $this->verify($message, $certs);
	}

	/**
	 * Generate certificate, private and public key pair
	 *
	 * @param array $_dn distinguished name to be used in certificate
	 * @param mixed $_cacert certificate will be signed by cacert (CA). Null means
	 * self-signed certificate.
	 * @param string $passphrase = null, protect private key by passphrase
	 *
	 * @return mixed returns signed certificate, private key and pubkey or False on failure.
	 */
	public function generate_certificate ($_dn, $_cacert = null, $passphrase = null)
	{
		$config = array(
			'digest_alg' => 'sha1',
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		);
		$result = array();
		$csrsout = '';
		if (!!($pkey = openssl_pkey_new($config)))
		{
			if(openssl_pkey_export($pkey, $result['privkey'], $passphrase))
			{
				$pubkey = openssl_pkey_get_details($pkey);
				$result['pubkey'] = $pubkey['key'];
			}
			$csr = openssl_csr_new($_dn, $pkey, $config);
			$csrs = openssl_csr_sign($csr, $_cacert, $pkey, $_dn['validation']?$_dn['validation']:365);
			if (openssl_x509_export($csrs, $csrsout)) $result['cert'] = $csrsout;
		}
		return $result;
	}
}
