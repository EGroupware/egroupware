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
	 * @param int $validity = 365, validity of self-signed certificate in days
	 *
	 * @return mixed returns array with keys privkey, pubkey, csr and (self-signed) cert, or False on failure.
	 */
	public function generate_certificate ($_dn, $_cacert = null, $passphrase = null, $validity = 365)
	{
		$config = array(
			'digest_alg' => 'sha256',
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		);
		$result = array();
		if (!!($pkey = openssl_pkey_new($config)) &&
			openssl_pkey_export($pkey, $result['privkey'], $passphrase))
		{
			$pubkey = openssl_pkey_get_details($pkey);
			$result['pubkey'] = $pubkey['key'];
			if (($csr = openssl_csr_new($_dn, $pkey, $config)) &&
				openssl_csr_export($csr, $result['csr']) &&
				($x509 = openssl_csr_sign($csr, $_cacert, $pkey, $validity ?: 365, $config)) &&
				openssl_x509_export($x509, $csrsout))
			{
				$result['cert'] = $csrsout;
			}
		}
		return $result;
	}

	/**
	 * Generate a CSR (certificate signing request) for an existing private key
	 *
	 * Used to (re-)request a CA-signed certificate for a private key already
	 * stored for a mail account, either because the account currently has no
	 * certificate yet (freshly generated key, self-signed placeholder cert),
	 * or to replace/renew the certificate of an existing one.
	 *
	 * @param string $privkey private key in PEM format
	 * @param array $_dn distinguished name to be used in the CSR
	 * @param string $passphrase = '' passphrase protecting $privkey, if any
	 * @return string|false CSR in PEM format or false on failure
	 */
	public static function generate_csr($privkey, array $_dn, $passphrase = '')
	{
		if (!($key = openssl_pkey_get_private($privkey, $passphrase)) ||
			!($csr = openssl_csr_new($_dn, $key, array('digest_alg' => 'sha256'))) ||
			!openssl_csr_export($csr, $out))
		{
			return false;
		}
		return $out;
	}

	/**
	 * Combine a private key and a certificate into a PKCS12 (p12) blob
	 *
	 * Used to import a CA-signed certificate received for a previously
	 * exported CSR: the certificate is combined with the private key already
	 * stored for the account, so message signing/decryption keeps using the
	 * very same private key.
	 *
	 * @param string $privkey private key in PEM format
	 * @param string $cert certificate in PEM format
	 * @param string $privPassphrase = '' passphrase protecting $privkey, if any
	 * @param string $exportPassword = '' passphrase to protect the resulting p12, if any
	 * @return string|false p12 in binary format or false on failure (eg. cert does not match private key)
	 */
	public static function build_pkcs12($privkey, $cert, $privPassphrase = '', $exportPassword = '')
	{
		if (!($key = openssl_pkey_get_private($privkey, $privPassphrase)) ||
			!openssl_pkcs12_export($cert, $out, $key, $exportPassword))
		{
			return false;
		}
		return $out;
	}

	/**
	 * Extract the distinguished name from a certificate, in the long-form keys
	 * used by openssl_csr_new()/generate_certificate() (eg. "countryName"),
	 * as openssl_x509_parse() returns them in short form (eg. "C")
	 *
	 * @param string $cert certificate in PEM format
	 * @return array
	 */
	public static function dn_from_cert($cert)
	{
		if (!($data = openssl_x509_parse($cert)))
		{
			return array();
		}
		static $map = array(
			'C' => 'countryName',
			'ST' => 'stateOrProvinceName',
			'L' => 'localityName',
			'O' => 'organizationName',
			'OU' => 'organizationalUnitName',
			'CN' => 'commonName',
			'emailAddress' => 'emailAddress',
		);
		$dn = array();
		foreach ($map as $short => $long)
		{
			if (!empty($data['subject'][$short])) $dn[$long] = $data['subject'][$short];
		}
		return $dn;
	}

	/**
	 * Method to extract smime related info from credential table
	 *
	 * @param int $acc_id acc id of mail account
	 * @param string $passphrase = '' protect private key by passphrase
	 * @param int $account_id =null account the credential was stored for, eg.
	 *  admin_mail's $content['called_for'] - defaults to the current user, but
	 *  MUST be given when acting on behalf of another user (eg. admin editing
	 *  a shared/other user's mail account), or an existing credential will not
	 *  be found even though Mail\Credentials::write() stored it correctly
	 * @return mixed return array of smime info or false if fails
	 */
	public static function get_acc_smime($acc_id, $passphrase = '', $account_id = null)
	{
		if (Api\Cache::getSession('mail', 'smime_passphrase'))
		{
			$passphrase = Api\Cache::getSession('mail', 'smime_passphrase');
		}
		$acc_smime = Credentials::read(
				$acc_id,
				Credentials::SMIME,
				$account_id ? array(0, $account_id) : $GLOBALS['egw_info']['user']['account_id']
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