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

/**
 * S/MIME certificate/key ajax handlers, extracted from mail_ui.
 *
 * `mail_ui`'s own `ajax_smimeAttachmentsChecker()`/`ajax_smimeAddCertToContact()` stay in place as
 * one-line delegations to here - required because EGroupware's ajax/menuaction dispatch resolves
 * handlers by `mail_ui::methodName`, not by class-agnostic name (see
 * doc/ai/projects/mail-bo-decoupling.md). `smimePassphraseFormHtml()` was NOT moved here - unlike
 * the rest of this group, it's coupled to the mail-body-render state (`mail_bo`,
 * `get_email_header()`), so it stays with the body-rendering code it's part of, now
 * `Mail\Ui\MessageDisplayHandler`, rather than this otherwise-self-contained cert/key-management
 * group.
 *
 * `exportCert()`/`exportCsr()`/`accountId()` (+ `mail_ui`'s own `smimeExportCert()`/
 * `smimeExportCsr()` delegations) removed 2026-09-08 - zero real callers left anywhere: the admin
 * S/MIME cert-management UI (admin/templates/*\/mailaccount.xet's export buttons) goes through
 * admin_mail::smimeExportFile() directly now, not through mail_ui.
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
}
