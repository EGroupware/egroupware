<?php

include_once 'Horde/SyncML/State.php';
include_once 'Horde/SyncML/Command.php';
include_once 'Horde/SyncML/Command/Results.php';

define('DEFAULT_DEFINF_10', '<DevInf xmlns="syncml:devinf"><VerDTD>1.0</VerDTD><Man>The Horde Framework</Man><DevID>4711</DevID><DevTyp>workstation</DevTyp><DataStore><SourceRef>./contacts</SourceRef><Rx-Pref><CTType>text/x-vcard</CTType><VerCT>2.1</VerCT></Rx-Pref><Tx-Pref><CTType>text/x-vcard</CTType><VerCT>2.1</VerCT></Tx-Pref><SyncCap><SyncType>1</SyncType><SyncType>2</SyncType><SyncType>3</SyncType><SyncType>4</SyncType><SyncType>5</SyncType><SyncType>6</SyncType><SyncType>7</SyncType></SyncCap></DataStore><DataStore><SourceRef>./calendar</SourceRef><Rx-Pref><CTType>text/x-vcalendar</CTType><VerCT>2.0</VerCT></Rx-Pref><Rx><CTType>text/x-vcalendar</CTType><VerCT>1.0</VerCT></Rx><Tx-Pref><CTType>text/x-vcalendar</CTType><VerCT>2.0</VerCT></Tx-Pref><Tx><CTType>text/x-vcalendar</CTType><VerCT>1.0</VerCT></Tx><SyncCap><SyncType>1</SyncType><SyncType>2</SyncType><SyncType>3</SyncType><SyncType>4</SyncType><SyncType>5</SyncType><SyncType>6</SyncType><SyncType>7</SyncType></SyncCap></DataStore><CTCap><CTType>text/x-vcalendar</CTType><PropName>BEGIN</PropName><ValEnum>VCALENDAR</ValEnum><ValEnum>VEVENT</ValEnum><ValEnum>VTODO</ValEnum><PropName>DTSTART</PropName><PropName>DTEND</PropName><PropName>DTSTAMP</PropName><PropName>SEQUENCE</PropName><PropName>END</PropName><ValEnum>VCALENDAR</ValEnum><ValEnum>VEVENT</ValEnum><ValEnum>VTODO</ValEnum><PropName>UID</PropName><PropName>SUMMARY</PropName><PropName>VERSION</PropName><ValEnum>1.0</ValEnum><PropName>AALARM</PropName><PropName>CATEGORIES</PropName><PropName>CLASS</PropName><PropName>DALARM</PropName><PropName>EXDATE</PropName><PropName>RESOURCES</PropName><PropName>STATUS</PropName><PropName>ATTACH</PropName><PropName>ATTENDEE</PropName><PropName>DCREATED</PropName><PropName>COMPLETED</PropName><PropName>DESCRIPTION</PropName><PropName>DUE</PropName><PropName>LAST-MODIFIED</PropName><PropName>LOCATION</PropName><PropName>PRIORITY</PropName><PropName>RELATED-TO</PropName><PropName>RRULE</PropName><PropName>TRANSP</PropName><PropName>URL</PropName></CTCap><CTCap><CTType>text/calendar</CTType><PropName>BEGIN</PropName><ValEnum>VCALENDAR</ValEnum><ValEnum>VEVENT</ValEnum><ValEnum>VTODO</ValEnum><ValEnum>VALARM</ValEnum><PropName>DTSTART</PropName><PropName>DTEND</PropName><PropName>DTSTAMP</PropName><PropName>SEQUENCE</PropName><PropName>END</PropName><ValEnum>VCALENDAR</ValEnum><ValEnum>VEVENT</ValEnum><ValEnum>VTODO</ValEnum><ValEnum>VALARM</ValEnum><PropName>UID</PropName><PropName>SUMMARY</PropName><PropName>VERSION</PropName><ValEnum>2.0</ValEnum><PropName>CATEGORIES</PropName><PropName>CLASS</PropName><PropName>DALARM</PropName><PropName>EXDATE</PropName><PropName>RESOURCES</PropName><PropName>STATUS</PropName><PropName>ATTACH</PropName><PropName>ATTENDEE</PropName><PropName>DCREATED</PropName><PropName>COMPLETED</PropName><PropName>DESCRIPTION</PropName><PropName>DUE</PropName><PropName>LAST-MODIFIED</PropName><PropName>LOCATION</PropName><PropName>PRIORITY</PropName><PropName>RELATED-TO</PropName><PropName>TRANSP</PropName><PropName>URL</PropName><PropName>RRULE</PropName><PropName>COMMMENT</PropName><PropName>ACTION</PropName><PropName>TRIGGER</PropName><PropName>DURATION</PropName><PropName>REPEAT</PropName></CTCap><CTCap><CTType>text/x-vcard</CTType><PropName>BEGIN</PropName><ValEnum>VCARD</ValEnum><PropName>END</PropName><ValEnum>VCARD</ValEnum><PropName>VERSION</PropName><ValEnum>2.1</ValEnum><PropName>ENCODING</PropName><PropName>VALUE</PropName><PropName>CHARSET</PropName><PropName>FN</PropName><PropName>N</PropName><PropName>NAME</PropName><PropName>NICKNAME</PropName><PropName>PHOTO</PropName><PropName>BDAY</PropName><PropName>ADR</PropName><PropName>LABEL</PropName><PropName>TEL</PropName><PropName>EMAIL</PropName><PropName>MAILER</PropName><PropName>TZ</PropName><PropName>GEO</PropName><PropName>TITLE</PropName><PropName>ROLE</PropName><PropName>LOGO</PropName><PropName>AGENT</PropName><PropName>ORG</PropName><PropName>CATEGORIES</PropName><PropName>NOTE</PropName><PropName>PRODID</PropName><PropName>REV</PropName><PropName>SORT-STRING</PropName><PropName>SOUND</PropName><PropName>URL</PropName><PropName>UID</PropName><PropName>CLASS</PropName><PropName>KEY</PropName></CTCap></DevInf>');
define('DEFAULT_DEFINF_11', '<DevInf xmlns="syncml:devinf"><VerDTD>1.1</VerDTD><Man>The Horde Framework</Man><DevID>4711</DevID><DevTyp>workstation</DevTyp><DataStore><SourceRef>./contacts</SourceRef><Rx-Pref><CTType>text/x-vcard</CTType><VerCT>2.1</VerCT></Rx-Pref><Tx-Pref><CTType>text/x-vcard</CTType><VerCT>2.1</VerCT></Tx-Pref><SyncCap><SyncType>1</SyncType><SyncType>2</SyncType><SyncType>3</SyncType><SyncType>4</SyncType><SyncType>5</SyncType><SyncType>6</SyncType><SyncType>7</SyncType></SyncCap></DataStore><DataStore><SourceRef>./calendar</SourceRef><Rx-Pref><CTType>text/x-vcalendar</CTType><VerCT>2.0</VerCT></Rx-Pref><Rx><CTType>text/x-vcalendar</CTType><VerCT>1.0</VerCT></Rx><Tx-Pref><CTType>text/x-vcalendar</CTType><VerCT>2.0</VerCT></Tx-Pref><Tx><CTType>text/x-vcalendar</CTType><VerCT>1.0</VerCT></Tx><SyncCap><SyncType>1</SyncType><SyncType>2</SyncType><SyncType>3</SyncType><SyncType>4</SyncType><SyncType>5</SyncType><SyncType>6</SyncType><SyncType>7</SyncType></SyncCap></DataStore><CTCap><CTType>text/x-vcalendar</CTType><PropName>BEGIN</PropName><ValEnum>VCALENDAR</ValEnum><ValEnum>VEVENT</ValEnum><ValEnum>VTODO</ValEnum><PropName>DTSTART</PropName><PropName>DTEND</PropName><PropName>DTSTAMP</PropName><PropName>SEQUENCE</PropName><PropName>END</PropName><ValEnum>VCALENDAR</ValEnum><ValEnum>VEVENT</ValEnum><ValEnum>VTODO</ValEnum><PropName>UID</PropName><PropName>SUMMARY</PropName><PropName>VERSION</PropName><ValEnum>1.0</ValEnum><PropName>AALARM</PropName><PropName>CATEGORIES</PropName><PropName>CLASS</PropName><PropName>DALARM</PropName><PropName>EXDATE</PropName><PropName>RESOURCES</PropName><PropName>STATUS</PropName><PropName>ATTACH</PropName><PropName>ATTENDEE</PropName><PropName>DCREATED</PropName><PropName>COMPLETED</PropName><PropName>DESCRIPTION</PropName><PropName>DUE</PropName><PropName>LAST-MODIFIED</PropName><PropName>LOCATION</PropName><PropName>PRIORITY</PropName><PropName>RELATED-TO</PropName><PropName>RRULE</PropName><PropName>TRANSP</PropName><PropName>URL</PropName></CTCap><CTCap><CTType>text/calendar</CTType><PropName>BEGIN</PropName><ValEnum>VCALENDAR</ValEnum><ValEnum>VEVENT</ValEnum><ValEnum>VTODO</ValEnum><ValEnum>VALARM</ValEnum><PropName>DTSTART</PropName><PropName>DTEND</PropName><PropName>DTSTAMP</PropName><PropName>SEQUENCE</PropName><PropName>END</PropName><ValEnum>VCALENDAR</ValEnum><ValEnum>VEVENT</ValEnum><ValEnum>VTODO</ValEnum><ValEnum>VALARM</ValEnum><PropName>UID</PropName><PropName>SUMMARY</PropName><PropName>VERSION</PropName><ValEnum>2.0</ValEnum><PropName>CATEGORIES</PropName><PropName>CLASS</PropName><PropName>DALARM</PropName><PropName>EXDATE</PropName><PropName>RESOURCES</PropName><PropName>STATUS</PropName><PropName>ATTACH</PropName><PropName>ATTENDEE</PropName><PropName>DCREATED</PropName><PropName>COMPLETED</PropName><PropName>DESCRIPTION</PropName><PropName>DUE</PropName><PropName>LAST-MODIFIED</PropName><PropName>LOCATION</PropName><PropName>PRIORITY</PropName><PropName>RELATED-TO</PropName><PropName>TRANSP</PropName><PropName>URL</PropName><PropName>RRULE</PropName><PropName>COMMMENT</PropName><PropName>ACTION</PropName><PropName>TRIGGER</PropName><PropName>DURATION</PropName><PropName>REPEAT</PropName></CTCap><CTCap><CTType>text/x-vcard</CTType><PropName>BEGIN</PropName><ValEnum>VCARD</ValEnum><PropName>END</PropName><ValEnum>VCARD</ValEnum><PropName>VERSION</PropName><ValEnum>2.1</ValEnum><PropName>ENCODING</PropName><PropName>VALUE</PropName><PropName>CHARSET</PropName><PropName>FN</PropName><PropName>N</PropName><PropName>NAME</PropName><PropName>NICKNAME</PropName><PropName>PHOTO</PropName><PropName>BDAY</PropName><PropName>ADR</PropName><PropName>LABEL</PropName><PropName>TEL</PropName><PropName>EMAIL</PropName><PropName>MAILER</PropName><PropName>TZ</PropName><PropName>GEO</PropName><PropName>TITLE</PropName><PropName>ROLE</PropName><PropName>LOGO</PropName><PropName>AGENT</PropName><PropName>ORG</PropName><PropName>CATEGORIES</PropName><PropName>NOTE</PropName><PropName>PRODID</PropName><PropName>REV</PropName><PropName>SORT-STRING</PropName><PropName>SOUND</PropName><PropName>URL</PropName><PropName>UID</PropName><PropName>CLASS</PropName><PropName>KEY</PropName></CTCap></DevInf>');
#define('DEFAULT_DEFINF', '<DevInf xmlns="syncml:devinf"><VerDTD>1.0</VerDTD><Man>The Horde Framework</Man><DevID>4711</DevID>'.
#'<DevTyp>workstation</DevTyp><DataStore><SourceRef>contacts</SourceRef><Rx-Pref><CTType>text/x-vcard</CTType><VerCT>2.1</VerCT>'.
#'</Rx-Pref><Tx-Pref><CTType>text/x-vcard</CTType><VerCT>2.1</VerCT></Tx-Pref><SyncCap><SyncType>1</SyncType><SyncType>2</SyncType><SyncType>7</SyncType></SyncCap>'.
#'</DataStore>'.
#
#'<DataStore><SourceRef>calendar</SourceRef><Rx-Pref><CTType>text/x-vcalendar</CTType><VerCT>2.0</VerCT></Rx-Pref><Rx>'.
#'<CTType>text/x-vcalendar</CTType><VerCT>1.0</VerCT></Rx><Tx-Pref><CTType>text/x-vcalendar</CTType><VerCT>2.0</VerCT></Tx-Pref>'.
#'<Tx><CTType>text/x-vcalendar</CTType><VerCT>1.0</VerCT></Tx><SyncCap><SyncType>1</SyncType><SyncType>7</SyncType></SyncCap></DataStore>'.
#
#'<CTCap><CTType>text/x-vcalendar</CTType><PropName>BEGIN</PropName><ValEnum>VCALENDAR</ValEnum><ValEnum>VEVENT</ValEnum>'.
#'<ValEnum>VTODO</ValEnum><PropName>DTSTART</PropName><PropName>DTEND</PropName><PropName>DTSTAMP</PropName><PropName>SEQUENCE</PropName>'.
#'<PropName>END</PropName><ValEnum>VCALENDAR</ValEnum><ValEnum>VEVENT</ValEnum><ValEnum>VTODO</ValEnum><PropName>UID</PropName><PropName>'.
#'SUMMARY</PropName><PropName>VERSION</PropName><ValEnum>1.0</ValEnum><PropName>AALARM</PropName><PropName>CATEGORIES</PropName><PropName>',
#'CLASS</PropName><PropName>DALARM</PropName><PropName>EXDATE</PropName><PropName>RESOURCES</PropName><PropName>STATUS</PropName><PropName>',
#'ATTACH</PropName><PropName>ATTENDEE</PropName><PropName>DCREATED</PropName><PropName>COMPLETED</PropName><PropName>DESCRIPTION</PropName>'.
#'<PropName>DUE</PropName><PropName>LAST-MODIFIED</PropName><PropName>LOCATION</PropName><PropName>PRIORITY</PropName>'.
#'<PropName>RELATED-TO</PropName><PropName>RRULE</PropName><PropName>TRANSP</PropName><PropName>URL</PropName></CTCap><CTCap>'.
#'<CTType>text/calendar</CTType><PropName>BEGIN</PropName><ValEnum>VCALENDAR</ValEnum><ValEnum>VEVENT</ValEnum><ValEnum>VTODO</ValEnum>'.
#'<ValEnum>VALARM</ValEnum><PropName>DTSTART</PropName><PropName>DTEND</PropName><PropName>DTSTAMP</PropName><PropName>SEQUENCE</PropName>'.
#'<PropName>END</PropName><ValEnum>VCALENDAR</ValEnum><ValEnum>VEVENT</ValEnum><ValEnum>VTODO</ValEnum><ValEnum>VALARM</ValEnum>'.
#'<PropName>UID</PropName><PropName>SUMMARY</PropName><PropName>VERSION</PropName><ValEnum>2.0</ValEnum><PropName>CATEGORIES</PropName>'.
#'<PropName>CLASS</PropName><PropName>DALARM</PropName><PropName>EXDATE</PropName><PropName>RESOURCES</PropName><PropName>STATUS</PropName>'.
#'<PropName>ATTACH</PropName><PropName>ATTENDEE</PropName><PropName>DCREATED</PropName><PropName>COMPLETED</PropName><PropName>DESCRIPTION'.
#'</PropName><PropName>DUE</PropName><PropName>LAST-MODIFIED</PropName><PropName>LOCATION</PropName><PropName>PRIORITY</PropName>'.
#'<PropName>RELATED-TO</PropName><PropName>TRANSP</PropName><PropName>URL</PropName><PropName>RRULE</PropName><PropName>COMMMENT</PropName>'.
#'<PropName>ACTION</PropName><PropName>TRIGGER</PropName><PropName>DURATION</PropName><PropName>REPEAT</PropName></CTCap><CTCap>'.
#
#'<CTType>text/x-vcard</CTType><PropName>BEGIN</PropName><ValEnum>VCARD</ValEnum><PropName>END</PropName><ValEnum>VCARD</ValEnum><PropName>'.
#'VERSION</PropName><ValEnum>2.1</ValEnum><PropName>ENCODING</PropName><PropName>VALUE</PropName><PropName>CHARSET</PropName>'.
#'<PropName>FN</PropName><PropName>N</PropName><PropName>NAME</PropName><PropName>NICKNAME</PropName><PropName>PHOTO</PropName>'.
#'<PropName>BDAY</PropName><PropName>ADR</PropName><PropName>LABEL</PropName><PropName>TEL</PropName><PropName>EMAIL</PropName>'.
#'<PropName>MAILER</PropName><PropName>TZ</PropName><PropName>GEO</PropName><PropName>TITLE</PropName><PropName>ROLE</PropName>'.
#'<PropName>LOGO</PropName><PropName>AGENT</PropName><PropName>ORG</PropName><PropName>CATEGORIES</PropName><PropName>NOTE</PropName>'.
#'<PropName>PRODID</PropName><PropName>REV</PropName><PropName>SORT-STRING</PropName><PropName>SOUND</PropName><PropName>URL</PropName>'.
#'<PropName>UID</PropName><PropName>CLASS</PropName><PropName>KEY</PropName></CTCap></DevInf>');

