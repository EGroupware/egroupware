<?php
   define('FPDF_FONTPATH',EGW_INCLUDE_ROOT."/phpgwapi/inc/fpdf/font/");
   require_once( EGW_INCLUDE_ROOT."/phpgwapi/inc/fpdf/fpdf.php" );
   require_once( EGW_INCLUDE_ROOT."/phpgwapi/inc/fpdf/pdml.php" );

   /**
    * pdmlwrapper implements the Portable Document Markup Language in the API
    * 
    * @uses PDML
	* @package api
    * @version $Id$
    * @author Pim Snel <pim-AT-lingewoud-DOT-nl> 
    * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	* @link http://pdml.sourceforge.net PDML documentation
    */
	class pdmlwrapper extends PDML
	{}
?>
