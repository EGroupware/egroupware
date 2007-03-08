<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @link http://www.egroupware.org
 * @author Knut Moeller <k.moeller@metaways.de>
 * @copyright Knut Moeller <k.moeller@metaways.de>
 * @version $Id:$
 */


/**
 *
 * Spreadsheet main class
 *
 *
 */
class SpreadSheetDocument {

	// TODO generalize...
	// TODO internalize counts

	const TEMPDIR = '/tmp';		// system temppath   		TODO: better solution?
	const ZIP = 'zip';				// system zip command		TODO: zip via php possible? no shellexec ?

	private $debug = 0;

	private $temppath; 				// temppath (to assemble zip file)
	private $tempzip;				// temp zip file

	private $handle;				// filehandle OO export file for importexport

	// xml nodes
	private $doc;
	private $styles;
	private $body;
	private $spreadsheet; 			// contains tables ....



	// File handling, INIT

	function __construct($_handle) {
		$this->temppath = $this->mk_tempdir(self::TEMPDIR, 'cmsliteexport');
		$this->tempzip = tempnam('/tmp','cmslite_export_outfile');
		$this->handle = $_handle;
		$this->initSpreadsheet();
	}


	function __destruct() {
		@unlink($this->tempzip);
		@unlink($this->tempzip . '.zip');
		$this->remove_directory($this->temppath.'/');
	}



	/**
	 *   assemble the zipped OpenOffice Document
	 *
	 */
	public function finalize() {

		@mkdir($this->temppath."/META-INF");
		@mkdir($this->temppath."/Thumbnails");
		@mkdir($this->temppath."/Configurations2");
		@mkdir($this->temppath."/Configurations2/statusbar");
		@mkdir($this->temppath."/Configurations2/accelerator");
		@touch($this->temppath."/Configurations2/accelerator/current.xml");
		@mkdir($this->temppath."/Configurations2/floater");
		@mkdir($this->temppath."/Configurations2/popupmenu");
		@mkdir($this->temppath."/Configurations2/progressbar");
		@mkdir($this->temppath."/Configurations2/menubar");
		@mkdir($this->temppath."/Configurations2/toolbar");
		@mkdir($this->temppath."/Configurations2/images");
		@mkdir($this->temppath."/Configurations2/images/Bitmaps");

		// main content
		$this->write( $this->doc->saveXML(), "content.xml");
		if ($this->debug > 0) error_log(print_r($this->doc->saveXML(),true));

		$this->createMimetype();
		$this->createManifest();
		$this->createMeta();

		// create zip
		shell_exec("cd $this->temppath; ".self::ZIP." -r $this->tempzip.zip * ");

		// copy to filehandle
		if ($this->handle) {
			$fh = fopen($this->tempzip.'.zip', "rb");
			if($fh) {
				while (!feof($fh)) {
			   		fwrite($this->handle, fread($fh, 8192));
			 	}
			}
			fclose($fh);
		}
		else error_log("output file error");
	}



	protected function createMeta() {

		//TODO generate
		$content = '<?xml version="1.0" encoding="UTF-8"?>
<office:document-meta xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0" xmlns:ooo="http://openoffice.org/2004/office" office:version="1.0">
<office:meta><meta:generator>OpenOffice.org/2.0$Linux OpenOffice.org_project/680m5$Build-9011</meta:generator>
<meta:creation-date>2005-01-18T12:05:29</meta:creation-date><dc:date>2006-10-25T10:35:55</dc:date><meta:print-date>2006-01-17T10:57:23</meta:print-date>
<dc:language>de-DE</dc:language><meta:editing-cycles>218</meta:editing-cycles><meta:editing-duration>P1DT9H3M54S</meta:editing-duration>
<meta:user-defined meta:name="Info 1"/><meta:user-defined meta:name="Info 2"/><meta:user-defined meta:name="Info 3"/>
<meta:user-defined meta:name="Info 4"/><meta:document-statistic meta:table-count="12" meta:cell-count="969"/></office:meta></office:document-meta>';
		$this->write($content,"meta.xml");
	}

