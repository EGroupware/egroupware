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
use EGroupware\Api;
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
	 * @var string[}
	 */
	static $SMIME_SIGNATURE_ONLY_TYPES = array (
		'application/x-pkcs7-signature',
		'application/pkcs7-signature'
	);

	/**
	 * SMIME public key regular expression
	 */
	static public $pubkey_regexp = '/-----BEGIN PUBLIC KEY-----.*-----END PUBLIC KEY-----\r?\n?/s';

	/**
	 * SMIME encrypted private key regular expresion
	 */
	static public $privkey_encrypted_regexp = '/-----BEGIN ENCRYPTED PRIVATE KEY-----.*-----END ENCRYPTED PRIVATE KEY-----\r?\n?/s';

	/**
	 * SMIME private key regular expression
	 */
	static public $privkey_regexp = '/-----BEGIN PRIVATE KEY-----.*-----END PRIVATE KEY-----\r?\n?/s';

	/**
	 * SMIME certificate regular expression
	 */
	static public $certificate_regexp = '/-----BEGIN CERTIFICATE-----.*-----END CERTIFICATE-----\r?\n?/s';

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
	 * Smime content type of signed message
	 *
	 * @var string
	 */
	const SMIME_TYPE_SIGNED_DATA = 'signed-data';

	/**
	 * Smime content type of encrypted message
	 *
	 * @var string
	 */
	const SMIME_TYPE_ENVELOPED_DATA = 'enveleoped-data';


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
	 * Check if a given smime type is smime type of signature only
	 *
	 * @param string $_smimeType smime type
	 * @param string $_mimeType mime type, it takes into account only if smimeType is not found
	 *
	 * @return boolean return whether given type is smime signature or not
	 */
	public static function isSmimeSignatureOnly ($_smimeType)
	{
		return $_smimeType == self::SMIME_TYPE_SIGNED_DATA ? true : false;
	}

	/**
	 * Extract smime type form mime part
	 * @param Horde_Mime_Part $_mime_part
	 *
	 * @return string return smime type or null if not found
	 */
	public static function getSmimeType (Horde_Mime_Part $_mime_part)
	{
		if (($type = $_mime_part->getContentTypeParameter('smime-type'))) {
            return strtolower($type);
        }
		//
		$protocol = $_mime_part->getContentTypeParameter('protocol');
		switch ($_mime_part->getType())
		{
			case "multipart/signed":
				return self::isSmime($protocol) ? self::SMIME_TYPE_SIGNED_DATA : null;
		}

        return null;
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
		} catch (\Exception $ex) {
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
	public static function extractCertPKCS12 ($pkcs12, $passphrase = '')
	{
		$certs = $out = array ();
		if (openssl_pkcs12_read($pkcs12, $certs, $passphrase))
		{
			openssl_pkey_export($certs['pkey'], $out, $passphrase);
			$certs['pkey'] = $out;
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
    public function extractSignedContents($data, $sslpath = null)
    {
        return Horde_Mime_Part::parseMessage(parent::extractSignedContents($data), array('forcemime' => true));
    }

	/**
	 * Verify a signature
	 *
	 * @param string $message
	 * @return \stdClass
	 */
	public function verifySignature($message)
	{
		$cert_locations = openssl_get_cert_locations();
		$certs = array();
		foreach (scandir($cert_locations['default_cert_dir']) as $file)
		{
			if ($file !== '..' && $file !=='.'
					&& !is_dir($cert_locations['default_cert_dir'].'/'.$file)) $certs[]= $cert_locations['default_cert_dir'].'/'.$file;
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

	/**
	 * Method to extract smime related info from credential table
	 *
	 * @param int $acc_id acc id of mail account
	 * @param string $passphrase = '' protect private key by passphrase
	 * @return mixed return array of smime info or false if fails
	 */
	public static function get_acc_smime($acc_id, $passphrase = '')
	{
		if (Api\Cache::getSession('mail', 'smime_passphrase'))
		{
			$passphrase = Api\Cache::getSession('mail', 'smime_passphrase');
		}
		$acc_smime = Credentials::read(
				$acc_id,
				Credentials::SMIME,
				$GLOBALS['egw_info']['user']['account_id']
		);
		foreach ($acc_smime as $key => $val)
		{
			// remove other imap stuffs but smime
			if (!preg_match("/acc_smime/", $key)) unset($acc_smime[$key]);
		}
		if (!empty($acc_smime['acc_smime_password']))
		{
			$extracted = self::extractCertPKCS12(
					$acc_smime['acc_smime_password'],
					$passphrase
			);
			return array_merge($acc_smime, is_array($extracted) ? $extracted : array());
		}
		return false;
	}
}