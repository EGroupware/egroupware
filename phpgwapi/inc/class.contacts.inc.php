<?php
  /*
     phpgw_contacts (
       contact_id          int,
       contact_owner       int,
       contact_name        varchar(255),
       contact_value       varchar(255)
     );
  */

  class contacts
  {
     var $db;
     var $account_id;
     var $stock_addressbook_fields;     // This is an array of all the fields in the addressbook
     var $total_records;

     function contacts()
     {
        global $phpgw, $phpgw_info;

        $this->db         = $phpgw->db;
        $this->account_id = $phpgw_info["user"]["account_id"];
        $this->stock_addressbook_fields = array("firstname" => "firstname",
                                                "lastname"  => "lastname",
                                                "email"     => "email",
                                                "hphone"    => "hphone",
                                                "wphone"    => "wphone",
                                                "fax"       => "fax",
                                                "pager"     => "pager",
                                                "mphone"    => "mphone",
                                                "ophone"    => "ophone",
                                                "street"    => "street",
                                                "city"      => "city",
                                                "state"     => "state",
                                                "zip"       => "zip",
                                                "bday"      => "bday",
                                                "notes"     => "notes",
                                                "company"   => "company",
                                                "title"     => "title",
                                                "address2"  => "address2",
                                                "url"       => "url"
                                               );
     }

     function split_ab_and_extras($fields)
     {
        while (list($field,$value) = each($fields)) {
           // Depending on how the array was build, this is needed.
           // Yet, I can't figure out why ....
           if (gettype($field) == "integer") {
              $field = $value;
           }
           if ($this->stock_addressbook_fields[$field]) {
              $ab_fields[$field]     = $value;
              $ab_fieldnames[$field] = $field;
           } else {
              $extra_fields[$field] = $value;
           }
        }
        return array($ab_fields,$ab_fieldnames,$extra_fields);
     }

     function read_single_entry($id,$fields)
     {
        list($ab_fields,$ab_fieldnames,$extra_fields) = $this->split_ab_and_extras($fields);
        if (count($ab_fieldnames)) {
           $t_fields = ",ab_" . implode(",ab_",$ab_fieldnames);
           if ($t_fields == ",ab_") {
              unset($t_fields);
           }
        }

        $this->db2 = $this->db;
 
        $this->db->query("select ab_id,ab_owner,ab_access $t_fields from addressbook WHERE ab_id='$id'");
        $this->db->next_record();
       
        $return_fields[0]["id"]     = $this->db->f("ab_id");
        $return_fields[0]["owner"]  = $this->db->f("ab_owner");
        $return_fields[0]["access"] = $this->db->f("ab_access");
        if (gettype($ab_fieldnames) == "array") {
          while (list($f_name) = each($ab_fieldnames)) {
            $return_fields[0][$f_name] = $this->db->f("ab_" . $f_name);
          }
        }
        $this->db2->query("select contact_name,contact_value from phpgw_contacts where contact_id='"
                           . $this->db->f("ab_id") . "'",__LINE__,__FILE__);
        while ($this->db2->next_record()) {
          // If its not in the list to be return, don't return it.
          // This is still quicker then 5(+) seperate querys
          if ($extra_fields[$this->db2->f("contact_name")]) {
            $return_fields[0][$this->db2->f("contact_name")] = $this->db2->f("contact_value");
          }
        }

        return $return_fields;
     }

     function read($start,$offset,$access,$filters,$fields)
     {
        list($ab_fields,$ab_fieldnames,$extra_fields) = $this->split_ab_and_extras($fields);
        if (count($ab_fieldnames)) {
           $t_fields = ",ab_" . implode(",ab_",$ab_fieldnames);
           if ($t_fields == ",ab_") {
              unset($t_fields);
           }
        }

        $this->db->query("select ab_id,ab_owner,ab_access $t_fields from addressbook "
                       . $filters,__LINE__,__FILE__);
        $this->total_records = $this->db->num_rows();
	
        $i = 0;
        $this->db2 = $this->db;        // Create new result object before our query

        $this->db->query("select ab_id,ab_owner,ab_access $t_fields from addressbook "
                       . $filters . $this->db->limit($start,$offset),__LINE__,__FILE__);
        while ($this->db->next_record()) {
           $return_fields[$i]["id"]     = $this->db->f("ab_id");
           $return_fields[$i]["owner"]  = $this->db->f("ab_owner");
           $return_fields[$i]["access"] = $this->db->f("ab_access");
           if (gettype($ab_fieldnames) == "array") {
              while (list($f_name) = each($ab_fieldnames)) {
                 $return_fields[$i][$f_name] = $this->db->f("ab_" . $f_name);
              }
              reset($ab_fieldnames);
           }

           $this->db2->query("select contact_name,contact_value from phpgw_contacts where contact_id='"
                           . $this->db->f("ab_id") . "'",__LINE__,__FILE__);
           while ($this->db2->next_record()) {
              // If its not in the list to be return, don't return it.
              // This is still quicker then 5(+) seperate querys
              if ($extra_fields[$this->db2->f("contact_name")]) {
                 $return_fields[$i][$this->db2->f("contact_name")] = $this->db2->f("contact_value");
              }
           }
           $i++;
        }
        
        return $return_fields;
     }

     function loop_addslashes($fields)
     {
        $absf = $this->stock_addressbook_fields;
        while ($t = each($absf)) {
           $ta[] = addslashes($fields[$t[0]]);
        }
        reset($absf);        // Is this needed ?
        return $ta;
     }

     function add($owner,$access,$fields)
     {
        list($ab_fields,$ab_fieldnames,$extra_fields) = $this->split_ab_and_extras($fields);

        //$this->db->lock(array("phpgw_addressbook"));
        $this->db->query("insert into addressbook (ab_owner,ab_access,ab_"
                       . implode(",ab_",$this->stock_addressbook_fields)
                       . ") values ('$owner','$access','"
                       . implode("','",$this->loop_addslashes($ab_fields)) . "')",__LINE__,__FILE__);

        $this->db->query("select max(ab_id) from addressbook",__LINE__,__FILE__);
        $this->db->next_record();
        $ab_id = $this->db->f(0);
        //$this->db->unlock();

        if (count($extra_fields)) {
           while (list($name,$value) = each($extra_fields)) {
              $this->db->query("insert into phpgw_contacts values ('$ab_id','" . $this->account_id . "','"
                             . addslashes($name) . "','" . addslashes($value) . "')",__LINE__,__FILE__);
           }
        }
     }

     function field_exists($id,$field_name)
     {
        $this->db->query("select count(*) from phpgw_contacts where contact_id='$id' and contact_name='"
                       . addslashes($field_name) . "'",__LINE__,__FILE__);
        $this->db->next_record();
        return $this->db->f(0);
     }

     function add_single_extra_field($id,$owner,$field_name,$field_value)
     {
        $this->db->query("insert into phpgw_contacts values ($id,'$owner','" . addslashes($field_name)
                       . "','" . addslashes($field_value) . "')",__LINE__,__FILE__);
     }

     function delete_single_extra_field($id,$field_name)
     {
        $this->db->query("delete from phpgw_contacts where contact_id='$id' and contact_name='"
                       . addslashes($field_name) . "'",__LINE__,__FILE__);
     }

     function update($id,$owner,$access,$fields)
     {
        // First make sure that id number exists
        $this->db->query("select count(*) from addressbook where ab_id='$id'",__LINE__,__FILE__);
        $this->db->next_record();
        if (! $this->db->f(0)) {
           return False;
        }

        list($ab_fields,$ab_fieldnames,$extra_fields) = $this->split_ab_and_extras($fields);
        if (count($ab_fields)) {
           while (list($ab_fieldname) = each($ab_fieldnames)) {
              $ta[] = $ab_fieldname . "='" . addslashes($ab_fields[$ab_fieldname]) . "'";
           }
           $fields_s = ",ab_" . implode(",ab_",$ta);
           if ($field_s == ",") {
              unset($field_s);
           }
           $this->db->query("update addressbook set ab_owner='$owner',ab_access='$access' $fields_s where "
                          . "ab_id='$id'",__LINE__,__FILE__);
        }

        while (list($x_name,$x_value) = each($extra_fields)) {
           if ($this->field_exists($id,$x_name)) {
              if (! $x_value) {
                 $this->delete_single_extra_field($id,$x_name);
              } else {
                 $this->db->query("update phpgw_contacts set contact_value='" . addslashes($x_value)
                                . "',contact_owner='$owner' where contact_name='" . addslashes($x_name)
                                . "' and contact_id='$id'",__LINE__,__FILE__);
              }
           } else {
              $this->add_single_extra_field($id,$owner,$x_name,$x_value);
           }
        }
     }

     // This is where the real work of delete() is done
     function delete_($id)
     {
        $this->db->query("delete from addressbook where ab_owner='" . $this->account_id . "' and "
                       . "ab_id='$id'",__LINE__,__FILE__);
        $this->db->query("delete from phpgw_contacts where contact_id='$id' and contact_owner='"
                       . $this->account_id . "'",__LINE__,__FILE__);
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
