<?php
/**
 * EGroupware - Setup - db-schema-processor
 *
 * Originaly written by
 *  - Michael Dean <mdean@users.sourceforge.net>
 *  - Miles Lott<milosch@groupwhere.org>
 * Rewritten and adapted to ADOdb's schema processor by Ralf Becker.
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage db
 * @version $Id$
 */

namespace EGroupware\Api\Db;

use EGroupware\Api;

/**
 * eGW's ADOdb based schema-processor
 */
class Schema
{
	/**
	 * @deprecated formerly used translator class, now a reference to ourself
	 */
	var $m_oTranslator;
	/**
	 * db-object
	 *
	 * @var EGroupware\Api\Db\Deprecated
	 */
	var $m_odb;
	/**
	 * reference to the global ADOdb object
	 *
	 * @var ADOConnection
	 */
	var $adodb;
	/**
	 * adodb's datadictionary object for the used db-type
	 *
	 * @var ADODB_DataDict
	 */
	var $dict;
	/**
	 * Debuglevel: 0=Off, 1=some, eg. primary function calls, 2=lots incl. the SQL used
	 *
	 * @var int
	 */
	var $debug = 0;
	/**
	 * Array with db => max. length of indexes pairs (if there is a considerable low limit for a db)
	 *
	 * @var array
	 */
	var $max_index_length=array(
		'maxdb'  => 32,
		'oracle' => 30,
	);
	/**
	 * type of the database, set by the the constructor: 'mysql','pgsql','mssql','maxdb'
	 *
	 * @var string
	 */
	var $sType;
	/**
	 *	maximum length of a varchar column, everything above get converted to text
	 *
	 *	@var int
	 */
	var $max_varchar_length = 255;
	/**
	 * system-charset if set
	 *
	 * @var string
	 */
	var $system_charset = 'utf8';
	/**
	 * reference to the capabilities array of the db-class
	 *
	 * @var array
	 */
	var $capabilities;
	/**
	 * preserve value of old sequences in PostgreSQL
	 *
	 * @var int
	 */
	var $pgsql_old_seq;

	/**
	 * Constructor of schema-processor
	 *
	 * @param string $dbms type of the database: 'mysql','pgsql','mssql','maxdb'
	 * @param Db $db =null database class, if null we use $GLOBALS['egw']->db
	 * @return schema_proc
	 */
	function __construct($dbms=False, Api\Db $db=null)
	{
	    if(is_object($db))
		{
			$this->m_odb = $db;
	    }
	    else
	    {
			$this->m_odb = isset($GLOBALS['egw']->db) && is_object($GLOBALS['egw']->db) ? $GLOBALS['egw']->db : $GLOBALS['egw_setup']->db;
	    }
	    if (!($this->m_odb instanceof Api\Db))
	    {
	    	throw new Api\Exception\AssertionFailed('no EGroupware\Api\Db object!');
	    }
	    $this->m_odb->connect();
		$this->capabilities =& $this->m_odb->capabilities;

		$this->sType = $dbms ? $dbms : $this->m_odb->Type;
		$this->adodb = &$this->m_odb->Link_ID;
		$this->dict = NewDataDictionary($this->adodb);

		// enable the debuging in ADOdb's datadictionary if the debug-level is greater then 1
		if ($this->debug > 1) $this->dict->debug = True;

		// to allow some of the former translator-functions to be called, we assign ourself as the translator
		$this->m_oTranslator = &$this;

		switch($this->sType)
		{
			case 'maxdb':
				$this->max_varchar_length = 8000;
				break;
			case 'mysql':
				// since MySQL 5.0 65535, but with utf8 and row-size-limit of 64k:
				// it's effective 65535/3 - size of other columns, so we use 20000 (mysql silently convert to text anyway)
				if ((float)$this->m_odb->ServerInfo['version'] >= 5.0)
				{
					$this->max_varchar_length = 20000;
				}
				break;
		}
		if (is_object($GLOBALS['egw_setup']))
		{
			$this->system_charset =& $GLOBALS['egw_setup']->system_charset;
		}
		elseif (isset($GLOBALS['egw_info']['server']['system_charset']))
		{
			$this->system_charset = $GLOBALS['egw_info']['server']['system_charset'];
		}
	}

	/**
	 * Check if the given $columns exist as index in the index array $indexes
	 *
	 * @param string|array $columns column-name as string or array of column-names plus optional options key
	 * @param array $indexs array of indexes (column-name as string or array of column-names plus optional options key)
	 * @param boolean $ignore_length_limit =false should we take lenght-limits of indexes into account or not
	 * @return boolean true if index over $columns exist in the $indexes array
	 */
	function _in_index($columns, $indexs, $ignore_length_limit=false)
	{
		if (is_array($columns))
		{
			unset($columns['options']);
			$columns = implode('-',$columns);
			if ($ignore_length_limit) $columns = preg_replace('/\(\d+\)/', '', $columns);
		}
		foreach($indexs as $index)
		{
			if (is_array($index))
			{
				unset($index['options']);
				$index = implode('-',$index);
			}
			if ($ignore_length_limit) $index = preg_replace('/\(\d+\)/', '', $index);
			if ($columns == $index) return true;
		}
		return false;
	}