	protected function createMimetype() {
		$content = 'application/vnd.oasis.opendocument.spreadsheet';
		$this->write($content,"mimetype");

	}

	protected function createManifest() {
		//TODO generate
$content = '<?xml version="1.0" encoding="UTF-8"?>
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0">
 <manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.spreadsheet" manifest:full-path="/"/>
 <manifest:file-entry manifest:media-type="" manifest:full-path="Configurations2/statusbar/"/>
 <manifest:file-entry manifest:media-type="" manifest:full-path="Configurations2/accelerator/current.xml"/>
 <manifest:file-entry manifest:media-type="" manifest:full-path="Configurations2/accelerator/"/>
 <manifest:file-entry manifest:media-type="" manifest:full-path="Configurations2/floater/"/>
 <manifest:file-entry manifest:media-type="" manifest:full-path="Configurations2/popupmenu/"/>
 <manifest:file-entry manifest:media-type="" manifest:full-path="Configurations2/progressbar/"/>
 <manifest:file-entry manifest:media-type="" manifest:full-path="Configurations2/menubar/"/>
 <manifest:file-entry manifest:media-type="" manifest:full-path="Configurations2/toolbar/"/>
 <manifest:file-entry manifest:media-type="" manifest:full-path="Configurations2/images/Bitmaps/"/>
 <manifest:file-entry manifest:media-type="" manifest:full-path="Configurations2/images/"/>
 <manifest:file-entry manifest:media-type="application/vnd.sun.xml.ui.configuration" manifest:full-path="Configurations2/"/>
 <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>
 <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/>
 <manifest:file-entry manifest:media-type="" manifest:full-path="Thumbnails/"/>
</manifest:manifest>';

		$this->write($content,"META-INF/manifest.xml");
	}


	protected function initSpreadsheet() {

		$this->doc = new DOMDocument ('1.0', 'UTF-8');
		$this->doc->formatOutput = true;

		$root = $this->doc->createElementNS('urn:oasis:names:tc:opendocument:xmlns:office:1.0',
								'office:document-content');
		$this->doc->appendChild($root);

		// define Namespaces....

		$namespaces = array(
			'style' => 'urn:oasis:names:tc:opendocument:xmlns:style:1.0',
			'text' => 'urn:oasis:names:tc:opendocument:xmlns:text:1.0',
			'table' => 'urn:oasis:names:tc:opendocument:xmlns:table:1.0',
			'draw' => 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0',
			'fo' => 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0',
			'xlink' => 'http://www.w3.org/1999/xlink',
			'dc' => 'http://purl.org/dc/elements/1.1/',
			'meta' => 'urn:oasis:names:tc:opendocument:xmlns:meta:1.0',
			'number' => 'urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0',
			'svg' => 'urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0',
			'chart' => 'urn:oasis:names:tc:opendocument:xmlns:chart:1.0',
			'dr3d' => 'urn:oasis:names:tc:opendocument:xmlns:dr3d:1.0',
			'math' => 'http://www.w3.org/1998/Math/MathML',
			'form' => 'urn:oasis:names:tc:opendocument:xmlns:form:1.0',
			'script' => 'urn:oasis:names:tc:opendocument:xmlns:script:1.0',
			'ooo' => 'http://openoffice.org/2004/office',
			'ooow' => 'http://openoffice.org/2004/writer',
			'oooc' => 'http://openoffice.org/2004/calc',
			'dom' => 'http://www.w3.org/2001/xml-events',
			'xforms' => 'http://www.w3.org/2002/xforms',
			'xsd' => 'http://www.w3.org/2001/XMLSchema',
			'xsi' => 'http://www.w3.org/2001/XMLSchema-instance');

		foreach ($namespaces as $name => $url) {
			$root->setAttributeNS("http://www.w3.org/2000/xmlns/", "xmlns:$name", $url);
		}
		$root->setAttribute('office:version', '1.0');


		// main parts

		$node = $root->appendChild( $this->doc->createElement('office:scripts') );
		$node = $root->appendChild( $this->doc->createElement('office:font-face-decls') );
		$this->styles = $root->appendChild( $this->doc->createElement('office:automatic-styles') );
		$this->body = $root->appendChild( $this->doc->createElement('office:body') );
		$this->spreadsheet = $this->body->appendChild( $this->doc->createElement('office:spreadsheet') );

		// styles

		$this->initTableStyles();
		$this->initColumnStyles();
		$this->initRowStyles();
		$this->initCellStyles();
		$this->initNumberStyles();
	}


