<?php
/**
 * Class representing iCalendar files.
 *
 * $Horde: framework/iCalendar/iCalendar.php,v 1.53 2004/09/24 03:34:43 chuck Exp $
 *
 * Copyright 2003-2004 Mike Cochrane <mike@graftonhall.co.nz>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @version $Revision$
 * @since   Horde 3.0
 * @package Horde_iCalendar
 */
class Horde_iCalendar {

    /**
     * The parent (containing) iCalendar object.
     *
     * @var object Horde_iCalendar $_container
     */
    var $_container = false;

    var $_attributes = array();

    var $_components = array();

    /**
     * According to RFC 2425, we should always use CRLF-terminated
     * lines.
     *
     * @var string $_newline
     */
    var $_newline = "\r\n";

    /**
     * Return a reference to a new component.
     *
     * @param string $type       The type of component to return
     * @param object $container  A container that this component
     *                           will be associated with.
     *
     * @return object  Reference to a Horde_iCalendar_* object as specified.
     */
    function &newComponent($type, &$container)
    {
#        require_once 'Horde/String.php';
        $type = strtolower($type);
        #error_log("called horde ical new comp". print_r($type,true) );
		$class = 'Horde_iCalendar_' . strtolower($type);
		if (!class_exists($class,false)) {
			include_once dirname(__FILE__) . '/iCalendar/' . $type . '.php';
		}
		if (class_exists($class)) {
			#include_once dirname(__FILE__) . '/iCalendar/' . $type . '.php';
			$component = &new $class();
			if ($container !== false) {
				$component->_container = &$container;
			}
			return $component;
		} else {
			// Should return an dummy x-unknown type class here.
			return false;
		}
    }

    /**
     * Set the value of an attribute.
     *
     * @param string  $name   The name of the attribute.
     * @param string  $value  The value of the attribute.
     * @param array   $params (optional) Array containing any addition
     *                        parameters for this attribute.
     * @param boolean $append (optional) True to append the attribute, False
     *                        to replace the first matching attribute found.
     * @param array $values   (optional) array representation of $value.
     *                        For comma/semicolon seperated lists of values.
     *                        If not set use $value as single array element.
     */
    function setAttribute($name, $value, $params = array(), $append = true, $values = false)
    {
        $found = $append;
        if (!$values) {
            $values = array($value);
        }
        $keys = array_keys($this->_attributes);
        foreach ($keys as $key) {
            if ($found) break;
            if ($this->_attributes[$key]['name'] == $name) {
                $this->_attributes[$key]['params'] = $params;
                $this->_attributes[$key]['value'] = $value;
                $this->_attributes[$key]['values'] = $values;
                $found = true;
            }
        }

        if ($append || !$found) {
            $this->_attributes[] = array(
                'name'      => $name,
                'params'    => $params,
                'value'     => $value,
                'values'    => $values
            );
        }
    }

    /**
     * Sets parameter(s) for an (already existing) attribute.  The
     * parameter set is merged into the existing set.
     *
     * @param string $name    The name of the attribute.
     * @param array  $params  Array containing any additional
     *                        parameters for this attribute.
     * @return boolean  True on success, false if no attribute $name exists.
     */
    function setParameter($name, $params)
    {
        $keys = array_keys($this->_attributes);
        foreach ($keys as $key) {
            if ($this->_attributes[$key]['name'] == $name) {
                $this->_attributes[$key]['params'] =
                    array_merge((array)$this->_attributes[$key]['params'] , $params);
                return true;
            }
        }

        return false;
    }

    /**
     * Get the value of an attribute.
     *
     * @param string  $name    The name of the attribute.
     * @param boolean $params  Return the parameters for this attribute
     *                         instead of its value.
     *
     * @return mixed (object)  PEAR_Error if the attribute does not exist.
     *               (string)  The value of the attribute.
     *               (array)   The parameters for the attribute or
     *                         multiple values for an attribute.
     */
    function getAttribute($name, $params = false)
    {
        $result = array();
        foreach ($this->_attributes as $attribute) {
            if ($attribute['name'] == $name) {
                if ($params) {
                    $result[] = $attribute['params'];
                } else {
                    $result[] = $attribute['value'];
                }
            }
        }
        if (count($result) == 0) {
            require_once 'PEAR.php';
            return PEAR::raiseError('Attribute "' . $name . '" Not Found');
        } if (count($result) == 1 && !$params) {
            return $result[0];
        } else {
            return $result;
        }
    }

    /**
     * Gets the values of an attribute as an array.  Multiple values
     * are possible due to:
     *
     *  a) multiplce occurences of 'name'
     *  b) (unsecapd) comma seperated lists.
     *
     * So for a vcard like "KEY:a,b\nKEY:c" getAttributesValues('KEY')
     * will return array('a','b','c').
     *
     * @param string  $name    The name of the attribute.
     * @return mixed (object)  PEAR_Error if the attribute does not exist.
     *               (array)   Multiple values for an attribute.
     */
    function getAttributeValues($name)
    {
        $result = array();
        foreach ($this->_attributes as $attribute) {
            if ($attribute['name'] == $name) {
                $result = array_merge($attribute['values'], $result);
            }
        }
        if (!count($result)) {
            return PEAR::raiseError('Attribute "' . $name . '" Not Found');
        }
        return $result;
    }

