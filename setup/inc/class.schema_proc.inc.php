<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
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
			if(@is_object($GLOBALS['phpgw']))
			{
				/* this allows usage from within phpgw */
				$basedir = PHPGW_SERVER_ROOT . '/setup/';
			}
			else
			{
				$basedir = './';
			}

			include($basedir . 'inc/class.schema_proc_' . $dbms . '.inc.php');
			eval("\$this->m_oTranslator = new schema_proc_$dbms;");

			include($basedir . 'inc/class.schema_proc_array.inc.php');

			$this->m_oDeltaProc = new schema_proc_array;
			$this->m_aTables = array();
			$this->m_bDeltaOnly = False; // Default to false here in case it's just a CreateTable script
		}

		function GenerateScripts($aTables, $bOutputHTML = false)
		{
			if (!is_array($aTables))
			{
				return false;
			}

			$this->m_aTables = $aTables;

			reset($this->m_aTables);
			$sAllTableSQL = '';
			while (list($sTableName, $aTableDef) = each($this->m_aTables))
			{
				$sSequenceSQL = '';
				if ($this->_GetTableSQL($sTableName, $aTableDef, $sTableSQL, $sSequenceSQL))
				{
					$sTableSQL = "CREATE TABLE $sTableName (\n$sTableSQL\n)"
						. $this->m_oTranslator->m_sStatementTerminator;
					if ($sSequenceSQL != "")
					{
						$sAllTableSQL .= $sSequenceSQL . "\n";
					}
					$sAllTableSQL .= $sTableSQL . "\n\n";
				}
				else
				{
					if ($bOutputHTML)
					{
						print('<br>Failed generating script for <b>' . $sTableName . '</b><br>');
					}

					return false;
				}
			}

			if ($bOutputHTML)
			{
				print('<pre>' . $sAllTableSQL . '</pre><br><br>');
			}

			return true;
		}

		function ExecuteScripts($aTables, $bOutputHTML = false)
		{
			if (!is_array($aTables) || !IsSet($this->m_odb))
			{
				return false;
			}

			reset($aTables);
			$this->m_aTables = $aTables;

			while (list($sTableName, $aTableDef) = each($aTables))
			{
				if ($this->CreateTable($sTableName, $aTableDef))
				{
					if ($bOutputHTML)
					{
						echo '<br>Create Table <b>' . $sTableName . '</b>';
					}
				}
				else
				{
					if ($bOutputHTML)
					{
						echo '<br>Create Table Failed For <b>' . $sTableName . '</b>';
					}

					return false;
				}
			}

			return true;
		}

		function DropAllTables($aTables, $bOutputHTML = false)
		{
			if (!is_array($aTables) || !IsSet($this->m_odb))
			{
				return false;
			}

			$this->m_aTables = $aTables;

			reset($this->m_aTables);
			while (list($sTableName, $aTableDef) = each($this->m_aTables))
			{
				if ($this->DropTable($sTableName))
				{
					if ($bOutputHTML)
					{
						echo '<br>Drop Table <b>' . $sTableSQL . '</b>';
					}
				}
				else
				{
					return false;
				}
			}

			return true;
		}

		function DropTable($sTableName)
		{
			$retVal = $this->m_oDeltaProc->DropTable($this, $this->m_aTables, $sTableName);
			if ($this->m_bDeltaOnly)
			{
				return $retVal;
			}

			return $retVal && $this->m_oTranslator->DropTable($this, $this->m_aTables, $sTableName);
		}

		function DropColumn($sTableName, $aTableDef, $sColumnName, $bCopyData = true)
		{
			$retVal = $this->m_oDeltaProc->DropColumn($this, $this->m_aTables, $sTableName, $aTableDef, $sColumnName, $bCopyData);
			if ($this->m_bDeltaOnly)
			{
				return $retVal;
			}

			return $retVal && $this->m_oTranslator->DropColumn($this, $this->m_aTables, $sTableName, $aTableDef, $sColumnName, $bCopyData);
		}

		function RenameTable($sOldTableName, $sNewTableName)
		{
			$retVal = $this->m_oDeltaProc->RenameTable($this, $this->m_aTables, $sOldTableName, $sNewTableName);
			if ($this->m_bDeltaOnly)
			{
				return $retVal;
			}

			return $retVal && $this->m_oTranslator->RenameTable($this, $this->m_aTables, $sOldTableName, $sNewTableName);
		}

		function RenameColumn($sTableName, $sOldColumnName, $sNewColumnName, $bCopyData = true)
		{
			$retVal = $this->m_oDeltaProc->RenameColumn($this, $this->m_aTables, $sTableName, $sOldColumnName, $sNewColumnName, $bCopyData);
			if ($this->m_bDeltaOnly)
			{
				return $retVal;
			}

			return $retVal && $this->m_oTranslator->RenameColumn($this, $this->m_aTables, $sTableName, $sOldColumnName, $sNewColumnName, $bCopyData);
		}

		function AlterColumn($sTableName, $sColumnName, $aColumnDef, $bCopyData = true)
		{
			$retVal = $this->m_oDeltaProc->AlterColumn($this, $this->m_aTables, $sTableName, $sColumnName, $aColumnDef, $bCopyData);
			if ($this->m_bDeltaOnly)
			{
				return $retVal;
			}

			return $retVal && $this->m_oTranslator->AlterColumn($this, $this->m_aTables, $sTableName, $sColumnName, $aColumnDef, $bCopyData);
		}

		function AddColumn($sTableName, $sColumnName, $aColumnDef)
		{
			$retVal = $this->m_oDeltaProc->AddColumn($this, $this->m_aTables, $sTableName, $sColumnName, $aColumnDef);
			if ($this->m_bDeltaOnly)
			{
				return $retVal;
			}

			return $retVal && $this->m_oTranslator->AddColumn($this, $this->m_aTables, $sTableName, $sColumnName, $aColumnDef);
		}

		function CreateTable($sTableName, $aTableDef)
		{
			$retVal = $this->m_oDeltaProc->CreateTable($this, $this->m_aTables, $sTableName, $aTableDef);
			if ($this->m_bDeltaOnly)
			{
				return $retVal;
			}

			return $retVal && $this->m_oTranslator->CreateTable($this, $this->m_aTables, $sTableName, $aTableDef);
		}

		function f($value)
		{
			if ($this->m_bDeltaOnly)
			{
				// Don't care, since we are processing deltas only
				return False;
			}

			return $this->m_odb->f($value);
		}

		function num_rows()
		{
			if ($this->m_bDeltaOnly)
			{
				// If not False, we will cause while loops calling us to hang
				return False;
			}

			return $this->m_odb->num_rows();
		}

		function next_record()
		{
			if ($this->m_bDeltaOnly)
			{
				// If not False, we will cause while loops calling us to hang
				return False;
			}

			return $this->m_odb->next_record();
		}

		function query($sQuery, $line = '', $file = '')
		{
			if ($this->m_bDeltaOnly)
			{
				// Don't run this query, since we are processing deltas only
				return True;
			}

			return $this->m_odb->query($sQuery, $line, $file);
		}

		function _GetTableSQL($sTableName, $aTableDef, &$sTableSQL, &$sSequenceSQL)
		{
			global $DEBUG;

			if (!is_array($aTableDef))
			{
				return false;
			}

			$sTableSQL = '';
			reset($aTableDef['fd']);
			while (list($sFieldName, $aFieldAttr) = each($aTableDef['fd']))
			{
				$sFieldSQL = '';
				if ($this->_GetFieldSQL($aFieldAttr, $sFieldSQL))
				{
					if ($sTableSQL != '')
					{
						$sTableSQL .= ",\n";
					}

					$sTableSQL .= "$sFieldName $sFieldSQL";

					if ($aFieldAttr['type'] == 'auto')
					{
						$this->m_oTranslator->GetSequenceSQL($sTableName, $sSequenceSQL);
						if ($sSequenceSQL != '')
						{
							$sTableSQL .= sprintf(" DEFAULT nextval('seq_%s')", $sTableName);
						}
					}
				}
				else
				{
					if ($DEBUG) { echo 'GetFieldSQL failed for ' . $sFieldName; }
					return false;
				}
			}

			$sUCSQL = '';
			$sPKSQL = '';

			if (count($aTableDef['pk']) > 0)
			{
				if (!$this->_GetPK($aTableDef['pk'], $sPKSQL))
				{
					if ($bOutputHTML)
					{
						print('<br>Failed getting primary key<br>');
					}

					return false;
				}
			}

			if (count($aTableDef['uc']) > 0)
			{
				if (!$this->_GetUC($aTableDef['uc'], $sUCSQL))
				{
					if ($bOutputHTML)
					{
						print('<br>Failed getting unique constraint<br>');
					}

					return false;
				}
			}

			if ($sPKSQL != '')
			{
				$sTableSQL .= ",\n" . $sPKSQL;
			}

			if ($sUCSQL != '')
			{
				$sTableSQL .= ",\n" . $sUCSQL;
			}

			return true;
		}

		// Get field DDL
		function _GetFieldSQL($aField, &$sFieldSQL)
		{
			global $DEBUG;
			if ($DEBUG) { echo'<br>_GetFieldSQL(): Incoming ARRAY: '; var_dump($aField); }
			if (!is_array($aField))
			{
				return false;
			}

			$sType = '';
			$iPrecision = 0;
			$iScale = 0;
			$sDefault = '';
			$bNullable = true;

			reset($aField);
			while (list($sAttr, $vAttrVal) = each($aField))
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
					case 'default':
						$sDefault = $vAttrVal;
						if ($DEBUG) { echo'<br>_GetFieldSQL(): Default="' . $sDefault . '"'; }
						break;
					case 'nullable':
						$bNullable = $vAttrVal;
						break;
				}
			}

			// Translate the type for the DBMS
			if ($this->m_oTranslator->TranslateType($sType, $iPrecision, $iScale, $sFieldSQL))
			{
				if ($bNullable == false)
				{
					$sFieldSQL .= ' NOT NULL';
				}

				if ($sDefault != '')
				{
					if ($DEBUG) { echo'<br>_GetFieldSQL(): Calling TranslateDefault for "' . $sDefault . '"'; }
					// Get default DDL - useful for differences in date defaults (eg, now() vs. getdate())
					$sTranslatedDefault = $this->m_oTranslator->TranslateDefault($sDefault);
					$sFieldSQL .= " DEFAULT '$sTranslatedDefault'";
				}
				elseif($sDefault == '0')
				{
					$sFieldSQL .= " DEFAULT '0'";
				}
				if ($DEBUG) { echo'<br>_GetFieldSQL(): Outgoing SQL:   ' . $sFieldSQL; }
				return true;
			}

			if ($DEBUG) { echo '<br>Failed to translate field: type[' . $sType . '] precision[' . $iPrecision . '] scale[' . $iScale . ']<br>'; }

			return false;
		}

		function _GetPK($aFields, &$sPKSQL)
		{
			$sPKSQL = '';
			if (count($aFields) < 1)
			{
				return true;
			}

			$sFields = '';
			reset($aFields);
			while (list($key, $sField) = each($aFields))
			{
				if ($sFields != '')
				{
					$sFields .= ',';
				}
				$sFields .= $sField;
			}

			$sPKSQL = $this->m_oTranslator->GetPKSQL($sFields);

			return true;
		}

		function _GetUC($aFields, &$sUCSQL)
		{
			$sUCSQL = '';
			if (count($aFields) < 1)
			{
				return true;
			}

			$sFields = '';
			reset($aFields);
			while (list($key, $sField) = each($aFields))
			{
				if ($sFields != '')
				{
					$sFields .= ',';
				}
				$sFields .= $sField;
			}

			$sUCSQL = $this->m_oTranslator->GetUCSQL($sFields);

			return true;
		}
	}
?>
