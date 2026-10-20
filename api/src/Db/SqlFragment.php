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
 * A piece of raw, already-valid SQL, explicitly marked as such.
 *
 * Api\Db historically let a caller splice raw SQL into a $where/$cols/$data array by using an
 * integer array key - a convention indistinguishable, from inside Api\Db, from a caller
 * accidentally (or maliciously, if the array came from decoded user input) putting an integer key
 * into a column=>value array. This class makes "this is deliberately raw SQL" an explicit value
 * type instead of an incidental array-key shape, so Api\Db::requireSqlFragmentObjects() has
 * something reliable to check for.
 *
 * The methods here were moved out of Api\Db (which keeps thin stub methods delegating to these),
 * since building most of them requires the originating connection's driver/capabilities - they are
 * not pure functions of their arguments alone.
 *
 * Deliberately stays a SqlFragment instance (not cast back to a string) through every intermediate
 * step - a caller storing one directly in eg. a $filter[]/$criteria[] array, passed on to another
 * method, and eventually into Api\Db::select()/update()/... - and only turns into its SQL text at
 * the point Api\Db actually uses it. Casting it back to a string anywhere along the way would
 * reintroduce exactly the bug this class exists to prevent: an indistinguishable plain string at an
 * integer array key by the time it reaches column_data_implode(). To stay usable as a drop-in
 * replacement for a string in that intermediate code (eg. $field[0] offset access), it implements
 * ArrayAccess read-only over its own string content.
 */
final class SqlFragment implements \Stringable, \ArrayAccess
{
	private function __construct(private readonly string $sql)
	{
	}

	public function __toString() : string
	{
		return $this->sql;
	}

	public function offsetExists(mixed $offset) : bool
	{
		return isset($this->sql[$offset]);
	}

	public function offsetGet(mixed $offset) : string
	{
		return $this->sql[$offset];
	}

	public function offsetSet(mixed $offset, mixed $value) : void
	{
		throw new \LogicException(self::class.' is immutable, it can not be modified via array access');
	}

	public function offsetUnset(mixed $offset) : void
	{
		throw new \LogicException(self::class.' is immutable, it can not be modified via array access');
	}

	/**
	 * Wrap an already-built, trusted SQL string as a fragment
	 *
	 * Idempotent: given an already-wrapped SqlFragment, returns it unchanged instead of
	 * double-wrapping - lets a caller handling a value that's sometimes already converted and
	 * sometimes still a legacy raw string just call raw($val) unconditionally, no instanceof check
	 * needed.
	 */
	public static function raw(string|self $sql) : self
	{
		return $sql instanceof self ? $sql : new self($sql);
	}

	/**
	 * Build a WHERE-clause (or similar) expression from a mix of plain SQL, (array $args[$n]===false
	 * to skip the next array, "if ($cond) $then else $else" style) booleans, and column=>value arrays
	 *
	 * @param Db $db
	 * @param array|string $table_def table-name or table-definition array
	 * @param mixed ...$args see Api\Db::expression()'s original docs for the calling convention
	 */
	public static function expression(Db $db, array|string|null $table_def, mixed ...$args) : self
	{
		if (!is_array($table_def)) $table_def = $db->get_table_definitions(true, $table_def);

		$sql = '';
		$ignore_next = 0;
		foreach($args as $arg)
		{
			if ($ignore_next)
			{
				--$ignore_next;
				continue;
			}
			if (is_null($arg)) $arg = false;

			switch(gettype($arg))
			{
				case 'string':
					$sql .= $arg;
					break;
				case 'boolean':
					$ignore_next += !$arg ? 2 : 0;
					break;
				case 'array':
					$sql .= (string)self::columnDataImplode($db, ' AND ', $arg, true, false, $table_def['fd']);
					break;
				case 'object':
					if ($arg instanceof self) $sql .= (string)$arg;
					break;
			}
		}
		return new self($sql);
	}

