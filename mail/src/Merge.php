<?php
/**
 * EGroupware Mail: mail-merge a document/template to one or more recipients
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Mail;

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Mail;
use EGroupware\Api\Vfs;

/**
 * Mail-merge ajax handlers, extracted from Compose 2026-09-08 - EgwApp::_mergeEmail()
 * (api/js/jsapi/egw_app.ts), a generic action multiple apps use (addressbook's own single/multi
 * -contact "insert into email document" merge is the concrete example), is the only real caller of
 * either method, and neither ever needed anything from Compose beyond a connected mail_bo -
 * no createMessage()/_getAttachmentLinks()/etc. from the ComposeMessageBuilder trait. Dispatched
 * directly via menuaction (mail.EGroupware\Mail\Merge.ajax_merge/ajax_mergeSingle) - both callers
 * are this repo's own JS, updated alongside this move, so no delegating stub was left on
 * Compose.
 */
class Merge
{
	/**
	 * @var Mail
	 */
	protected Mail $mail_bo;

	function __construct(?int $_acc_id=null)
	{
		$profileID = $_acc_id ?: (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];
		$this->mail_bo = Mail::getInstance(true, $profileID);
	}

	/**
	 * Merge a single document (eg. addressbook's own "mail merge" action, single recipient) into a
	 * NEW draft, for a fully client-driven compose bootstrap to open afterward - doc/ai/projects/
	 * mail-compose-jmap-migration.md, Step 10's own last classic-postback caller of compose()
	 * (api/js/jsapi/egw_app.ts's own _mergeEmail(), single-recipient branch, found auditing
	 * compose()'s remaining callers 2026-09-07).
	 *
	 * Reuses the exact same merge-into-drafts-folder mechanism ajax_merge() (multi-recipient,
	 * always-send) already uses, via Api\Mail::importMessageToMergeAndSend() - just a single,
	 * genuinely-numeric merge id here, so it's never treated as the "extra non-numeric id" trick
	 * that method uses to force an actual send instead of a draft save. Returns the resulting
	 * draft's own row id + profileID instead of rendering a classic compose() postback around it -
	 * the client already knows how to open a draft entirely client-side (MailApp.composeMessage()'s
	 * own 'composefromdraft' dispatch -> MailCompose.bootstrapDraft(), same as reopening any other
	 * draft, see mail_ui::ajax_view()'s own compose.php redirect for that same mechanism).
	 *
	 * @param string|int $id merge target id (eg. a contact id) - single value only, matching
	 *  _mergeEmail()'s own "exactly one row selected" branch (multiple/select-all still goes
	 *  through ajax_merge()'s own long-task loop, always-send, unchanged)
	 * @param string $document vfs path of the document to merge
	 * @param string $mergeClass Api\Storage\Merge subclass to use, defaults to Api\Contacts\Merge
	 *  (same validation regex as ajax_merge() - anything else silently falls back to the default)
	 * @return void writes {id} via Api\Json\Response, or {msg} on error
	 */
	public function ajax_mergeSingle($id, string $document, string $mergeClass = '')
	{
		$response = Api\Json\Response::get();
		try
		{
			// acc_id deliberately not returned - MailApp.composeMessage() already derives it from
			// the row id itself (Api\Mail::splitRowID()'s own profileID segment), same as reopening
			// any other draft.
			$response->data(['id' => $this->mergeSingle($id, $document, $mergeClass)]);
		}
		catch (\Exception $e)
		{
			$response->data(['msg' => $e->getMessage()]);
		}
	}