    /**
     * Returns the value of an attribute, or a specified default value
     * if the attribute does not exist.
     *
     * @param string $name     The name of the attribute.
     * @param mixed  $default  (optional) What to return if the attribute
     *                         specified by $name does not exist.
     *
     * @return mixed (string) The value of $name.
     *               (mixed)  $default if $name does not exist.
     */
    function getAttributeDefault($name, $default = '')
    {
        $value = $this->getAttribute($name);
        return is_a($value, 'PEAR_Error') ? $default : $value;
    }

    /**
     * Remove all occurences of an attribute.
     *
     * @param string  $name   The name of the attribute.
     */
    function removeAttribute($name)
    {
        $keys = array_keys($this->_attributes);
        foreach ($keys as $key) {
            if ($this->_attributes[$key]['name'] == $name) {
                unset($this->_attributes[$key]);
            }
        }
    }

    /**
     * Get attributes for all tags or for a given tag.
     *
     * @param string  $tag   (optional) return attributes for this tag.
     *                       or all attributes if not given
     * @return array  Array containing all the attributes and their types.
     */
    function getAllAttributes($tag = false)
    {
        if ($tag === false) {
            return $this->_attributes;
        }
        $result = array();
        foreach ($this->_attributes as $attribute) {
            if ($attribute['name'] == $tag) {
                $result[] = $attribute;
            }
        }
        return $result;
    }

    /**
     * Add a vCalendar component (eg vEvent, vTimezone, etc.).
     *
     * @param object Horde_iCalendar $component  Component (subclass) to add.
     */
    function addComponent($component)
    {
        if (is_a($component, 'Horde_iCalendar')) {
            $component->_container = &$this;
            $this->_components[] = &$component;
        }
    }

    /**
     * Retrieve all the components.
     *
     * @return array  Array of Horde_iCalendar objects.
     */
    function getComponents()
    {
        return $this->_components;
    }

    /**
     * Return the classes (entry types) we have.
     *
     * @return array  Hash with class names Horde_iCalendar_xxx as keys
     *                and number of components of this class as value.
     */
    function getComponentClasses()
    {
        $r = array();
        foreach ($this->_components as $c) {
            $cn = strtolower(get_class($c));
            if (empty($r[$cn])) {
                $r[$cn] = 1;
            } else {
                $r[$cn]++;
            }
        }

        return $r;
    }

    /**
     * Number of components in this container.
     *
     * @return integer  Number of components in this container.
     */
    function getComponentCount()
    {
        return count($this->_components);
    }

    /**
     * Retrieve a specific component.
     *
     * @param integer $idx  The index of the object to retrieve.
     *
     * @return mixed    (boolean) False if the index does not exist.
     *                  (Horde_iCalendar_*) The requested component.
     */
    function getComponent($idx)
    {
        if (isset($this->_components[$idx])) {
            return $this->_components[$idx];
        } else {
            return false;
        }
    }

    /**
     * Locates the first child component of the specified class, and
     * returns a reference to this component.
     *
     * @param string $type  The type of component to find.
     *
     * @return mixed (boolean) False if no subcomponent of the specified
     *                         class exists.
     *               (Horde_iCalendar_*) A reference to the requested component.
     */
    function &findComponent($childclass)
    {
#        require_once 'Horde/String.php';
#        $childclass = 'Horde_iCalendar_' . String::lower($childclass);
        $childclass = 'Horde_iCalendar_' . strtolower($childclass);
        $keys = array_keys($this->_components);
        foreach ($keys as $key) {
            if (is_a($this->_components[$key], $childclass)) {
                return $this->_components[$key];
            }
        }

        return false;
    }

    /**
     * Clears the iCalendar object (resets the components and
     * attributes arrays).
     */
    function clear()
    {
        $this->_components = array();
        $this->_attributes = array();
    }

    /**
     * Export as vCalendar format.
     */
    function exportvCalendar()
    {
	#error_log(__METHOD__.": called");
        // Default values.
        $requiredAttributes['VERSION'] = '2.0';
        $requiredAttributes['PRODID'] = '-//The Horde Project//Horde_iCalendar Library, Horde 3.0-cvs //EN';
        $requiredAttributes['METHOD'] = 'PUBLISH';

        foreach ($requiredAttributes as $name => $default_value) {
            if (is_a($this->getattribute($name), 'PEAR_Error')) {
                $this->setAttribute($name, $default_value);
            }
        }
	//error_log(__METHOD__.":requiredAttributes->".print_r($requiredAttributes,true));
	//njv:$buffcontent = ob_get_clean();
	#error_log(__METHOD__.":".print_r($buffcontent,true));
	#ob_end_clean();
        return $this->_exportvData('VCALENDAR') . $this->_newline;
    }

    /**
     * Export this entry as a hash array with tag names as keys.
     *
     * @param boolean (optional) $paramsInKeys
     *                If false, the operation can be quite lossy as the
     *                parameters are ignored when building the array keys.
     *                So if you export a vcard with
     *                LABEL;TYPE=WORK:foo
     *                LABEL;TYPE=HOME:bar
     *                the resulting hash contains only one label field!
     *                If set to true, array keys look like 'LABEL;TYPE=WORK'
     * @return array  A hash array with tag names as keys.
     */
    function toHash($paramsInKeys = false)
    {
        $hash = array();
        foreach ($this->_attributes as $a)  {
            $k = $a['name'];
            if ($paramsInKeys && is_array($a['params'])) {
                foreach ($a['params'] as $p => $v) {
                    $k .= ";$p=$v";
                }
            }
            $hash[$k] = $a['value'];
        }

        return $hash;
    }