	/**
	 * 	define the table styles
 	 */
	protected function initTableStyles() {
		$style = $this->styles->appendChild($this->doc->createElement('style:style'));
		$style->setAttribute('style:name', 'ta1');
		$style->setAttribute('style:family', 'table');
		$style->setAttribute('style:master-page-name', 'Default');
		$property = $style->appendChild($this->doc->createElement('style:table-properties'));
		$property->setAttribute('table:display', 'true');
		$property->setAttribute('style:writing-mode', 'lr-tb');
	}

	/**
	 * 	define the column styles
 	 */
	protected function initColumnStyles() {
		$this->addColumnStyle('co1');
	}

	public function addColumnStyle($_name, $_width='2.267cm') {
		$style = $this->styles->appendChild($this->doc->createElement('style:style'));
		$style->setAttribute('style:name', $_name);
		$style->setAttribute('style:family', 'table-column');
		$property = $style->appendChild($this->doc->createElement('style:table-column-properties'));
		$property->setAttribute('fo:break-before', 'auto');
		$property->setAttribute('style:column-width', $_width);
	}


	/**
	 * 	define the row styles
 	 */
	protected function initRowStyles() {

			// standard
		$style = $this->styles->appendChild($this->doc->createElement('style:style'));
		$style->setAttribute('style:name', 'ro1');
		$style->setAttribute('style:family', 'table-row');
		$property = $style->appendChild($this->doc->createElement('style:table-row-properties'));
		$property->setAttribute('style:row-height', '0.48cm');
		$property->setAttribute('fo:break-before', 'auto');
		$property->setAttribute('style:use-optimal-row-height', 'true');

			// double height
		$style = $this->styles->appendChild($this->doc->createElement('style:style'));
		$style->setAttribute('style:name', 'ro2');
		$style->setAttribute('style:family', 'table-row');
		$property = $style->appendChild($this->doc->createElement('style:table-row-properties'));
		$property->setAttribute('style:row-height', '1.053cm');
		$property->setAttribute('fo:break-before', 'auto');
		$property->setAttribute('style:use-optimal-row-height', 'false');

	}