	/**
	 * Created a table named $sTableName as defined in $aTableDef
	 *
	 * @param string $sTableName
	 * @param array $aTableDef
	 * @param bool $preserveSequence
	 * @return int 2: no error, 1: errors, but continued, 0: errors aborted
	 */
	function CreateTable($sTableName, $aTableDef, $preserveSequence=False)
	{
		if ($this->debug)
		{
			$this->debug_message('schema_proc::CreateTable(%1,%2)',False,$sTableName, $aTableDef);
		}
		// for mysql 4.0+ we set the charset for the table
		if ($this->system_charset && substr($this->sType,0,5) == 'mysql' &&
			(float) $this->m_odb->ServerInfo['version'] >= 4.0)
		{
			$set_table_charset = array($this->sType => 'CHARACTER SET utf8');
		}
		// creating the table
		$aSql = $this->dict->CreateTableSQL($sTableName,$ado_cols = $this->_egw2adodb_columndef($aTableDef),$set_table_charset);
		if (!($retVal = $this->ExecuteSQLArray($aSql,2,'CreateTableSQL(%1,%2) sql=%3',False,$sTableName,$ado_cols,$aSql)))
		{
			return $retVal;
		}
		// creating unique indices/constrains
		foreach ($aTableDef['uc'] as $name => $mFields)
		{
			if (empty($mFields))
			{
				continue;	// cant create an index without fields (was observed in broken backups)
			}
			if ($this->_in_index($mFields,array($aTableDef['pk'])))
			{
				continue;	// is already created as primary key
			}
			if (!($retVal = $this->CreateIndex($sTableName,$mFields,true,'',$name)))
			{
				return $retVal;
			}
		}
		// creation indices
		foreach ($aTableDef['ix'] as $name => $mFields)
		{
			if (empty($mFields))
			{
				continue;	// cant create an index without fields (was observed in broken backups)
			}
			if ($this->_in_index($mFields,array($aTableDef['pk'])) ||
				$this->_in_index($mFields,$aTableDef['uc']))
			{
				continue;	// is already created as primary key or unique index
			}
			$options = False;
			if (is_array($mFields))
			{
				if (isset($mFields['options']))		// array sets additional options
				{
					if (isset($mFields['options'][$this->sType]))
					{
						$options = $mFields['options'][$this->sType];	// db-specific options, eg. index-type
						if (!$options) continue;	// no index for our db-type
					}
					unset($mFields['options']);
				}
			}
			foreach((array)$mFields as $k => $col)
			{
				// only create indexes on text-columns, if (db-)specifiy options are given or FULLTEXT for mysql
				// most DB's cant do them and give errors
				if (in_array($aTableDef['fd'][$col]['type'],array('text','longtext')))
				{
					if (is_array($mFields))	// index over multiple columns including a text column
					{
						$mFields[$k] .= '(32)';	// 32=limit of egw_addressbook_extra.extra_value to fix old backups
					}
					elseif (!$options)	// index over a single text column and no options given
					{
						if ($this->sType == 'mysql')
						{
							$options = 'FULLTEXT';
						}
						else
						{
							continue 2;	// ignore that index, 2=not just column but whole index!
						}
					}
				}
			}
			if (!($retVal = $this->CreateIndex($sTableName,$mFields,false,$options,$name)))
			{
				return $retVal;
			}
		}
		// preserve last value of an old sequence
		if ($this->sType == 'pgsql' && $preserveSequence && $this->pgsql_old_seq)
		{
			if (($seq = $this->_PostgresHasOldSequence($sTableName)))
			{
				$this->pgsql_old_seq = $this->pgsql_old_seq + 1;
				$this->m_odb->query("ALTER SEQUENCE $seq RESTART WITH " . $this->pgsql_old_seq,__LINE__,__FILE__);
			}
			$this->pgsql_old_seq = 0;
		}
		return $retVal;
	}

	/**
	 * Drops all tables in $aTables
	 *
	 * @param array $aTables array of eGW table-definitions
	 * @param boolean $bOutputHTML should we give diagnostics, default False
	 * @return boolean True if no error, else False
	 */
	function DropAllTables($aTables, $bOutputHTML=False)
	{
		if(!is_array($aTables) || !isset($this->m_odb))
		{
			return False;
		}
		// set our debug-mode or $bOutputHTML is the other one is set
		if ($this->debug) $bOutputHTML = True;
		if ($bOutputHTML && !$this->debug) $this->debug = 2;

		foreach(array_keys($aTables) as $sTableName)
		{
			if($this->DropTable($sTableName))
			{
				if($bOutputHTML)
				{
					echo '<br>Drop Table <b>' . $sTableName . '</b>';
				}
			}
			else
			{
				return False;
			}
		}
		return True;
	}

	/**
	 * Drops the table $sTableName
	 *
	 * @param string $sTableName
	 * @return int 2: no error, 1: errors, but continued, 0: errors aborted
	 */
	function DropTable($sTableName)
	{
		if ($this->sType == 'pgsql') $this->_PostgresTestDropOldSequence($sTableName);

		$aSql = $this->dict->DropTableSql($sTableName);

		return $this->ExecuteSQLArray($aSql,2,'DropTable(%1) sql=%2',False,$sTableName,$aSql);
	}

	/**
	 * Drops column $sColumnName from table $sTableName
	 *
	 * @param string $sTableName table-name
	 * @param array $aTableDef eGW table-defintion
	 * @param string $sColumnName column-name
	 * @return int 2: no error, 1: errors, but continued, 0: errors aborted
	 */
	function DropColumn($sTableName, $aTableDef, $sColumnName)
	{
		unset($aTableDef);	// not used, but required by function signature

		if (!($table_def = $this->GetTableDefinition($sTableName))) return 0;
		unset($table_def['fd'][$sColumnName]);

		$aSql = $this->dict->DropColumnSql($sTableName,$sColumnName,$ado_table=$this->_egw2adodb_columndef($table_def));

		return $this->ExecuteSQLArray($aSql,2,'DropColumnSQL(%1,%2,%3) sql=%4',False,$sTableName,$sColumnName,$ado_table,$aSql);
	}

	/**
	 * Renames table $sOldTableName to $sNewTableName
	 *
	 * @param string $sOldTableName old (existing) table-name
	 * @param string $sNewTableName new table-name
	 * @return int 2: no error, 1: errors, but continued, 0: errors aborted
	 */
	function RenameTable($sOldTableName, $sNewTableName)
	{
		// if we have an old postgres sequence or index (the ones not linked to the table),
		// we create a new table, copy the content and drop the old one
		if ($this->sType == 'pgsql')
		{
			if (!($table_def = $this->GetTableDefinition($sOldTableName))) return 0;

			// only use old PostgreSQL stuff, if we have an old sequence, otherwise rely on it being new enough
			if ($this->_PostgresHasOldSequence($sOldTableName,True) &&
				(count($table_def['pk']) || count($table_def['ix']) || count($table_def['uc'])))
			{
				if ($this->adodb->BeginTrans() &&
					$this->CreateTable($sNewTableName,$table_def,True) &&
					$this->m_odb->query("INSERT INTO $sNewTableName SELECT * FROM $sOldTableName",__LINE__,__FILE__) &&
					// sequence must be updated, after inserts containing pkey, otherwise new inserst will fail!
					(count($table_def['pk']) !== 1 || $this->UpdateSequence($sNewTableName, $table_def['pk'][0])) &&
					$this->DropTable($sOldTableName))
				{
					$this->adodb->CommitTrans();
					return 2;
				}
				$this->adodb->RollbackTrans();
				return 0;
			}
		}
		$aSql = $this->dict->RenameTableSQL($sOldTableName, $sNewTableName);

		return $this->ExecuteSQLArray($aSql,2,'RenameTableSQL(%1,%2) sql=%3',False,$sOldTableName,$sNewTableName,$aSql);
	}