    /**
     * Parse a string containing vCalendar data.
     *
     * @param string  $text  The data to parse.
     * @param string  $base  The type of the base object.
     * @param string  $charset (optional) The encoding charset for $text. Defaults to utf-8
     * @param boolean $clear (optional) True to clear() the iCal object before parsing.
     *
     * @return boolean  True on successful import, false otherwise.
     */
    function parsevCalendar($text, $base = 'VCALENDAR', $charset = 'utf-8', $clear = true)
    {
        if ($clear) {
            $this->clear();
        }
        error_log(__FILE__ . __METHOD__ . ":\n".$text."\n xxxxxxxxx");
	if (preg_match('/(BEGIN:' . $base . '\r?\n)([\W\w]*)(END:' . $base . '\r?\n?)/i', $text, $matches)) {
            $vCal = $matches[2];
        } else {
            // Text isn't enclosed in BEGIN:VCALENDAR
            // .. END:VCALENDAR. We'll try to parse it anyway.
            $vCal = $text;
        }

        // All subcomponents.
        $matches = null;
        if (preg_match_all('/BEGIN:([\S]*)(\r\n|\r|\n)([\W\w]*)END:\1(\r\n|\r|\n)/U', $vCal, $matches)) {
            foreach ($matches[0] as $key => $data) {
                $type = $matches[1][$key];
                $component = &Horde_iCalendar::newComponent(trim($type), $this);
                if ($component === false) {
                    return PEAR::raiseError("Unable to create object for type $type");
                }
                $component->parsevCalendar($data);

                $this->addComponent($component);

                // Remove from the vCalendar data.
                $vCal = str_replace($data, '', $vCal);
            }
        }

        // Unfold any folded lines.
        #$vCal = preg_replace ('/(\r|\n)+ /', ' ', $vCal);

        // Unfold "quoted printable" folded lines like:
        //  BODY;ENCODING=QUOTED-PRINTABLE:=
        //  another=20line=
        //  last=20line
#        Horde::logMessage("SymcML: match 1", __FILE__, __LINE__, PEAR_LOG_DEBUG);
#        if (preg_match_all('/^([^:]+;\s*ENCODING=QUOTED-PRINTABLE(.*=[\r\n|\r|\n])+(.*[^=])[\r\n|\r|\n])/mU', $vCal, $matches)) {
##        if (preg_match_all('/^(BODY;ENCODING=QUOTED-PRINTABLE(.*=\r\n)+(.*)?\r?\n)/mU', $vCal, $matches)) {
#	    Horde::logMessage("SymcML: match 2", __FILE__, __LINE__, PEAR_LOG_DEBUG);
#            foreach ($matches[1] as $s) {
#	        Horde::logMessage("SymcML: match 3 $s", __FILE__, __LINE__, PEAR_LOG_DEBUG);
#                $r = preg_replace('/=[\r\n|\r|\n]/', '', $s);
#	        Horde::logMessage("SymcML: match 4 $r", __FILE__, __LINE__, PEAR_LOG_DEBUG);
#                $vCal = str_replace($s, $r, $vCal);
#            }
#        }

        // Unfold "quoted printable" folded lines like:
        //  BODY;ENCODING=QUOTED-PRINTABLE:=
        //  another=20line=
        //  last=20line
        #if (preg_match_all('/^([^:]+;\s*ENCODING=QUOTED-PRINTABLE(.*=*\s))/mU', $vCal, $matches)) {
	#    $matches = preg_split('/=+\s/',$vCal);
	#    $vCal = implode('',$matches);
        #}
        if (preg_match_all('/^([^:]+;\s*ENCODING=QUOTED-PRINTABLE(.*=*\s))/mU', $vCal, $matches)) {
	    $matches = preg_split('/=(\r\n|\r|\n)/',$vCal);
	    $vCal = implode('',$matches);
        }

        // Parse the remaining attributes.

        if (preg_match_all('/(.*):([^\r\n]*)[\r\n]+/', $vCal, $matches)) {
            foreach ($matches[0] as $attribute) {
                preg_match('/([^;^:]*)((;[^:]*)?):([^\r\n]*)[\r\n]*/', $attribute, $parts);
                $tag = $parts[1];
                $value = $parts[4];
                $params = array();

                // Parse parameters.
                if (!empty($parts[2])) {
                    preg_match_all('/;(([^;=]*)(=([^;]*))?)/', $parts[2], $param_parts);
                    foreach ($param_parts[2] as $key => $paramName) {
                        $paramValue = $param_parts[4][$key];
                        $params[$paramName] = $paramValue;
                    }
                }

                // Charset and encoding handling.
                

				// njv sanity todo: decode  text fields containing qp but not tagged
					

				if ((isset($params['ENCODING'])
                     && $params['ENCODING'] == 'QUOTED-PRINTABLE')
                    || isset($params['QUOTED-PRINTABLE'])) {

                    $value = quoted_printable_decode($value);
                }

                if (isset($params['CHARSET'])) {
                    $value = $GLOBALS['egw']->translation->convert($value, $params['CHARSET']);
                } else {
                    // As per RFC 2279, assume UTF8 if we don't have
                    // an explicit charset parameter.
                    $value = $GLOBALS['egw']->translation->convert($value, 'utf-8');
                }

                switch ($tag) {
                // Date fields.
                case 'DTSTAMP':
                case 'COMPLETED':
                case 'CREATED':
                case 'LAST-MODIFIED':
                case 'BDAY':
                case 'DTEND':
                case 'DTSTART':
                case 'DUE':
                case 'RECURRENCE-ID':
                    $this->setAttribute($tag, $this->_parseDateTime($value), $params);
                    break;

                case 'RDATE':
                    if (isset($params['VALUE'])) {
                        if ($params['VALUE'] == 'DATE') {
                            $this->setAttribute($tag, $this->_parseDate($value), $params);
                        } elseif ($params['VALUE'] == 'PERIOD') {
                            $this->setAttribute($tag, $this->_parsePeriod($value), $params);
                        } else {
                            $this->setAttribute($tag, $this->_parseDateTime($value), $params);
                        }
                    } else {
                        $this->setAttribute($tag, $this->_parseDateTime($value), $params);
                    }
                    break;

                case 'TRIGGER':
                    if (isset($params['VALUE'])) {
                        if ($params['VALUE'] == 'DATE-TIME') {
                            $this->setAttribute($tag, $this->_parseDateTime($value), $params);
                        } else {
                            $this->setAttribute($tag, $this->_parseDuration($value), $params);
                        }
                    } else {
                        $this->setAttribute($tag, $this->_parseDuration($value), $params);
                    }
                    break;

                // Comma and semicolon seperated dates.
                case 'EXDATE':
                    $values = array();
                    $dates = array();
                    preg_match_all('/[,;]([^,;]*)/', ';' . $value, $values);

                    foreach ($values[1] as $value) {
                        if (isset($params['VALUE'])) {
                            if ($params['VALUE'] == 'DATE-TIME') {
                                $dates[] = $this->_parseDateTime($value);
                            } elseif ($params['VALUE'] == 'DATE') {
                                $dates[] = $this->_parseDate($value);
                            }
                        } else {
                            $dates[] = $this->_parseDateTime($value);
                        }
                    }
                    $this->setAttribute($tag, $dates, $params);
                    break;

                // Duration fields.
                case 'DURATION':
                    $this->setAttribute($tag, $this->_parseDuration($value), $params);
                    break;

                // Period of time fields.
                case 'FREEBUSY':
                    $values = array();
                    $periods = array();
                    preg_match_all('/[,;]([^,;]*)/', ';' . $value, $values);
                    foreach ($values[1] as $value) {
                        $periods[] = $this->_parsePeriod($value);
                    }

                    $this->setAttribute($tag, $periods, $params);
                    break;

                // UTC offset fields.
                case 'TZOFFSETFROM':
                case 'TZOFFSETTO':
                    $this->setAttribute($tag, $this->_parseUtcOffset($value), $params);
                    break;

                // Integer fields.
                case 'PERCENT-COMPLETE':
                case 'PRIORITY':
                case 'REPEAT':
                case 'SEQUENCE':
                    $this->setAttribute($tag, intval($value), $params);
                    break;

                // Geo fields.
                case 'GEO':
                    $floats = split(';', $value);
                    $value['latitude'] = floatval($floats[0]);
                    $value['longitude'] = floatval($floats[1]);
                    $this->setAttribute($tag, $value, $params);
                    break;

                // Recursion fields.
                case 'EXRULE':
                case 'RRULE':
                    $this->setAttribute($tag, trim($value), $params);
                    break;

                // ADR an N are lists seperated by unescaped semi-colons.
                case 'ADR':
                case 'N':
                case 'ORG':

                    $value = trim($value);
                    // As of rfc 2426 2.4.2 semi-colon, comma, and
                    // colon must be escaped.
                    // njv an "urban myth" a colon is tsafe and should not be escaped
		    		$value = str_replace('\\n', $this->_newline, $value);
                    $value = str_replace('\\,', ',', $value);
                    //njv:$value = str_replace('\\:', ':', $value);

                    // Split by unescaped semi-colons:
                    $values = preg_split('/(?<!\\\\);/',$value);
                    $value = str_replace('\\;', ';', $value);
                    $values = str_replace('\\;', ';', $values);
                    $this->setAttribute($tag, trim($value), $params, true, $values);

                    break;

                // String fields.
                default:
                    
					$value = trim($value);
                    
					//sanity $value should not contain qp
					if(preg_match('/^=[24]/',$value)){
						error_log(__FILE__ .__METHOD__ ."?qp decoded : ". print_r($value , true));
						quoted_printable_decode($value);
					}
					// As of rfc 2426 2.4.2 semi-colon, comma, and
                    // colon must be escaped.
                    $value = str_replace('\\n', $this->_newline, $value);
                    $value = str_replace('\\;', ';', $value);
                    //njv:$value = str_replace('\\:', ':', $value);

                    // Split by unescaped commas:
                    $values = preg_split('/(?<!\\\\),/',$value);
                    $value = str_replace('\\,', ',', $value);
                    $values = str_replace('\\,', ',', $values);

                    $this->setAttribute($tag, trim($value), $params, true, $values);
                    break;
                }
            }
        }

        return true;
    }