/**
 * The Horde_SyncML_Command_Get class.
 *
 * $Horde: framework/SyncML/SyncML/Command/Get.php,v 1.14 2004/07/02 19:24:44 chuck Exp $
 *
 * Copyright 2003-2004 Anthony Mills <amills@pyramid6.com>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Anthony Mills <amills@pyramid6.com>
 * @author  Karsten Fourmont <fourmont@gmx.de>
 * @version $Revision$
 * @since   Horde 3.0
 * @package Horde_SyncML
 */
class Horde_SyncML_Command_Get extends Horde_SyncML_Command {

    function output($currentCmdID, &$output)
    {
        $state = $_SESSION['SyncML.state'];

        $ref = ($state->getVersion() == 0) ? './devinf10' : './devinf11';

        $status = &new Horde_SyncML_Command_Status((($state->isAuthorized()) ? RESPONSE_OK : RESPONSE_INVALID_CREDENTIALS), 'Get');
        $status->setCmdRef($this->_cmdID);
        $status->setTargetRef($ref);
        $currentCmdID = $status->output($currentCmdID, $output);
	Horde::logMessage('SyncML: end output ref: '.$ref, __FILE__, __LINE__, PEAR_LOG_DEBUG);

        // Currently DEVINF seems to be ok only for SyncML 1.0. But
        // this is used by P800/P900 and these seem to require it:
        if ($state->isAuthorized() && $state->getVersion() == 0) {
            $results = &new Horde_SyncML_Command_Results();
            $results->setCmdRef($this->_cmdID);
            $results->setType("application/vnd.syncml-devinf+xml");
            $results->setlocSourceURI($ref);
            $results->setData(DEFAULT_DEFINF_10);

            $currentCmdID = $results->output($currentCmdID, $output);
        }
        elseif($state->isAuthorized() && $state->getVersion() == 1)
        {
            $results = &new Horde_SyncML_Command_Results();
            $results->setCmdRef($this->_cmdID);
            $results->setType("application/vnd.syncml-devinf+xml");
            $results->setlocSourceURI($ref);
            $results->setData(DEFAULT_DEFINF_11);

            $currentCmdID = $results->output($currentCmdID, $output);
            
        }

        return $currentCmdID;
    }

}
