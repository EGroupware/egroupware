<?php

include_once 'XML/WBXML/DTD.php';

/**
 * $Horde: framework/XML_WBXML/WBXML/DTD/SyncMLDevInf.php,v 1.11 2006/01/01 21:10:26 jan Exp $
 *
 * Copyright 2003-2006 Anthony Mills <amills@pyramid6.com>
 *
 * From Binary XML Content Format Specification Version 1.3, 25 July 2001
 * found at http://www.wapforum.org
 *
 * See the enclosed file COPYING for license information (LGPL).  If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @package XML_WBXML
 */
class XML_WBXML_DTD_SyncMLDevInf extends XML_WBXML_DTD {

    function init()
    {
        /* this code table has been extracted from libwbxml
         * (see http://libwbxml.aymerick.com/) by using
         *
         * grep '\"[^\"]*\", *0x.., 0x.. },' wbxml_tables.c
         * | sed -e 's#^.*\"\([^\"]*\)\", *\(0x..\), \(0x..\) },.*$#        \$this->setTag\(\3, \"\1\"\); // \2#g'
         */

        $this->setTag(0x05, "CTCap"); // 0x00
        $this->setTag(0x06, "CTType"); // 0x00
        $this->setTag(0x07, "DataStore"); // 0x00
        $this->setTag(0x08, "DataType"); // 0x00
        $this->setTag(0x09, "DevID"); // 0x00
        $this->setTag(0x0a, "DevInf"); // 0x00
        $this->setTag(0x0b, "DevTyp"); // 0x00
        $this->setTag(0x0c, "DisplayName"); // 0x00
        $this->setTag(0x0d, "DSMem"); // 0x00
        $this->setTag(0x0e, "Ext"); // 0x00
        $this->setTag(0x0f, "FwV"); // 0x00
        $this->setTag(0x10, "HwV"); // 0x00
        $this->setTag(0x11, "Man"); // 0x00
        $this->setTag(0x12, "MaxGUIDSize"); // 0x00
        $this->setTag(0x13, "MaxID"); // 0x00
        $this->setTag(0x14, "MaxMem"); // 0x00
        $this->setTag(0x15, "Mod"); // 0x00
        $this->setTag(0x16, "OEM"); // 0x00
        $this->setTag(0x17, "ParamName"); // 0x00
        $this->setTag(0x18, "PropName"); // 0x00
        $this->setTag(0x19, "Rx"); // 0x00
        $this->setTag(0x1a, "Rx-Pref"); // 0x00
        $this->setTag(0x1b, "SharedMem"); // 0x00
        $this->setTag(0x1c, "Size"); // 0x00
        $this->setTag(0x1d, "SourceRef"); // 0x00
        $this->setTag(0x1e, "SwV"); // 0x00
        $this->setTag(0x1f, "SyncCap"); // 0x00
        $this->setTag(0x20, "SyncType"); // 0x00
        $this->setTag(0x21, "Tx"); // 0x00
        $this->setTag(0x22, "Tx-Pref"); // 0x00
        $this->setTag(0x23, "ValEnum"); // 0x00
        $this->setTag(0x24, "VerCT"); // 0x00
        $this->setTag(0x25, "VerDTD"); // 0x00
        $this->setTag(0x26, "XNam"); // 0x00
        $this->setTag(0x27, "XVal"); // 0x00
        $this->setTag(0x28, "UTC"); // 0x00
        $this->setTag(0x29, "SupportNumberOfChanges"); // 0x00
        $this->setTag(0x2a, "SupportLargeObjs"); // 0x00

        if ($this->version == 0) {
            $this->setCodePage(0, '-//SYNCML//DTD DevInf 1.0//EN', 'syncml:devinf');
            $this->setURI('syncml:devinf');
        } else {
            $this->setCodePage(0, '-//SYNCML//DTD DevInf 1.1//EN', 'syncml:devinf1.1');
            $this->setURI('syncml:devinf1.1');
        }
    }

}