    /**
     * Export this component in vCal format.
     *
     * @param string $base  (optional) The type of the base object.
     *
     * @return string  vCal format data.
     */
    function _exportvData($base = 'VCALENDAR')
    {
        $result  = 'BEGIN:' . strtoupper($base) . $this->_newline;

        // Ensure that version is the first attribute.
        $v = $this->getAttributeDefault('VERSION', false);
        if ($v) {
            $result .= 'VERSION:' . $v. $this->_newline;
        }

        foreach ($this->_attributes as $attribute) {
            $name = $attribute['name'];
            if ($name == 'VERSION') {
                // Already done.
                continue;
            }

            $params = $attribute['params'];
            $params_str = '';

            if (count($params)) {
                foreach ($params as $param_name => $param_value) {
                    $params_str .= ";$param_name=$param_value";
                }
            }

            $value = $attribute['value'];

            switch ($name) {
            // Date fields.
            case 'DTSTAMP':
            case 'COMPLETED':
            case 'CREATED':
            case 'DCREATED':
            case 'LAST-MODIFIED':
                $value = $this->_exportDateTime($value);
                break;

            case 'DTEND':
            case 'DTSTART':
            case 'DUE':
            case 'RECURRENCE-ID':
                if (isset($params['VALUE'])) {
                    if ($params['VALUE'] == 'DATE') {
                        $value = $this->_exportDate($value);
                    } else {
                        $value = $this->_exportDateTime($value);
                    }
                } else {
                    $value = $this->_exportDateTime($value);
                }
                break;

            case 'RDATE':
                if (isset($params['VALUE'])) {
                    if ($params['VALUE'] == 'DATE') {
                        $value = $this->_exportDate($value);
                    } elseif ($params['VALUE'] == 'PERIOD') {
                        $value = $this->_exportPeriod($value);
                    } else {
                        $value = $this->_exportDateTime($value);
                    }
                } else {
                    $value = $this->_exportDateTime($value);
                }
                break;

            case 'TRIGGER':
                if (isset($params['VALUE'])) {
                    if ($params['VALUE'] == 'DATE-TIME') {
                        $value = $this->_exportDateTime($value);
                    } elseif ($params['VALUE'] == 'DURATION') {
                        $value = $this->_exportDuration($value);
                    }
                } else {
                    $value = $this->_exportDuration($value);
                }
                break;

            // Duration fields.
            case 'DURATION':
                $value = $this->_exportDuration($value);
                break;

            // Period of time fields.
            case 'FREEBUSY':
                $value_str = '';
                foreach ($value as $period) {
                    $value_str .= empty($value_str) ? '' : ',';
                    $value_str .= $this->_exportPeriod($period);
                }
                $value = $value_str;
                break;

            // UTC offset fields.
            case 'TZOFFSETFROM':
            case 'TZOFFSETTO':
                $value = $this->_exportUtcOffset($value);
                break;

            // Integer fields.
            case 'PERCENT-COMPLETE':
            case 'PRIORITY':
            case 'REPEAT':
            case 'SEQUENCE':
                $value = "$value";
                break;

            // Geo fields.
            case 'GEO':
                $value = $value['latitude'] . ',' . $value['longitude'];
                break;

            // Recurrence fields.
            case 'RRULE':
                break;
            case 'EXRULE':

	    //Text Fields
	    case 'SUMMARY':
	    case 'DESCRIPTION':
	    case 'COMMENT':
		$value = str_replace('\\', '\\\\', $value);
		#$value = str_replace($this->_newline, '\n', $value);
		$value = str_replace(',', '\,', $value);
		$value = str_replace(';', '\;', $value);
		//njv:RFC 2445 says very definately NO!
		//$value = str_replace(':', '\:', $value);
                break;

            default:
                break;
            }

            if (!empty($params['ENCODING']) &&
                $params['ENCODING'] == 'QUOTED-PRINTABLE' && strlen(trim($value)) > 0) {
                $value = str_replace("\r", '', $value);
#                $result .= "$name$params_str:=" . $this->_newline
#                    . $this->_quotedPrintableEncode($value)
#                    . $this->_newline;
                $result .= "$name$params_str:"
                    . $this->_quotedPrintableEncode($value)
                    . $this->_newline;
            } else {
                $attr_string = "$name$params_str:$value";

                $result .= $this->_foldLine($attr_string) . $this->_newline;
				if (!empty($params['ENCODING']) && $params['ENCODING'] == 'BASE64' &&
					strlen(trim($value)) > 0)
				{
					$result .= $this->_newline;
				}
            }
        }

        foreach ($this->getComponents() as $component) {
            $result .= $component->exportvCalendar() . $this->_newline;
        }

        $result .= 'END:' . $base;

        return $result;
    }