	/**
	 * Check if we have an old, not automaticaly droped sequence
	 *
	 * @param string $sTableName
	 * @param bool $preserveValue
	 * @return boolean|string sequence-name or false
	 */
	function _PostgresHasOldSequence($sTableName,$preserveValue=False)
	{
		if ($this->sType != 'pgsql') return false;

		$seq = $this->adodb->GetOne("SELECT d.adsrc FROM pg_attribute a, pg_class c, pg_attrdef d WHERE c.relname='$sTableName' AND c.oid=d.adrelid AND d.adsrc LIKE '%seq_$sTableName''::text)' AND a.attrelid=c.oid AND d.adnum=a.attnum");
		$seq2 = $this->adodb->GetOne("SELECT d.adsrc FROM pg_attribute a, pg_class c, pg_attrdef d WHERE c.relname='$sTableName' AND c.oid=d.adrelid AND d.adsrc LIKE '%$sTableName%_seq''::text)' AND a.attrelid=c.oid AND d.adnum=a.attnum");

		$matches = null;
		if ($seq && preg_match('/^nextval\(\'(.*)\'/',$seq,$matches))
		{
			if ($preserveValue) $this->pgsql_old_seq = $this->adodb->GetOne("SELECT last_value FROM " . $matches[1]);
			return $matches[1];
		}
		if ($seq2 && preg_match('/^nextval\(\'public\.(.*)\'/',$seq2,$matches))
		{
			if ($preserveValue) $this->pgsql_old_seq = $this->adodb->GetOne("SELECT last_value FROM " . $matches[1]);
			return $matches[1];
		}
		return false;
	}

	/**
	 * Check if we have an old, not automaticaly droped sequence and drop it
	 *
	 * @param $sTableName
	 */
	function _PostgresTestDropOldSequence($sTableName)
	{
		$this->pgsql_old_seq = 0;
		if ($this->sType == 'pgsql' && ($seq = $this->_PostgresHasOldSequence($sTableName)))
		{
			// only drop sequence, if there is no dependency on it
			if (!$this->adodb->GetOne("SELECT relname FROM pg_class JOIN pg_depend ON pg_class.relfilenode=pg_depend.objid WHERE relname='$seq' AND relkind='S' AND deptype='i'"))
			{
				$this->query('DROP SEQUENCE '.$seq,__LINE__,__FILE__);
			}
		}
	}

	/**
	 * Changes one (exiting) column in a table
	 *
	 * @param string $sTableName table-name
	 * @param string $sColumnName column-name
	 * @param array $aColumnDef new column-definition
	 * @return int 2: no error, 1: errors, but continued, 0: errors aborted
	 */
	function AlterColumn($sTableName, $sColumnName, $aColumnDef)
	{
		if (!($table_def = $this->GetTableDefinition($sTableName))) return 0;

		// PostgreSQL: varchar or ascii column shortened, use substring to avoid error if current content is to long
		if($this->sType == 'pgsql' && in_array($table_def['fd'][$sColumnName]['type'], array('varchar', 'ascii')) &&
			in_array($aColumnDef['type'], array('varchar', 'ascii')) &&
			$table_def['fd'][$sColumnName]['precision'] > $aColumnDef['precision'])
		{
			$this->m_odb->update($sTableName, array(
				"$sColumnName=SUBSTRING($sColumnName FROM 1 FOR ".(int)$aColumnDef['precision'].')',
			), "LENGTH($sColumnName) > ".(int)$aColumnDef['precision'], __LINE__, __FILE__);

			if (($shortend = $this->m_odb->affected_rows()))
			{
				error_log(__METHOD__."('$sTableName', '$sColumnName', ".array2string($aColumnDef).") $shortend values shortened");
			}
		}
		$table_def['fd'][$sColumnName] = $aColumnDef;

		$aSql = $this->dict->AlterColumnSQL($sTableName,$ado_col = $this->_egw2adodb_columndef(array(
				'fd' => array($sColumnName => $aColumnDef),
				'pk' => array(),
			)),$ado_table=$this->_egw2adodb_columndef($table_def));

		return $this->ExecuteSQLArray($aSql,2,'AlterColumnSQL(%1,%2,%3) sql=%4',False,$sTableName,$ado_col,$ado_table,$aSql);
	}

	/**
	 * Renames column $sOldColumnName to $sNewColumnName in table $sTableName
	 *
	 * @param string $sTableName table-name
	 * @param string $sOldColumnName old (existing) column-name
	 * @param string $sNewColumnName new column-name
	 * @return int 2: no error, 1: errors, but continued, 0: errors aborted
	 */
	function RenameColumn($sTableName, $sOldColumnName, $sNewColumnName)
	{
		$table_def = $this->GetTableDefinition($sTableName);
		$old_def = array();

		if (isset($table_def['fd'][$sOldColumnName]))
		{
			$old_def = $table_def['fd'][$sOldColumnName];
		}
		else
		{
			foreach($table_def['fd'] as $col => $def)
			{
				if (strtolower($col) == strtolower($sOldColumnName))
				{
					$old_def = $def;
					break;
				}
			}
		}
		$col_def = $this->_egw2adodb_columndef(array(
				'fd' => array($sNewColumnName => $old_def),
				'pk' => array(),
			));

		$aSql = $this->dict->RenameColumnSQL($sTableName,$sOldColumnName,$sNewColumnName,$col_def);

		return $this->ExecuteSQLArray($aSql,2,'RenameColumnSQL(%1,%2,%3) sql=%4',False,$sTableName,$sOldColumnName, $sNewColumnName,$aSql);
	}

	/**
	 * Add one (new) column to a table
	 *
	 * @param string $sTableName table-name
	 * @param string $sColumnName column-name
	 * @param array $aColumnDef column-definition
	 * @return int 2: no error, 1: errors, but continued, 0: errors aborted
	 */
	function AddColumn($sTableName, $sColumnName, $aColumnDef)
	{
		$aSql = $this->dict->AddColumnSQL($sTableName,$ado_cols = $this->_egw2adodb_columndef(array(
				'fd' => array($sColumnName => $aColumnDef),
				'pk' => array(),
			)));

		return $this->ExecuteSQLArray($aSql,2,'AlterColumnSQL(%1,%2,%3) sql=%4',False,$sTableName,$sColumnName, $aColumnDef,$aSql);
	}