	/**
	 * 	define the cell styles
 	 */
	protected function initCellStyles() {

			// default
		$style = $this->styles->appendChild($this->doc->createElement('style:style'));
		$style->setAttribute('style:name', 'ce1');
		$style->setAttribute('style:family', 'table-cell');
		$style->setAttribute('style:parent-style-name', 'Default');

		$property = $style->appendChild($this->doc->createElement('style:table-cell-properties'));
		$property->setAttribute('fo:border', 'none');
		$this->appendTextAttributes($style);

			// default bold
		$style = $this->styles->appendChild($this->doc->createElement('style:style'));
		$style->setAttribute('style:name', 'ce2');
		$style->setAttribute('style:family', 'table-cell');
		$style->setAttribute('style:parent-style-name', 'Default');

		$property = $style->appendChild($this->doc->createElement('style:table-cell-properties'));
		$property->setAttribute('fo:border', 'none');
		$this->appendTextAttributes($style, true);

			// default bold + border
		$style = $this->styles->appendChild($this->doc->createElement('style:style'));
		$style->setAttribute('style:name', 'ce3');
		$style->setAttribute('style:family', 'table-cell');
		$style->setAttribute('style:parent-style-name', 'Default');

		$property = $style->appendChild($this->doc->createElement('style:table-cell-properties'));
		$property->setAttribute('fo:border', '0.002cm solid #000000');
		$this->appendTextAttributes($style, true);



			// N2 decimal
		$style = $this->styles->appendChild($this->doc->createElement('style:style'));
		$style->setAttribute('style:name', 'ce101');
		$style->setAttribute('style:family', 'table-cell');
		$style->setAttribute('style:parent-style-name', 'Default');
		$style->setAttribute('style:data-style-name', 'N2');

		$property = $style->appendChild($this->doc->createElement('style:table-cell-properties'));
		$property->setAttribute('fo:border', 'none');
		$this->appendTextAttributes($style);


			// N0 decimal
		$style = $this->styles->appendChild($this->doc->createElement('style:style'));
		$style->setAttribute('style:name', 'ce100');
		$style->setAttribute('style:family', 'table-cell');
		$style->setAttribute('style:parent-style-name', 'Default');
		$style->setAttribute('style:data-style-name', 'N0');

		$property = $style->appendChild($this->doc->createElement('style:table-cell-properties'));
		$property->setAttribute('fo:border', 'none');
		$this->appendTextAttributes($style);


			// N3 decimal Sum
		$style = $this->styles->appendChild($this->doc->createElement('style:style'));
		$style->setAttribute('style:name', 'ce102');
		$style->setAttribute('style:family', 'table-cell');
		$style->setAttribute('style:parent-style-name', 'Default');
		$style->setAttribute('style:data-style-name', 'N2');

		$property = $style->appendChild($this->doc->createElement('style:table-cell-properties'));
		$property->setAttribute('fo:border', 'none');
		$this->appendTextAttributes($style, true);


			// N4 date
		$style = $this->styles->appendChild($this->doc->createElement('style:style'));
		$style->setAttribute('style:name', 'ce103');
		$style->setAttribute('style:family', 'table-cell');
		$style->setAttribute('style:parent-style-name', 'Default');
		$style->setAttribute('style:data-style-name', 'N4');

		$property = $style->appendChild($this->doc->createElement('style:table-cell-properties'));
		$property->setAttribute('fo:border', 'none');
		$this->appendTextAttributes($style);

	}


	protected function appendTextAttributes($_style, $_bold=false) {
		$property = $_style->appendChild($this->doc->createElement('style:text-properties'));
		$property->setAttribute('style:use-window-font-color', 'true' );
		$property->setAttribute('style:text-outline', 'false' );
		$property->setAttribute('style:text-line-through-style', 'none' );
		$property->setAttribute('style:font-name', 'Arial1' );
		$property->setAttribute('style:text-underline-style', 'none' );
		$property->setAttribute('style:font-size-asian', '10pt'  );
		$property->setAttribute('style:font-style-asian', 'normal' );
		$property->setAttribute('style:font-weight-asian', 'bold' );
		$property->setAttribute('style:font-size-complex', '10pt' );
		$property->setAttribute('style:font-style-complex', 'normal' );
		$property->setAttribute('style:font-weight-complex', 'bold' );
		$property->setAttribute('fo:font-size', '10pt'  );
		$property->setAttribute('fo:font-style', 'normal'  );
		$property->setAttribute('fo:text-shadow', 'none' );
		$property->setAttribute('fo:font-weight', ($_bold)?'bold':'normal' );
	}