    /**
     * Parse a UTC Offset field.
     */
    function _parseUtcOffset($text)
    {
        $offset = array();
        if (preg_match('/(\+|-)([0-9]{2})([0-9]{2})([0-9]{2})?/', $text, $timeParts)) {
            $offset['ahead']  = (boolean)($timeParts[1] == '+');
            $offset['hour']   = intval($timeParts[2]);
            $offset['minute'] = intval($timeParts[3]);
            if (isset($timeParts[4])) {
                $offset['second'] = intval($timeParts[4]);
            }
            return $offset;
        } else {
            return false;
        }
    }

    /**
     * Export a UTC Offset field.
     */
    function _exportUtcOffset($value)
    {
        $offset = $value['ahead'] ? '+' : '-';
        $offset .= sprintf('%02d%02d',
                           $value['hour'], $value['minute']);
        if (isset($value['second'])) {
            $offset .= sprintf('%02d', $value['second']);
        }

        return $offset;
    }

    /**
     * Parse a Time Period field.
     */
    function _parsePeriod($text)
    {
        $periodParts = split('/', $text);

        $start = $this->_parseDateTime($periodParts[0]);

        if ($duration = $this->_parseDuration($periodParts[1])) {
            return array('start' => $start, 'duration' => $duration);
        } elseif ($end = $this->_parseDateTime($periodParts[1])) {
            return array('start' => $start, 'end' => $end);
        }
    }

    /**
     * Export a Time Period field.
     */
    function _exportPeriod($value)
    {
        $period = $this->_exportDateTime($value['start']);
        $period .= '/';
        if (isset($value['duration'])) {
            $period .= $this->_exportDuration($value['duration']);
        } else {
            $period .= $this->_exportDateTime($value['end']);
        }
        return $period;
    }