	/**
	 * Create an (unique) Index over one or more columns
	 *
	 * @param string $sTableName table-name
	 * @param array $aColumnNames columns for the index
	 * @param boolean $bUnique =false true for a unique index, default false
	 * @param array|string $options ='' db-sepecific options, default '' = none
	 * @param string $sIdxName ='' name of the index, if not given (default) its created automaticaly
	 * @return int 2: no error, 1: errors, but continued, 0: errors aborted
	 */
	function CreateIndex($sTableName,$aColumnNames,$bUnique=false,$options='',$sIdxName='')
	{
		// remove length limits from column names, if DB type is NOT MySQL
		if ($this->sType != 'mysql')
		{
			$aColumnNames = preg_replace('/ *\(\d+\)$/','',$aColumnNames);
		}
		if (!$sIdxName || is_numeric($sIdxName))
		{
			$sIdxName = $this->_index_name($sTableName,$aColumnNames);
		}
		if (!is_array($options)) $options = $options ? array($options) : array();
		if ($bUnique) $options[] = 'UNIQUE';

		$aSql = $this->dict->CreateIndexSQL($sIdxName,$sTableName,$aColumnNames,$options);

		return $this->ExecuteSQLArray($aSql,2,'CreateIndexSQL(%1,%2,%3,%4) sql=%5',False,$sTableName,$aColumnNames,$options,$sIdxName,$aSql);
	}

	/**
	 * Drop an Index
	 *
	 * @param string $sTableName table-name
	 * @param array|string $aColumnNames columns of the index or the name of the index
	 * @return int 2: no error, 1: errors, but continued, 0: errors aborted
	 */
	function DropIndex($sTableName,$aColumnNames)
	{
		if (is_array($aColumnNames))
		{
			$indexes = $this->dict->MetaIndexes($sTableName);

			if ($indexes === False)
			{
				// if MetaIndexes is not availible for the DB, we try the name the index was created with
				// this fails if one of the columns have been renamed
				$sIdxName = $this->_index_name($sTableName,$aColumnNames);
			}
			else
			{
				foreach($indexes as $idx => $idx_data)
				{
					if (strtolower(implode(':',$idx_data['columns'])) == implode(':',$aColumnNames))
					{
						$sIdxName = $idx;
						break;
					}
				}
			}
		}
		else
		{
			$sIdxName = $aColumnNames;
		}
		if(!$sIdxName)
		{
			return True;
		}
		$aSql = $this->dict->DropIndexSQL($sIdxName,$sTableName);

		return $this->ExecuteSQLArray($aSql,2,'DropIndexSQL(%1(%2),%3) sql=%4',False,$sIdxName,$aColumnNames,$sTableName,$aSql);
	}

	/**
	 * Updating the sequence-value, after eg. copying data via RefreshTable
	 * @param string $sTableName table-name
	 * @param string $sColumnName column-name, which default is set to nextval()
	 */
	function UpdateSequence($sTableName,$sColumnName)
	{
		switch($this->sType)
		{
			case 'pgsql':
				// identify the sequence name, ADOdb uses a different name or it might be renamed
				$columns = $this->dict->MetaColumns($sTableName);
				$seq_name = 'seq_'.$sTableName;
				$matches = null;
				if (preg_match("/nextval\('([^']+)'::(text|regclass)\)/",$columns[strtoupper($sColumnName)]->default_value,$matches))
				{
					$seq_name = $matches[1];
				}
				$sql = "SELECT setval('$seq_name',MAX($sColumnName)) FROM $sTableName";
				if($this->debug) { echo "<br>Updating sequence '$seq_name using: $sql"; }
				return $this->query($sql,__LINE__,__FILE__);
		}
		return True;
	}

	/**
	 * This function manually re-created the table incl. primary key and all other indices
	 *
	 * It is meant to use if the primary key, existing indices or column-order changes or
	 * columns are not longer used or new columns need to be created (with there default value or NULL)
	 * Beside the default-value in the schema, one can give extra defaults via $aDefaults to eg. use an
	 * other colum or function to set the value of a new or changed column
	 *
	 * @param string $sTableName table-name
	 * @param array $aTableDef eGW table-defintion
	 * @param array|boolean $aDefaults array with default for the colums during copying, values are either (old) column-names or quoted string-literals
	 */
	function RefreshTable($sTableName, $aTableDef, $aDefaults=False)
	{
		if($this->debug) { echo "<p>schema_proc::RefreshTable('$sTableName',"._debug_array($aTableDef,False).")\n"; }

		$old_table_def = $this->GetTableDefinition($sTableName);

		$tmp_name = 'tmp_'.$sTableName;
		$this->m_odb->transaction_begin();

		$select = array();
		$blob_column_included = $auto_column_included = False;
		foreach($aTableDef['fd'] as $name => $data)
		{
			// new auto column with no default or explicit NULL as default (can be an existing column too!)
			if ($data['type'] == 'auto' &&
				(!isset($old_table_def['fd'][$name]) && (!$aDefaults || !isset($aDefaults[$name])) ||
				$aDefaults && strtoupper($aDefaults[$name]) == 'NULL'))
			{
				$sequence_name = $sTableName.'_'.$name.'_seq';
				switch($GLOBALS['egw_setup']->db->Type)
				{
					case 'mysql':
						$value = 'NULL'; break;
					case 'pgsql':
						$value = "nextval('$sequence_name'::regclass)"; break;
					default:
						$value = "nextval('$sequence_name')"; break;
				}
			}
			elseif ($aDefaults && isset($aDefaults[$name]))	// use given default
			{
				$value = $aDefaults[$name];
			}
			elseif (isset($old_table_def['fd'][$name]))	// existing column, use its value => column-name in query
			{
				$value = $name;

				if ($this->sType == 'pgsql')			// some postgres specific code
				{
					// this is eg. necessary to change a varchar into an int column under postgres
					if (in_array($old_table_def['fd'][$name]['type'],array('char','varchar','text','blob')) &&
						in_array($data['type'],array('int','decimal')))
					{
						$value = "to_number($name,'S9999999999999D99')";
					}
					// blobs cant be casted to text
					elseif($old_table_def['fd'][$name]['type'] == 'blob' && $data['type'] == 'text')
					{
						$value = "ENCODE($value,'escape')";
					}
					// varchar or ascii column shortened, use substring to avoid error if current content is to long
					elseif(in_array($old_table_def['fd'][$name]['type'], array('varchar', 'ascii')) &&
						in_array($data['type'], array('varchar', 'ascii')) &&
						$old_table_def['fd'][$name]['precision'] > $data['precision'])
					{
						$value = "SUBSTRING($value FROM 1 FOR ".(int)$data['precision'].')';
					}
					// cast everything which is a different type
					elseif($old_table_def['fd'][$name]['type'] != $data['type'] && ($type_translated = $this->TranslateType($data['type'])))
					{
						$value = "CAST($value AS $type_translated)";
					}
				}
			}
			else	// new column => use default value or NULL
			{
				if (!isset($data['default']) && (!isset($data['nullable']) || $data['nullable']))
				{
					$value = 'NULL';
				}
				// some stuff is NOT to be quoted
				elseif (in_array(strtoupper($data['default']),array('CURRENT_TIMESTAMP','CURRENT_DATE','NULL','NOW()')))
				{
					$value = $data['default'];
				}
				else
				{
					$value = $this->m_odb->quote(isset($data['default']) ? $data['default'] : '',$data['type']);
				}
				if ($this->sType == 'pgsql')
				{
					// fix for postgres error "no '<' operator for type 'unknown'"
					if(($type_translated = $this->TranslateType($data['type'])))
					{
						$value = "CAST($value AS $type_translated)";
					}
				}
			}
			$blob_column_included = $blob_column_included || in_array($data['type'],array('blob','text','longtext'));
			$auto_column_included = $auto_column_included || $data['type'] == 'auto';
			$select[] = $value;
		}

		$extra = '';
		$distinct = 'DISTINCT';
		switch($this->sType)
		{
			case 'mssql':
				if ($auto_column_included) $extra = "SET IDENTITY_INSERT $sTableName ON\n";
				if ($blob_column_included) $distinct = '';	// no distinct on blob-columns
				break;
		}
		// because of all the trouble with sequences and indexes in the global namespace,
		// we use an additional temp. table for postgres and not rename the existing one, but drop it.
		if ($this->sType == 'pgsql')
		{
			$Ok = $this->m_odb->query("SELEcT * INTO TEMPORARY TABLE $tmp_name FROM $sTableName",__LINE__,__FILE__) &&
				$this->DropTable($sTableName);
		}
		else
		{
			// drop evtl. existing temp. table eg. from a previous failed upgrade
			if (($tables = $this->m_odb->table_names(true)) && in_array($tmp_name, $tables))
			{
				$this->DropTable($tmp_name);
			}
			$Ok = $this->RenameTable($sTableName,$tmp_name);
		}
		$Ok = $Ok && $this->CreateTable($sTableName,$aTableDef) &&
			$this->m_odb->query($sql_copy_data="$extra INSERT INTO $sTableName (".
				implode(',',array_keys($aTableDef['fd'])).
				") SELEcT $distinct ".implode(',',$select)." FROM $tmp_name",__LINE__,__FILE__) &&
			$this->DropTable($tmp_name);
		//error_log($sql_copy_data);

		if (!$Ok)
		{
			$this->m_odb->transaction_abort();
			return False;
		}
		// do we need to update the new sequences value ?
		if (count($aTableDef['pk']) == 1 && $aTableDef['fd'][$aTableDef['pk'][0]]['type'] == 'auto')
		{
			$this->UpdateSequence($sTableName,$aTableDef['pk'][0]);
		}
		$this->m_odb->transaction_commit();

		return True;
	}

