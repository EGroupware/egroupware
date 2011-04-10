<?php
	/***************************************************************************\
	* EGroupWare - pdf creation class                                           *
	* http://www.linux-at-work.de                                               *
	* http://www.phpgw.de                                                       *
	* http://www.egroupware.org                                                 *
	* Written by : Lars Kneschke [lkneschke@linux-at-work.de]                   *
	* -------------------------------------------------                         *
	* Copyright (c) 2004, Lars Kneschke                                         *
	* All rights reserved.                                                      *
	*                                                                           *
	* Redistribution and use in source and binary forms, with or without        *
	* modification, are permitted provided that the following conditions are    *
	* met:                                                                      *
	*                                                                           *
	* * Redistributions of source code must retain the above copyright          *
	* notice, this list of conditions and the following disclaimer.             *
	* * Redistributions in binary form must reproduce the above copyright       *
	* notice, this list of conditions and the following disclaimer in the       *
	* documentation and/or other materials provided with the distribution.      *
	* * Neither the name of the FeLaMiMail organization nor the names of        *
	* its contributors may be used to endorse or promote products derived       *
	* from this software without specific prior written permission.             *
	*                                                                           *
	* THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS       *
	* "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED *
	* TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR*
	* PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR          *
	* CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,     *
	* EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,       *
	* PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR        *
	* PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF    *
	* LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING      *
	* NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS        *
	* SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.              *
	\***************************************************************************/

	/* $Id$ */

	define('FPDF_FONTPATH',PHPGW_SERVER_ROOT.'/phpgwapi/inc/fpdf/font/');
	require(PHPGW_SERVER_ROOT.'/phpgwapi/inc/fpdf/fpdf.php');

	/**
	 * wrapper class for FPDF
	 *
	 * @package phpgwapi
	 * @author Lars Kneschke
	 * @version 1.35
	 * @copyright Lars Kneschke 2004
	 * @license http://www.opensource.org/licenses/bsd-license.php BSD
	 */
	class pdf extends FPDF
	{
		function pdf()
		{
			parent::FPDF();
			$this->SetCreator('eGroupWare '.$GLOBALS['egw_info']['server']['versions']['phpgwapi']);
			$this->SetAuthor($GLOBALS['egw']->common->display_fullname());
		}

		//Page footer
		function Footer()
		{
			//Position at 1.5 cm from bottom
			$this->SetY(-15);
			//Arial italic 8
			$this->SetFont('Arial','I',8);
			//Page number
			$this->Cell(0,10,lang('Page').' '.$this->PageNo().'/{nb}',0,0,'C');
		}
	}
?>