    /**
     * Parse a DateTime field into a unix timestamp.
     */
    function _parseDateTime($text)
    {
        $dateParts = split('T', $text);
        if (count($dateParts) != 2 && !empty($text)) {
            // Not a datetime field but may be just a date field.
            if (!$date = $this->_parseDate($text)) {
                return $date;
            }
            return @mktime(0, 0, 0, $date['month'], $date['mday'], $date['year']);
        }

        if (!$date = $this->_parseDate($dateParts[0])) {
            return $date;
        }
        if (!$time = $this->_parseTime($dateParts[1])) {
            return $time;
        }

		//error_log("parseDateTime: ".$text." => ".print_r($time, true));

        if ($time['zone'] == 'UTC') {
            return @gmmktime($time['hour'], $time['minute'], $time['second'],
                             $date['month'], $date['mday'], $date['year']);
        } else {
            return @mktime($time['hour'], $time['minute'], $time['second'],
                           $date['month'], $date['mday'], $date['year']);
        }
    }

    /**
     * Export a DateTime field.
     */
    function _exportDateTime($value)
    {
        $temp = array();
        if (!is_object($value) || is_array($value)) {
            $TZOffset  = 3600 * substr(date('O',$value), 0, 3);
            $TZOffset += 60 * substr(date('O',$value), 3, 2);
            $value -= $TZOffset;

            $temp['zone']   = 'UTC';
            $temp['year']   = date('Y', $value);
            $temp['month']  = date('n', $value);
            $temp['mday']   = date('j', $value);
            $temp['hour']   = date('G', $value);
            $temp['minute'] = date('i', $value);
            $temp['second'] = date('s', $value);
        } else {
            $dateOb = (object)$value;

            $TZOffset = date('O',mktime($dateOb->hour,$dateOb->min,$dateOb->sec,$dateOb->month,$dateOb->mday,$dateOb->year));

            // Minutes.
            $TZOffsetMin = substr($TZOffset, 0, 1) . substr($TZOffset, 3, 2);
            $thisMin = $dateOb->min - $TZOffsetMin;

            // Hours.
            $TZOffsetHour = substr($TZOffset, 0, 3);
            $thisHour = $dateOb->hour - $TZOffsetHour;

            if ($thisMin < 0) {
                $thisHour -= 1;
                $thisMin += 60;
            }

            if ($thisHour < 0) {
                require_once 'Date/Calc.php';
                $prevday = Date_Calc::prevDay($dateOb->mday, $dateOb->month, $dateOb->year);
                $dateOb->mday  = substr($prevday, 6, 2);
                $dateOb->month = substr($prevday, 4, 2);
                $dateOb->year  = substr($prevday, 0, 4);
                $thisHour += 24;
            }

            $temp['zone']   = 'UTC';
            $temp['year']   = $dateOb->year;
            $temp['month']  = $dateOb->month;
            $temp['mday']   = $dateOb->mday;
            $temp['hour']   = $thisHour;
            $temp['minute'] = $dateOb->min;
            $temp['second'] = $dateOb->sec;
        }

        return Horde_iCalendar::_exportDate($temp) . 'T' . Horde_iCalendar::_exportTime($temp);
    }

    /**
     * Parse a Time field.
     */
    function _parseTime($text)
    {
        if (preg_match('/([0-9]{2})([0-9]{2})([0-9]{2})(Z)?/', $text, $timeParts)) {
            $time['hour'] = intval($timeParts[1]);
            $time['minute'] = intval($timeParts[2]);
            $time['second'] = intval($timeParts[3]);
            if (isset($timeParts[4])) {
                $time['zone'] = 'UTC';
            } else {
                $time['zone'] = 'Local';
            }
            return $time;
        } else {
            return false;
        }
    }

    /**
     * Export a Time field.
     */
    function _exportTime($value)
    {
        $time = sprintf('%02d%02d%02d',
                        $value['hour'], $value['minute'], $value['second']);
        if ($value['zone'] == 'UTC') {
            $time .= 'Z';
        }
        return $time;
    }

    /**
     * Parse a Date field.
     */
    function _parseDate($text)
    {
		if (strlen($text) == 10)
		{
			$text = str_replace('-','',$text);
		}
        if (strlen($text) != 8)
		{
            return false;
        }

        $date['year']  = intval(substr($text, 0, 4));
        $date['month'] = intval(substr($text, 4, 2));
        $date['mday']  = intval(substr($text, 6, 2));

        return $date;
    }

    /**
     * Export a Date field.
     */
    function _exportDate($value)
    {
        return sprintf('%04d%02d%02d',
                       $value['year'], $value['month'], $value['mday']);
    }

    /**
     * Parse a Duration Value field.
     */
    function _parseDuration($text)
    {
        if (preg_match('/([+]?|[-])P(([0-9]+W)|([0-9]+D)|)(T(([0-9]+H)|([0-9]+M)|([0-9]+S))+)?/', trim($text), $durvalue)) {
            // Weeks.
            $duration = 7 * 86400 * intval($durvalue[3]);

            if (count($durvalue) > 4) {
                // Days.
                $duration += 86400 * intval($durvalue[4]);
            }
            if (count($durvalue) > 5) {
                // Hours.
                $duration += 3600 * intval($durvalue[7]);

                // Mins.
                if (isset($durvalue[8])) {
                    $duration += 60 * intval($durvalue[8]);
                }

                // Secs.
                if (isset($durvalue[9])) {
                    $duration += intval($durvalue[9]);
                }
            }

            // Sign.
            if ($durvalue[1] == "-") {
                $duration *= -1;
            }

            return $duration;
        } else {
            return false;
        }
    }