	/**
	 * depricated Function does nothing any more
	 * @depricated
	 */
	function GenerateScripts()
	{
		return True;
	}

	/**
	 * Creates all tables for one application
	 *
	 * @param array $aTables array of eGW table-definitions
	 * @param boolean $bOutputHTML =false should we give diagnostics, default False
	 * @return boolean True on success, False if an (fatal) error occured
	 */
	function ExecuteScripts($aTables, $bOutputHTML=False)
	{
		if(!is_array($aTables) || !IsSet($this->m_odb))
		{
			return False;
		}
		// set our debug-mode or $bOutputHTML is the other one is set
		if ($this->debug) $bOutputHTML = True;
		if ($bOutputHTML && !$this->debug) $this->debug = 2;

		foreach($aTables as $sTableName => $aTableDef)
		{
			if($this->CreateTable($sTableName, $aTableDef))
			{
				if($bOutputHTML)
				{
					echo '<br>Create Table <b>' . $sTableName . '</b>';
				}
			}
			else
			{
				if($bOutputHTML)
				{
					echo '<br>Create Table Failed For <b>' . $sTableName . '</b>';
				}

				return False;
			}
		}
		return True;
	}

	/**
	* Return the value of a column
	*
	* @param string|integer $value name of field or positional index starting from 0
	* @param bool $strip_slashes string escape chars from field(optional), default false
	* @deprecated use result-set returned by query/select
	* @return string the field value
	*/
	function f($value,$strip_slashes=False)
	{
		if (!($this->m_odb instanceof Deprecated))
		{
	    	throw new Api\Exception\AssertionFailed(__METHOD__.' requires an EGroupware\Api\Db\Deprecated object!');
		}
		return $this->m_odb->f($value,$strip_slashes);
	}

	/**
	* Number of rows in current result set
	*
	* @deprecated use result-set returned by query/select
	* @return int number of rows
	*/
	function num_rows()
	{
		if (!($this->m_odb instanceof Deprecated))
		{
	    	throw new Api\Exception\AssertionFailed(__METHOD__.' requires an EGroupware\Api\Db\Deprecated object!');
		}
		return $this->m_odb->num_rows();
	}

	/**
	* Move to the next row in the results set
	*
	* @deprecated use result-set returned by query/select
	* @return bool was another row found?
	*/
	function next_record()
	{
		if (!($this->m_odb instanceof Deprecated))
		{
	    	throw new Api\Exception\AssertionFailed(__METHOD__.' requires an EGroupware\Api\Db\Deprecated object!');
		}
		return $this->m_odb->next_record();
	}

	/**
	* Execute a query
	*
	* @param string $Query_String the query to be executed
	* @param mixed $line the line method was called from - use __LINE__
	* @param string $file the file method was called from - use __FILE__
	* @param int $offset row to start from
	* @param int $num_rows number of rows to return (optional), if unset will use $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs']
	* @return ADORecordSet or false, if the query fails
	*/
	function query($sQuery, $line='', $file='')
	{
		return $this->m_odb->query($sQuery, $line, $file);
	}

	/**
	* Insert a row of data into a table or updates it if $where is given, all data is quoted according to it's type
	*
	* @param string $table name of the table
	* @param array $data with column-name / value pairs
	* @param mixed $where string with where clause or array with column-name / values pairs to check if a row with that keys already exists, or false for an unconditional insert
	*	if the row exists db::update is called else a new row with $date merged with $where gets inserted (data has precedence)
	* @param int $line line-number to pass to query
	* @param string $file file-name to pass to query
	* @param string $app=false string with name of app, this need to be set in setup anyway!!!
	* @return ADORecordSet or false, if the query fails
	*/
	function insert($table,$data,$where,$line,$file,$app=False,$use_prepared_statement=false)
	{
		return $this->m_odb->insert($table,$data,$where,$line,$file,$app,$use_prepared_statement);
	}

