<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  * This file written by Michael Dean<mdean@users.sourceforge.net>           *
  *  and Miles Lott<milosch@phpgroupware.org>                                *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class schema_proc
	{
		var $m_oTranslator;
		var $m_oDeltaProc;
		var $m_odb;
		var $m_aTables;
		var $m_bDeltaOnly;

		function schema_proc($dbms)
		{
			$this->sType = $dbms;
			$this->m_oTranslator = CreateObject('phpgwapi.schema_proc_' . $dbms);
			$this->m_oDeltaProc = CreateObject('phpgwapi.schema_proc_array');
			$this->m_aTables = array();
			$this->m_bDeltaOnly = False; // Default to false here in case it's just a CreateTable script
		}

		function GenerateScripts($aTables, $bOutputHTML=False)
		{
			if (!is_array($aTables))
			{
				return False;
			}
			$this->m_aTables = $aTables;

			$sAllTableSQL = '';
			foreach ($this->m_aTables as $sTableName => $aTableDef)
			{
				$sSequenceSQL = '';
				$append_ix = False;
				if($this->_GetTableSQL($sTableName, $aTableDef, $sTableSQL, $sSequenceSQL,$append_ix))
				{
					if($append_ix)
					{
						$sTableSQL = "CREATE TABLE $sTableName (\n$sTableSQL\n"
							. $this->m_oTranslator->m_sStatementTerminator;
					}
					else
					{
						$sTableSQL = "CREATE TABLE $sTableName (\n$sTableSQL\n)"
							. $this->m_oTranslator->m_sStatementTerminator;
					}
					if($sSequenceSQL != '')
					{
						$sAllTableSQL .= $sSequenceSQL . "\n";
					}
					$sAllTableSQL .= $sTableSQL . "\n\n";
				}
				else
				{
					if($bOutputHTML)
					{
						print('<br>Failed generating script for <b>' . $sTableName . '</b><br>');
						echo '<pre style="text-align: left;">'.$sTableName.' = '; print_r($aTableDef); echo "</pre>\n";
					}

					return false;
				}
			}

			if($bOutputHTML)
			{
				print('<pre>' . $sAllTableSQL . '</pre><br><br>');
			}

			return True;
		}

		function ExecuteScripts($aTables, $bOutputHTML=False)
		{
			if(!is_array($aTables) || !IsSet($this->m_odb))
			{
				return False;
			}

			reset($aTables);
			$this->m_aTables = $aTables;

			while(list($sTableName, $aTableDef) = each($aTables))
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

		function DropAllTables($aTables, $bOutputHTML=False)
		{
			if(!is_array($aTables) || !isset($this->m_odb))
			{
				return False;
			}

			$this->m_aTables = $aTables;

			reset($this->m_aTables);
			while(list($sTableName, $aTableDef) = each($this->m_aTables))
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

		function DropTable($sTableName)
		{
			$retVal = $this->m_oDeltaProc->DropTable($this, $this->m_aTables, $sTableName);
			if($this->m_bDeltaOnly)
			{
				return $retVal;
			}

			return $retVal && $this->m_oTranslator->DropTable($this, $this->m_aTables, $sTableName);
		}

		function DropColumn($sTableName, $aTableDef, $sColumnName, $bCopyData = true)
		{
			$retVal = $this->m_oDeltaProc->DropColumn($this, $this->m_aTables, $sTableName, $aTableDef, $sColumnName, $bCopyData);
			if($this->m_bDeltaOnly)
			{
				return $retVal;
			}

			return $retVal && $this->m_oTranslator->DropColumn($this, $this->m_aTables, $sTableName, $aTableDef, $sColumnName, $bCopyData);
		}

		function RenameTable($sOldTableName, $sNewTableName)
		{
			$retVal = $this->m_oDeltaProc->RenameTable($this, $this->m_aTables, $sOldTableName, $sNewTableName);
			if($this->m_bDeltaOnly)
			{
				return $retVal;
			}

			return $retVal && $this->m_oTranslator->RenameTable($this, $this->m_aTables, $sOldTableName, $sNewTableName);
		}

		function RenameColumn($sTableName, $sOldColumnName, $sNewColumnName, $bCopyData=True)
		{
			$retVal = $this->m_oDeltaProc->RenameColumn($this, $this->m_aTables, $sTableName, $sOldColumnName, $sNewColumnName, $bCopyData);
			if($this->m_bDeltaOnly)
			{
				return $retVal;
			}

			return $retVal && $this->m_oTranslator->RenameColumn($this, $this->m_aTables, $sTableName, $sOldColumnName, $sNewColumnName, $bCopyData);
		}

		function AlterColumn($sTableName, $sColumnName, $aColumnDef, $bCopyData=True)
		{
			$retVal = $this->m_oDeltaProc->AlterColumn($this, $this->m_aTables, $sTableName, $sColumnName, $aColumnDef, $bCopyData);
			if($this->m_bDeltaOnly)
			{
				return $retVal;
			}

			return $retVal && $this->m_oTranslator->AlterColumn($this, $this->m_aTables, $sTableName, $sColumnName, $aColumnDef, $bCopyData);
		}

		function AddColumn($sTableName, $sColumnName, $aColumnDef)
		{
			$retVal = $this->m_oDeltaProc->AddColumn($this, $this->m_aTables, $sTableName, $sColumnName, $aColumnDef);
			if($this->m_bDeltaOnly)
			{
				return $retVal;
			}

			return $retVal && $this->m_oTranslator->AddColumn($this, $this->m_aTables, $sTableName, $sColumnName, $aColumnDef);
		}

		function CreateTable($sTableName, $aTableDef)
		{
			$retVal = $this->m_oDeltaProc->CreateTable($this, $this->m_aTables, $sTableName, $aTableDef);
			if($this->m_bDeltaOnly)
			{
				return $retVal;
			}

			return $retVal && $this->m_oTranslator->CreateTable($this, $this->m_aTables, $sTableName, $aTableDef);
		}

		// This function manually re-created the table incl. primary key and all other indices
		// It is meant to use if the primary key, existing indices or column-order changes or
		// columns are not longer used or new columns need to be created (with there default value or NULL)
		// Beside the default-value in the schema, one can give extra defaults via $aDefaults to eg. use an
		// other colum or function to set the value of a new or changed column
		function RefreshTable($sTableName, $aTableDef, $aDefaults=False)
		{
			if($GLOBALS['DEBUG']) { echo "<p>schema_proc::RefreshTable('$sTableName',<pre>".print_r($aTableDef,True).")\n\nm_aTables[$sTableName]=".print_r($this->m_aTables[$sTableName],True)."</pre>\n"; }
			$old_fd = $this->m_aTables[$sTableName]['fd'];

			$Ok = $this->m_oDeltaProc->RefreshTable($this, $this->m_aTables, $sTableName, $aTableDef);
			if(!$Ok || $this->m_bDeltaOnly)
			{
				return $Ok;	// nothing else to do
			}
			$tmp_name = 'tmp_'.$sTableName;
			$this->m_odb->transaction_begin();

			$select = array();
			foreach($aTableDef['fd'] as $name => $data)
			{
				if ($aDefaults && isset($aDefaults[$name]))	// use given default
				{
					$select[] = $aDefaults[$name];
				}
				elseif (isset($old_fd[$name]))	// existing column, use its value => column-name in query
				{
					$select[] = $name;
				}
				else	// new column => use default value or NULL
				{
					if (!isset($data['default']) && (!isset($data['nullable']) || $data['nullable']))
					{
						$select[] = 'NULL';
					}
					else
					{
						$select[] = $this->m_odb->quote(isset($data['default']) ? $data['default'] : '',$data['type']);
					}
				}
			}
			$select = implode(',',$select);

			$Ok = $this->RenameTable($sTableName,$tmp_name) &&
				$this->CreateTable($sTableName,$aTableDef) &&
				$this->m_odb->query("INSERT INTO $sTableName SELECT $select FROM $tmp_name",__LINE__,__FILE__);

			if (!$Ok)
			{
				$this->m_odb->transaction_fail();
				return False;
			}
			$this->DropTable($tmp_name);
			$this->m_odb->transaction_commit();

			return True;
		}

		function f($value)
		{
			if($this->m_bDeltaOnly)
			{
				// Don't care, since we are processing deltas only
				return False;
			}

			return $this->m_odb->f($value);
		}

		function num_rows()
		{
			if($this->m_bDeltaOnly)
			{
				// If not False, we will cause while loops calling us to hang
				return False;
			}

			return $this->m_odb->num_rows();
		}

		function next_record()
		{
			if($this->m_bDeltaOnly)
			{
				// If not False, we will cause while loops calling us to hang
				return False;
			}

			return $this->m_odb->next_record();
		}

		function query($sQuery, $line='', $file='')
		{
			if($this->m_bDeltaOnly)
			{
				// Don't run this query, since we are processing deltas only
				return True;
			}

			return $this->m_odb->query($sQuery, $line, $file);
		}

		function _GetTableSQL($sTableName, $aTableDef, &$sTableSQL, &$sSequenceSQL,&$append_ix)
		{
			if(!is_array($aTableDef))
			{
				return False;
			}

			$sTableSQL = '';
			reset($aTableDef['fd']);
			while(list($sFieldName, $aFieldAttr) = each($aTableDef['fd']))
			{
				$sFieldSQL = '';
				if($this->_GetFieldSQL($aFieldAttr, $sFieldSQL))
				{
					if($sTableSQL != '')
					{
						$sTableSQL .= ",\n";
					}

					$sTableSQL .= "$sFieldName $sFieldSQL";

					if($aFieldAttr['type'] == 'auto')
					{
						$this->m_oTranslator->GetSequenceSQL($sTableName, $sSequenceSQL);
						if($sSequenceSQL != '')
						{
							$sTableSQL .= sprintf(" DEFAULT nextval('seq_%s')", $sTableName);
						}
					}
				}
				else
				{
					if($GLOBALS['DEBUG']) { echo 'GetFieldSQL failed for ' . $sFieldName; }
					return False;
				}
			}

			$sUCSQL = '';
			$sPKSQL = '';
			$sIXSQL = '';

			if(count($aTableDef['pk']) > 0)
			{
				if(!$this->_GetPK($aTableDef['pk'], $sPKSQL))
				{
					if($bOutputHTML)
					{
						print('<br>Failed getting primary key<br>');
					}

					return False;
				}
			}

			if(count($aTableDef['uc']) > 0)
			{
				if(!$this->_GetUC($aTableDef['uc'], $sUCSQL))
				{
					if($bOutputHTML)
					{
						print('<br>Failed getting unique constraint<br>');
					}

					return False;
				}
			}

			if(count($aTableDef['ix']) > 0)
			{
				$append_ix = False;
				if(!$this->_GetIX($aTableDef['ix'], $sIXSQL,$append_ix,$sTableName))
				{
					if($bOutputHTML)
					{
						print('<br>Failed getting index<br>');
					}

					return False;
				}
//				print('<br>HELLO!: ' .  $sIXSQL);
			}

			if($sPKSQL != '')
			{
				$sTableSQL .= ",\n" . $sPKSQL;
			}

			if($sUCSQL != '')
			{
				$sTableSQL .= ",\n" . $sUCSQL;
			}

			if($sIXSQL != '')
			{
				if($append_ix)
				{
					$sTableSQL .= ");\n" . $sIXSQL;
					//pg: CREATE INDEX test1_id_index ON test1 (id);
				}
				else
				{
					$sTableSQL .= ",\n" . $sIXSQL;
				}
			}

			return True;
		}

		// Get field DDL
		function _GetFieldSQL($aField, &$sFieldSQL)
		{
			if($GLOBALS['DEBUG']) { echo'<br>_GetFieldSQL(): Incoming ARRAY: '; var_dump($aField); }
			if(!is_array($aField))
			{
				return false;
			}

			$sType = '';
			$iPrecision = 0;
			$iScale = 0;
			$bNullable = true;

			reset($aField);
			while(list($sAttr, $vAttrVal) = each($aField))
			{
				switch ($sAttr)
				{
					case 'type':
						$sType = $vAttrVal;
						break;
					case 'precision':
						$iPrecision = (int)$vAttrVal;
						break;
					case 'scale':
						$iScale = (int)$vAttrVal;
						break;
					case 'nullable':
						$bNullable = $vAttrVal;
						break;
					default:
						break;
				}
			}

			// Translate the type for the DBMS
			if($sFieldSQL = $this->m_oTranslator->TranslateType($sType, $iPrecision, $iScale))
			{
				if(!$bNullable)
				{
					if(strpos(strtolower($sFieldSQL),' not null')===false)
					{
						$sFieldSQL .= ' NOT NULL';
					}
				}

				if(isset($aField['default']))
				{
					if($GLOBALS['DEBUG']) { echo'<br>_GetFieldSQL(): Calling TranslateDefault for "' . $aField['default'] . '"'; }
					// Get default DDL - useful for differences in date defaults (eg, now() vs. getdate())
					$sTranslatedDefault = $aField['default'] == '0' ? $aField['default'] : $this->m_oTranslator->TranslateDefault($aField['default']);
					$sFieldSQL .= " DEFAULT '$sTranslatedDefault'";
				}
				if($GLOBALS['DEBUG']) { echo'<br>_GetFieldSQL(): Outgoing SQL:   ' . $sFieldSQL; }
				return true;
			}

			if($GLOBALS['DEBUG']) { echo '<br>Failed to translate field: type[' . $sType . '] precision[' . $iPrecision . '] scale[' . $iScale . ']<br>'; }

			return False;
		}

		function _GetPK($aFields, &$sPKSQL)
		{
			$sPKSQL = '';
			if(count($aFields) < 1)
			{
				return True;
			}

			$sPKSQL = $this->m_oTranslator->GetPKSQL(implode(',',$aFields));

			return True;
		}

		function _GetUC($aFields, &$sUCSQL)
		{
			$sUCSQL = '';
			if(count($aFields) < 1)
			{
				return True;
			}
			foreach($aFields as $mFields)
			{
				$aUCSQL[] = $this->m_oTranslator->GetUCSQL(
					is_array($mFields) ? implode(',',$mFields) : $mFields);
			}
			$sUCSQL = implode(",\n",$aUCSQL);

			return True;
		}

		function _GetIX($aFields, &$sIXSQL, &$append, $sTableName)
		{
			$sUCSQL = '';
			if(count($aFields) < 1)
			{
				return True;
			}
			foreach($aFields as $mFields)
			{
				$options = False;
				if (is_array($mFields))
				{
					if (isset($mFields['options']))		// array sets additional options
					{
						$options = @$mFields['options'][$this->sType];	// db-specific options, eg. index-type
						unset($mFields['options']);
					}
					$mFields = implode(',',$mFields);
				}
				$aIXSQL[] = $this->m_oTranslator->GetIXSQL($mFields,$append,$options,$sTableName);
			}
			if($append)
			{
				$sIXSQL = implode("\n",$aIXSQL);
			}
			else
			{
				$sIXSQL = implode(",\n",$aIXSQL);
			}

			return True;
		}
	}
?>