    /**
     * Export a duration value.
     */
    function _exportDuration($value)
    {
        $duration = '';
        if ($value < 0) {
            $value *= -1;
            $duration .= '-';
        }
        $duration .= 'P';

        $weeks = floor($value / (7 * 86400));
        $value = $value % (7 * 86400);
        if ($weeks) {
            $duration .= $weeks . 'W';
        }

        $days = floor($value / (86400));
        $value = $value % (86400);
        if ($days) {
            $duration .= $days . 'D';
        }

        if ($value) {
            $duration .= 'T';

            $hours = floor($value / 3600);
            $value = $value % 3600;
            if ($hours) {
                $duration .= $hours . 'H';
            }

            $mins = floor($value / 60);
            $value = $value % 60;
            if ($mins) {
                $duration .= $mins . 'M';
            }

            if ($value) {
                $duration .= $value . 'S';
            }
        }

        return $duration;
    }

    /**
     * Return the folded version of a line.
	 * JVL rewritten to fold on any ; or: or = if present before column 75
	 * this is still rfc2445 section 4.1 compliant
     */
    function _foldLine($line)
    {
        $line = preg_replace("/\r\n|\n|\r/", '\n', $line);
        if (strlen($line) > 75) {
            $foldedline = '';
            while (!empty($line)) {
			  $maxLine = substr($line, 0, 75);
			  $cutPoint = 1+max(is_numeric($p1 = strrpos($maxLine,';')) ? $p1 : -1,
					    is_numeric($p1 = strrpos($maxLine,':')) ? $p1 : -1,
					    is_numeric($p1 = strrpos($maxLine,'=')) ? $p1 : -1);
			  if ($cutPoint <  1)  // nothing found, then fold complete maxLine
				$cutPoint = 75;
			  // now fold [0..(cutPoint-1)]
			  $foldedline .= (empty($foldedline))
				?   substr($line, 0, $cutPoint)
				:  $this->_newline . ' ' . substr($line, 0, $cutPoint);

			  $line = (strlen($line) <= $cutPoint)
				? ''
				: substr($line, $cutPoint);

			  if (strlen($line) < 75) {
				$foldedline .=  $this->_newline . ' ' . $line;
				$line = '';
			  }

            }
            return $foldedline;
        }
        return $line;
    }

    /**
     * Convert an 8bit string to a quoted-printable string according
     * to RFC2045, section 6.7.
     *
     * Uses imap_8bit if available.
     *
     * @param  string $input  The string to be encoded.
     *
     * @return string         The quoted-printable encoded string.
     */
    function _quotedPrintableEncode($input = '')
    {
    	return $this->EncodeQP($input);

	#$input = preg_replace('!(\r\n|\r|\n)!',"\n",$input);

        // If imap_8bit() is available, use it.
        if (function_exists('imap_8bit')) {
            $retValue = imap_8bit($input);
	    #$retValue = preg_replace('/=0A/',"=0D=0A=\r\n",$retValue);
	    return $retValue;
        }

        // Rather dumb replacment: just encode everything.
        $hex = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
                     'A', 'B', 'C', 'D', 'E', 'F');

