<?php
  /**************************************************************************\
  * phpGroupWare API - VCard import/export class                             *
  * This file written by Miles Lott <milosch@phpgroupware.org>               *
  * Parse vcards->contacts class fields, and vice-versa                      *
  * Copyright (C) 2001 Miles Lott                                            *
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

	// Prelminary vcard import/export class
	// The method of calls to this should probably be cleaned up, but
	//   following are some examples:
	//
	class vcard
	{
		// This array is used by vcard->in to aid in parsing the multiple
		//   attributes that are possible with vcard.
		// Import is messier than export and needs to be changed.
		// You MUST use this in conjunction with the vcard->in function:
		//
		// $contacts = CreateObject("phpgwapi.contacts");
		// $vcard = CreateObject("phpgwapi.vcard");
		// $myimport = $vcard->import;
		// $buffer = array();
		//
		// $fp=fopen($filename,"r");
		// while ($data = fgets($fp,8000)) {
		//     list($name,$value,$extra) = split(':', $data);
		//     if (substr($value,0,5) == "http") {
		//         $value = $value . ":".$extra;
		//     }
		//     if ($name && $value) {
		//         reset($vcard->import);
		//         while ( list($fname,$fvalue) = each($vcard->import) ) {
		//             if ( strstr(strtolower($name), $vcard->import[$fname]) ) {
		//                 $value = trim($value);
		//                 $value = ereg_replace("=0D=0A","\n",$value);
		//                 $buffer += array($name => $value);
		//             }
		//         }
		//     }
		// }
		// fclose($fp);
		//
		// Then, to convert the vcard array to a contacts class array:
		//
		//$entry = $vcard->in($buffer);
		//$contacts->add($phpgw_info["user"]["account_id"],$entry);
		//
		var $import = array(
			"n"        => "n",
			"sound"    => "sound",
			"bday"     => "bday",
			"note"     => "note",
			"tz"       => "tz",
			"geo"      => "geo",
			"url"      => "url",
			"pubkey"   => "pubkey",
			"org"      => "org",
			"title"    => "title",
			"adr"      => "adr",
			"label"    => "label",
			"tel"      => "tel",
			"email"    => "email"
		);

		// This array is used by vcard->out to aid in parsing the multiple
		//   attributes that are possible with vcard.
		// You MUST use this in conjunction with the vcard->out function:
		//
		// $this->vcard = CreateObject("phpgwapi.vcard");
		// $myexport = $this->vcard->export;
		//
		// while( list($name,$value) = each($currentrecord) ) {
		//     if ($myexport[$name] && ($value != "") ) {
		//         $buffer[$i][$myexport[$name]] = $value;
		//     }
		// }
		//
		// Then, to convert the data array to a vcard string:
		//
		// for ($i=0;$i<count($buffer);$i++) {
		//     $vcards .= $this->vcard->out($buffer[$i]);
		// }
		//
		var $export = array(
			"fn"                  => "FN",
			"n_given"             => "N;GIVEN",
			"n_family"            => "N;FAMILY",
			"n_middle"            => "N;MIDDLE",
			"n_prefix"            => "N;PREFIX",
			"n_suffix"            => "N;SUFFIX",
			"sound"               => "SOUND",
			"bday"                => "BDAY",
			"note"                => "NOTE",
			"tz"                  => "TZ",
			"geo"                 => "GEO",
			"url"                 => "URL",
			"pubkey"              => "PUBKEY",
			"org_name"            => "ORG;NAME",
			"org_unit"            => "ORG;UNIT",
			"title"               => "TITLE",

			"adr_one_type"        => "ADR;TYPE;WORK",
			"adr_two_type"        => "ADR;TYPE;HOME",
			"tel_prefer"          => "TEL;PREFER",
			"email_type"          => "EMAIL;TYPE;WORK",
			"email_home_type"     => "EMAIL;TYPE;HOME",

			"adr_one_street"      => "ADR;WORK;STREET",
			"adr_one_locality"    => "ADR;WORK;LOCALITY", 
			"adr_one_region"      => "ADR;WORK;REGION", 
			"adr_one_postalcode"  => "ADR;WORK;POSTALCODE",
			"adr_one_countryname" => "ADR;WORK;COUNTRYNAME",
			"address2"            => "EXT",
			"label"               => "LABEL",

			"adr_two_street"      => "ADR;HOME;STREET",
			"adr_two_locality"    => "ADR;HOME;LOCALITY",
			"adr_two_region"      => "ADR;HOME;REGION",
			"adr_two_postalcode"  => "ADR;HOME;POSTALCODE",
			"adr_two_countryname" => "ADR;HOME;COUNTRYNAME",

			"tel_work"            => "TEL;WORK",
			"tel_home"            => "TEL;HOME",
			"tel_voice"           => "TEL;VOICE",
			"tel_fax"             => "TEL;FAX",
			"tel_msg"             => "TEL;MSG",
			"tel_cell"            => "TEL;CELL",
			"tel_pager"           => "TEL;PAGER",
			"tel_bbs"             => "TEL;BBS",
			"tel_modem"           => "TEL;MODEM",
			"tel_car"             => "TEL;CAR",
			"tel_isdn"            => "TEL;ISDN",
			"tel_video"           => "TEL;VIDEO",
			"email"               => "EMAIL;WORK",
			"email_home"          => "EMAIL;HOME",
		);

		var $names = array(
			"family" => "family",
			"given"  => "given",
			"middle" => "middle",
			"prefix" => "prefix",
			"suffix" => "suffix"
		);

		// Pass this an associative array of fieldnames and values
		// returns a clean array based on contacts class std fields
		// This array can then be passed via $phpgw->contacts->add($ownerid,$buffer)
		function in($buffer)
		{
			global $phpgw;
			// Following is a lot of pain and little magic
			while ( list($name,$value) = @each($buffer) ) {
				$field  = split(";",$name);
				$field[0] = ereg_replace("A\.","",$field[0]);
				$field[0] = ereg_replace("B\.","",$field[0]);
				$field[0] = ereg_replace("C\.","",$field[0]);
				$field[0] = ereg_replace("D\.","",$field[0]);
				$values = split(";",$value);
				if ($field[1]) {
					//echo $field[0];
					switch ($field[0]) {
						case "LABEL":
							$buffer["label"] = ereg_replace("=0D=0A","\n",$values[0]);
							break;
						case "NOTE":
							$buffer["note"] = ereg_replace("=0D=0A","\n",$values[0]);
							break;
						case "ADR":
							switch ($field[1]) {
								case "INTL":
									switch ($field[2]) {
										case "WORK":
											if ( !stristr($buffer["adr_one_type"],$field[1])) {
												$buffer["adr_one_type"] .= "intl;";
											}
											if (!$buffer["adr_one_street"]) {
												$buffer["address2"]            = $values[1];
												$buffer["adr_one_street"]      = $values[2];
												$buffer["adr_one_locality"]    = $values[3];
												$buffer["adr_one_region"]      = $values[4];
												$buffer["adr_one_postalcode"]  = $values[5];
												$buffer["adr_one_countryname"] = $values[6];
											}
											break;
										case "HOME":
											if ( !stristr($buffer["adr_two_type"],$field[1]) ) {
												$buffer["adr_two_type"] .= "intl;";
											}
											if (!$buffer["adr_two_street"]) {
												$buffer["adr_two_street"]      = $values[2];
												$buffer["adr_two_locality"]    = $values[3];
												$buffer["adr_two_region"]      = $values[4];
												$buffer["adr_two_postalcode"]  = $values[5];
												$buffer["adr_two_countryname"] = $values[6];
											}
											break;
										default:
											break;
									}
									break;
								case "DOM":
									switch ($field[2]) {
										case "WORK":
											if ( !stristr($buffer["adr_one_type"],$field[1])) {
												$buffer["adr_one_type"] .= "dom;";
											}
											if (!$buffer["adr_one_street"]) {
												$buffer["address2"]            = $values[1];
												$buffer["adr_one_street"]      = $values[2];
												$buffer["adr_one_locality"]    = $values[3];
												$buffer["adr_one_region"]      = $values[4];
												$buffer["adr_one_postalcode"]  = $values[5];
												$buffer["adr_one_countryname"] = $values[6];
											}
											break;
										case "HOME":
											if ( !stristr($buffer["adr_two_type"],$field[1]) ) {
												$buffer["adr_two_type"] .= "dom;";
											}
											if (!$buffer["adr_two_street"]) {
												$buffer["adr_two_street"]      = $values[2];
												$buffer["adr_two_locality"]    = $values[3];
												$buffer["adr_two_region"]      = $values[4];
												$buffer["adr_two_postalcode"]  = $values[5];
												$buffer["adr_two_countryname"] = $values[6];
											}
											break;
										default:
											break;
									}
									break;
								case "PARCEL":
									switch ($field[2]) {
										case "WORK":
											if ( !stristr($buffer["adr_one_type"],$field[1])) {
												$buffer["adr_one_type"] .= "parcel;";
											}
											if (!$buffer["adr_one_street"]) {
												$buffer["address2"]            = $values[1];
												$buffer["adr_one_street"]      = $values[2];
												$buffer["adr_one_locality"]    = $values[3];
												$buffer["adr_one_region"]      = $values[4];
												$buffer["adr_one_postalcode"]  = $values[5];
												$buffer["adr_one_countryname"] = $values[6];
											}
											break;
										case "HOME":
											if ( !stristr($buffer["adr_two_type"],$field[1]) ) {
												$buffer["adr_two_type"] .= "parcel;";
											}
											if (!$buffer["adr_two_street"]) {
												$buffer["adr_two_street"]      = $values[2];
												$buffer["adr_two_locality"]    = $values[3];
												$buffer["adr_two_region"]      = $values[4];
												$buffer["adr_two_postalcode"]  = $values[5];
												$buffer["adr_two_countryname"] = $values[6];
											}
											break;
										default:
											break;
									}
									break;
								case "POSTAL":
									switch ($field[2]) {
										case "WORK":
											if ( !stristr($buffer["adr_one_type"],$field[1])) {
												$buffer["adr_one_type"] .= "postal;";
											}
											if (!$buffer["adr_one_street"]) {
												$buffer["address2"]            = $values[1];
												$buffer["adr_one_street"]      = $values[2];
												$buffer["adr_one_locality"]    = $values[3];
												$buffer["adr_one_region"]      = $values[4];
												$buffer["adr_one_postalcode"]  = $values[5];
												$buffer["adr_one_countryname"] = $values[6];
											}
											break;
										case "HOME":
											if ( !stristr($buffer["adr_two_type"],$field[1]) ) {
												$buffer["adr_two_type"] .= "postal;";
											}
											if (!$buffer["adr_two_street"]) {
												$buffer["adr_two_street"]      = $values[2];
												$buffer["adr_two_locality"]    = $values[3];
												$buffer["adr_two_region"]      = $values[4];
												$buffer["adr_two_postalcode"]  = $values[5];
												$buffer["adr_two_countryname"] = $values[6];
											}
											break;
										default:
											break;
									}
									break;
								case "WORK":
									if (!$buffer["adr_one_street"]) {
										$buffer["address2"]            = $values[1];
										$buffer["adr_one_street"]      = $values[2];
										$buffer["adr_one_locality"]    = $values[3];
										$buffer["adr_one_region"]      = $values[4];
										$buffer["adr_one_postalcode"]  = $values[5];
										$buffer["adr_one_countryname"] = $values[6];
									}
									break;
								case "HOME":
									$buffer["adr_two_street"]      = $values[2];
									$buffer["adr_two_locality"]    = $values[3];
									$buffer["adr_two_region"]      = $values[4];
									$buffer["adr_two_postalcode"]  = $values[5];
									$buffer["adr_two_countryname"] = $values[6];									
									break;
								default:
									if (!$buffer["adr_one_street"]) {
										$buffer["address2"]            = $values[1];
										$buffer["adr_one_street"]      = $values[2];
										$buffer["adr_one_locality"]    = $values[3];
										$buffer["adr_one_region"]      = $values[4];
										$buffer["adr_one_postalcode"]  = $values[5];
										$buffer["adr_one_countryname"] = $values[6];
									}
									break;
							}
							break;
						case "TEL":
							switch ($field[1]) {
								case "PREF":
									//echo $field[2]." is preferred";
									if ($field[2]) {
										$buffer["tel_prefer"] .= strtolower($field[2]) . ";";
									}
									break;
								case "WORK":
									$buffer["tel_work"] = $values[0];
									if ($field[2] == "PREF") {
										$buffer["tel_prefer"] .= strtolower($field[1]) . ";";
									}
									break;
								case "HOME":
									$buffer["tel_home"] = $values[0];
									if ($field[2] == "PREF") {
										$buffer["tel_prefer"] .= strtolower($field[1]) . ";";
									}
									break;
								case "VOICE":
									$buffer["tel_voice"] = $values[0];
									if ($field[2] == "PREF") {
										$buffer["tel_prefer"] .= strtolower($field[1]) . ";";
									}
									break;
								case "FAX":
									$buffer["tel_fax"] = $values[0];
									if ($field[2] == "PREF") {
										$buffer["tel_prefer"] .= strtolower($field[1]) . ";";
									}
									break;
								case "MSG":
									$buffer["tel_msg"] = $values[0];
									if ($field[2] == "PREF") {
										$buffer["tel_prefer"] .= strtolower($field[1]) . ";";
									}
									break;
								case "CELL":
									$buffer["tel_cell"] = $values[0];
									if ($field[2] == "PREF") {
										$buffer["tel_prefer"] .= strtolower($field[1]) . ";";
									}
									break;
								case "PAGER":
									$buffer["tel_pager"] = $values[0];
									if ($field[2] == "PREF") {
										$buffer["tel_prefer"] .= strtolower($field[1]) . ";";
									}
									break;
								case "BBS":
									$buffer["tel_bbs"] = $values[0];
									if ($field[2] == "PREF") {
										$buffer["tel_prefer"] .= strtolower($field[1]) . ";";
									}
									break;
								case "MODEM":
									$buffer["tel_modem"] = $values[0];
									if ($field[2] == "PREF") {
										$buffer["tel_prefer"] .= strtolower($field[1]) . ";";
									}
									break;
								case "CAR":
									$buffer["tel_car"] = $values[0];
									if ($field[2] == "PREF") {
										$buffer["tel_prefer"] .= strtolower($field[1]) . ";";
									}
									break;
								case "ISDN":
									$buffer["tel_isdn"] = $values[0];
									if ($field[2] == "PREF") {
										$buffer["tel_prefer"] .= strtolower($field[1]) . ";";
									}
									break;
								case "VIDEO":
									$buffer["tel_video"] = $values[0];
									if ($field[2] == "PREF") {
										$buffer["tel_prefer"] .= strtolower($field[1]) . ";";
									}
									break;
								default:
									break;
							}
							break;
						case "EMAIL":
							switch ($field[1]) {
								case "WORK":
									$buffer["email"] = $values[0];
									$buffer["email_type"] = $field[2];
									break;
								case "HOME":
									$buffer["email_home"] = $values[0];
									$buffer["email_home_type"] = $field[2];
									break;
								default:
									if($buffer["email"]) {
										$buffer["email_type"] = $field[2];
									} elseif (!$buffer["email"]) {
										$buffer["email"] = $values[0];
										$buffer["email_type"] = $field[1];
									}
									break;
							}
						default:
							break;
					}
				} else {
					switch ($field[0]) {
						case "N":
							reset($this->names);
							$j=0;
							while(list($myname,$myval) = each($this->names) ) {
								$namel = "n_".$myname;
								$buffer[$namel] = $values[$j];
								$j++;
							}
							break;
						case "FN":
							$buffer["fn"] = $values[0];
							break;
						case "TITLE":
							$buffer["title"] = $values[0];
							break;
						case "TZ":
							$buffer["tz"] = $values[0];
							break;
						case "GEO":
							$buffer["geo"] = $values[0];
							break;
						case "URL":
							$buffer["url"] = $values[0];
							break;
						case "NOTE":
							$buffer["note"] = ereg_replace("=0D=0A","\n",$values[0]);
							break;
						case "KEY":
							$buffer["key"] = ereg_replace("=0D=0A","\n",$values[0]);
							break;
						case "LABEL":
							$buffer["label"] = ereg_replace("=0D=0A","\n",$values[0]);
							break;
						case "BDAY": #1969-12-31
							$tmp = split("-",$values[0]);
							if ($tmp[0]) {
								$buffer["bday"] = $tmp[1]."/".$tmp[2]."/".$tmp[0];
							}
							break;
					}
				}
			}
			$buffer["tel_prefer"]   = substr($buffer["tel_prefer"],0,-1);
			$buffer["adr_one_type"] = substr($buffer["adr_one_type"],0,-1);
			$buffer["adr_two_type"] = substr($buffer["adr_two_type"],0,-1);

			// Lastly, filter out all but standard fields, since they cover the vcard standard
			// and we don't want $buffer['BEGIN'] etc...
			$contacts = CreateObject('phpgwapi.contacts');
			while (list($fname,$fvalue) = each($contacts->stock_contact_fields)) {
				if($buffer[$fname]) {
					$entry[$fname] = $buffer[$fname];
					//echo '<br>'.$fname.' = "'.$entry[$fname].'"'."\n";
				}
			}
			return $entry;
		}

		// Takes an array of contacts class fields/values, turns it into a vcard string:
		//
		// for ($i=0;$i<count($buffer);$i++) {
		//     $vcards .= $this->vcard->out($buffer[$i]);
		// }
		//
		function out($buffer)
		{
			$entry     = "";
			$header    = "BEGIN:VCARD\n";
			$header   .= "VERSION:2.1\n";
			$header   .= "X-PHPGROUPWARE-FILE-AS:phpGroupWare.org\n";

			reset($this->export);
			while ( list($name,$value) = each($this->export) ) {
				if (!empty($buffer[$value])) {
					$mult = explode(";",$value);
					if (!$mult[1]) { // Normal
						if (strstr($buffer[$value],"\r\n") || strstr($buffer[$value],"\n")) {
							$buffer[$value] = ereg_replace("\r\n","=0D=0A",$buffer[$value]);
							$buffer[$value] = ereg_replace("\n","=0D=0A",$buffer[$value]);
							$entry .= $value . ";QUOTED-PRINTABLE:".$buffer[$value]."\n";
						} elseif ($value=="BDAY") {
							$tmp = split("/",$buffer[$value]); # 12/31/1969 -> 1969-12-31
							if ($tmp[0]) {
								if (strlen($tmp[0]) == 1) { $tmp[0] = '0'.$tmp[0]; }
								if (strlen($tmp[0]) == 1) { $tmp[1] = '0'.$tmp[1]; }
								$entry .= "BDAY:". $tmp[2]."-".$tmp[0]."-".$tmp[1]."\n";
							}
						} else {
							$entry .= $value . ":".$buffer[$value]."\n";
						}
					} else {
						switch ($mult[0]) {
							case "N":
								switch ($mult[1]) {
									case "PREFIX":
										$prefix    = ";" . $buffer[$value];
										break;
									case "GIVEN":
										$firstname = ";" . $buffer[$value];
										break;
									case "MIDDLE":
										$middle    = ";" . $buffer[$value];
										break;
									case "FAMILY":
										$lastname  =       $buffer[$value];
										break;
									case "SUFFIX":
										$suffix    = ";" . $buffer[$value];
										break;
								}
								break;
							case "ORG":
								switch ($mult[1]) {
									case "NAME":
										$org_name = $buffer[$value];
										break;
									case "UNIT":
										$org_unit = ";".$buffer[$value];
										break;
								}
								break;
							case "ADR":
								switch ($mult[1]) {
									case "TYPE":
										$types = explode(";",$buffer[$value]);
										if ($types[1]) {
											while ( $type = each($types) ) {
												$typei[$i][$mult[2]] .= ";".strtoupper($type[1]);
											}
										} elseif ($types[0]) {
											$typei[$i][$mult[2]] .= ";".strtoupper($types[0]);
										} else {
											$typei[$i][$mult[2]] .= ";".strtoupper($buffer[$value]);
										}
										//echo "TYPE=".$typei[$i][$mult[2]];
										break;
									case "WORK":
										$workaddr .= $buffer[$value].";";
										$workattr = $mult[0].";".$mult[1].$typei[$i][$mult[1]];
										break;
									case "HOME":
										$homeaddr .= $buffer[$value].";";
										$homeattr = $mult[0].";".$mult[1].$typei[$i][$mult[1]];
										break;
									default:
										break;
								}
								break;
							case "TEL":
								switch($mult[1]) {
									case "PREFER":
										$prefer = explode(";",$buffer[$value]);
										if ($prefer[1]) {
											while ($pref = strtoupper(each($prefer))) {
												$prefi[$i][$pref] = ";PREF";
											}
											//echo "PREF1";
										} elseif ($prefer[0]) {
											$prefi[$i][strtoupper($prefer[0])] = ";PREF";
											//echo "PREF=".strtoupper($prefer[0]);
										} elseif ($buffer[$value]) {
											$prefi[$i][$buffer[$value]] = ";PREF";
											//echo "PREF3";
										}
										break;
									case "WORK":
										// Wow, this is fun!
										$entry .= "A.".$mult[0].";".$mult[1].$prefi[$i][$mult[1]].":".$buffer[$value]."\n";
										break;
									case "HOME":
										$entry .= "B.".$mult[0].";".$mult[1].$prefi[$i][$mult[1]].":".$buffer[$value]."\n";
										break;
									default:
										$entry .= $mult[0].";".$mult[1].$prefi[$i][$mult[1]].":".$buffer[$value]."\n";
										break;
								}
								break;
							case "EMAIL":
								switch($mult[1]) {
									case "TYPE":
										if ($mult[2] == "WORK") { $emailtype  = ";".$buffer[$value]; }
										if ($mult[2] == "HOME") { $hemailtype = ";".$buffer[$value]; }
										break;
									case "WORK":
										$newval = "A.".$value;
										$entry .= $newval.$emailtype.":".$buffer[$value]."\n";
										break;
									case "HOME":
										$newval = "B.".$value;
										$entry .= $newval.$hemailtype.":".$buffer[$value]."\n";
										break;
									default:
										break;
								}
								break;
							default:
								break;
						} //end switch ($mult[0])
					} //end else
				} //end if (!empty)
			} //end while

			if ($lastname && $firstname) {
				$entries .= $header;
				$entries .= "N:".$lastname.$firstname.$middle.$prefix.$suffix."\n";
				$entries .= $entry;

				if (!$buffer["FN"]) {
					if ($lastname || $firstname ) {
						$entries .= "FN:".substr($firstname,1)." ".$lastname."\n";
					}
				}
				if ($org_name || $org_unit) {
					$entries .= "ORG:".$org_name.$org_unit."\n";
				}

				$workattr = ereg_replace("ADR;","",$workattr);
				$homeattr = ereg_replace("ADR;","",$homeattr);
				if (!$buffer['EXT']) { $buffer['EXT'] = ";"; }
				if ($workaddr) {
					$work = "A.ADR;".$workattr.":;".$buffer['EXT'].substr($workaddr,0,-1)."\n";
					if (!$buffer['LABEL']) {
						$wlabel = substr($workaddr,0,-1);
						$wlabel = ereg_replace(";","=0D=0A",$wlabel);
						//$wlabel = ereg_replace("(",",",$wlabel);
						//$wlabel = ereg_replace(")",",",$wlabel);
						$wlabel = "LABEL;WORK;QUOTED-PRINTABLE:".$wlabel."\n";
					}
				}
				if ($homeaddr) {
					$home = "B.ADR;".$homeattr.":;;".substr($homeaddr,0,-1)."\n";
					$hlabel = substr($homeaddr,0,-1);
					$hlabel = ereg_replace(";","=0D=0A",$hlabel);
					//$hlabel = ereg_replace("(",",",$hlabel);
					//$hlabel = ereg_replace(")",",",$hlabel);
					$hlabel = "LABEL;HOME;QUOTED-PRINTABLE:".$hlabel."\n";
				}
				$entries = ereg_replace("PUBKEY","KEY",$entries);
				$entries .= $work.$home.$wlabel.$hlabel."END:VCARD\n";
				$entries .= "\n";

				$buffer = $entries;
				return $buffer;
			} else {
				return;
			}
		} //end function
	} //end class
?>