	/**
	 * Execute the Sql statements in an array and give diagnostics, if any error occures
	 *
	 * @param array $aSql array of SQL strings to execute
	 * @param int $debug_level for which debug_level (and higher) should the diagnostics always been printed
	 * @param string $debug variable number of arguments for the debug_message functions in case of an error
	 * @return int 2: no error, 1: errors, but continued, 0: errors aborted
	 */
	function ExecuteSqlArray($aSql,$debug_level)
	{
		if ($this->m_odb->query_log)	// we use Db::query to log the queries
		{
			$retval = 2;
			foreach($aSql as $sql)
			{
				if (!$this->m_odb->query($sql,__LINE__,__FILE__))
				{
					$retval = 1;
				}
			}
		}
		else
		{
			$retval = $this->dict->ExecuteSQLArray($aSql);
		}
		if ($retval < 2 || $this->debug >= $debug_level || $this->debug > 3)
		{
			$debug_params = func_get_args();
			array_shift($debug_params);
			array_shift($debug_params);
			call_user_func_array(array($this,'debug_message'),$debug_params);
			if ($retval < 2 && !$this->dict->debug)
			{
				echo '<p><b>'.$this->adodb->ErrorMsg()."</b></p>\n";
			}
		}
		return $retval;
	}

	/**
	 * Created a (unique) name for an index
	 *
	 * As the length of the index name is limited on some databases, we use two algorithms:
	 * a) we use just the first column-name with and added _2, _3, ... if more indexes uses that column
	 * b) we use the table-names plus all column-names and remove dublicate parts
	 *
	 * @internal
	 * @param $sTableName string name of the table
	 * @param $aColumnNames array of column-names or string with a single column-name
	 * @return string the index-name
	 */
	function _index_name($sTableName,$aColumnNames)
	{
		// this code creates extrem short index-names, eg. for MaxDB
//			if (isset($this->max_index_length[$this->sType]) && $this->max_index_length[$this->sType] <= 32)
//			{
//				static $existing_indexes=array();
//
//				if (!isset($existing_indexes[$sTableName]) && method_exists($this->adodb,'MetaIndexes'))
//				{
//					$existing_indexes[$sTableName] = $this->adodb->MetaIndexes($sTableName);
//				}
//				$i = 0;
//				$firstCol = is_array($aColumnNames) ? $aColumnNames[0] : $aColumnNames;
//				do
//				{
//					++$i;
//					$name = $firstCol . ($i > 1  ? '_'.$i : '');
//				}
//				while (isset($existing_indexes[$sTableName][$name]) || isset($existing_indexes[strtoupper($sTableName)][strtoupper($name)]));
//
//				$existing_indexes[$sTableName][$name] = True;	// mark it as existing now
//
//				return $name;
//			}
		// This code creates longer index-names incl. the table-names and the used columns
		$table = str_replace(array('phpgw_','egw_'),'',$sTableName);
		// if the table-name or a part of it is repeated in the column-name, remove it there
		$remove[] = $table.'_';
		// also remove 3 or 4 letter shortcuts of the table- or app-name
		$remove[] = substr($table,0,3).'_';
		$remove[] = substr($table,0,4).'_';
		// if the table-name consists of '_' limtied parts, remove occurences of these parts too
		foreach (explode('_',$table) as $part)
		{
			$remove[] = $part.'_';
		}
		$cols = str_replace($remove,'',$aColumnNames);

		$name = $sTableName.'_'.(is_array($cols) ? implode('_',$cols) : $cols);
		// remove length limits from column names
		$name = preg_replace('/ *\(\d+\)/','',$name);

		// this code creates a fixed short index-names (30 chars) from the long and unique name, eg. for MaxDB or Oracle
		if (isset($this->max_index_length[$this->sType]) && $this->max_index_length[$this->sType] <= 32 && strlen($name) > 30 ||
			strlen($name) >= 64)	// even mysql has a limit here ;-)
		{
			$name = "i".substr(md5($name),0,29);
		}
		return $name;
	}

	/**
	 * Giving a non-fatal error-message
	 */
	function error($str)
	{
		echo "<p><b>Error:</b> $str</p>";
	}

	/**
	 * Giving a fatal error-message and exiting
	 */
	function fatal($str)
	{
		echo "<p><b>Fatal Error:</b> $str</p>";
		exit;
	}

	/**
	 * Gives out a debug-message with certain parameters
	 *
	 * All permanent debug-messages in the calendar should be done by this function !!!
	 *	(In future they may be logged or sent as xmlrpc-faults back.)
	 *
	 * Permanent debug-message need to make sure NOT to give secret information like passwords !!!
	 *
	 * This function do NOT honor the setting of the debug variable, you may use it like
	 * if ($this->debug > N) $this->debug_message('Error ;-)');
	 *
	 * The parameters get formated depending on their type.
	 *
	 * @param $msg string message with parameters/variables like lang(), eg. '%1'
	 * @param $backtrace include a function-backtrace, default True=On
	 *	should only be set to False=Off, if your code ensures a call with backtrace=On was made before !!!
	 * @param $param mixed a variable number of parameters, to be inserted in $msg
	 *	arrays get serialized with print_r() !
	 */
	function debug_message($msg,$backtrace=True)
	{
		for($i = 2; $i < func_num_args(); ++$i)
		{
			$param = func_get_arg($i);

			if (is_null($param))
			{
				$param='NULL';
			}
			else
			{
				switch(gettype($param))
				{
					case 'string':
						$param = "'$param'";
						break;
					case 'array':
					case 'object':
						list(,$content) = @each($param);
						$do_pre = is_array($param) ? count($param) > 6 || is_array($content)&&count($content) : True;
						$param = ($do_pre ? '<pre>' : '').print_r($param,True).($do_pre ? '</pre>' : '');
						break;
					case 'boolean':
						$param = $param ? 'True' : 'False';
						break;
				}
			}
			$msg = str_replace('%'.($i-1),$param,$msg);
		}
		echo '<p>'.$msg."<br>\n".($backtrace ? 'Backtrace: '.function_backtrace(1)."</p>\n" : '');
	}