        $output = '';
        $len = strlen($input);
        for ($i = 0; $i < $len; ++$i) {
            $c = substr($input, $i, 1);
            $dec = ord($c);
            $output .= '=' . $hex[floor($dec / 16)] . $hex[floor($dec % 16)];
            if (($i + 1) % 25 == 0) {
                $output .= "=\r\n";
            }
        }
        return $output;
    }
    var $LE              = "\r\n";

    /**
     * Encode string to quoted-printable.
     * @access private
     * @return string
     */
    function EncodeQP_old ($str) {
        $encoded = $this->FixEOL($str);
        #$encoded = $str;
        #if (substr($encoded, -(strlen($this->LE))) != $this->LE)
        #    $encoded .= $this->LE;

        // Replace every high ascii, control and = characters
        #$encoded = preg_replace('/([\000-\010\013\014\016-\037\075\177-\377])/e',
        #          "'='.sprintf('%02X', ord('\\1'))", $encoded);
        $encoded = preg_replace('/([\000-\012\015\016\020-\037\075\177-\377])/e',
                  "'='.sprintf('%02X', ord('\\1'))", $encoded);
        // Replace every spaces and tabs when it's the last character on a line
        #$encoded = preg_replace("/([\011\040])".$this->LE."/e",
        #          "'='.sprintf('%02X', ord('\\1')).'".$this->LE."'", $encoded);
        $encoded = preg_replace("/([\011\040])".$this->LE."/e",
                  "'='.sprintf('%02X', ord('\\1')).'".$this->LE."'", $encoded);

        // Maximum line length of 76 characters before CRLF (74 + space + '=')
        $encoded = $this->WrapText($encoded, 74, true);

        return $encoded;
    }

    /**
     * Wraps message for use with mailers that do not
     * automatically perform wrapping and for quoted-printable.
     * Original written by philippe.
     * @access private
     * @return string
     */
    function WrapText_old($message, $length, $qp_mode = false) {
        $soft_break = ($qp_mode) ? "=\r\n" : $this->LE;

        #$message = $this->FixEOL($message);
        if (substr($message, -1) == $this->LE)
            $message = substr($message, 0, -1);

        $line = explode("=0D=0A", $message);
        $message = "";
        for ($i=0 ;$i < count($line); $i++)
        {
          $line_part = explode(" ", $line[$i]);
          $buf = "";
          for ($e = 0; $e<count($line_part); $e++)
          {
              $word = $line_part[$e];
              if ($qp_mode and (strlen($word) > $length))
              {
                $space_left = $length - strlen($buf) - 1;
                if ($e != 0)
                {
                    if ($space_left > 20)
                    {
                        $len = $space_left;
                        if (substr($word, $len - 1, 1) == "=")
                          $len--;
                        elseif (substr($word, $len - 2, 1) == "=")
                          $len -= 2;
                        $part = substr($word, 0, $len);
                        $word = substr($word, $len);
                        $buf .= " " . $part;
                        $message .= $buf . sprintf("=%s", $this->LE);
                    }
                    else
                    {
                        $message .= $buf . $soft_break;
                    }
                    $buf = "";
                }
                while (strlen($word) > 0)
                {
                    $len = $length;
                    if (substr($word, $len - 1, 1) == "=")
                        $len--;
                    elseif (substr($word, $len - 2, 1) == "=")
                        $len -= 2;
                    $part = substr($word, 0, $len);
                    $word = substr($word, $len);

                    if (strlen($word) > 0)
                        $message .= $part . sprintf("=%s", $this->LE);
                    else
                        $buf = $part;
                }
              }
              else
              {
                $buf_o = $buf;
                $buf .= ($e == 0) ? $word : (" " . $word);

                if (strlen($buf) > $length and $buf_o != "")
                {
                    $message .= $buf_o . $soft_break;
                    $buf = $word;
                }
              }
          }
          $message .= $buf;
          if((count($line)-1) > $i)
          	$message .= "=0D=0A=\r\n";
        }

        return $message;
    }
    /**
     * Changes every end of line from CR or LF to CRLF.
     * @access private
     * @return string
     */
    function FixEOL($str) {
      	$str = str_replace("\r\n", "\n", $str);
        $str = str_replace("\r", "\n", $str);
        $str = str_replace("\n", $this->LE, $str);
        return $str;
    }

    /**
     * Encode string to quoted-printable.
     * @access private
     * @return string
     */
    function EncodeQP ($str) {
        $encoded = $this->FixEOL($str);
        # see bug report http://sourceforge.net/tracker/index.php?func=detail&aid=1536674&group_id=78745&atid=554338
        #if (substr($encoded, -(strlen($this->LE))) != $this->LE)
        #    $encoded .= $this->LE;

        // Replace every high ascii, control and = characters
        #$encoded = preg_replace('/([\000-\010\013\014\016-\037\075\177-\377])/e',
        #          "'='.sprintf('%02X', ord('\\1'))", $encoded);
        $encoded = preg_replace('/([\000-\012\015\016\020-\037\075\177-\377])/e',
                  "'='.sprintf('%02X', ord('\\1'))", $encoded);
        // Replace every spaces and tabs when it's the last character on a line
        $encoded = preg_replace("/([\011\040])".$this->LE."/e",
                  "'='.sprintf('%02X', ord('\\1')).'".$this->LE."'", $encoded);

        // Maximum line length of 76 characters before CRLF (74 + space + '=')
        #$encoded = $this->WrapText($encoded, 74, true);

        return $encoded;
    }

    /**
     * Wraps message for use with mailers that do not
     * automatically perform wrapping and for quoted-printable.
     * Original written by philippe.
     * @access private
     * @return string
     */
    function WrapText($message, $length, $qp_mode = false) {
        $soft_break = ($qp_mode) ? sprintf(" =%s", $this->LE) : $this->LE;
        $soft_break = "..=";

        $message = $this->FixEOL($message);
        if (substr($message, -1) == $this->LE)
            $message = substr($message, 0, -1);

        $line = explode($this->LE, $message);
        $message = "";
        for ($i=0 ;$i < count($line); $i++)
        {
          $line_part = explode(" ", $line[$i]);
          $buf = "";
          for ($e = 0; $e<count($line_part); $e++)
          {
              $word = $line_part[$e];
              if ($qp_mode and (strlen($word) > $length))
              {
                $space_left = $length - strlen($buf) - 1;
                if ($e != 0)
                {
                    if ($space_left > 20)
                    {
                        $len = $space_left;
                        if (substr($word, $len - 1, 1) == "=")
                          $len--;
                        elseif (substr($word, $len - 2, 1) == "=")
                          $len -= 2;
                        $part = substr($word, 0, $len);
                        $word = substr($word, $len);
                        $buf .= " " . $part;
                        $message .= $buf . sprintf("=%s", $this->LE);
                    }
                    else
                    {
                        $message .= $buf . $soft_break;
                    }
                    $buf = "";
                }
                while (strlen($word) > 0)
                {
                    $len = $length;
                    if (substr($word, $len - 1, 1) == "=")
                        $len--;
                    elseif (substr($word, $len - 2, 1) == "=")
                        $len -= 2;
                    $part = substr($word, 0, $len);
                    $word = substr($word, $len);

                    if (strlen($word) > 0)
                        $message .= $part . sprintf("=%s", $this->LE);
                    else
                        $buf = $part;
                }
              }
              else
              {
                $buf_o = $buf;
                $buf .= ($e == 0) ? $word : (" " . $word);

                if (strlen($buf) > $length and $buf_o != "")
                {
                    $message .= $buf_o . $soft_break;
                    $buf = $word;
                }
              }
          }
          $message .= $buf . $this->LE;
        }

        return $message;
    }

}