	/**
	 * Merge a single document into a new draft - the actual work behind ajax_mergeSingle(),
	 * extracted 2026-09-08 so a plain (non-ajax) PHP caller can reuse it directly: invoices'
	 * own "mail this invoice" postback (invoices/src/Ui.php) used to redirect to the classic,
	 * now-deleted `mail.mail_compose.compose` menuaction (`from=merge`) - the modern equivalent is
	 * this method plus building a `/mail/compose.php?from=composefromdraft&id=...` redirect the
	 * same way mail_ui::ajax_view()'s own composefromdraft redirect already does, since the merge
	 * itself always lands in the drafts folder first regardless of caller.
	 *
	 * @param string|int $id merge target id (eg. a contact/invoice id)
	 * @param string $document vfs path of the document to merge
	 * @param string $mergeClass Api\Storage\Merge subclass to use, defaults to Api\Contacts\Merge
	 *  (anything not matching this method's own validation regex silently falls back to that default)
	 * @return string the resulting draft's own mail row id (Api\Mail::splitRowID()'s own profileID
	 *  segment identifies the account/acc_id to open it with)
	 * @throws Api\Exception\WrongUserinput if $document isn't a valid, mergeable template
	 * @throws \Exception if the merge/send itself fails
	 */
	public function mergeSingle($id, string $document, string $mergeClass = '') : string
	{
		$merge_class = preg_match('/^(EGroupware\\\\.+\\\\Merge|[a-z_-]+_merge)$/', $mergeClass) ?
			$mergeClass : 'EGroupware\\Api\\Contacts\\Merge';
		$document_merge = new $merge_class();
		$this->mail_bo->openConnection();

		if (($error = $document_merge->check_document($document, '')))
		{
			throw new Api\Exception\WrongUserinput($error);
		}
		$merged_mail_id = '';
		$folder = $this->mail_bo->getDraftFolder();
		$this->mail_bo->importMessageToMergeAndSend($document_merge, Vfs::PREFIX.$document, [$id], $folder, $merged_mail_id);

		return \mail_ui::generateRowID($this->mail_bo->profileID, $folder, $merged_mail_id, true);
	}

	/**
	 * Merge the selected contact ID into the document given in $_REQUEST['document']
	 * and send it.
	 *
	 * @param int $contact_id
	 */
	public function ajax_merge($contact_id)
	{
		$response = Api\Json\Response::get();
		if(class_exists($_REQUEST['merge']) && is_subclass_of($_REQUEST['merge'], 'EGroupware\\Api\\Storage\\Merge'))
		{
			$document_merge = new $_REQUEST['merge']();
		}
		else
		{
			$document_merge = new Api\Contacts\Merge();
		}
		$this->mail_bo->openConnection();

		if(($error = $document_merge->check_document($_REQUEST['document'],'')))
		{
			$response->error($error);
			return;
		}

		// Actually do the merge
		$folder = $merged_mail_id = null;
		try
		{
			$results = $this->mail_bo->importMessageToMergeAndSend(
				$document_merge, Vfs::PREFIX . $_REQUEST['document'],
				// Send an extra non-numeric ID to force actual send of document
				// instead of save as draft
				array((int)$contact_id, ''),
				$folder,$merged_mail_id
			);

			// Also save as infolog
			if($merged_mail_id && $_REQUEST['to_app'] && isset($GLOBALS['egw_info']['user']['apps'][$_REQUEST['to_app']]))
			{
				$rowid = \mail_ui::generateRowID($this->mail_bo->profileID, $folder, $merged_mail_id, true);
				$data = \mail_integration::get_integrate_data($rowid);
				if($data && $_REQUEST['to_app'] == 'infolog')
				{
					$bo = new \infolog_bo();
					$entry = $bo->import_mail($data['addresses'],$data['subject'],$data['message'],$data['attachments'],$data['date']);
					if($_REQUEST['info_type'] && isset($bo->enums['type'][$_REQUEST['info_type']]))
					{
						$entry['info_type'] = $_REQUEST['info_type'];
					}
					$bo->write($entry);
				}
			}
		}
		catch (\Exception $e)
		{
			// $contact_id is only a contacts id for addressbook's own merge - for every other app
			// (eg. infolog) it's that app's entity id, so use its link-title rather than
			// misreading an unrelated, coincidentally-numbered contact for the error message.
			if($document_merge instanceof Api\Contacts\Merge)
			{
				$contact = $document_merge->contacts->read((int)$contact_id);
				$email = ($contact['email'] ? $contact['email'] : $contact['email_home']);
				$nfn = ($contact['n_fn'] ? $contact['n_fn'] : $contact['n_given'].' '.$contact['n_family']);
				$label = "$nfn <$email>";
			}
			else
			{
				$app = $document_merge->get_app();
				$label = $app ? Link::title($app, $contact_id) : $contact_id;
			}
			$response->error(lang('Sending mail to "%1" failed', $label).
				"\n".$e->getMessage()
			);
		}

		if($results['success'])
		{
			$response->data(implode(',',$results['success']));
		}
		if($results['failed'])
		{
			$response->error(implode(',',$results['failed']));
		}
		// Missing recipient address is a data problem, not a (possibly transient) send failure -
		// report it as a plain skip in the long-task log instead of a retryable error/toast.
		if($results['no_email'])
		{
			$response->generic('skipped', ['message' => implode(',',$results['no_email'])]);
		}
	}
}