	/**
	 * Implodes an array of column-value pairs for the use in sql-querys.
	 *
	 * All data is run through $db->quote() (does either addslashes() or (int)) - prevents SQL
	 * injection and SQL errors. See Api\Db::column_data_implode()'s original docs for the full
	 * calling convention (the $use_key/$only/$column_definitions semantics are unchanged).
	 *
	 * @throws Exception\InvalidSql for an unknown column name
	 * @throws Exception\InvalidArgument if $db->requireSqlFragmentObjects() is active and $array
	 *	contains an integer key whose value is not itself a SqlFragment instance
	 */
	public static function columnDataImplode(Db $db, string $glue, array $array, bool|string $use_key = true,
		array|bool $only = false, array|bool|null $column_definitions = false) : self
	{
		if (empty($column_definitions))
		{
			$column_definitions = $db->column_definitions ?? null;
		}
		if ($db->Debug) echo "<p>db::column_data_implode('$glue',".print_r($array,true).",'$use_key',".print_r($only,true).",<pre>".print_r($column_definitions,true)."</pre>\n";

		// do we need to truncate varchars to their max length (INSERT and UPDATE on Postgres)
		$truncate_varchar = $glue == ',' && $db->capabilities[Db::CAPABILITY_REQUIRE_TRUNCATE_VARCHAR];

		$keys = $values = array();
		foreach($array as $key => $data)
		{
			if (is_int($key) && $use_key !== 'VALUES' || !$only || $only === true && isset($column_definitions[$key]) ||
				is_array($only) && in_array($key, $only))
			{
				$keys[] = $db->name_quote($key);

				$col = $key;
				// fix "table.column" expressions, to not trigger exception, if column alone would work
				if (!is_int($key) && is_array($column_definitions) && !isset($column_definitions[$key]) &&
					(!preg_match('/^([a-z0-9_]+)\.([a-z0-9_]+)$/i', $key, $matches) || !isset($column_definitions[$matches[2]])))
				{
					throw new Exception\InvalidSql("db::column_data_implode('$glue',".print_r($array,true).",'$use_key',".print_r($only,true).",<pre>".print_r($column_definitions,true)."</pre><b>nothing known about column '$key'!</b>");
				}
				$column_type = is_array($column_definitions) ? ($column_definitions[$col]['type'] ?? false) : false;
				$not_null = is_array($column_definitions) && isset($column_definitions[$col]['nullable']) ? !$column_definitions[$col]['nullable'] : false;

				$maxlength = null;
				if ($truncate_varchar && !is_int($col) && isset($column_definitions[$col]) &&
					in_array($column_definitions[$col]['type'], ['varchar', 'ascii']))
				{
					$maxlength = $column_definitions[$col]['precision'];
				}
				// dont use IN ( ), if there's only one value, it's slower for MySQL
				if (is_array($data) && count($data) <= 1)
				{
					$data = array_shift($data);
				}
				// array for SET or VALUES, not WHERE --> automatic store comma-separated
				if (is_array($data) && $glue === ',' && in_array($column_type, ['varchar', 'ascii']))
				{
					$data = implode(',', $data);
				}
				if ($column_type === 'vector' && is_array($data))
				{
					$values[] = ($use_key === true ? $db->name_quote($key).'=' : '').$db->quote($data, $column_type, $not_null, $maxlength);
				}
				elseif (is_array($data))
				{
					$or_null = '';
					foreach($data as $k => $v)
					{
						if (!$not_null && $use_key === true && is_null($v))
						{
							$or_null = $db->name_quote($key).' IS NULL)';
							unset($data[$k]);
							continue;
						}
						$data[$k] = $db->quote($v, $column_type, $not_null, $maxlength);
					}
					$values[] = ($or_null ? '(' : '').(!count($data) ?
						// empty array on insert/update, store as NULL, or if not allowed whatever value NULL is casted to
						$db->quote(null, $column_type, $not_null) :
						($use_key === true ? $db->name_quote($key).' IN ' : '').
						'('.implode(',', $data).')'.($or_null ? ' OR ' : '')).$or_null;
				}
				elseif (is_int($key) && $use_key === true)
				{
					if ($db->requireSqlFragmentObjects() && !($data instanceof self))
					{
						throw new Exception\InvalidArgument("db::column_data_implode('$glue',...): integer array key $key used for a raw SQL fragment that is not an ".self::class." instance!");
					}
					if (empty($data)) continue;	// would give SQL error
					$values[] = (string)$data;
				}
				elseif ($glue != ',' && $use_key === true && !$not_null && is_null($data))
				{
					$values[] = $db->name_quote($key).' IS NULL';
				}
				else
				{
					$values[] = ($use_key === true ? $db->name_quote($key).'=' : '').$db->quote($data, $column_type, $not_null, $maxlength);
				}
			}
		}
		return new self(($use_key === 'VALUES' ? '('.implode(',', $keys).') VALUES (' : '').
			implode($glue, $values).($use_key === 'VALUES' ? ')' : ''));
	}

