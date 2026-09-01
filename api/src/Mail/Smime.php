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
	 * Try decrypting a message against several candidate recipient certificates
	 *
	 * openssl_pkcs7_decrypt() (used by Horde_Crypt_Smime::decrypt()) picks the message's
	 * RecipientInfo to use by matching the given certificate's issuer+serial - NOT simply by
	 * whether the private key mathematically fits. So renewing a certificate via CSR (same key
	 * pair, but a new issuer/serial from the CA) makes it impossible to decrypt messages that
	 * were encrypted under the previous certificate, even though the private key never changed.
	 * Try each candidate certificate in turn (current one first) so old mail keeps decrypting
	 * across a renewal, as long as the matching certificate is still available as a candidate
	 * (see import_smime_cert() in admin_mail, which retains the previous certificate for this).
	 *
	 * @param Horde_Crypt_Smime $smime
	 * @param string $message
	 * @param string[] $pubkeys candidate certificates to try, most-likely-first
	 * @param string $privkey
	 * @param string $passphrase
	 * @return string decrypted message
	 * @throws \Horde_Crypt_Exception if none of the candidates could decrypt the message
	 */
	public static function decryptWithCandidates(Horde_Crypt_Smime $smime, string $message,
		array $pubkeys, string $privkey, string $passphrase='') : string
	{
		$exception = null;
		foreach (array_unique(array_filter($pubkeys)) as $pubkey)
		{
			try
			{
				return $smime->decrypt($message, [
					'type' => 'message',
					'pubkey' => $pubkey,
					'privkey' => $privkey,
					'passphrase' => $passphrase,
				]);
			}
			catch (\Horde_Crypt_Exception $e)
			{
				$exception = $e;
			}
		}
		throw $exception ?: new \Horde_Crypt_Exception('No certificate to try for decryption.');
	}

	/**
	 * Does the given certificate belong to the given private key?
	 *
	 * Used to tell a retired OWN leaf-certificate (kept around in extracerts by
	 * import_smime_cert() so old messages stay decryptable, see decryptWithCandidates()) apart
	 * from a genuine CA/intermediate certificate (belonging to the CA's key, not ours) also stored
	 * in extracerts - only the latter belongs in the certificate chain sent along with outgoing
	 * signed mail (mail_compose::_encrypt()).
	 *
	 * @param string $certPem
	 * @param string $privkey
	 * @param string $passphrase = ''
	 * @return bool
	 */
	public static function isOwnCertificate(string $certPem, string $privkey, string $passphrase='') : bool
	{
		if (!($key = @openssl_pkey_get_private($privkey, $passphrase)))
		{
			return false;
		}
		return openssl_x509_check_private_key($certPem, $key);
	}

	/**
	 * JMAP-native S/MIME resolution: decrypt/verify a raw message already fetched via JMAP (Blob
	 * download for Stalwart - Api\Mail\Imap\Jmap - or JmapShim::fetchRawMessage() for the local
	 * shim) instead of Mail::resolveSmimeMessage()'s IMAP-based getMessageRawBody(). Same
	 * decrypt/verify logic as that method (Horde_Crypt_Smime, via this class, unchanged) - only
	 * how the raw bytes were obtained differs, so this is the one place that logic lives, reused by
	 * both backends instead of duplicated.
	 *
	 * @param int $profileID mail account id, for cert/key lookup (get_acc_smime())
	 * @param string $rawMessage raw RFC822 bytes, however obtained
	 * @param string $topLevelType top-level Content-Type of the still-encrypted message, e.g.
	 *  "multipart/signed" or "application/pkcs7-mime" - used only to decide signature-only vs.
	 *  encrypted, mirrors Mail\Smime::getSmimeType()'s own logic without needing a Horde_Mime_Part
	 * @param string $passphrase = '' falls back to the cached session passphrase, same as
	 *  Mail::_decryptSmimeBody() already does
	 * @param ?string $fromAddress sender address (already known from the Email envelope/JMAP
	 *  "from", no extra fetch needed) - cross-checked against the signer certificate's email
	 * @return Horde_Mime_Part the decrypted/verified structure, with 'X-EGroupware-Smime' metadata
	 *  attached (same convention Mail::getStructure() already uses)
	 * @throws Smime\PassphraseMissing
	 */
	public static function resolveMessage(int $profileID, string $rawMessage, string $topLevelType,
		string $passphrase='', ?string $fromAddress=null) : Horde_Mime_Part
	{
		$passphrase = $passphrase ?: (Api\Cache::getSession('mail', 'smime_passphrase') ?: '');
		$metadata = ['mimeType' => $topLevelType];
		$smime = new self;
		$message = $rawMessage;

		$signatureOnly = self::isSmimeSignatureOnly(
			$topLevelType === 'multipart/signed' ? self::SMIME_TYPE_SIGNED_DATA : null);

		if (!$signatureOnly)
		{
			$acc_smime = self::get_acc_smime($profileID, $passphrase);
			if (empty($acc_smime) || !$smime->verifyPassphrase($acc_smime['pkey'] ?? '', $passphrase))
			{
				throw new Smime\PassphraseMissing(lang('Authentication failure!'));
			}
			$AB_bo = new \addressbook_bo();
			$certkey = $AB_bo->get_smime_keys($acc_smime['acc_smime_username'] ?? '');
			try
			{
				$message = self::decryptWithCandidates($smime, $message, array_merge([
					$certkey[strtolower($acc_smime['acc_smime_username'] ?? '')] ?? '',
					$acc_smime['cert'] ?? '',
				], $acc_smime['extracerts'] ?? []), $acc_smime['pkey'], $passphrase);
			}
			catch (\Horde_Crypt_Exception $e)
			{
				throw new Smime\PassphraseMissing(lang('Could not decrypt '.
					'S/MIME data. This message may not be encrypted by your '.
					'public key and not being able to find corresponding private key.'));
			}
			$metadata['encrypted'] = true;
		}

		$cert = null;
		try
		{
			$cert = $smime->verifySignature($message);
		}
		catch (\Exception $ex)
		{
			if (isset($message['password_required']))
			{
				throw new Smime\PassphraseMissing($message['msg']);
			}
			// verification failure - either tampered, not validly signed, or encrypted-only
			$metadata['verify'] = false;
			$metadata['signed'] = true;
			$metadata['msg'] = $ex->getMessage();
		}

		if ($cert)	// signed message, might be encrypted too
		{
			$message_parts = $smime->extractSignedContents($message);
			$cert_email = strtolower($cert->email);
			$metadata = array_merge($metadata, [
				'verify' => $cert->verify,
				'cert' => $cert->cert,
				'certDetails' => $smime->parseCert($cert->cert),
				'msg' => $cert->msg,
				'certHtml' => $smime->certToHTML($cert->cert),
				'email' => $cert_email,
				'signed' => true,
			]);
			if ($fromAddress && strcasecmp($fromAddress, $cert_email) != 0 &&
				stripos($metadata['certDetails']['extensions']['subjectAltName'] ?? '', $fromAddress) === false)
			{
				$metadata['unknownemail'] = true;
				$metadata['msg'] .= ' '.lang('Email address of signer is different from the email address of sender!');
			}
			$AB_bo ??= new \addressbook_bo();
			$certkey = $AB_bo->get_smime_keys($cert_email);
			if (!is_array($certkey) || strcasecmp(trim($certkey[$cert_email] ?? ''), trim($cert->cert)) != 0)
			{
				$metadata['addtocontact'] = true;
			}
		}
		else	// only encrypted, or verification failed above
		{
			$message_parts = Horde_Mime_Part::parseMessage($message, ['forcemime' => true]);
		}
		$message_parts->setMetadata('X-EGroupware-Smime', $metadata);

		return $message_parts;
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
	 * Combine a private key and a certificate (optionally with intermediate/CA
	 * certificates) into a PKCS12 (p12) blob
	 *
	 * Used to import a CA-signed certificate received for a previously
	 * exported CSR: the certificate is combined with the private key already
	 * stored for the account, so message signing/decryption keeps using the
	 * very same private key. Any $extracerts given (eg. an intermediate CA
	 * certificate) get bundled into the p12 too - mail_compose::_encrypt()
	 * already reads them back out (via get_acc_smime()'s 'extracerts') and
	 * passes them into Horde_Crypt_Smime/openssl_pkcs7_sign(), which embeds
	 * them into outgoing signed messages so recipients can validate the
	 * certificate chain up to a CA they trust, without needing the
	 * intermediate separately.
	 *
	 * @param string $privkey private key in PEM format
	 * @param string $cert certificate in PEM format
	 * @param string $privPassphrase = '' passphrase protecting $privkey, if any
	 * @param string $exportPassword = '' passphrase to protect the resulting p12, if any
	 * @param string[] $extracerts = [] additional (eg. intermediate CA) certificates in PEM format
	 * @return string|false p12 in binary format or false on failure (eg. cert does not match private key)
	 */
	public static function build_pkcs12($privkey, $cert, $privPassphrase = '', $exportPassword = '', array $extracerts = array())
	{
		$options = empty($extracerts) ? array() : array('extracerts' => $extracerts);
		if (!($key = openssl_pkey_get_private($privkey, $privPassphrase)) ||
			!openssl_pkcs12_export($cert, $out, $key, $exportPassword, $options))
		{
			return false;
		}
		return $out;
	}

	/**
	 * Normalize an uploaded certificate/certificate-bundle into an array of PEM certificates
	 *
	 * Accepts whatever a CA is likely to hand back: a single PEM certificate, several PEM
	 * certificates concatenated (a common way CAs deliver "your cert + the chain" as one file),
	 * a PEM-armored PKCS#7 bundle (-----BEGIN PKCS7-----), or any of those in raw/binary DER
	 * encoding instead of PEM text (eg. a Windows-style .cer or .p7b) - PEM is just base64(DER)
	 * wrapped in armor, so DER input is converted by wrapping it the same way, then parsed.
	 *
	 * @param string $data raw uploaded file content, PEM or DER, single cert or bundle
	 * @return string[] PEM certificates found (possibly empty, if $data was not usable at all)
	 */
	public static function normalize_cert_pem($data)
	{
		// one or more bare PEM certificates, possibly concatenated
		if (preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----\r?\n?/s', $data, $matches) &&
			$matches[0])
		{
			return $matches[0];
		}
		// PEM-armored PKCS#7 bundle
		if (str_contains($data, '-----BEGIN PKCS7-----') && openssl_pkcs7_read($data, $certs))
		{
			return $certs;
		}
		// raw/binary DER: PEM is just base64(DER) wrapped in armor - try as a single certificate...
		$as_cert = "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($data), 64, "\n")."-----END CERTIFICATE-----\n";
		if (openssl_x509_read($as_cert))
		{
			return array($as_cert);
		}
		// ...then as a PKCS#7 bundle
		$as_pkcs7 = "-----BEGIN PKCS7-----\n".chunk_split(base64_encode($data), 64, "\n")."-----END PKCS7-----\n";
		if (openssl_pkcs7_read($as_pkcs7, $certs))
		{
			return $certs;
		}
		return array();
	}

	/**
	 * Check whether a stored p12 blob requires a passphrase to even open the container
	 *
	 * Deliberately bypasses get_acc_smime()'s session-cached-passphrase fallback (tries a literal
	 * empty passphrase only), so it gives a clean "does the STORED blob itself need one" answer
	 * regardless of whatever might currently be cached - used to proactively tell the user their
	 * certificate needs a passphrase, before they hit a confusing "not found"/"wrong passphrase"
	 * error on export/import.
	 *
	 * @param string $pkcs12 raw p12 content, eg. Credentials::read()'s 'acc_smime_password'
	 * @return bool true if a passphrase is required (opening with an empty one fails)
	 */
	public static function isPassphraseProtected($pkcs12)
	{
		$certs = array();
		return !@openssl_pkcs12_read($pkcs12, $certs, '');
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
		// only fall back to the cached passphrase if the caller didn't supply one: the cache is a
		// single session-wide slot, NOT keyed by acc_id, so for a user with multiple S/MIME-enabled
		// accounts it can hold a DIFFERENT account's passphrase - an explicitly given passphrase
		// (eg. freshly typed into a form) must take priority over that stale/wrong cached value.
		if (empty($passphrase) && Api\Cache::getSession('mail', 'smime_passphrase'))
		{
			$passphrase = Api\Cache::getSession('mail', 'smime_passphrase');
		}
		// use_cache: false - the key may have just been created/imported in a different request
		// (different PHP-FPM worker), whose write() only invalidates its OWN process-local cache
		$on_login = null;
		$acc_smime = Credentials::read(
				$acc_id,
				Credentials::SMIME,
				$account_id ? array(0, $account_id) : $GLOBALS['egw_info']['user']['account_id'],
				$on_login,
				null,
				false
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

	/**
	 * Opportunistically resync the addressbook's own separate copy of this account's S/MIME
	 * certificate (addressbook_bo::get_smime_keys()/set_smime_keys(), a VFS-file-backed contact
	 * field - NOT the same storage as get_acc_smime()'s own Credentials/p12) - call this whenever
	 * $passphrase is already in hand as a plain variable, right after it's been PROVEN to work
	 * (a confirmed decrypt, or a confirmed sign/encrypt), rather than depending on
	 * get_acc_smime()'s own session-cached-passphrase fallback surviving to a LATER, separate
	 * request (found live 2026-09-02: session write/close instability elsewhere in the codebase -
	 * unrelated, actively-changing work - made that fallback an unreliable trigger for
	 * admin_mail::edit()'s own equivalent check, which only ever runs on a later account-settings
	 * page load).
	 *
	 * Covers the case found live 2026-09-02: an addressbook-write ACL failure (since fixed) left
	 * the addressbook holding a stale certificate indefinitely after a key rotation, silently
	 * breaking outgoing signing (embeds the wrong cert - recipients see an unverifiable signature)
	 * and encryption to this account's own address (encrypts under a possibly-retired public key)
	 * with no other way to notice or fix it short of generating/importing a whole new certificate.
	 *
	 * Deliberately silent (no message shown, all failures swallowed) - unlike
	 * admin_mail::edit()'s own resync (shown while the user is looking at S/MIME settings anyway),
	 * this fires from otherwise-unrelated actions (viewing/sending mail), where a random "public
	 * key added to addressbook" toast would be surprising and where a failure here should never
	 * block the actual view/send that triggered it.
	 *
	 * @param int $acc_id
	 * @param string $passphrase already-confirmed-working passphrase
	 * @param int|null $account_id see get_acc_smime()'s own docblock - only needed when acting on
	 *  behalf of another user
	 * @return void
	 */
	public static function resyncAddressbookCert(int $acc_id, string $passphrase, ?int $account_id=null) : void
	{
		try
		{
			$acc_smime = self::get_acc_smime($acc_id, $passphrase, $account_id);
			if (empty($acc_smime['cert']))
			{
				return;
			}
			$smime = new self();
			$email = $smime->getEmailFromKey($acc_smime['cert']);
			$AB_bo = new \addressbook_bo();
			$stored = $AB_bo->get_smime_keys($email)[strtolower($email)] ?? null;
			if ($stored === null || trim($stored) !== trim($acc_smime['cert']))
			{
				$AB_bo->set_smime_keys([$email => $acc_smime['cert']]);
			}
		}
		catch (\Throwable $e)
		{
			_egw_log_exception($e);
		}
	}
}