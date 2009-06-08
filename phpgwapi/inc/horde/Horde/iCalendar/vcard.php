<?php

require_once EGW_API_INC.'/horde/Horde/iCalendar.php';


// The following were shamelessly yoinked from Contact_Vcard_Build
// Part numbers for N components.
define('VCARD_N_FAMILY',     0);
define('VCARD_N_GIVEN',      1);
define('VCARD_N_ADDL',       2);
define('VCARD_N_PREFIX',     3);
define('VCARD_N_SUFFIX',     4);

// Part numbers for ADR components.
define('VCARD_ADR_POB',      0);
define('VCARD_ADR_EXTEND',   1);
define('VCARD_ADR_STREET',   2);
define('VCARD_ADR_LOCALITY', 3);
define('VCARD_ADR_REGION',   4);
define('VCARD_ADR_POSTCODE', 5);
define('VCARD_ADR_COUNTRY',  6);

// Part numbers for GEO components.
define('VCARD_GEO_LAT',      0);
define('VCARD_GEO_LON',      1);

/**
 * Class representing vCard entries.
 *
 * $Horde: framework/iCalendar/iCalendar/vcard.php,v 1.2 2004/08/18 03:16:24 chuck Exp $
 *
 * Copyright 2003-2004 Karsten Fourmont (karsten@horde.org)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Karsten Fourmont <karsten@horde.org>
 * @version $Revision$
 * @package Horde_iCalendar
 */
class Horde_iCalendar_vcard extends Horde_iCalendar {

    function getType()
    {
        return 'vcard';
    }

    function parsevCalendar($data)
    {
        return parent::parsevCalendar($data, 'vcard');
    }

    /**
     * Unlike vevent and vtodo, a vcard is normally not enclosed in an
     * iCalendar container. (BEGIN..END)
     */
    function exportvCalendar()
    {
        #$requiredAttributes['BODY'] = '';
        $requiredAttributes['VERSION'] = '2.1';

        foreach ($requiredAttributes as $name => $default_value) {
            if (is_a($this->getattribute($name), 'PEAR_Error')) {
                $this->setAttribute($name, $default_value);
            }
        }
		//error_log(__METHOD__.":requiredAttributes->".print_r($requiredAttributes,true));
		//njv:$buffcontent = ob_get_clean();
		#error_log(__METHOD__.":".print_r($buffcontent,true));
		#ob_end_clean();

        return $this->_exportvData('VCARD') . $this->_newline;
    }

    /**
     * Returns the contents of the "N" tag as a printable Name:
     * i.e. converts:
     *
     *   N:Duck;Dagobert;T;Professor;Sen.
     * to
     *   "Professor Dagobert T Duck Sen"
     *
     * @return string  Full name of vcard "N" tag
     *                 or null if no N tag.
     */
    function printableName()
    {
        $name_parts = $this->getAttributeValues('N');
        if (is_a($name_parts, 'PEAR_Error')) {
            return null;
        }

        if (!empty($name_parts[VCARD_N_PREFIX])) {
            $name_arr[] = $name_parts[VCARD_N_PREFIX];
        }
        if (!empty($name_parts[VCARD_N_GIVEN])) {
            $name_arr[] = $name_parts[VCARD_N_GIVEN];
        }
        if (!empty($name_parts[VCARD_N_ADDL])) {
            $name_arr[] = $name_parts[VCARD_N_ADDL];
        }
        if (!empty($name_parts[VCARD_N_FAMILY])) {
            $name_arr[] = $name_parts[VCARD_N_FAMILY];
        }
        if (!empty($name_parts[VCARD_N_SUFFIX])) {
            $name_arr[] = $name_parts[VCARD_N_SUFFIX];
        }

        return implode(' ', $name_arr);
    }

    /**
     * Static function to make a given email address rfc822 compliant.
     *
     * @param string $address  An email address.
     *
     * @return string  The RFC822-formatted email address.
     */
    function getBareEmail($address)
    {
        require_once 'Mail/RFC822.php';
        require_once 'Horde/MIME.php';

        static $rfc822;
        if (is_null($rfc822)) {
            $rfc822 = new Mail_RFC822();
        }

        $rfc822->validateMailbox($address);

        return MIME::rfc822WriteAddress($address->mailbox, $address->host);
    }

}
