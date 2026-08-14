<?php
/**
 * EGroupware Mail: JMAP session bootstrap, push enablement, and quota-display formatting
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Mail\Ui;

use EGroupware\Api;
use EGroupware\Api\Mail;
use EGroupware\Api\Mail\CustomLabels;
use mail_ui;

/**
 * A lighter-coupled slice of the "Account/session/profile ajax handlers" group from
 * doc/ai/projects/mail-bo-decoupling.md - unlike ImportHandler/MessageActionHandler, these methods
 * were already written to take an explicit profile/server id rather than relying on `mail_ui`'s
 * connected mail_bo (see jmapBootstrap()/enablePush()'s own docblocks: this is deliberate, so they
 * have no side effect on the session's "active profile" state), so this class needs no `mail_ui`
 * instance at all - only two read-only references to `mail_ui`'s own static properties
 * ($icServerID as jmapBootstrap()'s "no explicit id given" fallback, $delimiter for folder-id
 * parsing), not a constructor dependency.
 *
 * `changeProfile()`/`ajax_changeProfile()`/`gatherVacation()`/`ajax_refreshVacationNotice()`/
 * `ajax_refreshFilters()`/`ajax_refreshQuotaDisplay()` stayed on `mail_ui` - each either mutates
 * `mail_ui`'s own instance state directly (`$this->searchTypes`, `$this->statusTypes`,
 * `self::$icServerID` itself) or explicitly constructs/depends on a full `mail_ui` instance
 * (`ajax_changeProfile()` does `new mail_ui(false)`, `ajax_refreshVacationNotice()` does
 * `new mail_ui()`), so there was no clean way to pull them out without just relocating the same
 * full coupling.
 */
class ProfileHandler
{
	/**
	 * Bootstrap payload for client-side direct JMAP access (see Mail\Imap\Stalwart::jmapBootstrap)
	 *
	 * Every account is JMAP-eligible (Stalwart directly, or plain IMAP via localBootstrap()), so
	 * returning null here means the account/server is genuinely unreachable right now - there is no
	 * server-side row-fetch fallback anymore, the client surfaces this as an error.
	 *
	 * @param int|null $icServerID profile / server ID, defaults to the active profile
	 */
	public static function jmapBootstrap($icServerID=null) : void
	{
		$response = Api\Json\Response::get();
		try
		{
			// accountId "0" is never a real account - it's served by mail/jmap.php from an
			// in-file fixture, purely so the client-side JMAP code can be tested/exercised
			// without a real mailbox.
			// Checked BEFORE the "?: mail_ui::$icServerID" fallback below: '0' is falsy in PHP,
			// so applying that fallback first would silently replace it with the active profile.
			if ((string)$icServerID === '0')
			{
				$bootstrap = self::localBootstrap('0');
				$bootstrap['customLabels'] = CustomLabels::getCustomLabels();
				$response->data($bootstrap);
				return;
			}
			$imapServer = Mail\Account::read($icServerID ?: mail_ui::$icServerID)->imapServer();
			$local = !($imapServer instanceof Mail\Imap\Stalwart);
			$bootstrap = $local
				// any plain IMAP account is served by our own local JMAP shim
				? self::localBootstrap((string)$icServerID)
				: $imapServer->jmapBootstrap();
			if ($bootstrap)
			{
				$bootstrap['isLocal'] = $local;
				$bootstrap['customLabels'] = CustomLabels::getCustomLabels();
				// account config only (no IMAP round-trip, no full special-use autodetection like
				// Mail::getTrashFolder()/getJunkFolder() do) - good enough for
				// MailJmap.deleteMessages()'s move-to-trash resolution and the "empty trash"/
				// "empty junk" fast paths; a reasonable simplification for accounts that don't
				// override the conventional "Trash"/"Junk" names without configuring
				// acc_folder_trash/acc_folder_junk
				$bootstrap['trashFolder'] = $imapServer->acc_folder_trash ?: 'Trash';
				$bootstrap['junkFolder'] = $imapServer->acc_folder_junk ?: null;
			}
			$response->data($bootstrap);
		}
		catch (\Exception $e)
		{
			unset($e);
			$response->data(null);
		}
	}

