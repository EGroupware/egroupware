<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id: $
 */

/**
 * class arrayxml
 * easy to use array2xml and xml2array functions. Presumably this is the n+1 
 * class of this kind out there, but i wasn't able to find a wokring one...
 *
 * @abstract  PHP allowes array keys to be numeric while XML prohibits this.
 * Therefore the XML structur of this class is a bit uncommon but nessesary!
 * @todo deal with other types like objects
 * @static only namespace here
 */ 
class importexport_arrayxml {
	
	/**
	 * converts a php array to an xml string
	 *
	 * @param mixed $_data
	 * @param string $_name
	 * @param DOMElement $_node
	 * @return string XML string
	 */
	public static function array2xml ( $_data, $_name = 'root', $_node=null ) {
		$returnXML = false;
		if ( $_node === null ) {
			$_node = new DOMDocument( '1.0', 'utf-8' );
			$_node->formatOutput = true;
			$returnXML = true;
		}
		
		$datatype = gettype( $_data );
		switch ( $datatype ) {
			case 'array' :
				$subnode = new DOMElement( 'entry' );
				$_node->appendChild( $subnode );
				$subnode->setAttribute( 'type', $datatype );
				$subnode->setAttribute( 'name' , $_name );
					
				foreach ( $_data as $ikey => $ivalue ) {
					self::array2xml( $ivalue, $ikey, $subnode );
				}
				break;

			default : 
				switch ( $datatype ) {
					case 'boolean' :
						$data = $_data !== false ? 'TRUE' : 'FALSE';
						break;
					default:
						$data = &$_data;
				}
				$subnode = new DOMElement( 'entry' , $data );
				$_node->appendChild( $subnode );
				$subnode->setAttribute( 'type', $datatype );
				$subnode->setAttribute( 'name' , $_name );
				break;
		}
		return $returnXML ? $_node->saveXML() : '';
	}
	
	/**
	 * converts XML string into php array
	 *
	 * @param string $_xml
	 * @return array
	 */
	public static function xml2array( $_xml ) {
		if ( $_xml instanceof DOMElement ) {
			$n = &$_xml;
		} else {
			$n = new DOMDocument;
			$n->loadXML($_xml);
		}
		$xml_array = array();
		
		foreach($n->childNodes as $nc) {
			
			if ( $nc->nodeType != XML_ELEMENT_NODE ) continue;
				
			$name = $nc->attributes->getNamedItem('name')->nodeValue;
			$type = $nc->attributes->getNamedItem('type')->nodeValue;

			//echo $nc->nodeType. "(length ): ". $nc->nodeName. " => ". $nc->nodeValue. "; Attriubtes: name=$name, type=$type  \n ";
			if( $nc->childNodes->length >= 2) {
				$xml_array[$name] = self::xml2array($nc);
			} else {
				switch ( $type ) {
					case 'boolean' :
						$value = $nc->nodeValue == 'FALSE' ? false : true;
						break;
					default :
						$value = $nc->nodeValue;
				}
				$xml_array[$name] = $value;
			} 
		}
		
		return $xml_array;
	}
}	
?>
