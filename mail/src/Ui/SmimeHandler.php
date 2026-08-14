<?php
/**
 * EGroupware Mail: S/MIME certificate/key management ajax handlers
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Mail\Ui;

use EGroupware\Api;
use EGroupware\Api\Mail;

/**
 * S/MIME certificate/key ajax handlers, extracted from mail_ui.
 *
 * `mail_ui`'s own `ajax_smimeAttachmentsChecker()`/`ajax_smimeAddCertToContact()`/
 * `smimeExportCert()`/`smimeExportCsr()` stay in place as one-line delegations to here - required
 * because EGroupware's ajax/menuaction dispatch resolves handlers by `mail_ui::methodName`, not by
 * class-agnostic name (see doc/ai/projects/mail-bo-decoupling.md). `smimePassphraseFormHtml()` was
 * NOT moved here - unlike the rest of this group, it's coupled to `mail_ui`'s own instance state
 * (`$this->mail_bo`, `$this->get_email_header()`), so it stays with the body-rendering code it's
 * part of rather than this otherwise-self-contained cert/key-management group.
 */
class SmimeHandler
{
	/**
	 * @see mail_ui::ajax_smimeAttachmentsChecker()
	 */
	public function ajaxAttachmentsChecker() : void
	{
		Api\Json\Response::get()->data(true);
	}

	/**
	 * Adds certificate to relevant contact
	 *
	 * @param array $metadata data of sender's certificate
	 */
	public function ajaxAddCertToContact(array $metadata) : void
	{
		$ab = new \addressbook_bo();
		Api\Json\Response::get()->data($ab->set_smime_keys([$metadata['email'] => $metadata['cert']]));
	}

	/**
	 * Vet $_GET['account_id'] for the exportCert()/exportCsr() download endpoints
	 *
	 * Only trusted for users with admin rights: the credential was stored on behalf of another user
	 * (eg. an admin managing a shared/other user's mail account via admin_mail's called_for) -
	 * Mail\Smime::get_acc_smime() otherwise only looks up the current user's own credentials.
	 * Without this check, any logged in user could pass an arbitrary account_id to export another
	 * user's S/MIME private key.
	 *
	 * @return int|null null uses get_acc_smime()'s own (current user) default
	 */
	public function accountId() : ?int
	{
		if (empty($_GET['account_id']) || !isset($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			return null;
		}
		return (int)$_GET['account_id'];
	}

	/**
	 * Export stored smime certificate in database
	 *
	 * @return boolean return false if not successful
	 */
	public function exportCert() : bool
	{
		if (empty($_GET['acc_id']))
		{
			return false;
		}
		$acc_smime = Mail\Smime::get_acc_smime($_GET['acc_id'], '', $this->accountId());
		$length = 0;
		$mime = 'application/x-pkcs12';
		Api\Header\Content::safe($acc_smime['acc_smime_password'], "certificate.p12", $mime, $length, true, true);
		echo $acc_smime['acc_smime_password'];
		exit();
	}

	/**
	 * Export a CSR (certificate signing request) generated from the stored smime private key, so a
	 * CA can (re-)issue a certificate for it
	 *
	 * See accountId() docblock re. $_GET['account_id'].
	 *
	 * @return boolean return false if not successful
	 */
	public function exportCsr() : bool
	{
		if (empty($_GET['acc_id']))
		{
			return false;
		}
		$acc_smime = Mail\Smime::get_acc_smime($_GET['acc_id'], '', $this->accountId());
		if (empty($acc_smime['pkey']))
		{
			echo lang('No S/MIME private key stored for this account.');
			exit();
		}
		$dn = !empty($acc_smime['cert']) ? Mail\Smime::dn_from_cert($acc_smime['cert']) : [];
		if (!($csr = Mail\Smime::generate_csr($acc_smime['pkey'], $dn)))
		{
			echo lang('Could not generate CSR.');
			exit();
		}
		$length = 0;
		Api\Header\Content::safe($csr, "certificate.csr", 'application/pkcs10', $length, true, true);
		echo $csr;
		exit();
	}
}