	/**
	 * Concat multiple expressions/strings into one
	 */
	public static function concat(Db $db, string ...$parts) : self|false
	{
		if (!$db->Link_ID && !$db->connect())
		{
			return false;
		}
		return new self($db->Link_ID->concat(...$parts));
	}

	/**
	 * Concat grouped values of an expression with optional order and separator
	 *
	 * @param string $expr column-name or expression optional prefixed with "DISTINCT"
	 * @param string $order_by optional order
	 * @param string $separator optional separator, default is comma
	 */
	public static function groupConcat(Db $db, string $expr, string $order_by = '', string $separator = ',') : self|false
	{
		switch($db->Type)
		{
			case 'mysqli':
			case 'mysql':
				$sql = 'GROUP_CONCAT('.$expr;
				if ($order_by) $sql .= ' ORDER BY '.$order_by;
				if ($separator != ',') $sql .= ' SEPARATOR '.$db->quote($separator);
				$sql .= ')';
				break;

			case 'pgsql':	// requires for Postgresql < 8.4 to have a custom ARRAY_AGG method installed!
				if ((float)$db->ServerInfo['version'] < 8.4)
				{
					return false;
				}
				$sql = 'ARRAY_TO_STRING(ARRAY_AGG('.$expr;
				if ($order_by) $sql .= ' ORDER BY '.$order_by;
				$sql .= '), '.$db->quote($separator).')';
				break;

			default:	// probably gives an sql error anyway
				return false;
		}
		return new self($sql);
	}

	/**
	 * Calls REGEXP_REPLACE if available for the DB otherwise returns just $expr
	 *
	 * Example: REGEXP_REPLACE('tel_work', '[^0-9]', "''") to remove non-numbers from tel_work column
	 *
	 * @param string $expr SQL expression, must be quoted for strings, eg. "'string'"
	 * @param string $regexp no quotes neccessary will be run through quote()
	 * @param string $with SQL expression, must be quoted for strings, eg. "'string'"
	 */
	public static function regexpReplace(Db $db, string $expr, string $regexp, string $with) : self
	{
		switch($db->Type)
		{
			case 'mysqli':
			case 'mysql':
				if ((float)$db->ServerInfo['version'] < 8.0) break;	// MySQL 8.0 or MariaDB 10.0 required
				// fall through
			case 'pgsql':
				return new self('REGEXP_REPLACE('.$expr.','.$db->quote($regexp).','.$with.')');
		}
		return new self($expr);
	}

	/**
	 * SQL returning character (not byte!) positions for $substr in $str
	 */
	public static function strpos(Db $db, string $str, string $substr) : self
	{
		switch($db->Type)
		{
			case 'mysql':
			case 'mysqli':
				return new self("LOCATE($substr,$str)");
			case 'pgsql':
				return new self("STRPOS($str,$substr)");
			case 'mssql':
				return new self("CHARINDEX($substr,$str)");
		}
		die(__METHOD__." not implemented for DB type '$db->Type'!");
	}

