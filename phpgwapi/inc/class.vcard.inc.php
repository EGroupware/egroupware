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

	class vcard
	{
		var $import = array(
			'n'        => 'n',
			'sound'    => 'sound',
			'bday'     => 'bday',
			'note'     => 'note',
			'tz'       => 'tz',
			'geo'      => 'geo',
			'url'      => 'url',
			'pubkey'   => 'pubkey',
			'org'      => 'org',
			'title'    => 'title',
			'adr'      => 'adr',
			'label'    => 'label',
			'tel'      => 'tel',
			'email'    => 'email'
		);

		var $export = array(
			'fn'                  => 'FN',
			'n_given'             => 'N;GIVEN',
			'n_family'            => 'N;FAMILY',
			'n_middle'            => 'N;MIDDLE',
			'n_prefix'            => 'N;PREFIX',
			'n_suffix'            => 'N;SUFFIX',
			'sound'               => 'SOUND',
			'bday'                => 'BDAY',
			'note'                => 'NOTE',
			'tz'                  => 'TZ',
			'geo'                 => 'GEO',
			'url'                 => 'URL',
			'pubkey'              => 'PUBKEY',
			'org_name'            => 'ORG;NAME',
			'org_unit'            => 'ORG;UNIT',
			'title'               => 'TITLE',

			'adr_one_type'        => 'ADR;TYPE;WORK',
			'adr_two_type'        => 'ADR;TYPE;HOME',
			'tel_prefer'          => 'TEL;PREFER',
			'email_type'          => 'EMAIL;TYPE;WORK',
			'email_home_type'     => 'EMAIL;TYPE;HOME',

			'adr_one_street'      => 'ADR;WORK;STREET',
			'adr_one_locality'    => 'ADR;WORK;LOCALITY', 
			'adr_one_region'      => 'ADR;WORK;REGION', 
			'adr_one_postalcode'  => 'ADR;WORK;POSTALCODE',
			'adr_one_countryname' => 'ADR;WORK;COUNTRYNAME',
			'address2'            => 'EXT',
			'label'               => 'LABEL',

			'adr_two_street'      => 'ADR;HOME;STREET',
			'adr_two_locality'    => 'ADR;HOME;LOCALITY',
			'adr_two_region'      => 'ADR;HOME;REGION',
			'adr_two_postalcode'  => 'ADR;HOME;POSTALCODE',
			'adr_two_countryname' => 'ADR;HOME;COUNTRYNAME',

			'tel_work'            => 'TEL;WORK',
			'tel_home'            => 'TEL;HOME',
			'tel_voice'           => 'TEL;VOICE',
			'tel_fax'             => 'TEL;FAX',
			'tel_msg'             => 'TEL;MSG',
			'tel_cell'            => 'TEL;CELL',
			'tel_pager'           => 'TEL;PAGER',
			'tel_bbs'             => 'TEL;BBS',
			'tel_modem'           => 'TEL;MODEM',
			'tel_car'             => 'TEL;CAR',
			'tel_isdn'            => 'TEL;ISDN',
			'tel_video'           => 'TEL;VIDEO',
			'email'               => 'EMAIL;WORK',
			'email_home'          => 'EMAIL;HOME'
		);

		var $names = array(
			'family' => 'family',
			'given'  => 'given',
			'middle' => 'middle',
			'prefix' => 'prefix',
			'suffix' => 'suffix'
		);

		function vcard()
		{
			/* _debug_array($this); */
		}

		/*
			This now takes the upload filename
			and parses using the class string and array processors.
			The return is a contacts class entry, ready for add.
		*/
		function in_file($filename='')
		{
			if (!$filename)
			{
				return array();
			}

			$buffer = array();
			
			$fp = fopen($filename,'r');
			while ($data = fgets($fp,8000))
			{
				list($name,$value,$extra) = split(':', $data);
				if (substr($value,0,5) == 'http')
				{
					$value = $value . ':'.$extra;
				}
				if ($name && $value)
				{
					reset($this->import);
					while ( list($fname,$fvalue) = each($this->import) )
					{
						if ( strstr(strtolower($name), $this->import[$fname]) )
						{
							$value = trim($value);
							$value = str_replace('=0D=0A',"\n",$value);
							$buffer += array($name => $value);
						}
					}
				}
			}
			fclose($fp);

			$entry = $this->in($buffer);
			/* _debug_array($entry);exit; */

			return $entry;
		}

		/* Try to decode strings that may be base64 encoding, otherwise assume they are QUOTED-PRINTABLE */
		function decode($string)
		{
			$tmp = base64_decode($string);
			if(base64_encode($tmp) == $string)
			{
				return $tmp;
			}
			else
			{
				return (str_replace('=0D=0A',"\n",$string));
			}
			return $out;
		}

		function encode($string, $type='qp')
		{
			$out = '';
			switch($type)
			{
				case 'qp':
					$out = ereg_replace("\r\n",'=0D=0A',$string);
					$out = ereg_replace("\n",'=0D=0A',$out);
					break;
				case 'base64':
					$out = base64_encode($string);
			}
			return $out;
		}

		/*
			This is here to match the old in() function, now called _parse_in().
			It is called now also by in_file() above.
			It takes a pre-parsed file using the methods in in_file(), returns a clean contacts class array.
		*/
		function in($buffer)
		{
			$buffer = $this->_parse_in($buffer);

			$contacts = CreateObject('phpgwapi.contacts');		/* RB 2001/05/08 Lotus Organizer uses/needs extrafields from edit.php */
			$all_fields = $contacts->stock_contact_fields + array(
				'ophone'   => 'ophone',
				'address2' => 'address2',
				'address3' => 'address3'
			);

			while (list($fname,$fvalue) = each($all_fields))
			{
				if($buffer[$fname])
				{
					$entry[$fname] = $buffer[$fname];
					/* echo '<br>'.$fname.' = "'.$entry[$fname].'"'."\n"; */
				}
			}
			return $entry;
		}

		/*
			Pass this an associative array of fieldnames and values
			returns a clean array based on contacts class std fields
			This array can then be passed via $phpgw->contacts->add($ownerid,$buffer)
		*/
		function _parse_in($buffer)
		{
			/* Following is a lot of pain and little magic */
			while ( list($name,$value) = @each($buffer) )
			{
				$field  = split(';',$name);
				$field[0] = ereg_replace("A\.",'',$field[0]);
				$field[0] = ereg_replace("B\.",'',$field[0]);
				$field[0] = ereg_replace("C\.",'',$field[0]);
				$field[0] = ereg_replace("D\.",'',$field[0]);
				$values = split(';',$value);
				if ($field[1])
				{
					//echo $field[0];
					switch ($field[0])
					{
						case 'LABEL':
							$entry['label'] = $this->decode($values[0]);
							break;
						case 'NOTE':
							$entry['note'] = $this->decode($values[0]);
							break;
						case 'ADR':
							switch ($field[1])
							{
								case 'INTL':
									switch ($field[2])
									{
										case 'WORK':
											if ( !stristr($buffer['adr_one_type'],$field[1]))
											{
												$entry['adr_one_type'] .= 'intl;';
											}
											if (!$buffer['adr_one_street'])
											{
												$entry['address2']            = $values[1];
												$entry['adr_one_street']      = $values[2];
												$entry['adr_one_locality']    = $values[3];
												$entry['adr_one_region']      = $values[4];
												$entry['adr_one_postalcode']  = $values[5];
												$entry['adr_one_countryname'] = $values[6];
											}
											break;
										case 'HOME':
											if ( !stristr($buffer['adr_two_type'],$field[1]) )
											{
												$entry['adr_two_type'] .= 'intl;';
											}
											if (!$buffer['adr_two_street'])
											{
												$entry['adr_two_street']      = $values[2];
												$entry['adr_two_locality']    = $values[3];
												$entry['adr_two_region']      = $values[4];
												$entry['adr_two_postalcode']  = $values[5];
												$entry['adr_two_countryname'] = $values[6];
											}
											break;
										default:
											break;
									}
									break;
								case 'DOM':
									switch ($field[2])
									{
										case 'WORK':
											if ( !stristr($buffer['adr_one_type'],$field[1]))
											{
												$entry['adr_one_type'] .= 'dom;';
											}
											if (!$buffer['adr_one_street'])
											{
												$entry['address2']            = $values[1];
												$entry['adr_one_street']      = $values[2];
												$entry['adr_one_locality']    = $values[3];
												$entry['adr_one_region']      = $values[4];
												$entry['adr_one_postalcode']  = $values[5];
												$entry['adr_one_countryname'] = $values[6];
											}
											break;
										case 'HOME':
											if ( !stristr($buffer['adr_two_type'],$field[1]) )
											{
												$entry['adr_two_type'] .= 'dom;';
											}
											if (!$buffer['adr_two_street'])
											{
												$entry['adr_two_street']      = $values[2];
												$entry['adr_two_locality']    = $values[3];
												$entry['adr_two_region']      = $values[4];
												$entry['adr_two_postalcode']  = $values[5];
												$entry['adr_two_countryname'] = $values[6];
											}
											break;
										default:
											break;
									}
									break;
								case 'PARCEL':
									switch ($field[2])
									{
										case 'WORK':
											if ( !stristr($buffer['adr_one_type'],$field[1]))
											{
												$entry['adr_one_type'] .= 'parcel;';
											}
											if (!$buffer['adr_one_street'])
											{
												$entry['address2']            = $values[1];
												$entry['adr_one_street']      = $values[2];
												$entry['adr_one_locality']    = $values[3];
												$entry['adr_one_region']      = $values[4];
												$entry['adr_one_postalcode']  = $values[5];
												$entry['adr_one_countryname'] = $values[6];
											}
											break;
										case 'HOME':
											if ( !stristr($buffer['adr_two_type'],$field[1]) )
											{
												$entry['adr_two_type'] .= 'parcel;';
											}
											if (!$buffer['adr_two_street'])
											{
												$entry['adr_two_street']      = $values[2];
												$entry['adr_two_locality']    = $values[3];
												$entry['adr_two_region']      = $values[4];
												$entry['adr_two_postalcode']  = $values[5];
												$entry['adr_two_countryname'] = $values[6];
											}
											break;
										default:
											break;
									}
									break;
								case 'POSTAL':
									switch ($field[2])
									{
										case 'WORK':
											if ( !stristr($buffer['adr_one_type'],$field[1]))
											{
												$entry['adr_one_type'] .= 'postal;';
											}
											if (!$buffer['adr_one_street'])
											{
												$entry['address2']            = $values[1];
												$entry['adr_one_street']      = $values[2];
												$entry['adr_one_locality']    = $values[3];
												$entry['adr_one_region']      = $values[4];
												$entry['adr_one_postalcode']  = $values[5];
												$entry['adr_one_countryname'] = $values[6];
											}
											break;
										case 'HOME':
											if ( !stristr($buffer['adr_two_type'],$field[1]) )
											{
												$entry['adr_two_type'] .= 'postal;';
											}
											if (!$buffer['adr_two_street'])
											{
												$entry['adr_two_street']      = $values[2];
												$entry['adr_two_locality']    = $values[3];
												$entry['adr_two_region']      = $values[4];
												$entry['adr_two_postalcode']  = $values[5];
												$entry['adr_two_countryname'] = $values[6];
											}
											break;
										default:
											break;
									}
									break;
								case 'WORK':
									if (!$buffer['adr_one_street'])
									{
										$entry['address2']            = $values[1];
										$entry['adr_one_street']      = $values[2];
										$entry['adr_one_locality']    = $values[3];
										$entry['adr_one_region']      = $values[4];
										$entry['adr_one_postalcode']  = $values[5];
										$entry['adr_one_countryname'] = $values[6];
									}
									break;
								case 'HOME':
									$entry['adr_two_street']      = $values[2];
									$entry['adr_two_locality']    = $values[3];
									$entry['adr_two_region']      = $values[4];
									$entry['adr_two_postalcode']  = $values[5];
									$entry['adr_two_countryname'] = $values[6];
									break;
								default:
									if (!$buffer['adr_one_street'])
									{
										$entry['address2']            = $values[1];
										$entry['adr_one_street']      = $values[2];
										$entry['adr_one_locality']    = $values[3];
										$entry['adr_one_region']      = $values[4];
										$entry['adr_one_postalcode']  = $values[5];
										$entry['adr_one_countryname'] = $values[6];
									}
									break;
							}
							break;
						case 'TEL': 	// RB 2001/05/07 added for Lotus Organizer ueses TEL;{WORK|HOME};{VOICE|FAX}[;PREF]
							if ($field[2] == 'FAX' && ($field[1] == 'WORK' || $field[i] == 'HOME'))
							{
								/* TODO This is PHP4 only */
								array_shift($field);	// --> ignore the WORK or HOME if FAX follows, HOME;FAX and HOME;TEL are maped later
							}
							switch ($field[1])
							{
								case 'PREF':
									//echo $field[2].' is preferred';
									if ($field[2])
									{
										$buffer['tel_prefer'] .= strtolower($field[2]) . ';';
									}
									break;
								case 'WORK':	// RB don't overwrite TEL;WORK;VOICE (main nr.) with TEL;WORK, TEL;WORK --> tel_isdn
									$entry[$buffer['tel_work'] ? 'tel_isdn' : 'tel_work'] = $values[0];
									if ($field[2] == 'PREF')
									{
										$entry['tel_prefer'] .= strtolower($field[1]) . ';';
									}
									break;
								case 'HOME':	// RB don't overwrite TEL;HOME;VOICE (main nr.) with TEL;HOME, TEL;HOME --> ophone
									$entry[$buffer['tel_home'] ? 'ophone' : 'tel_home' ] = $values[0];
									if ($field[2] == 'PREF')
									{
										$entry['tel_prefer'] .= strtolower($field[1]) . ';';
									}
									break;
								case 'VOICE':
									$entry['tel_voice'] = $values[0];
									if ($field[2] == 'PREF')
									{
										$entry['tel_prefer'] .= strtolower($field[1]) . ';';
									}
									break;
								case 'FAX':
									if ($entry['tel_fax'])
									{
										// RB don't overwrite TEL;WORK;FAX with TEL;HOME;FAX, TEL;HOME;FAX --> ophone
									   $entry['ophone'] = $values[0] . ' Fax';
									   break;
									}
									$entry['tel_fax'] = $values[0];
									if ($field[2] == 'PREF')
									{
										$entry['tel_prefer'] .= strtolower($field[1]) . ';';
									}
									break;
								case 'MSG':
									$entry['tel_msg'] = $values[0];
									if ($field[2] == 'PREF')
									{
										$entry['tel_prefer'] .= strtolower($field[1]) . ';';
									}
									break;
								case 'CELL':
									$entry['tel_cell'] = $values[0];
									if ($field[2] == 'PREF')
									{
										$entry['tel_prefer'] .= strtolower($field[1]) . ';';
									}
									break;
								case 'PAGER':
									$entry['tel_pager'] = $values[0];
									if ($field[2] == 'PREF')
									{
										$entry['tel_prefer'] .= strtolower($field[1]) . ';';
									}
									break;
								case 'BBS':
									$entry['tel_bbs'] = $values[0];
									if ($field[2] == 'PREF')
									{
										$entry['tel_prefer'] .= strtolower($field[1]) . ';';
									}
									break;
								case 'MODEM':
									$entry['tel_modem'] = $values[0];
									if ($field[2] == 'PREF')
									{
										$entry['tel_prefer'] .= strtolower($field[1]) . ';';
									}
									break;
								case 'CAR':
									$entry['tel_car'] = $values[0];
									if ($field[2] == 'PREF')
									{
										$entry['tel_prefer'] .= strtolower($field[1]) . ';';
									}
									break;
								case 'ISDN':
									$entry['tel_isdn'] = $values[0];
									if ($field[2] == 'PREF')
									{
										$entry['tel_prefer'] .= strtolower($field[1]) . ';';
									}
									break;
								case 'VIDEO':
									$entry['tel_video'] = $values[0];
									if ($field[2] == 'PREF')
									{
										$entry['tel_prefer'] .= strtolower($field[1]) . ';';
									}
									break;
								default:
									break;
							}
							break;
						case 'EMAIL':
							switch ($field[1])
							{
								case 'WORK':
									$entry['email'] = $values[0];
									$entry['email_type'] = $field[2];
									break;
								case 'HOME':
									$entry['email_home'] = $values[0];
									$entry['email_home_type'] = $field[2];
									break;
								default:
									if($buffer['email'])
									{
										$entry['email_type'] = $field[2];
									}
									elseif (!$buffer['email'])
									{
										$entry['email'] = $values[0];
										$entry['email_type'] = $field[1];
									}
									break;
							}
							break;
						case 'URL':	// RB 2001/05/08 Lotus Organizer uses URL;WORK and URL;HOME (URL;HOME droped if both)
							$entry['url'] = $values[0];
							break;
						default:
							break;
					}
				}
				else
				{
					switch ($field[0])
					{
						case 'N':
							reset($this->names);
							$j=0;
							while(list($myname,$myval) = each($this->names) )
							{
								$namel = 'n_' . $myname;
								$entry[$namel] = $values[$j];
								$j++;
							}
							break;
						case 'FN':
							$entry['fn'] = $values[0];
							break;
						case 'TITLE':
							$entry['title'] = $values[0];
							break;
						case 'TZ':
							$entry['tz'] = $values[0];
							break;
						case 'GEO':
							$entry['geo'] = $values[0];
							break;
						case 'URL':
							$entry['url'] = $values[0];
							break;
						case 'NOTE':
							$entry['note'] = $this->decode($values[0]);
							break;
						case 'KEY':
							$entry['key'] = $this->decode($values[0]);
							break;
						case 'LABEL':
							$entry['label'] = $this->decode($values[0]);
							break;
						case 'BDAY': #1969-12-31
							if(strlen($values[0]) == 8)
							{
								$entry['bday'] = substr($values[0],4,2) . '/' . substr($values[0],6,2) . '/' . substr($values[0],0,4);
							}
							else
							{
								$tmp = split('-',$values[0]);
								if($tmp[0])
								{
									$entry['bday'] = $tmp[1] . '/' . $tmp[2] . '/' . $tmp[0];
								}
							}
							break;
						case 'ORG':	// RB 2001/05/07 added for Lotus Organizer: ORG:Company;Department
							$entry['org_name'] = $values[0];
							$entry['org_unit'] = $values[1];
							break;
					}
				}
			}
			$entry['tel_prefer']   = substr($buffer['tel_prefer'],0,-1);
			$entry['adr_one_type'] = substr($buffer['adr_one_type'],0,-1);
			$entry['adr_two_type'] = substr($buffer['adr_two_type'],0,-1);
			
			if (count($street = split("\r*\n",$buffer['adr_one_street'],3)) > 1)
			{
				$entry['adr_one_street'] = $street[0];			// RB 2001/05/08 added for Lotus Organizer to split multiline adresses
				$entry['address2']		  = $street[1];
				$entry['address3']		  = $street[2];
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
			$entry   = '';
			$header  = 'BEGIN:VCARD' . "\n";
			$header .= 'VERSION:2.1' . "\n";
			$header .= 'X-EGROUPWARE-FILE-AS:eGroupWare.org' . "\n";

			reset($this->export);
			while ( list($name,$value) = each($this->export) )
			{
				if (!empty($buffer[$value]))
				{
					$mult = explode(';',$value);
					if (!$mult[1])
					{ // Normal
						if(strstr($buffer[$value],"\r\n") || strstr($buffer[$value],"\n"))
						{
							$buffer[$value] = $this->encode($buffer[$value]);
							$entry .= $value . ';QUOTED-PRINTABLE:' . $buffer[$value]."\n";
						}
						elseif ($value == 'BDAY')
						{
							$tmp = split('/',$buffer[$value]); # 12/31/1969 -> 1969-12-31
							if ($tmp[0])
							{
								if (strlen($tmp[0]) == 1) { $tmp[0] = '0'.$tmp[0]; }
								if (strlen($tmp[1]) == 1) { $tmp[1] = '0'.$tmp[1]; }
								$entry .= 'BDAY:' . $tmp[2] . '-' . $tmp[0] . '-' . $tmp[1] . "\n";
							}
						}
						else
						{
							$entry .= $value . ':' . $buffer[$value] . "\n";
						}
					}
					else
					{
						switch ($mult[0])
						{
							case 'N':
								switch ($mult[1])
								{
									case 'PREFIX':
										$prefix    = ';' . $buffer[$value];
										break;
									case 'GIVEN':
										$firstname = ';' . $buffer[$value];
										break;
									case 'MIDDLE':
										$middle    = ';' . $buffer[$value];
										break;
									case 'FAMILY':
										$lastname  =       $buffer[$value];
										break;
									case 'SUFFIX':
										$suffix    = ';' . $buffer[$value];
										break;
								}
								break;
							case 'ORG':
								switch ($mult[1])
								{
									case 'NAME':
										$org_name = $buffer[$value];
										break;
									case 'UNIT':
										$org_unit = ';' . $buffer[$value];
										break;
								}
								break;
							case 'ADR':
								switch ($mult[1])
								{
									case 'TYPE':
										$types = explode(';',$buffer[$value]);
										if ($types[1])
										{
											while ( $type = each($types) )
											{
												$typei[$i][$mult[2]] .= ';' . strtoupper($type[1]);
											}
										}
										elseif ($types[0])
										{
											$typei[$i][$mult[2]] .= ';' . strtoupper($types[0]);
										}
										else
										{
											$typei[$i][$mult[2]] .= ';' . strtoupper($buffer[$value]);
										}
										//echo "TYPE=".$typei[$i][$mult[2]];
										break;
									case 'WORK':
										$workaddr .= $buffer[$value] . ';';
										$workattr = $mult[0] . ';' . $mult[1] . $typei[$i][$mult[1]];
										break;
									case 'HOME':
										$homeaddr .= $buffer[$value] . ';';
										$homeattr = $mult[0] . ';' . $mult[1] . $typei[$i][$mult[1]];
										break;
									default:
										break;
								}
								break;
							case 'TEL':
								switch($mult[1])
								{
									case 'PREFER':
										$prefer = explode(';',$buffer[$value]);
										if ($prefer[1])
										{
											while ($pref = strtoupper(each($prefer)))
											{
												$prefi[$i][$pref] = ';PREF';
											}
											//echo 'PREF1';
										}
										elseif ($prefer[0])
										{
											$prefi[$i][strtoupper($prefer[0])] = ';PREF';
											//echo 'PREF='.strtoupper($prefer[0]);
										}
										elseif ($buffer[$value])
										{
											$prefi[$i][$buffer[$value]] = ';PREF';
											//echo 'PREF3';
										}
										break;
									case 'WORK':
										// Wow, this is fun!
										$entry .= 'A.' . $mult[0] . ';' . $mult[1] . $prefi[$i][$mult[1]] . ':' . $buffer[$value] . "\n";
										break;
									case 'HOME':
										$entry .= 'B.' . $mult[0] . ';' . $mult[1] . $prefi[$i][$mult[1]] . ':' . $buffer[$value] . "\n";
										break;
									default:
										$entry .= $mult[0] . ';' . $mult[1] . $prefi[$i][$mult[1]] . ':' . $buffer[$value] . "\n";
										break;
								}
								break;
							case 'EMAIL':
								switch($mult[1])
								{
									case 'TYPE':
										if ($mult[2] == 'WORK') { $emailtype  = ';' . $buffer[$value]; }
										if ($mult[2] == 'HOME') { $hemailtype = ';' . $buffer[$value]; }
										break;
									case 'WORK':
										$newval = 'A.'.$value;
										$entry .= $newval . $emailtype . ':' . $buffer[$value] . "\n";
										break;
									case 'HOME':
										$newval = 'B.' . $value;
										$entry .= $newval . $hemailtype . ':' . $buffer[$value] . "\n";
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

			if ($lastname && $firstname)
			{
				$entries .= $header;
				$entries .= 'N:' . $lastname . $firstname . $middle . $prefix . $suffix . "\n";
				$entries .= $entry;

				if (!$buffer['FN'])
				{
					if ($lastname || $firstname )
					{
						$entries .= 'FN:' . substr($firstname,1) . ' ' . $lastname . "\n";
					}
				}
				if ($org_name || $org_unit)
				{
					$entries .= 'ORG:' . $org_name . $org_unit . "\n";
				}

				$workattr = str_replace('ADR;','',$workattr);
				$homeattr = str_replace('ADR;','',$homeattr);
				if (!$buffer['EXT']) { $buffer['EXT'] = ';'; }
				if ($workaddr)
				{
					$work = 'A.ADR;' . $workattr . ':;' . $buffer['EXT'] . substr($workaddr,0,-1) . "\n";
					if (!$buffer['LABEL'])
					{
						$wlabel = substr($workaddr,0,-1);
						$wlabel = str_replace(';','=0D=0A',$wlabel);
						//$wlabel = str_replace('(',',',$wlabel);
						//$wlabel = str_replace(')',',',$wlabel);
						$wlabel = 'LABEL;WORK;QUOTED-PRINTABLE:' . $wlabel . "\n";
					}
				}
				if ($homeaddr)
				{
					$home = 'B.ADR;'.$homeattr.':;;'.substr($homeaddr,0,-1)."\n";
					$hlabel = substr($homeaddr,0,-1);
					$hlabel = str_replace(';','=0D=0A',$hlabel);
					//$hlabel = str_replace('(',',',$hlabel);
					//$hlabel = str_replace(')',',',$hlabel);
					$hlabel = 'LABEL;HOME;QUOTED-PRINTABLE:' . $hlabel . "\n";
				}
				$entries = str_replace('PUBKEY','KEY',$entries);
				$entries .= $work . $home . $wlabel . $hlabel . 'END:VCARD' . "\n";
				$entries .= "\n";

				$buffer = $entries;
				return $buffer;
			}
			else
			{
				return;
			}
		} //end function
	} //end class
?>
