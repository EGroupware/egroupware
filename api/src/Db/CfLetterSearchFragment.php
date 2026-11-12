<?php
/**
 * EGroupware API - Db SQL fragments
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage db
 */

namespace EGroupware\Api\Db;

use EGroupware\Api\Db;

/**
 * A custom-field lettersearch condition ("#cf_name LIKE 'x%'"), carried as its already-validated,
 * already-quoted parts instead of a single pre-assembled SQL string.
 *
 * Storage::cf_filter()'s lettersearch branch used to receive that condition as one raw string at an
 * integer $filter key and had to re-parse it apart again - re-discovering where the custom-field
 * name ended and re-validating the shape of whatever followed it - before it dared splice it into a
 * JOIN. Building the condition as one of these instead keeps $cf_name and $value apart and
 * individually checked from the moment a caller has them, so cf_filter() only ever has to read
 * $cf_name back out and cast the rest to its already-safe SQL text via __toString(). The operator is
 * never taken from a caller - it's always this connection's own case-insensitive-LIKE capability
 * (eg. plain 'LIKE' vs Postgres' '::text ILIKE'), so there's no operator text left to validate or for
 * a caller to smuggle anything through.
 */
class CfLetterSearchFragment extends SqlFragment
{
	public function __construct(Db $db, public readonly string $cf_name, string $value)
	{
		parent::__construct($db->capabilities[Db::CAPABILITY_CASE_INSENSITIV_LIKE].' '.$db->quote($value));
	}
}
