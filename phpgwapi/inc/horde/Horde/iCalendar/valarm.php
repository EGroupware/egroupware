<?php
/**
 * Class representing vAlarms.
 *
 * $Horde: framework/iCalendar/iCalendar/valarm.php,v 1.8 2004/08/13 19:11:35 karsten Exp $
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
class Horde_iCalendar_valarm extends Horde_iCalendar {

    function getType()
    {
        return 'vAlarm';
    }

    function parsevCalendar($data)
    {
        parent::parsevCalendar($data, 'VALARM');
    }

    function exportvCalendar()
    {
        return parent::_exportvData('VALARM');
    }

}