	/**
	 * Converts an eGW table-definition array into an ADOdb column-definition string
	 *
	 * @internal
	 * @param array $aTableDef eGW table-defintion
	 * @return string ADOdb column-definition string (comma separated)
	 */
	function _egw2adodb_columndef($aTableDef)
	{
		$ado_defs = array();
		foreach($aTableDef['fd'] as $col => $col_data)
		{
			$ado_col = False;

			switch($col_data['type'])
			{
				case 'auto':
					$ado_col = 'I AUTOINCREMENT NOTNULL';
					unset($col_data['nullable']);	// else we set it twice
					break;
				case 'blob':
					$ado_col = 'B';
					break;
				case 'bool':
					$ado_col = 'L';
					break;
				case 'char':
					// ADOdb does not differ between char and varchar
				case 'ascii':
				case 'varchar':
					$ado_col = "C";
					if(0 < $col_data['precision'] && $col_data['precision'] <= $this->max_varchar_length)
					{
						$ado_col .= "($col_data[precision])";
					}
					if($col_data['precision'] > $this->max_varchar_length)
					{
						$ado_col = 'X';
					}
					if ($col_data['type'] == 'ascii' && $this->sType == 'mysql')
					{
						$ado_col .= ' CONSTRAINT "CHARACTER SET ascii"';
					}
					break;
				case 'date':
					$ado_col = 'D';
					// allow to use now() beside current_date, as Postgres backups contain it and it's easier to remember anyway
					if (in_array($col_data['default'],array('current_date','now()')))
					{
						$ado_col .= ' DEFDATE';
						unset($col_data['default']);
					}
					break;
				case 'decimal':
					$ado_col = "N($col_data[precision].$col_data[scale])";
					break;
				case 'double':
				case 'float':
					// ADOdb does not differ between float and double
					$ado_col = 'F';
					break;
				case 'int':
					$ado_col = 'I';
					switch($col_data['precision'])
					{
						case 1:
						case 2:
						case 4:
						case 8:
							$ado_col .= $col_data['precision'];
							break;
					}
					break;
				case 'longtext':
					$ado_col = 'XL';
					break;
				case 'text':
					$ado_col = 'X';
					break;
				case 'timestamp':
					$ado_col = 'T';
					// allow to use now() beside current_timestamp, as Postgres backups contain it and it's easier to remember anyway
					if (in_array($col_data['default'],array('current_timestamp','now()')))
					{
						$ado_col .= ' DEFTIMESTAMP';
						unset($col_data['default']);
					}
					break;
			}
			if (!$ado_col)
			{
				$this->error("Ignoring unknown column-type '$col_data[type]($col_data[precision])' !!!<br>".function_backtrace());
				continue;
			}
			if (isset($col_data['nullable']) && !$col_data['nullable'])
			{
				$ado_col .= ' NOTNULL';
			}
			if (isset($col_data['default']))
			{
				$ado_col .= (in_array($col_data['type'],array('bool','int','decimal','float','double')) && $col_data['default'] != 'NULL' ? ' NOQUOTE' : '').
					' DEFAULT '.$this->m_odb->quote($col_data['default'],$col_data['type']);
			}
			if (in_array($col,$aTableDef['pk']))
			{
				$ado_col .= ' PRIMARY';
			}
			$ado_defs[] = $col . ' ' . $ado_col;
		}
		//print_r($aTableDef); echo implode(",\n",$ado_defs)."\n";
		return implode(",\n",$ado_defs);
	}

	/**
	 * Translates an eGW type into the DB's native type
	 *
	 * @param string $egw_type eGW name of type
	 * @param string|boolean DB's name of the type or false if the type could not be identified (should not happen)
	 */
	function TranslateType($egw_type)
	{
		$ado_col = $this->_egw2adodb_columndef(array(
			'fd' => array('test' => array('type' => $egw_type)),
			'pk' => array(),
		));
		$matches = null;
		return preg_match('/test ([A-Z0-9]+)/i',$ado_col,$matches) ? $this->dict->ActualType($matches[1]) : false;
	}

	/**
	 * Read the table-definition direct from the database
	 *
	 * The definition might not be as accurate, depending on the DB!
	 *
	 * @param string $sTableName table-name
	 * @return array|boolean table-defition, like $phpgw_baseline[$sTableName] after including tables_current, or false on error
	 */
	function GetTableDefinition($sTableName)
	{
		// MetaType returns all varchar >= blobSize as blob, it's by default 100, which is wrong
		$this->dict->blobSize = $this->max_varchar_length;

		if (!method_exists($this->dict,'MetaColumns') ||
			!($columns = $this->dict->MetaColumns($sTableName)))
		{
			return False;
		}
		$definition = array(
			'fd' => array(),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array(),
		);
		//echo "$sTableName: <pre>".print_r($columns,true)."</pre>";
		foreach($columns as $column)
		{
			$name = $this->capabilities['name_case'] == 'upper' ? strtolower($column->name) : $column->name;

			$type = method_exists($this->dict,'MetaType') ? $this->dict->MetaType($column) : strtoupper($column->type);

			// fix longtext not correctly handled by ADOdb
			if ($type == 'X' && $column->type == 'longtext') $type = 'XL';

			static $ado_type2egw = array(
				'C'		=> 'varchar',
				'C2'	=> 'varchar',
				'X'		=> 'text',
				'X2'	=> 'text',
				'XL'	=> 'longtext',
				'B'		=> 'blob',
				'I'		=> 'int',
				'T'		=> 'timestamp',
				'D'		=> 'date',
				'F'		=> 'float',
				'N'		=> 'decimal',
				'R'		=> 'auto',
				'L'		=> 'bool',
			);
			$definition['fd'][$name]['type'] = $ado_type2egw[$type];

			switch($type)
			{
				case 'D': case 'T':
					// detecting the automatic timestamps again
					if ($column->has_default && preg_match('/(0000-00-00|timestamp)/i',$column->default_value))
					{
						$column->default_value = $type == 'D' ? 'current_date' : 'current_timestamp';
					}
					break;
				case 'C': case 'C2':
					// ascii columns are reported as varchar
					$definition['fd'][$name]['type'] = $this->m_odb->get_column_attribute($name, $sTableName, true, 'type') === 'ascii' ?
						'ascii' : 'varchar';
					$definition['fd'][$name]['precision'] = $column->max_length;
					break;
				case 'B':
				case 'X': case 'XL': case 'X2':
					// text or blob's need to be nullable for most databases
					$column->not_null = false;
					break;
				case 'F':
					$definition['fd'][$name]['precision'] = $column->max_length;
					break;
				case 'N':
					$definition['fd'][$name]['precision'] = $column->max_length;
					$definition['fd'][$name]['scale'] = $column->scale;
					break;
				case 'R':
					$column->auto_increment = true;
					// fall-through
				case 'I': case 'I1': case 'I2': case 'I4': case 'I8':
					switch($type)
					{
						case 'I1': case 'I2': case 'I4': case 'I8':
							$definition['fd'][$name]['precision'] = (int) $type[1];
							break;
						default:
							if ($column->max_length > 11)
							{
								$definition['fd'][$name]['precision'] = 8;
							}
							elseif ($column->max_length > 6 || !$column->max_length)
							{
								$definition['fd'][$name]['precision'] = 4;
							}
							elseif ($column->max_length > 2)
							{
								$definition['fd'][$name]['precision'] = 2;
							}
							else
							{
								$definition['fd'][$name]['precision'] = 1;
							}
							break;
					}
					if ($column->auto_increment)
					{
						// no precision for auto!
						$definition['fd'][$name] = array(
							'type' => 'auto',
							'nullable' => False,
						);
						$column->has_default = False;
						$definition['pk'][] = $name;
					}
					else
					{
						$definition['fd'][$name]['type'] = 'int';
						// detect postgres type-spec and remove it
						$matches = null;
						if ($this->sType == 'pgsql' && $column->has_default && preg_match('/\(([^)])\)::/',$column->default_value,$matches))
						{
							$definition['fd'][$name]['default'] = $matches[1];
							$column->has_default = False;
						}
					}
					// fix MySQL stores bool columns as smallint
					if ($this->sType == 'mysql' && $definition['fd'][$name]['precision'] == 1 &&
						$this->m_odb->get_column_attribute($name, $sTableName, true, 'type') === 'bool')
					{
						$definition['fd'][$name]['type'] = 'bool';
						unset($definition['fd'][$name]['precision']);
						$column->default_value = (bool)$column->default_value;
					}
					break;
			}
			if ($column->has_default)
			{
				if (preg_match("/^'(.*)'::.*$/",$column->default_value,$matches))	// postgres
				{
					$column->default_value = $matches[1];
				}
				$definition['fd'][$name]['default'] = $column->default_value;
			}
			if ($column->not_null)
			{
				$definition['fd'][$name]['nullable'] = False;
			}
			if ($column->primary_key && !in_array($name,$definition['pk']))
			{
				$definition['pk'][] = $name;
			}
		}
		if ($this->debug > 2) $this->debug_message("schema_proc::GetTableDefintion: MetaColumns(%1) = %2",False,$sTableName,$columns);

		// not all DB's (odbc) return the primary keys via MetaColumns
		if (!count($definition['pk']) && method_exists($this->dict,'MetaPrimaryKeys') &&
			is_array($primary = $this->dict->MetaPrimaryKeys($sTableName)) && count($primary))
		{
			if($this->capabilities['name_case'] == 'upper')
			{
				array_walk($primary, function(&$s)
				{
					$s = strtolower($s);
				});
			}
			$definition['pk'] = $primary;
		}
		if ($this->debug > 1) $this->debug_message("schema_proc::GetTableDefintion: MetaPrimaryKeys(%1) = %2",False,$sTableName,$primary);

		$this->GetIndexes($sTableName, $definition);
		if ($this->debug > 1) $this->debug_message("schema_proc::GetTableDefintion(%1) = %2",False,$sTableName,$definition);

		return $definition;
	}

