<?php
  /**************************************************************************\
  * phpGroupWare - addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */
  class addressbook extends addressbook_
  {
    function coldata($column,$listid) {
      if     ($column == "company")   return $this->company[$listid];
      elseif ($column == "firstname") return $this->firstname[$listid];
      elseif ($column == "lastname")  return $this->lastname[$listid];
      elseif ($column == "email")     return $this->email[$listid];
      elseif ($column == "wphone")    return $this->wphone[$listid];
      elseif ($column == "hphone")    return $this->hphone[$listid];
      elseif ($column == "fax")       return $this->fax[$listid];
      elseif ($column == "pager")     return $this->pager[$listid];
      elseif ($column == "mphone")    return $this->mphone[$listid];
      elseif ($column == "ophone")    return $this->ophone[$listid];
      elseif ($column == "street")    return $this->street[$listid];
      elseif ($column == "city")      return $this->city[$listid];
      elseif ($column == "state")     return $this->state[$listid];
      elseif ($column == "zip")       return $this->zip[$listid];
      elseif ($column == "bday")      return $this->bday[$listid];
      elseif ($column == "url")       return $this->url[$listid];
      else return "";
    }
  }
?>