	/**
	 * 	define the number styles
 	 */
	protected function initNumberStyles() {

			// N2 : decimal precision 2
		$style = $this->styles->appendChild($this->doc->createElement('number:number-style'));
		$style->setAttribute('style:name', 'N2');
		$property = $style->appendChild($this->doc->createElement('number:number'));
		$property->setAttribute('number:decimal-places', '2');
		$property->setAttribute('number:min-integer-digits', '1');
		$property->setAttribute('number:grouping', 'false');

			// N0 : plain integer N0
		$style = $this->styles->appendChild($this->doc->createElement('number:number-style'));
		$style->setAttribute('style:name', 'N0');
		$property = $style->appendChild($this->doc->createElement('number:number'));
		$property->setAttribute('number:decimal-places', '0');
		$property->setAttribute('number:min-integer-digits', '0');
		$property->setAttribute('number:grouping', 'false');

			// N4 : date
		$style = $this->styles->appendChild($this->doc->createElement('number:date-style'));
		$style->setAttribute('style:name', 'N4');
		$style->setAttribute('number:automatic-order', 'true');

		$property = $style->appendChild($this->doc->createElement('number:day'));
		$property->setAttribute('number:style', 'long');
		$property = $style->appendChild($this->doc->createElement('number:text', '.'));

		$property = $style->appendChild($this->doc->createElement('number:month'));
		$property->setAttribute('number:style', 'long');
		$property = $style->appendChild($this->doc->createElement('number:text', '.'));

		$property = $style->appendChild($this->doc->createElement('number:year'));
	}




	/* GETTER,SETTER FUNCTIONS */

	public function getDoc() {
		return $this->doc;
	}

	public function get() {
		return $this->spreadsheet;
	}

	public function toString() {
		return (is_object($this->doc)) ? $this->doc->saveXML() : 'empty' ;
	}



	/* HELPER FUNCTIONS */

	private function mk_tempdir($dir, $prefix='', $mode=0700) {
	   if (substr($dir, -1) != '/') $dir .= '/';

	   do {
	     $path = $dir.$prefix.mt_rand(0, 9999999);
	   } while (!mkdir($path, $mode));

	   return $path;
	}

	private function write($content, $file) {
		$fh = fopen ($this->temppath.'/'.$file, "w") or die("can't open tempfile ". $this->temppath.'/'.$file);
		fwrite($fh, $content) or die ("cannot write to tempfile ". $this->temppath.'/'.$file);
		fclose($fh);
	}

	private function remove_directory($dir) {
		   $dir_contents = scandir($dir);
		   foreach ($dir_contents as $item) {
		       if (is_dir($dir.$item) && $item != '.' && $item != '..') {
		           $this->remove_directory($dir.$item.'/');
		       }
		       elseif (file_exists($dir.$item) && $item != '.' && $item != '..') {
		           unlink($dir.$item);
		       }
		   }
		   rmdir($dir);
	}

} // class




/**
 *
 *
 *
 */
class SpreadSheetTable {

	private $doc;
	private $table;
	private $columns;


	function __construct($_spreadsheet, $_name) {
		$this->doc = $_spreadsheet->getDoc();
		$this->spreadsheet = $_spreadsheet->get();

		if (is_object($this->doc)) {
			$this->table = $this->spreadsheet->appendChild( $this->doc->createElement('table:table') );
			$this->table->setAttribute('table:name', $_name);
			$this->table->setAttribute('table:style-name', 'ta1');
			$this->table->setAttribute('table:print','false');
		}
		else return false;
	}

	/**
	 *
	 */
	public function get() {
		return $this->table;
	}


	/**
	 * add column
	 * @return colElement
	 */
	public function addColumn($_name='co1', $_cellstylename='ce1', $_repeat=1) {
		$col = $this->table->appendChild( $this->doc->createElement('table:table-column') );
		$col->setAttribute('table:style-name', $_name);
		$col->setAttribute('table:number-columns-repeated',$_repeat);
		$col->setAttribute('table:default-cell-style-name', $_cellstylename);

		// table:number-columns-repeated="2"
		return $col;
	}


	/**
	 * add row
	 * @return rowElement
	 * @param type: normal, double (height)
	 */
	public function addRow($_type='normal') {
		$row = $this->table->appendChild( $this->doc->createElement('table:table-row') );
		$row->setAttribute('table:style-name', ($_type=='normal') ? 'ro1' : 'ro2');
		return $row;
	}


