
function read($start,$offset,$fields,$query="",$sort="",$order="")
	$start = start of list, e.g. 1,16,31
	$offset = numrows, e.g. 15,30,etc.
	$fields = simple array of fields to return
	$query = simple string to search for
	$sort = ASC, DESC, or ""
	$order = sort on this field, e.g. N_Given

	returns an array of name/values, e.g.:
		$fields[0]["D_EMAIL"] => "name@domain.com"
		...
		$fields[1]["D_EMAIL"] => "othername@otherdomain.com"
		...

function read_single_entry($id,$fields)
	$id = id of entry you want to return
	$fields = simple array of fields to return

	returns a single array of name/value, e.g.:
		$fields[0]["D_EMAIL"] => "name@domain.com"
		$fields[0]["N_Given"] => "Bob"

function add($owner,$fields)
	$owner = lid of user adding this data
	$fields = assoc array of fields to write into the new record

function update($id,$owner,$fields)
	$id = id of entry you want to update
	$owner = lid of user modifying this data
	$fields = assoc array of fields to update in the record

function delete_($id)
	$id = id of entry you want to delete


        $this->stock_contact_fields = array("FN"              => "FN",        //'firstname lastname'
                                            "SOUND"           => "SOUND",
                                            "ORG_Name"        => "ORG_Name",  //company
                                            "ORG_Unit"        => "ORG_Unit",  //division
                                            "TITLE"           => "TITLE",
                                            "N_Given"         => "N_Given",   //firstname
                                            "N_Family"        => "N_Family",  //lastname
                                            "N_Middle"        => "N_Middle",
                                            "N_Prefix"        => "N_Prefix",
                                            "N_Suffix"        => "N_Suffix",
                                            "LABEL"           => "LABEL",
                                            "ADR_Street"      => "ADR_Street",
                                            "ADR_Locality"    => "ADR_Locality",   //city
                                            "ADR_Region"      => "ADR_Region",     //state
                                            "ADR_PostalCode"  => "ADR_PostalCode", //zip
                                            "ADR_CountryName" => "ADR_CountryName",
                                            "ADR_Work"        => "ADR_Work",   //yn
                                            "ADR_Home"        => "ADR_Home",   //yn
                                            "ADR_Parcel"      => "ADR_Parcel", //yn
                                            "ADR_Postal"      => "ADR_Postal", //yn
                                            "TZ"              => "TZ",
                                            "GEO"             => "GEO",
                                            "A_TEL"           => "A_TEL",
                                            "A_TEL_Work"      => "A_TEL_Work",   //yn
                                            "A_TEL_Home"      => "A_TEL_Home",   //yn
                                            "A_TEL_Voice"     => "A_TEL_Voice",  //yn
                                            "A_TEL_Msg"       => "A_TEL_Msg",    //yn
                                            "A_TEL_Fax"       => "A_TEL_Fax",    //yn
                                            "A_TEL_Prefer"    => "A_TEL_Prefer", //yn
                                            "B_TEL"           => "B_TEL",
                                            "B_TEL_Work"      => "B_TEL_Work",   //yn
                                            "B_TEL_Home"      => "B_TEL_Home",   //yn
                                            "B_TEL_Voice"     => "B_TEL_Voice",  //yn
                                            "B_TEL_Msg"       => "B_TEL_Msg",    //yn
                                            "B_TEL_Fax"       => "B_TEL_Fax",    //yn
                                            "B_TEL_Prefer"    => "B_TEL_Prefer", //yn
                                            "C_TEL"           => "C_TEL",
                                            "C_TEL_Work"      => "C_TEL_Work",   //yn
                                            "C_TEL_Home"      => "C_TEL_Home",   //yn
                                            "C_TEL_Voice"     => "C_TEL_Voice",  //yn
                                            "C_TEL_Msg"       => "C_TEL_Msg",    //yn
                                            "C_TEL_Fax"       => "C_TEL_Fax",    //yn
                                            "C_TEL_Prefer"    => "C_TEL_Prefer", //yn
                                            "D_EMAIL"         => "D_EMAIL",
                                            "D_EMAILTYPE"     => "D_EMAILTYPE",   //'INTERNET','CompuServe',etc...
                                            "D_EMAIL_Work"    => "D_EMAIL_Work",  //yn
                                            "D_EMAIL_Home"    => "D_EMAIL_Home",  //yn
                                            );

        $this->email_types = array("INTERNET"   => "INTERNET",
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
