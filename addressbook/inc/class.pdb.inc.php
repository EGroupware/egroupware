<?php
  /**************************************************************************\
  * phpGroupWare API - Palm Database Access                                  *
  * This file written by Miles Lott <milos@speakeasy.net>                    *
  * Access to palm OS database structures (?)                                *
  * -------------------------------------------------------------------------*
  * Portions of code from ToPTIP                                             *
  *   Copyright (C) 2000-2001 Pierre Dittgen                                 *
  *   This file is a translation of the txt2pdbdoc tool                      *
  *   written in C Paul J. Lucas (plj@best.com)                              *
  * -------------------------------------------------------------------------*
  * This library may be part of the phpGroupWare API                         *
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

	// ONLY THE FETCH FUNCTION SHOULD BE CALLED AT THIS TIME:
	//     $pdb = CreateObject('addressbook.pdb');
	//     $pdb->fetch($content, $title, $document);
	//
	// This will force a download of the pdb file.
	//
	// READ DOES NOT WORK
	// FORMAT OF FILE IS A DOC, NOT A TRUE PALM ADDRESS BOOK
	class pdb
	{
		var $record_size			= 4096;	// Size of text record
		var $pdb_header_size		= 78;	// Size of the file header (don't touch!)
		var $pdb_record_header_size	= 8;	// Size of a text record header (don't touch either!)

		/**
		* Convert a integer value to a two bytes value string
		*/
		function int2($value)
		{
			return sprintf("%c%c", $value / 256, $value % 256);	
		}

		/**
		* Convert a integer value to a four bytes value string
		*/
		function int4($value)
		{
			for ($i=0; $i<4; $i++)
			{
				$b[$i] = $value % 256;
				$value = (int)(($value - $b[$i]) / 256);
			}
			return sprintf("%c%c%c%c", $b[3], $b[2], $b[1], $b[0]);
		}

		/**
		* Writes the header of the pdb file containing the title of the document
		* and different parameters including its size
		*/
		function write_header($fd, $title, $content_length)
		{
			// ============ File header =========================================
			// Title of the document, it's limited to 31 characters
			if (strlen($title) > 31)
			{
				$title = substr($title, 0, 31);
			}
			fwrite($fd, $title);

			// Completion with null '\0' characters
			for ($i=0; $i<32-strlen($title); $i++)
			{
				fwrite($fd, sprintf("%c", 0), 1);
			}

			// attributes & version fields
			fwrite($fd, $this->int2(0));
			fwrite($fd, $this->int2(0));

			// create & modify time
			fwrite($fd, sprintf("%c%c%c%c", 6, 209, 68, 174), 4);
			fwrite($fd, sprintf("%c%c%c%c", 6, 209, 68, 174), 4);

			// backup time, modification number, app Info Id, sort info Id
			fwrite($fd, $this->int4(0));
			fwrite($fd, $this->int4(0));
			fwrite($fd, $this->int4(0));
			fwrite($fd, $this->int4(0));

			// Type & creator
			fwrite($fd, 'TEXt', 4);
			fwrite($fd, 'REAd', 4);

			// Id seed & next record list
			fwrite($fd, $this->int4(0));
			fwrite($fd, $this->int4(0));

			// Number of text records
			$full_tr	= (int)($content_length / $this->record_size);
			$notfull_tr	= $content_length % $this->record_size;
			$num_records = $full_tr;
			if ($notfull_tr != 0)
			{
				$num_records++;
			}

			// + 1 cause of record 0
			fwrite($fd, $this->int2($num_records + 1));

			// From here...
			$num_offsets = $num_records + 1;
			$offset = $this->pdb_header_size + $this->pdb_record_header_size * $num_offsets;
			$index	= 0x40 << 24 | 0x6F8000; // Why not!

			fwrite($fd, $this->int4($offset));
			fwrite($fd, $this->int4($index++));

			$val	= 110 + ($num_offsets - 2) * 8;
			while (--$num_offsets != 0)
			{
				fwrite($fd, $this->int4($val));
				$val += 4096;
				fwrite($fd, $this->int4($index++));
			}
			// To here
			// Don't ask me how it works ;-)

			// ====== Write record 0 ============
			fwrite($fd, $this->int2(1));				// Version
			fwrite($fd, $this->int2(0));				// Reserved1
			fwrite($fd, $this->int4($content_length));	// doc size
			fwrite($fd, $this->int2($num_records));	// num records
			fwrite($fd, $this->int2($this->record_size));	// record size
			fwrite($fd, $this->int4(0));				// Reserved2

		}

		/**
		* Writes the given text, title on the given file descriptor
		* Note: It's saved as uncompressed doc
		* Note2: File descriptor is not closed at the end of the function
		*/
		function write($fd, $content, $title)
		{
			// Write header
			$this->write_header($fd, $title, strlen($content));

			// Write content
			fwrite($fd, $content);

			// And flushes all
			flush($fd);
		}

		/**
		* Reads the header of the pdb file
		* and different parameters including its size
		*/
		function read_header($fd)
		{
			// ============ File header =========================================
			// Title of the document, it's limited to 31 characters
			$title = fread(31,$fd);

			// Completion with null '\0' characters
			for ($i=0; $i<32-strlen($title); $i++)
			{
				fwrite($fd, sprintf("%c", 0), 1);
			}

			// attributes & version fields
			fwrite($fd, $this->int2(0));
			fwrite($fd, $this->int2(0));

			// create & modify time
			fwrite($fd, sprintf("%c%c%c%c", 6, 209, 68, 174), 4);
			fwrite($fd, sprintf("%c%c%c%c", 6, 209, 68, 174), 4);

			// backup time, modification number, app Info Id, sort info Id
			fwrite($fd, $this->int4(0));
			fwrite($fd, $this->int4(0));
			fwrite($fd, $this->int4(0));
			fwrite($fd, $this->int4(0));

			// Type & creator
			fwrite($fd, 'TEXt', 4);
			fwrite($fd, 'REAd', 4);

			// Id seed & next record list
			fwrite($fd, $this->int4(0));
			fwrite($fd, $this->int4(0));

			// Number of text records
			$full_tr	= (int)($content_length / $this->record_size);
			$notfull_tr	= $content_length % $this->record_size;
			$num_records = $full_tr;
			if ($notfull_tr != 0)
			{
				$num_records++;
			}

			// + 1 cause of record 0
			fwrite($fd, $this->int2($num_records + 1));

			// From here...
			$num_offsets = $num_records + 1;
			$offset = $this->pdb_header_size + $this->pdb_record_header_size * $num_offsets;
			$index	= 0x40 << 24 | 0x6F8000; // Why not!

			fwrite($fd, $this->int4($offset));
			fwrite($fd, $this->int4($index++));

			$val	= 110 + ($num_offsets - 2) * 8;
			while (--$num_offsets != 0)
			{
				fwrite($fd, $this->int4($val));
				$val += 4096;
				fwrite($fd, $this->int4($index++));
			}
			// To here
			// Don't ask me how it works ;-)

			// ====== Write record 0 ============
			fwrite($fd, $this->int2(1));				// Version
			fwrite($fd, $this->int2(0));				// Reserved1
			fwrite($fd, $this->int4($content_length));	// doc size
			fwrite($fd, $this->int2($num_records));	// num records
			fwrite($fd, $this->int2($this->record_size));	// record size
			fwrite($fd, $this->int4(0));				// Reserved2
		}

		/**
		* Reads a pdb from the given file descriptor
		* Note2: File descriptor is not closed at the end of the function
		*/
		function read($fd)
		{
			// Read header
			$header = $this->read_header($fd);

			// Read content
			$content = fread($fd);

			// And flushes all
			flush($fd);
		}

		/**
		* Creates a palmdoc and force the user to download it
		*/
		function fetch($content, $title, $document)
		{
			// Creates a temp file to put the current doc
			$tempfile = tempnam(".", "palm");

			// Creates the doc from the content
			$fd = fopen($tempfile, 'w');
			$this->write($fd, $content, $title);
			fclose($fd);

			// Forces the download
			header("Content-Type: application/force-download");
			header("Content-Disposition: attachment; filename=\"$document\"");

			// And writes the file content on stdout
			$fd = fopen("$tempfile", 'r');
			$content = fread($fd, filesize($tempfile));
			fclose($fd);
			print($content);

			// Cleaning
			unlink($tempfile);
			exit;
		}
	}
?>