	/**
	 * Bootstrap payload for accounts served by our own local JMAP shim (mail/jmap.php),
	 * i.e. every plain IMAP account (no real JMAP server exists for those) plus the acc_id="0"
	 * demo/test fixture.
	 *
	 * @param string $accountId
	 * @return array values for keys "sessionUrl", "accountId", "access_token", "expires_in"
	 */
	private static function localBootstrap(string $accountId) : array
	{
		return [
			// accountId in the query string lets JmapShim::session() report this specific
			// account's "primaryAccounts"/"accounts" - session() itself is otherwise a shared,
			// generic endpoint with no other way to know which account is asking
			'sessionUrl' => Api\Framework::getUrl(Api\Framework::link('/mail/jmap.php')).'?accountId='.urlencode($accountId),
			'accountId' => $accountId,
			// NOT the session id: auth is via the session cookie (mail/jmap.php is a
			// same-origin endpoint), this only fills jmap-jam's required bearerToken field
			'access_token' => 'no-token-required',
			'expires_in' => 3600,	// session lifetime is renewed on every request anyway
			'isLocal' => true,
		];
	}

	/**
	 * (Re-)enable server push for a profile, if its mail server supports it
	 *
	 * Ported from the old get_rows()'s per-fetch call: now triggered client-side (fire-and-forget)
	 * from mail/js/jmap.ts's MailJmap.enablePushOnce(), at most once per profile per JMAP-token
	 * lifetime rather than on every row fetch.
	 *
	 * Covers both push mechanisms Api\Mail\Imap\PushIface implementors may use: Stalwart's native
	 * JMAP push subscriptions (Api\Mail\Imap\Jmap::enablePush(), always available), and plain
	 * IMAP/Dovecot's mailbox-metadata push token registration (Api\Mail\Imap::enablePush(),
	 * opt-in via the "imap_hosts_with_push" site config) - whichever the account's server class
	 * actually implements.
	 *
	 * Deliberately uses the same lightweight Mail\Account::read()->imapServer() object
	 * jmapBootstrap() already uses (not mail_ui::changeProfile()), so this has no side effect on
	 * the user's "active profile" session state - it may run for a profile that isn't the one
	 * currently being viewed.
	 *
	 * @param int|string $icServerID profile / server ID
	 * @param string|null $selectedFolder "profileID::folder/path", used to seed Stalwart's
	 *  "current folder" for its push-state diffing - defaults to INBOX if not given
	 */
	public static function enablePush($icServerID, $selectedFolder=null) : void
	{
		try
		{
			$imapServer = Mail\Account::read($icServerID)->imapServer();
			if ($imapServer instanceof Api\Mail\Imap\PushIface && $imapServer->pushAvailable())
			{
				$folder = explode(mail_ui::$delimiter, (string)$selectedFolder, 2)[1] ?? 'INBOX';
				$imapServer->enablePush(null, $icServerID.mail_ui::$delimiter.($folder ?: 'INBOX'));
			}
		}
		catch (\Exception $e)
		{
			_egw_log_exception($e);
		}
	}

	/**
	 * Gather info on how to display the quota info
	 *
	 * @param int $_usage amount of usage in Kb
	 * @param int $_limit amount of limit in Kb
	 * @return array array(class => string, text => string, percent => string, freespace => integer)
	 */
	public static function quotaDisplay($_usage, $_limit) : array
	{
		$percent = $_limit == 0 ? 100 : round(($_usage*100)/$_limit);
		$limit = Mail::show_readable_size($_limit*1024);
		$usage = Mail::show_readable_size($_usage*1024);

		if ($_limit > 0)
		{
			$text = $usage.'/'.$limit;
			switch ($percent)
			{
				case ($percent > 90):
					$class = 'mail-index_QuotaRed';
					break;
				case ($percent > 80):
					$class = 'mail-index_QuotaYellow';
					break;
				default:
					$class = 'mail-index_QuotaGreen';
			}
		}
		else
		{
			$text = $usage;
			$class = 'mail-index_QuotaGreen';
		}
		return [
			'class' => $class,
			'text' => lang('Quota: %1', $text),
			'percent' => $percent,
			'freespace' => $_limit*1024 - $_usage*1024,
		];
	}
}