	/**
	 * Query indexes (not primary index) from database
	 *
	 * @param string $sTableName
	 * @param array& $definition=array()
	 * @return array of arrays with keys 'ix' and 'uc'
	 */
	public function GetIndexes($sTableName, array &$definition=array())
	{
		if (method_exists($this->dict,'MetaIndexes') &&
			is_array($indexes = $this->dict->MetaIndexes($sTableName)) && count($indexes))
		{
			foreach($indexes as $index)
			{
				// append (optional) length of index in brackets to column
				foreach((array)$index['length'] as $col => $length)
				{
					if (($key = array_search($col, $index['columns']))) $index['columns'][$key] .= '('.$length.')';
				}
				if($this->capabilities['name_case'] == 'upper')
				{
					array_walk($index['columns'], function(&$s)
					{
						$s = strtolower($s);
					});
				}
				if (count($definition['pk']) && (implode(':',$definition['pk']) == implode(':',$index['columns']) ||
					$index['unique'] && count(array_intersect($definition['pk'],$index['columns'])) == count($definition['pk'])))
				{
					continue;	// is already the primary key => ignore it
				}
				$kind = $index['unique'] ? 'uc' : 'ix';

				$definition[$kind][] = count($index['columns']) > 1 ? $index['columns'] : $index['columns'][0];
			}
			if ($this->debug > 2) $this->debug_message("schema_proc::GetTableDefintion: MetaIndexes(%1) = %2",False,$sTableName,$indexes);
		}
		return $definition;
	}

	/**
	 * Check if all indexes exist and create them if not
	 *
	 * Does not check index-type of length!
	 */
	function CheckCreateIndexes()
	{
		foreach($this->adodb->MetaTables('TABLES') as $table)
		{
			if (!($table_def = $this->m_odb->get_table_definitions(true, $table))) continue;

			$definition = array();
			$this->GetIndexes($table, $definition);

			// iterate though indexes we should have according to tables_current
			foreach(array('uc', 'ix') as $type)
			{
				foreach($table_def[$type] as $columns)
				{
					// sometimes primary key is listed as (unique) index too --> ignore it
					if ($this->_in_index($columns, array($table_def['pk']), true)) continue;

					// check if they exist in real table and create them if not
					if (!$this->_in_index($columns, $definition[$type]) &&
						// sometimes index is listed as unique index too --> ignore that
						($type == 'uc' || !$this->_in_index($columns, $definition['uc'], true)))
					{
						// check if index may exists, but without limit in column-name
						if ($this->_in_index($columns, $definition[$type], true))
						{
							// for PostgreSQL we dont use length-limited indexes --> nothing to do
							if ($this->m_odb->Type == 'pgsql') continue;
							// for MySQL we drop current index and create it with correct length
							$this->DropIndex($table, $columns);
						}
						$this->CreateIndex($table, $columns, $type == 'uc');
					}
				}
			}
		}
	}

	/**
	 * Get actual columnnames as a comma-separated string in $sColumns and set indices as class-vars pk,fk,ix,uc
	 *
	 * old translator function, use GetTableDefition() instead
	 * @depricated
	 */
	function _GetColumns($oProc,$sTableName,&$sColumns)
	{
		unset($oProc);	// unused, but required by function signature
		$this->sCol = $this->pk = $this->fk = $this->ix = $this->uc = array();

		$tabledef = $this->GetTableDefinition($sTableName);

		$sColumns = implode(',',array_keys($tabledef['fd']));

		foreach($tabledef['fd'] as $column => $data)
		{
			$col_def = "'type' => '$data[type]'";
			unset($data['type']);
			foreach($data as $key => $val)
			{
				$col_def .= ", '$key' => ".(is_bool($val) ? ($val ? 'true' : 'false') :
					(is_int($val) ? $val : "'$val'"));
			}
			$this->sCol[] = "\t\t\t\t'$column' => array($col_def),\n";
		}
		foreach(array('pk','fk','ix','uc') as $kind)
		{
			$this->$kind = $tabledef[$kind];
		}
	}
}
