<?php
  /**************************************************************************\
  * phpGroupWare API - Accounts manager for SQL                              *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * View and manipulate contact records                                      *
  * Copyright (C) 2001 Joseph Engo                                           *
  * -------------------------------------------------------------------------*
  * This library is part of the phpGroupWare API                             *
  * http://www.phpgroupware.org/api                                          * 
  * ------------------------------------------------------------------------ *
  * This library is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published by *
  * the Free Software Foundation; either version 2.1 of the License,         *
  * or any later version.                                                    *
  * This library is distributed in the hope that it will be useful, but      *
  * WITHOUT ANY WARRANTY; without even the implied warranty of               *
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
  * See the GNU Lesser General Public License for more details.              *
  * You should have received a copy of the GNU Lesser General Public License *
  * along with this library; if not, write to the Free Software Foundation,  *
  * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
  \**************************************************************************/

  /* $Id$ */


  /*
     phpgw_contacts (
       contact_id          int,
       contact_owner       int,
       contact_name        varchar(255),
       contact_value       varchar(255)
     );
  */

  class contacts extends contacts_
  {
     var $db;
     var $account_id;
     var $stock_contact_fields;     // This is an array of all the fields in the addressbook
     var $email_types;
     var $total_records;

     function split_ab_and_extras($fields)
     {
        while (list($field,$value) = each($fields)) {
           // Depending on how the array was build, this is needed.
           // Yet, I can't figure out why ....
           if (gettype($field) == "integer") {
              $field = $value;
           }
           if ($this->stock_contact_fields[$field]) {
              $ab_fields[$field]     = $value;
              $ab_fieldnames[$field] = $field;
           } else {
              $extra_fields[$field] = $value;
           }
        }
        return array($ab_fields,$ab_fieldnames,$extra_fields);
     }

     function loop_addslashes($fields)
     {
        $absf = $this->stock_contact_fields;
        while ($t = each($absf)) {
           $ta[] = addslashes($fields[$t[0]]);
        }
        reset($absf);        // Is this needed ?
        return $ta;
     }

     // This will take an array or integer
     function delete($id)
     {
        if (gettype($id) == "array") {
           while (list($null,$t_id) = each($id)) {
              $this->delete_($t_id);
           }
        } else {
           $this->delete_($id);
        }
     }

  }
?>
