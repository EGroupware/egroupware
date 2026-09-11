<?php

/**
 * Shared bootstrap for infolog z-push tests (deliberately NOT namespaced -
 * see ZpushTaskTest.php's docblock: the stub below must become the global
 * \ZLog, not a namespaced one, or infolog_zpush.inc.php's unqualified
 * ZLog::Write() calls fall through to the real vendor class instead).
 *
 * infolog_zpush.inc.php calls ZLog::Write(...) unconditionally. The real
 * ZLog (vendor/egroupware/z-push-dev) requires a LOGBACKEND_CLASS constant
 * that is only defined by the full z-push server bootstrap
 * (activesync/index.php), which we deliberately do NOT want to run for a
 * unit test. Declaring this no-op stub BEFORE the z-push-dev autoload is
 * required means PHP resolves ZLog to it directly and never triggers the
 * real autoload, since a class that's already declared is never
 * autoloaded. Same technique calendar_zpush.inc.php already uses for its
 * own "run this file directly" self-test block.
 */
if (!class_exists('ZLog', false))
{
	class ZLog
	{
		static function Write($level, $msg, $truncate=true) { unset($level, $msg, $truncate); }
	}
}

// SyncTask / ContentParameters / activesync_backend's parents (BackendDiff,
// ISearchProvider) live in z-push-dev's own bundled vendor autoload, not
// EGroupware's composer classmap.
require_once EGW_SERVER_ROOT.'/vendor/egroupware/z-push-dev/src/vendor/autoload.php';
