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
 * JOIN. Building the condition as one of these instead keeps $cf_name, $operator and $value apart
 * and individually checked from the moment a caller has them, so cf_filter() only ever has to read
 * $cf_name back out and cast the rest to its already-safe SQL text via __toString().
 */
class CfLetterSearchFragment extends SqlFragment
{
	private const OPERATORS = ['LIKE', 'NOT LIKE', 'ILIKE', 'NOT ILIKE', '=', '!='];

	public function __construct(Db $db, public readonly string $cf_name, string $operator, string $value)
	{
		$operator = strtoupper($operator);
		if (!in_array($operator, self::OPERATORS, true))
		{
			throw new \InvalidArgumentException(self::class.": invalid lettersearch operator '$operator'");
		}
		parent::__construct($operator.' '.$db->quote($value));
	}
}
