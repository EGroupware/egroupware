
function read($start,$offset,$fields,$query="",$sort="",$order="")
	$start = start of list, e.g. 1,16,31
	$offset = numrows, e.g. 15,30,etc.
	$fields = simple array of fields to return
	$query = simple string to search for
	$sort = ASC, DESC, or ""
	$order = sort on this field, e.g. N_Given

	returns an array of name/values, e.g.:
		$fields[0]["email"] => "name@domain.com"
		...
		$fields[1]["email"] => "othername@otherdomain.com"
		...

function read_single_entry($id,$fields)
	$id = id of entry you want to return
	$fields = simple array of fields to return

	returns a single array of name/value, e.g.:
		$fields[0]["email"] => "name@domain.com"
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
				"fn"                     => "fn",        // 'prefix given middle family suffix'
				"n_given"                => "n_given",   // firstname
				"n_family"               => "n_family",  // lastname
				"n_middle"               => "n_middle",
				"n_prefix"               => "n_prefix",
				"n_suffix"               => "n_suffix",
				"sound"                  => "sound",
				"bday"                   => "bday",
				"note"                   => "note",
				"tz"                     => "tz",
				"geo"                    => "geo",
				"url"                    => "url",
				"pubkey"                 => "pubkey",

				"org_name"               => "org_name",  // company
				"org_unit"               => "org_unit",  // division
				"title"                  => "title",

				"adr_one_street"         => "adr_one_street",
				"adr_one_locality"       => "adr_one_locality", 
				"adr_one_region"         => "adr_one_region", 
				"adr_one_postalcode"     => "adr_one_postalcode",
				"adr_one_countryname"    => "adr_one_countryname",
				"adr_one_type"           => "adr_one_type", // address is domestic/intl/postal/parcel/work/home
				"label"                  => "label", // address label

				"adr_two_street"         => "adr_two_street",
				"adr_two_locality"       => "adr_two_locality", 
				"adr_two_region"         => "adr_two_region", 
				"adr_two_postalcode"     => "adr_two_postalcode",
				"adr_two_countryname"    => "adr_two_countryname",
				"adr_two_type"           => "adr_two_type", // address is domestic/intl/postal/parcel/work/home

				"tel_work"               => "tel_work",
				"tel_home"               => "tel_home",
				"tel_voice"              => "tel_voice",
				"tel_fax"                => "tel_fax", 
				"tel_msg"                => "tel_msg",
				"tel_cell"               => "tel_cell",
				"tel_pager"              => "tel_pager",
				"tel_bbs"                => "tel_bbs",
				"tel_modem"              => "tel_modem",
				"tel_car"                => "tel_car",
				"tel_isdn"               => "tel_isdn",
				"tel_video"              => "tel_video",
				"tel_prefer"             => "tel_prefer", // home, work, voice, etc
				"email"                  => "email",
				"email_type"             => "email_type", //'INTERNET','CompuServe',etc...
				"email_home"             => "email_home",
				"email_home_type"        => "email_home_type" //'INTERNET','CompuServe',etc...
			);

			$this->adr_types = array(
				"dom"    => lang("Domestic"),
				"intl"   => lang("International"),
				"parcel" => lang("Parcel"),
				"postal" => lang("Postal")
			);

			// Used to set preferred number field
			$this->tel_types = array(
				"work"  => "work",
				"home"  => "home",
				"voice" => "voice",
				"fax"   => "fax",
				"msg"   => "msg",
				"cell"  => "cell",
				"pager" => "pager",
				"bbs"   => "bbs",
				"modem" => "modem",
				"car"   => "car",
				"isdn"  => "isdn",
				"video" => "video"
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

