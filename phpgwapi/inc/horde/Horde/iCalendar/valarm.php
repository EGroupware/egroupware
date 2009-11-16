<?php
/**
 * Class representing vAlarms.
 *
 * $Horde: framework/iCalendar/iCalendar/valarm.php,v 1.8.10.8 2008/07/03 08:42:58 jan Exp $
 *
 * Copyright 2003-2008 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @since   Horde 3.0
 * @package Horde_iCalendar
 */
class Horde_iCalendar_valarm extends Horde_iCalendar {

    function getType()
    {
        return 'vAlarm';
    }

    function exportvCalendar()
    {
        return parent::_exportvData('VALARM');
    }

}
