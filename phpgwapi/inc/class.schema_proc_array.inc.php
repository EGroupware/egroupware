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

	class schema_proc_array
	{
		var $m_sStatementTerminator;

		function schema_proc_array()
		{
			$this->m_sStatementTerminator = ';';
		}

		/* Return a type suitable for DDL abstracted array */
		function TranslateType($sType, $iPrecision = 0, $iScale = 0, &$sTranslated)
		{
			$sTranslated = $sType;
			return (strlen($sTranslated) > 0);
		}

		function TranslateDefault($sDefault)
		{
			return $sDefault;
		}

		function GetPKSQL($sFields)
		{
			return '';
		}

		function GetUCSQL($sFields)
		{
			return '';
		}

		function _GetColumns($oProc, &$aTables, $sTableName, &$sColumns, $sDropColumn='')
		{
			$sColumns = '';
			while(list($sName, $aJunk) = each($aTables[$sTableName]['fd']))
			{
				if($sColumns != '')
				{
					$sColumns .= ',';
				}
				$sColumns .= $sName;
			}

			return True;
		}

		function DropTable($oProc, &$aTables, $sTableName)
		{
			if(isset($aTables[$sTableName]))
			{
				unset($aTables[$sTableName]);
			}
			
			return True;
		}

		function DropColumn($oProc, &$aTables, $sTableName, $aNewTableDef, $sColumnName, $bCopyData=True)
		{
			if(isset($aTables[$sTableName]))
			{
				if(isset($aTables[$sTableName]['fd'][$sColumnName]))
				{
					unset($aTables[$sTableName]['fd'][$sColumnName]);
				}
			}

			return True;
		}

		function RenameTable($oProc, &$aTables, $sOldTableName, $sNewTableName)
		{
			$aNewTables = array();
			while(list($sTableName, $aTableDef) = each($aTables))
			{
				if($sTableName == $sOldTableName)
				{
					$aNewTables[$sNewTableName] = $aTableDef;
				}
				else
				{
					$aNewTables[$sTableName] = $aTableDef;
				}
			}

			$aTables = $aNewTables;

			return True;
		}

		function RenameColumn($oProc, &$aTables, $sTableName, $sOldColumnName, $sNewColumnName, $bCopyData=True)
		{
			if (isset($aTables[$sTableName]))
			{
				$aNewTableDef = array();
				reset($aTables[$sTableName]['fd']);
				while(list($sColumnName, $aColumnDef) = each($aTables[$sTableName]['fd']))
				{
					if($sColumnName == $sOldColumnName)
					{
						$aNewTableDef[$sNewColumnName] = $aColumnDef;
					}
					else
					{
						$aNewTableDef[$sColumnName] = $aColumnDef;
					}
				}

				$aTables[$sTableName]['fd'] = $aNewTableDef;

				reset($aTables[$sTableName]['pk']);
				while(list($key, $sColumnName) = each($aTables[$sTableName]['pk']))
				{
					if($sColumnName == $sOldColumnName)
					{
						$aTables[$sTableName]['pk'][$key] = $sNewColumnName;
					}
				}

				reset($aTables[$sTableName]['uc']);
				while(list($key, $sColumnName) = each($aTables[$sTableName]['uc']))
				{
					if($sColumnName == $sOldColumnName)
					{
						$aTables[$sTableName]['uc'][$key] = $sNewColumnName;
					}
				}
			}

			return True;
		}

		function AlterColumn($oProc, &$aTables, $sTableName, $sColumnName, &$aColumnDef, $bCopyData=True)
		{
			if(isset($aTables[$sTableName]))
			{
				if(isset($aTables[$sTableName]['fd'][$sColumnName]))
				{
					$aTables[$sTableName]['fd'][$sColumnName] = $aColumnDef;
				}
			}

			return Rrue;
		}

		function AddColumn($oProc, &$aTables, $sTableName, $sColumnName, &$aColumnDef)
		{
			if(isset($aTables[$sTableName]))
			{
				if(!isset($aTables[$sTableName]['fd'][$sColumnName]))
				{
					$aTables[$sTableName]['fd'][$sColumnName] = $aColumnDef;
				}
			}

			return True;
		}

		function CreateTable($oProc, &$aTables, $sTableName, $aTableDef)
		{
			if(!isset($aTables[$sTableName]))
			{
				$aTables[$sTableName] = $aTableDef;
			}

			return True;
		}	
	}
?>
