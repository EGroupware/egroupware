<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * SQL for table properties taken from phpPgAdmin Version 2.2.1             *
  *   http://www.greatbridge.org/project/phppgadmin                          *
  * Copyright (C) 1999-2000 Dan Wilson <phpPgAdmin@acucore.com>              *
  * Copyright (C) 1998-1999 Tobias Ratschiller <tobias@dnet.it>              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class schema_proc_pgsql
	{
		var $m_sStatementTerminator;
		// Following added to convert sql to array
		var $sCol = array();
		var $pk = array();
		var $fk = array();
		var $ix = array();
		var $uc = array();

		function schema_proc_pgsql()
		{
			$this->m_sStatementTerminator = ';';
		}

		// Return a type suitable for DDL
		function TranslateType($sType, $iPrecision = 0, $iScale = 0, &$sTranslated)
		{
			switch($sType)
			{
				case 'auto':
					$sTranslated = 'int4';
					break;
				case 'blob':
					$sTranslated = 'text';
					break;
				case 'char':
					if ($iPrecision > 0 && $iPrecision < 256)
					{
						$sTranslated =  sprintf("char(%d)", $iPrecision);
					}
					if ($iPrecision > 255)
					{
						$sTranslated =  'text';
					}
					break;
				case 'date':
					$sTranslated =  'date';
					break;
				case 'decimal':
					$sTranslated =  sprintf("decimal(%d,%d)", $iPrecision, $iScale);
					break;
				case 'float':
					if ($iPrecision == 4 || $iPrecision == 8)
					{
						$sTranslated =  sprintf("float%d", $iPrecision);
					}
					break;
				case 'int':
					if ($iPrecision == 2 || $iPrecision == 4 || $iPrecision == 8)
					{
						$sTranslated =  sprintf("int%d", $iPrecision);
					}
					break;
				case 'longtext':
					$sTranslated = 'text';
					break;
				case 'text':
					$sTranslated = 'text';
					break;
				case 'timestamp':
					$sTranslated = 'timestamp';
					break;
				case 'varchar':
					if ($iPrecision > 0 && $iPrecision < 256)
					{
						$sTranslated =  sprintf("varchar(%d)", $iPrecision);
					}
					if ($iPrecision > 255)
					{
						$sTranslated =  'text';
					}
					break;
			}

			return (strlen($sTranslated) > 0);
		}

		function TranslateDefault($sDefault)
		{
			switch ($sDefault)
			{
				case 'current_date':
				case 'current_timestamp':
					return 'now';
			}

			return $sDefault;
		}

		// Inverse of above, convert sql column types to array info
		function rTranslateType($sType, $iPrecision = 0, $iScale = 0, &$sTranslated)
		{
			$sTranslated = '';
			switch($sType)
			{
				case 'serial':
					$sTranslated = "'type' => 'auto'";
					break;
				case 'int2':
					$sTranslated = "'type' => 'int', 'precision' => 2";
					break;
				case 'int4':
					$sTranslated = "'type' => 'int', 'precision' => 4";
					break;
				case 'int8':
					$sTranslated = "'type' => 'int', 'precision' => 8";
					break;
				case 'bpchar':
				case 'char':
					if ($iPrecision > 0 && $iPrecision < 256)
					{
						$sTranslated = "'type' => 'char', 'precision' => $iPrecision";
					}
					if ($iPrecision > 255)
					{
						$sTranslated =  "'type' => 'text'";
					}
					break;
				case 'numeric':
					/* Borrowed from phpPgAdmin */
					$iPrecision = ($iScale >> 16) & 0xffff;
					$iScale     = ($iScale - 4) & 0xffff;
					$sTranslated = "'type' => 'decimal', 'precision' => $iPrecision, 'scale' => $iScale";
					break;
				case 'float':
				case 'float4':
				case 'float8':
				case 'double':
					$sTranslated = "'type' => 'float', 'precision' => $iPrecision";
					break;
				case 'datetime':
					$sTranslated = "'type' => 'timestamp'";
					break;
				case 'varchar':
					if ($iPrecision > 0 && $iPrecision < 256)
					{
						$sTranslated =  "'type' => 'varchar', 'precision' => $iPrecision";
					}
					if ($iPrecision > 255)
					{
						$sTranslated =  "'type' => 'text'";
					}
					break;
				case 'text':
				case 'blob':
				case 'date':
					$sTranslated = "'type' => '$sType'";
					break;
			}

			return (strlen($sTranslated) > 0);
		}

		function GetPKSQL($sFields)
		{
			return "PRIMARY KEY($sFields)";
		}

		function GetUCSQL($sFields)
		{
			return "UNIQUE($sFields)";
		}

		function _GetColumns($oProc, $sTableName, &$sColumns, $sDropColumn = '', $sAlteredColumn = '', $sAlteredColumnType = '')
		{
			$sdb = $oProc->m_odb;
			$sdc = $oProc->m_odb;

			$sColumns = '';
			$this->pk = array();
			$this->fk = array();
			$this->ix = array();
			$this->uc = array();

			$query = "SELECT a.attname,a.attnum FROM pg_attribute a,pg_class b WHERE ";
			$query .= "b.oid=a.attrelid AND a.attnum>0 and b.relname='$sTableName'";
			if ($sDropColumn != '')
			{
				$query .= " AND a.attname != '$sDropColumn'";
			}
			$query .= ' ORDER BY a.attnum';

			$oProc->m_odb->query($query);
			while ($oProc->m_odb->next_record())
			{
				if ($sColumns != '')
				{
					$sColumns .= ',';
				}

				$sFieldName = $oProc->m_odb->f(0);
				$sColumns .= $sFieldName;
				if ($sAlteredColumn == $sFieldName && $sAlteredColumnType != '')
				{
					$sColumns .= '::' . $sAlteredColumnType;
				}
			}
			//$qdefault = "SELECT substring(d.adsrc for 128) FROM pg_attrdef d, pg_class c "
			//	. "WHERE c.relname = $sTableName AND c.oid = d.adrelid AND d.adnum =" . $oProc->m_odb->f(1);
			$sql_get_fields = "
				SELECT
					a.attnum,
					a.attname AS field,
					t.typname AS type,
					a.attlen AS length,
					a.atttypmod AS lengthvar,
					a.attnotnull AS notnull
				FROM
					pg_class c,
					pg_attribute a,
					pg_type t
				WHERE
					c.relname = '$sTableName'
					and a.attnum > 0
					and a.attrelid = c.oid
					and a.atttypid = t.oid
					ORDER BY a.attnum";
			// attnum field type length lengthvar notnull(Yes/No)
			$sdb->query($sql_get_fields);
			while ($sdb->next_record())
			{
				$colnum  = $sdb->f(0);
				$colname = $sdb->f(1);

				if ($sdb->f(5) == 'Yes')
				{
					$null = "'nullable' => True";
				}
				else
				{
					$null = "'nullable' => False";
				}

				if ($sdb->f(2) == 'numeric')
				{
					$prec  = $sdb->f(3);
					$scale = $sdb->f(4);
				}
				elseif ($sdb->f(3) > 0)
				{
					$prec  = $sdb->f(3);
					$scale = 0;
				}
				elseif ($sdb->f(4) > 0)
				{
					$prec = $sdb->f(4) - 4;
					$scale = 0;
				}
				else
				{
					$prec = 0;
					$scale = 0;
				}

				$this->rTranslateType($sdb->f(2),$prec,$scale,$type);

				$sql_get_default = "
					SELECT d.adsrc AS rowdefault
						FROM pg_attrdef d, pg_class c 
						WHERE 
							c.relname = '$sTableName' AND 
							c.oid = d.adrelid AND
							d.adnum = $colnum
					";
				$sdc->query($sql_get_default);
				$sdc->next_record();
				if ($sdc->f(0))
				{
					if (ereg('nextval',$sdc->f(0)))
					{
						$default = '';
						$nullcomma = '';
					}
					else
					{
						$default = "'default' => '".$sdc->f(0)."'";
						$nullcomma = ',';
					}
				}
				else
				{
					$default = '';
					$nullcomma = '';
				}

				$this->sCol[] = "\t\t\t\t'" . $colname . "' => array(" . $type . ',' . $null . $nullcomma . $default . '),' . "\n";
			}
			$sql_pri_keys = "
				SELECT 
					ic.relname AS index_name, 
					bc.relname AS tab_name, 
					ta.attname AS column_name,
					i.indisunique AS unique_key,
					i.indisprimary AS primary_key
				FROM 
					pg_class bc,
					pg_class ic,
					pg_index i,
					pg_attribute ta,
					pg_attribute ia
				WHERE 
					bc.oid = i.indrelid
					AND ic.oid = i.indexrelid
					AND ia.attrelid = i.indexrelid
					AND ta.attrelid = bc.oid
					AND bc.relname = '$sTableName'
					AND ta.attrelid = i.indrelid
					AND ta.attnum = i.indkey[ia.attnum-1]
				ORDER BY 
					index_name, tab_name, column_name";
			$sdc->query($sql_pri_keys);
			while ($sdc->next_record())
			{
				//echo '<br> checking: ' . $sdc->f(4);
				if ($sdc->f(4) == 't')
				{
					$this->pk[] = $sdc->f(2);
				}
				if ($sdc->f(3) == 't')
				{
					$this->uc[] = $sdc->f(2);
				}
			}
			// ugly as heck, but is here to chop the trailing comma on the last element (for php3)
			$this->sCol[count($this->sCol) - 1] = substr($this->sCol[count($this->sCol) - 1],0,-2) . "\n";

			return false;
		}

		function _CopyAlteredTable($oProc, &$aTables, $sSource, $sDest)
		{
			$oDB = $oProc->m_odb;
			$oProc->m_odb->query("select * from $sSource");
			while ($oProc->m_odb->next_record())
			{
				$sSQL = "INSERT INTO $sDest (";
				$i=0;
				@reset($aTables[$sDest]['fd']);
				while (list($name,$arraydef) = each($aTables[$sDest]['fd']))
				{
					if ($i > 0)
					{
						$sSQL .= ',';
					}

					$sSQL .= $name;
					$i++;
				}

				$sSQL .= ') VALUES (';
				@reset($aTables[$sDest]['fd']);
				$i=0;
				while (list($name,$arraydef) = each($aTables[$sDest]['fd']))
				{
					if ($i > 0)
					{
						$sSQL .= ',';
					}

					if ($oProc->m_odb->f($i) != null)
					{
						switch ($arraydef['type'])
						{
							case 'blob':
							case 'char':
							case 'date':
							case 'text':
							case 'timestamp':
							case 'varchar':
								$sSQL .= "'" . $oProc->m_odb->f($i) . "'";
								break;
							default:
								$sSQL .= $oProc->m_odb->f($i);
						}
					}
					else
					{
						$sSQL .= 'null';
					}
					$i++;
				}
				$sSQL .= ')';

				$oDB->query($sSQL);
			}

			return true;
		}

		function GetSequenceForTable($oProc,$table,&$sSequenceName)
		{
			global $DEBUG;
			if($DEBUG) { echo '<br>GetSequenceForTable: ' . $table; }

			$oProc->m_odb->query("SELECT relname FROM pg_class WHERE NOT relname ~ 'pg_.*' AND relname LIKE 'seq_$table' AND relkind='S' ORDER BY relname",__LINE__,__FILE__);
			$oProc->m_odb->next_record();
			if ($oProc->m_odb->f('relname'))
			{
				$sSequenceName = $oProc->m_odb->f('relname');
			}
			return True;
		}

		function GetSequenceFieldForTable($oProc,$table,&$sField)
		{
			global $DEBUG;
			if($DEBUG) { echo '<br>GetSequenceFieldForTable: You rang?'; }
			$oProc->m_odb->query("SELECT a.attname FROM pg_attribute a, pg_class c, pg_attrdef d WHERE c.relname='$table' AND c.oid=d.adrelid AND d.adsrc LIKE '%seq_$table%' AND a.attrelid=c.oid AND d.adnum=a.attnum");
			$oProc->m_odb->next_record();
			if ($oProc->m_odb->f('attname'))
			{
				$sField = $oProc->m_odb->f('attname');
			}
			return True;
		}

		function DropSequenceForTable($oProc,$table)
		{
			global $DEBUG;
			if($DEBUG) { echo '<br>DropSequenceForTable: ' . $table; }

			$this->GetSequenceForTable($oProc,$table,$sSequenceName);
			if ($sSequenceName)
			{
				$oProc->m_odb->query("DROP SEQUENCE " . $sSequenceName,__LINE__,__FILE__);
			}
			return True;
		}

		function DropTable($oProc, &$aTables, $sTableName)
		{
			$this->DropSequenceForTable($oProc,$sTableName);

			return !!($oProc->m_odb->query("DROP TABLE " . $sTableName));
		}

		function DropColumn($oProc, &$aTables, $sTableName, $aNewTableDef, $sColumnName, $bCopyData = true)
		{
			if ($bCopyData)
			{
				$oProc->m_odb->query("SELECT * INTO $sTableName" . "_tmp FROM $sTableName");
			}

			$this->DropTable($oProc, $aTables, $sTableName);

			$oProc->_GetTableSQL($sTableName, $aNewTableDef, $sTableSQL, $sSequenceSQL);
			if($sSequenceSQL)
			{
				$oProc->m_odb->query($sSequenceSQL);
			}
			$query = "CREATE TABLE $sTableName ($sTableSQL)";
			if (!$bCopyData)
			{
				return !!($oProc->m_odb->query($query));
			}

			$oProc->m_odb->query($query);
			$this->_GetColumns($oProc, $sTableName . '_tmp', $sColumns, $sColumnName);
			$query = "INSERT INTO $sTableName SELECT $sColumns FROM $sTableName" . '_tmp';
			$bRet = !!($oProc->m_odb->query($query));
			return ($bRet && $this->DropTable($oProc, $aTables, $sTableName . '_tmp'));
		}

		function RenameTable($oProc, &$aTables, $sOldTableName, $sNewTableName)
		{
			global $DEBUG;
			if ($DEBUG) { echo '<br>RenameTable(): Fetching old sequence for: ' . $sOldTableName; }
			$this->GetSequenceForTable($oProc,$sOldTableName,$sSequenceName);
			if ($DEBUG) { echo ' - ' . $sSequenceName; }
			if ($DEBUG) { echo '<br>RenameTable(): Fetching sequence field for: ' . $sOldTableName; }
			$this->GetSequenceFieldForTable($oProc,$sOldTableName,$sField);
			if ($DEBUG) { echo ' - ' . $sField; }

			if ($sSequenceName)
			{
				$oProc->m_odb->query("SELECT last_value FROM seq_$sOldTableName",__LINE__,__FILE__);
				$oProc->m_odb->next_record();
				$lastval = $oProc->m_odb->f(0);

				if ($DEBUG) { echo '<br>RenameTable(): dropping old sequence: ' . $sSequenceName . ' used on field: ' . $sField; }
				$this->DropSequenceForTable($oProc,$sOldTableName);

				if ($lastval)
				{
					$lastval = ' start ' . $lastval;
				}
				$this->GetSequenceSQL($sNewTableName,$sSequenceSQL);
				if ($DEBUG) { echo '<br>RenameTable(): Making new sequence using: ' . $sSequenceSQL . $lastval; }
				$oProc->m_odb->query($sSequenceSQL . $lastval,__LINE__,__FILE__);
				if ($DEBUG) { echo '<br>RenameTable(): Altering column default for: ' . $sField; }
				$oProc->m_odb->query("ALTER TABLE $sOldTableName ALTER $sField SET DEFAULT nextval('seq_" . $sNewTableName . "')",__LINE__,__FILE__);
			}

			$indexnames = $oProc->m_odb->index_names();
			while(list($key,$val) = @each($indexnames))
			{
				$indexes[] = $val['index_name'];
			}
			if(isinarray($sOldTableName . '_pkey',$indexes))
			{
				$oProc->m_odb->query("DROP INDEX " . $sOldTableName . "_pkey",__LINE__,__FILE__);
			}

			return !!($oProc->m_odb->query("ALTER TABLE $sOldTableName RENAME TO $sNewTableName"));
		}

		function RenameColumn($oProc, &$aTables, $sTableName, $sOldColumnName, $sNewColumnName, $bCopyData = true)
		{
			// This really needs testing - it can affect primary keys, and other table-related objects
			// like sequences and such
			if ($bCopyData)
			{
				$oProc->m_odb->query("SELECT * INTO $sTableName" . "_tmp FROM $sTableName");
			}

			$this->DropTable($oProc, $aTables, $sTableName);

			if (!$bCopyData)
			{
				return $this->CreateTable($oProc, $aTables, $sTableName, $oProc->m_aTables[$sTableName], false);
			}

			$this->CreateTable($oProc, $aTables, $sTableName, $aTables[$sTableName], True);
			$this->_GetColumns($oProc, $sTableName . "_tmp", $sColumns);
			$query = "INSERT INTO $sTableName SELECT $sColumns FROM $sTableName" . "_tmp";

			$bRet = !!($oProc->m_odb->query($query));
			return ($bRet && $this->DropTable($oProc, $aTables, $sTableName . "_tmp"));
		}

		function AlterColumn($oProc, &$aTables, $sTableName, $sColumnName, &$aColumnDef, $bCopyData = true)
		{
			if ($bCopyData)
			{
				$oProc->m_odb->query("SELECT * INTO $sTableName" . "_tmp FROM $sTableName");
			}

			$this->DropTable($oProc, $aTables, $sTableName);

			if (!$bCopyData)
			{
				return $this->CreateTable($oProc, $aTables, $sTableName, $aTables[$sTableName], True);
			}

			$this->CreateTable($oProc, $aTables, $sTableName, $aTables[$sTableName], True);
			$this->_GetColumns($oProc, $sTableName . "_tmp", $sColumns, '', $sColumnName, $aColumnDef['type'] == 'auto' ? 'int4' : $aColumnDef['type']);

			// TODO: analyze the type of change and determine if this is used or _CopyAlteredTable
			// this is a performance consideration only, _CopyAlteredTable should be safe
			//$query = "INSERT INTO $sTableName SELECT $sColumns FROM $sTableName" . "_tmp";
			//$bRet = !!($oProc->m_odb->query($query));

			$bRet = $this->_CopyAlteredTable($oProc, $aTables, $sTableName . '_tmp', $sTableName);

			return ($bRet && $this->DropTable($oProc, $aTables, $sTableName . "_tmp"));
		}

		function AddColumn($oProc, &$aTables, $sTableName, $sColumnName, &$aColumnDef)
		{
			$oProc->_GetFieldSQL($aColumnDef, $sFieldSQL);
			$query = "ALTER TABLE $sTableName ADD COLUMN $sColumnName $sFieldSQL";

			return !!($oProc->m_odb->query($query));
		}

		function GetSequenceSQL($sTableName, &$sSequenceSQL)
		{
			$sSequenceSQL = sprintf("CREATE SEQUENCE seq_%s", $sTableName);
			return true;
		}

		function CreateTable($oProc, $aTables, $sTableName, $aTableDef, $bCreateSequence = true)
		{
			global $DEBUG;
			if ($oProc->_GetTableSQL($sTableName, $aTableDef, $sTableSQL, $sSequenceSQL))
			{
				// create sequence first since it will be needed for default
				if ($bCreateSequence && $sSequenceSQL != '')
				{
					if ($DEBUG) { echo '<br>Making sequence using: ' . $sSequenceSQL; }
					$oProc->m_odb->query($sSequenceSQL);
				}

				$query = "CREATE TABLE $sTableName ($sTableSQL)";

				return !!($oProc->m_odb->query($query));
			}

			return false;
		}
	}
?>
