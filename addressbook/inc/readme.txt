
function read($start,$offset,$fields,$query="",$sort="",$order="")
	$start = start of list, e.g. 1,16,31
	$offset = numrows, e.g. 15,30,etc.
	$fields = simple array of fields to return
	$query = simple string to search for
	$sort = ASC, DESC, or ""
	$order = sort on this field, e.g. N_Given

	returns an array of name/values, e.g.:
		$fields[0]["d_email"] => "name@domain.com"
		...
		$fields[1]["d_email"] => "othername@otherdomain.com"
		...

function read_single_entry($id,$fields)
	$id = id of entry you want to return
	$fields = simple array of fields to return

	returns a single array of name/value, e.g.:
		$fields[0]["d_email"] => "name@domain.com"
		$fields[0]["n_given"] => "Bob"

function add($owner,$fields)
	$owner = lid of user adding this data
	$fields = assoc array of fields to write into the new record

function update($id,$owner,$fields)
	$id = id of entry you want to update
	$owner = lid of user modifying this data
	$fields = assoc array of fields to update in the record

function delete_($id)
	$id = id of entry you want to delete


        $this->stock_contact_fields = array(
            "fn"              => "fn",        //'firstname lastname'
            "sound"           => "sound",
            "org_name"        => "org_name",  //company
            "org_unit"        => "org_unit",  //division
            "title"           => "title",
            "n_given"         => "n_given",   //firstname
            "n_family"        => "n_family",  //lastname
            "n_middle"        => "n_middle",
            "n_prefix"        => "n_prefix",
            "n_suffix"        => "n_suffix",
            "label"           => "label",
            "adr_street"      => "adr_street",
            "adr_locality"    => "adr_locality",   //city
            "adr_region"      => "adr_region",     //state
            "adr_postalcode"  => "adr_postalcode", //zip
            "adr_countryname" => "adr_countryname",
            "adr_work"        => "adr_work",   //yn
            "adr_home"        => "adr_home",   //yn
            "adr_parcel"      => "adr_parcel", //yn
            "adr_postal"      => "adr_postal", //yn
            "tz"              => "tz",
            "geo"             => "geo",
            "a_tel"           => "a_teL",
            "a_tel_work"      => "a_tel_work",   //yn
            "a_tel_home"      => "a_tel_home",   //yn
            "a_tel_voice"     => "a_tel_voice",  //yn
            "a_tel_msg"       => "a_tel_msg",    //yn
            "a_tel_fax"       => "a_tel_fax",    //yn
            "a_tel_prefer"    => "a_tel_prefer", //yn
            "b_tel"           => "b_tel",
            "b_tel_work"      => "b_tel_work",   //yn
            "b_tel_home"      => "b_tel_home",   //yn
            "b_tel_voice"     => "b_tel_voice",  //yn
            "b_tel_msg"       => "b_tel_msg",    //yn
            "b_tel_fax"       => "b_tel_fax",    //yn
            "b_tel_prefer"    => "b_tel_prefer", //yn
            "c_tel"           => "c_tel",
            "c_tel_work"      => "c_tel_work",   //yn
            "c_tel_home"      => "c_tel_home",   //yn
            "c_tel_voice"     => "c_tel_voice",  //yn
            "c_tel_msg"       => "c_tel_msg",    //yn
            "c_tel_fax"       => "c_tel_fax",    //yn
            "c_tel_prefer"    => "c_tel_prefer", //yn
            "d_email"         => "d_email",
            "d_emailtype"     => "d_emailtype",   //'INTERNET','CompuServe',etc...
            "d_email_work"    => "d_email_work",  //yn
            "d_email_home"    => "d_email_home",  //yn
        );

        $this->email_types = array(
            "INTERNET"   => "INTERNET",
            "CompuServe" => "CompuServe",
            "AOL"        => "AOL",
            "Prodigy"    => "Prodigy",
            "eWorld"     => "eWorld",
            "AppleLink"  => "AppleLink",
            "AppleTalk"  => "AppleTalk",
            "PowerShare" => "PowerShare",
            "IBMMail"    => "IBMMail",
            "ATTMail"    => "ATTMail",
            "MCIMail"    => "MCIMail",
            "X.400"      => "X.400",
            "TLX"        => "TLX"
        );