	/**
	 * requires row element, adds one cell to it
	 * @return cellElement
	 */
	public function addCell( $row, $type, $value, $attributes=array() ) {
		$cell = $row->appendChild( $this->doc->createElement('table:table-cell') );
		cellType($this->doc, $cell, $type, $value, $attributes);
		return $cell;
	}


	/**
	 * requires row element, adds multiple cells
	 */
	public function addCells( $row, $type, $values,  $attributes=array() ) {
		if (!is_array($values)) {
			echo "array required";
			return false;
		}
		foreach ($values as $value) {
			$this->addCell($row, $type, $value, $attributes);
		}
	}

} // class




/**
 *
 * type factory
 *
 */
function cellType($_doc, $_cell, $_type, $_value, $_attributes=array()) {

	//TODO think about attribute dispatching <-> xml cell definition

	switch ($_type) {
		case 'float' 	:  return new SpreadSheetCellTypeFloat	($_doc, $_cell, $_value, $_attributes);
		case 'int' 	:  return new SpreadSheetCellTypeInt	($_doc, $_cell, $_value, $_attributes);
		case 'formula' 	:  return new SpreadSheetCellTypeFormula($_doc, $_cell, $_value, $_attributes);
		case 'date' 	:  return new SpreadSheetCellTypeDate	($_doc, $_cell, $_value, $_attributes);
		default		:  return new SpreadSheetCellTypeString ($_doc, $_cell, $_value, $_attributes);
	}
}


/**
 * string type cell
 */
class SpreadSheetCellTypeString {

	function __construct($doc, $cell, $value, $_attributes) {
		$cell->setAttribute('office:value-type', 'string');

		$name = 'ce1';
		if (array_search('bold', $_attributes) !== FALSE ) {
			$name = 'ce2';
			if (array_search('border', $_attributes) !== FALSE ) {
				$name = 'ce3';
			}
		}
		$cell->setAttribute('table:style-name', $name);

		$cell->appendChild( $doc->createElement('text:p', $value) );
	}
}


/**
 * float type cell
 */
class SpreadSheetCellTypeFloat {
	function __construct($doc, $cell, $value, $_attributes) {
		$cell->setAttribute('office:value-type', 'float');
		$cell->setAttribute('office:value', number_format($value, 2, '.', '') );
		$cell->setAttribute('table:style-name', 'ce101');
		$cell->appendChild( $doc->createElement('text:p', number_format($value, 2, ',', '')) );
	}
}


/**
 * int type cell
 */
class SpreadSheetCellTypeInt {
	function __construct($doc, $cell, $value, $_attributes) {
		$cell->setAttribute('office:value-type', 'float');
		$cell->setAttribute('office:value', number_format($value, 0, ',', ''));
		$cell->setAttribute('table:style-name', 'ce100');
		$cell->appendChild( $doc->createElement('text:p', number_format($value, 0, ',', '')) );
	}
}


/**
 * date type cell
 */
class SpreadSheetCellTypeDate {
	function __construct($doc, $cell, $value, $_attributes) {
		if (($timestamp = strtotime($value)) === false) {
		   echo "Wrong date ($value)";
		}
		else {
			$cell->setAttribute('office:value-type', 'date');
			$cell->setAttribute('office:date-value', strftime("%Y-%m-%d", $timestamp) );
			$cell->setAttribute('table:style-name', 'ce103');
			$cell->appendChild( $doc->createElement('text:p', strftime("%d.%m.%Y", $timestamp) ) );
		}
	}
}


/**
 * formula type cell
 */
class SpreadSheetCellTypeFormula {

	function __construct($doc, $cell, $value, $_attributes) {
		$cell->setAttribute('table:formula', 'oooc:='.$value);
		$cell->setAttribute('office:value-type', 'float');
		$cell->setAttribute('office:value', '0');
		$cell->setAttribute('table:style-name', 'ce102');
		$cell->appendChild( $doc->createElement('text:p', '0,00' ));
	}
}


?>
