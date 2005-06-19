<?php

require_once PHPGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/iCalendar.php';

/**
 * Class representing vNotes.
 *
 * $Horde: framework/iCalendar/iCalendar/vnote.php,v 1.2 2004/08/13 19:11:35 karsten Exp $
 *
 * Copyright 2003-2004 Mike Cochrane <mike@graftonhall.co.nz>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Karsten Fourmont <fourmont@gmx.de>
 * @version $Revision$
 * @package Horde_iCalendar
 */
class Horde_iCalendar_vnote extends Horde_iCalendar {

    function getType()
    {
        return 'vNote';
    }

    function parsevCalendar($data)
    {
        return parent::parsevCalendar($data, 'VNOTE');
    }

    /**
     * Unlike vevent and vtodo, a vnote is normally not enclosed in an
     * iCalendar container. (BEGIN..END)
     */
    function exportvCalendar()
    {
        $requiredAttributes['BODY'] = '';
        $requiredAttributes['VERSION'] = '1.1';

        foreach ($requiredAttributes as $name => $default_value) {
            if (is_a($this->getattribute($name), 'PEAR_Error')) {
                $this->setAttribute($name, $default_value);
            }
        }

        return $this->_exportvData('VNOTE') . $this->_newline;
    }

}
