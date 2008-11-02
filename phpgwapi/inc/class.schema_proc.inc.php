<?php
/**
 * eGroupWare - Setup - db-schema-processor
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

/**
 * eGW's ADOdb based schema-processor
 */
class schema_proc
{
	/**
	 * @deprecated formerly used translator class, now a reference to ourself
	 */
	var $m_oTranslator;
	/**
	 * egw_db-object
	 *
	 * @var egw_db
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
	var $system_charset;
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
	 * @param object $db=null database class, if null we use $GLOBALS['egw']->db
	 * @return schema_proc
	 */
	function __construct($dbms=False,$db=null)
	{
	    if(is_object($db))
		{
			$this->m_odb = $db;
	    }
	    else
	    {
			$this->m_odb = isset($GLOBALS['egw']->db) && is_object($GLOBALS['egw']->db) ? $GLOBALS['egw']->db : $GLOBALS['egw_setup']->db;
	    }
	    if (!($this->m_odb instanceof egw_db))
	    {
	    	throw new egw_exception_assertion_failed('no egw_db object!');
	    }
	    $this->m_odb->connect();
		$this->capabilities =& $this->m_odb->capabilities;

		$this->sType = $dbms ? $dmbs : $this->m_odb->Type;
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
	 * @param string/array $columns column-name as string or array of column-names plus optional options key
	 * @param array $indexs array of indexes (column-name as string or array of column-names plus optional options key)
	 * @return boolean true if index over $columns exist in the $indexes array
	 */
	function _in_index($columns,$indexs)
	{
		if (is_array($columns))
		{
			unset($columns['options']);
			$columns = implode('-',$columns);
		}
		foreach($indexs as $index)
		{
			if (is_array($index))
			{
				unset($index['options']);
				$index = implode('-',$index);
			}
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
			(float) $this->m_odb->ServerInfo['version'] >= 4.0 && $this->m_odb->Link_ID->charset2mysql[$this->system_charset])
		{
			$set_table_charset = array($this->sType => 'CHARACTER SET '.$this->m_odb->Link_ID->charset2mysql[$this->system_charset]);
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
			if ($this->_in_index($mFields,array($aTableDef['pk'])))
			{
				continue;	// is already created as primary key
			}
			if (is_numeric($name))
			{
				$name = $this->_index_name($sTableName,$mFields);
			}
			$aSql = $this->dict->CreateIndexSQL($name,$sTableName,$mFields,array('UNIQUE'));
			if (!($retVal = $this->ExecuteSQLArray($aSql,2,'CreateIndexSql(%1,%2,%3,%4) sql=%5',False,$name,$sTableName,$mFields,array('UNIQUE'),$aSql)))
			{
				return $retVal;
			}
		}
		// creation indices
		foreach ($aTableDef['ix'] as $name => $mFields)
		{
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
			else
			{
				// only create indexes on text-columns, if (db-)specifiy options are given or FULLTEXT for mysql
				// most DB's cant do them and give errors
				if (in_array($aTableDef['fd'][$mFields]['type'],array('text','longtext')))
				{
					if ($this->sType == 'mysql')
					{
						$options = 'FULLTEXT';
					}
					else
					{
						continue;	// ignore that index
					}
				}
			}

			if (is_numeric($name))
			{
				$name = $this->_index_name($sTableName,$mFields);
			}
			$aSql = $this->dict->CreateIndexSQL($name,$sTableName,$mFields,array($options));
			if (!($retVal = $this->ExecuteSQLArray($aSql,2,'CreateIndexSql(%1,%2,%3,%4) sql=%5',False,$name,$sTableName,$mFields,$options,$aSql)))
			{
				return $retVal;
			}
		}
		// preserve last value of an old sequence
		if ($this->sType == 'pgsql' && $preserveSequence && $this->pgsql_old_seq)
		{
			if ($seq = $this->_PostgresHasOldSequence($sTableName))
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

		foreach($aTables as $sTableName => $aTableDef)
		{
			if($this->DropTable($sTableName))
			{
				if($bOutputHTML)
				{
					echo '<br>Drop Table <b>' . $sTableSQL . '</b>';
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
	 * @param boolean $bCopyData ???
	 * @return int 2: no error, 1: errors, but continued, 0: errors aborted
	 */
	function DropColumn($sTableName, $aTableDef, $sColumnName, $bCopyData = true)
	{
		$table_def = $this->GetTableDefinition($sTableName);
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
			$table_def = $this->GetTableDefinition($sOldTableName);

			if ($this->_PostgresHasOldSequence($sOldTableName,True) || count($table_def['pk']) ||
				count($table_def['ix']) || count($table_def['uc']))
			{
				if ($this->adodb->BeginTrans() &&
					$this->CreateTable($sNewTableName,$table_def,True) &&
					$this->m_odb->query("INSERT INTO $sNewTableName SELECT * FROM $sOldTableName",__LINE__,__FILE__) &&
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
	 * @return boolean/string sequence-name or false
	 */
	function _PostgresHasOldSequence($sTableName,$preserveValue=False)
	{
		if ($this->sType != 'pgsql') return false;

		$seq = $this->adodb->GetOne("SELECT d.adsrc FROM pg_attribute a, pg_class c, pg_attrdef d WHERE c.relname='$sTableName' AND c.oid=d.adrelid AND d.adsrc LIKE '%seq_$sTableName\'::text)' AND a.attrelid=c.oid AND d.adnum=a.attnum");
		$seq2 = $this->adodb->GetOne("SELECT d.adsrc FROM pg_attribute a, pg_class c, pg_attrdef d WHERE c.relname='$sTableName' AND c.oid=d.adrelid AND d.adsrc LIKE '%$sTableName%_seq\'::text)' AND a.attrelid=c.oid AND d.adnum=a.attnum");

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
	 * @param bool $preserveValue
	 */
	function _PostgresTestDropOldSequence($sTableName,$preserveValue=False)
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
	 * @param boolean $bCopyData ???
	 * @return int 2: no error, 1: errors, but continued, 0: errors aborted
	 */
	function AlterColumn($sTableName, $sColumnName, $aColumnDef, $bCopyData=True)
	{
		$table_def = $this->GetTableDefinition($sTableName);
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
	 * @param boolean $bCopyData ???
	 * @return int 2: no error, 1: errors, but continued, 0: errors aborted
	 */
	function RenameColumn($sTableName, $sOldColumnName, $sNewColumnName, $bCopyData=True)
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
	 * @param string $sTablename table-name
	 * @param array $aColumnNames columns for the index
	 * @param boolean $bUnique=false true for a unique index, default false
	 * @param array/string $options='' db-sepecific options, default '' = none
	 * @param string $sIdxName='' name of the index, if not given (default) its created automaticaly
	 * @return int 2: no error, 1: errors, but continued, 0: errors aborted
	 */
	function CreateIndex($sTableName,$aColumnNames,$bUnique=false,$options='',$sIdxName='')
	{
		if (!$sIdxName || is_numeric($sIdxName))
		{
			$sIdxName = $this->_index_name($sTableName,$aColumnNames);
		}
		if (!is_array($options)) $options = $options ? array($options) : array();
		if ($bUnique) $options[] = 'UNIQUE';

		$aSql = $this->dict->CreateIndexSQL($sIdxName,$sTableName,$aColumnNames,$options);

		return $this->ExecuteSQLArray($aSql,2,'CreateIndexSQL(%1,%2,%3,%4) sql=%5',False,$name,$sTableName,$aColumnNames,$options,$aSql);
	}

	/**
	 * Drop an Index
	 *
	 * @param string $sTablename table-name
	 * @param array/string $aColumnNames columns of the index or the name of the index
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
	 * @param array/boolean $aDefaults array with default for the colums during copying, values are either (old) column-names or quoted string-literals
	 */
	function RefreshTable($sTableName, $aTableDef, $aDefaults=False)
	{
		if($this->debug) { echo "<p>schema_proc::RefreshTable('$sTableName',"._debug_array($aTableDef,False).")<p>$sTableName="._debug_array($old_table_def,False)."\n"; }

		$old_table_def = $this->GetTableDefinition($sTableName);

		$tmp_name = 'tmp_'.$sTableName;
		$this->m_odb->transaction_begin();

		$select = array();
		$blob_column_included = $auto_column_included = False;
		foreach($aTableDef['fd'] as $name => $data)
		{
			if ($aDefaults && isset($aDefaults[$name]))	// use given default
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
		$select = implode(',',$select);

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
			$Ok = $this->RenameTable($sTableName,$tmp_name);
		}
		$Ok = $Ok && $this->CreateTable($sTableName,$aTableDef) &&
			$this->m_odb->query("$extra INSERT INTO $sTableName (".
				implode(',',array_keys($aTableDef['fd'])).
				") SELEcT $distinct $select FROM $tmp_name",__LINE__,__FILE__) &&
			$this->DropTable($tmp_name);

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
	function GenerateScripts($aTables, $bOutputHTML=False)
	{
		return True;
	}

	/**
	 * Creates all tables for one application
	 *
	 * @param array $aTables array of eGW table-definitions
	 * @param boolean $bOutputHTML=false should we give diagnostics, default False
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
	* @param string/integer $Name name of field or positional index starting from 0
	* @param bool $strip_slashes string escape chars from field(optional), default false
	* @return string the field value
	*/
	function f($value,$strip_slashes=False)
	{
		return $this->m_odb->f($value,$strip_slashes);
	}

	/**
	* Number of rows in current result set
	*
	* @return int number of rows
	*/
	function num_rows()
	{
		return $this->m_odb->num_rows();
	}

	/**
	* Move to the next row in the results set
	*
	* @return bool was another row found?
	*/
	function next_record()
	{
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
	 * @param $aSql array of SQL strings to execute
	 * @param $debug_level int for which debug_level (and higher) should the diagnostics always been printed
	 * @param $debug string variable number of arguments for the debug_message functions in case of an error
	 * @return int 2: no error, 1: errors, but continued, 0: errors aborted
	 */
	function ExecuteSqlArray($aSql,$debug_level,$debug)
	{
		if ($this->m_odb->query_log)	// we use egw_db::query to log the queries
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
			call_user_method_array('debug_message',$this,$debug_params);
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
		$aColumnNames = str_replace($remove,'',$aColumnNames);

		$name = $sTableName.'_'.(is_array($aColumnNames) ? implode('_',$aColumnNames) : $aColumnNames);

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
					break;
				case 'date':
					$ado_col = 'D';
					if ($col_data['default'] == 'current_date')
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
					if ($col_data['default'] == 'current_timestamp')
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
	 * @param string/boolean DB's name of the type or false if the type could not be identified (should not happen)
	 */
	function TranslateType($egw_type)
	{
		$ado_col = $this->_egw2adodb_columndef(array(
			'fd' => array('test' => array('type' => $egw_type)),
			'pk' => array(),
		));
		return preg_match('/test ([A-Z0-9]+)/i',$ado_col,$matches) ? $this->dict->ActualType($matches[1]) : false;
	}

	/**
	 * Read the table-definition direct from the database
	 *
	 * The definition might not be as accurate, depending on the DB!
	 *
	 * @param string $sTableName table-name
	 * @return array/boolean table-defition, like $phpgw_baseline[$sTableName] after including tables_current, or false on error
	 */
	function GetTableDefinition($sTableName)
	{
		// MetaType returns all varchar >= blobSize as blob, it's by default 100, which is wrong
		if ($this->dict->blobSize < 255) $this->dict->blobSize = 255;

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
					$definition['fd'][$name]['type'] = 'varchar';
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
						if ($this->sType == 'pgsql' && $column->has_default && preg_match('/\(([^)])\)::/',$column->default_value,$matches))
						{
							$definition['fd'][$name]['default'] = $matches[1];
							$column->has_default = False;
						}
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
				array_walk($primary,create_function('&$s','$s = strtolower($s);'));
			}
			$definition['pk'] = $primary;
		}
		if ($this->debug > 1) $this->debug_message("schema_proc::GetTableDefintion: MetaPrimaryKeys(%1) = %2",False,$sTableName,$primary);

		if (method_exists($this->dict,'MetaIndexes') &&
			is_array($indexes = $this->dict->MetaIndexes($sTableName)) && count($indexes))
		{
			foreach($indexes as $index)
			{
				if($this->capabilities['name_case'] == 'upper')
				{
					array_walk($index['columns'],create_function('&$s','$s = strtolower($s);'));
				}
				if (count($definition['pk']) && (implode(':',$definition['pk']) == implode(':',$index['columns']) ||
					$index['unique'] && count(array_intersect($definition['pk'],$index['columns'])) == count($definition['pk'])))
				{
					continue;	// is already the primary key => ignore it
				}
				$kind = $index['unique'] ? 'uc' : 'ix';

				$definition[$kind][] = count($index['columns']) > 1 ? $index['columns'] : $index['columns'][0];
			}
		}
		if ($this->debug > 2) $this->debug_message("schema_proc::GetTableDefintion: MetaIndexes(%1) = %2",False,$sTableName,$indexes);
		if ($this->debug > 1) $this->debug_message("schema_proc::GetTableDefintion(%1) = %2",False,$sTableName,$definition);

		return $definition;
	}

	/**
	 * Get actual columnnames as a comma-separated string in $sColumns and set indices as class-vars pk,fk,ix,uc
	 *
	 * old translator function, use GetTableDefition() instead
	 * @depricated
	 */
	function _GetColumns($oProc,$sTableName,&$sColumns)
	{
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