	/**
	 * Convert a DB specific timestamp in a unix timestamp stored as integer, like MySQL: UNIX_TIMESTAMP(ts)
	 *
	 * @param string $expr name of an integer column or integer expression
	 */
	public static function unixTimestamp(Db $db, string $expr) : ?self
	{
		switch($db->Type)
		{
			case 'mysql':
			case 'mysqli':
				return new self("UNIX_TIMESTAMP($expr)");

			case 'pgsql':
				return new self("EXTRACT(EPOCH FROM CAST($expr AS TIMESTAMP))");

			case 'mssql':
				return new self("DATEDIFF(second,'1970-01-01',($expr))");
		}
		return null;
	}

	/**
	 * Convert a unix timestamp stored as integer in the db into a db timestamp, like MySQL: FROM_UNIXTIME(ts)
	 *
	 * @param string $expr name of an integer column or integer expression
	 */
	public static function fromUnixtime(Db $db, string $expr) : self|false
	{
		switch($db->Type)
		{
			case 'mysql':
			case 'mysqli':
				return new self("FROM_UNIXTIME($expr)");

			case 'pgsql':
				return new self("(TIMESTAMP WITH TIME ZONE 'epoch' + ($expr) * INTERVAL '1 sec')");

			case 'mssql':	// we use date(,0) as we store server-time
				return new self("DATEADD(second,($expr),'".date('Y-m-d H:i:s', 0)."')");
		}
		return false;
	}

	/**
	 * format a timestamp as string, like MySQL: DATE_FORMAT(ts)
	 *
	 * Please note: only a subset of the MySQL formats are implemented
	 *
	 * @param string $expr name of a timestamp column or timestamp expression
	 * @param string $format format specifier like '%Y-%m-%d %H:%i:%s' or '%V%X' ('%v%x') weeknumber & year with Sunday (Monday) as first day
	 */
	public static function dateFormat(Db $db, string $expr, string $format) : self|false
	{
		switch($db->Type)
		{
			case 'mysql':
			case 'mysqli':
				return new self("DATE_FORMAT($expr,'$format')");

			case 'pgsql':
				$format = str_replace(
					array('%Y',  '%y','%m','%d','%H',  '%h','%i','%s','%V','%v','%X',  '%x'),
					array('YYYY','YY','MM','DD','HH24','HH','MI','SS','IW','IW','YYYY','YYYY'),
					$format);
				return new self("TO_CHAR($expr,'$format')");

			case 'mssql':
				$from = $to = array();
				foreach(array('%Y'=>'yyyy','%y'=>'yy','%m'=>'mm','%d'=>'dd','%H'=>'hh','%i'=>'mi','%s'=>'ss','%V'=>'wk','%v'=>'wk','%X'=>'yyyy','%x'=>'yyyy') as $f => $t)
				{
					$from[] = $f;
					$to[] = "'+DATEPART($t,($expr))+'";
				}
				$from[] = "''+"; $to[] = '';
				$from[] = "+''"; $to[] = '';
				return new self(str_replace($from, $to, $format));
		}
		return false;
	}

	/**
	 * Cast a column or sql expression to double, necessary at least for postgreSQL or MySQL for sorting
	 */
	public static function toDouble(Db $db, string $expr) : self
	{
		switch($db->Type)
		{
			case 'pgsql':
				return new self($expr.'::double');
			case 'mysql':
			case 'mysqli':
				return new self('CAST('.$expr.' AS DECIMAL(24,3))');
		}
		return new self($expr);
	}

	/**
	 * Cast a column or sql expression to integer, necessary at least for postgreSQL
	 */
	public static function toInt(Db $db, string $expr) : self
	{
		switch($db->Type)
		{
			case 'pgsql':
				return new self($expr.'::integer');
			case 'mysql':
			case 'mysqli':
				return new self('CAST('.$expr.' AS SIGNED)');
		}
		return new self($expr);
	}

	/**
	 * Cast a column or sql expression to varchar, necessary at least for postgreSQL
	 */
	public static function toVarchar(Db $db, string $expr) : self
	{
		switch($db->Type)
		{
			case 'pgsql':
				return new self('CAST('.$expr.' AS varchar)');
		}
		return new self($expr);
	}
}
