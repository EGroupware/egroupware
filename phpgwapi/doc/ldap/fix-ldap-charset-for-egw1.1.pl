#!/usr/bin/perl -w

use strict;
use MIME::Base64;
use Text::Iconv;

 #**************************************************************************
 #               fix-ldap-charset-for-egw1.1.pl  -  description            *
 #                             -------------------                         *
 #    begin                : Mon 2005/08/08                                *
 #    copyright            : (C) 2005 by Carsten Wolff                     *
 #    email                : wolffc@egroupware.org                         *
 #                                                                         *
 #   This program is free software; you can redistribute it and/or modify  *
 #   it under the terms of the GNU General Public License as published by  *
 #   the Free Software Foundation; either version 2 of the License, or     *
 #   (at your option) any later version.                                   *
 #                                                                         *
 #                                                                         *
 #   This script is used to adapt the charset in an egw ldap addressbook   *
 #   to the egw code in Release 1.1 and newer.                             *
 #                                                                         *
 #                                                                         *
 #   The old egw code just called utf8_encode on every attribute before    *
 #   writing and utf8_decode after reading an ldap attribute. This was     *
 #   fine as long as egw was run in iso8859-1, because then, calling       *
 #   utf8_encode was a proper conversion.                                  *
 #   But since egw supported systemcharsets, this call led to strings      *
 #   being encoded _twice_ before they were sent to ldap and thus being    *
 #   encoded in some weired mix of 2 charsets.                             *
 #   This of course confuses other LDAP-clients, because they don't        *
 #   know about the actual charset of the data anymore.                    *
 #   The new egw code now correctly _converts_ from every charset to utf8  *
 #   before sending data to ldap and converts from utf8 to systemcharset   *
 #   on reading. This of course makes it necessary, to correct the charset *
 #   of existing entries in the ldap-branch used by egw-addressbook        *
 #   (i.e. really make them utf-8), before the new code is being used.     *
 #                                                                         *
 #   How to use this script:                                               *
 #   1. make a datadump of your ldap database (f.e. slapcat>data.ldif)     *
 #   2. configure this script below                                        *
 #   3. convert the dump (./fix-ldap-charset-for-egw1.1.pl data.ldif)      *
 #   4. reimport the dump (f.e. slapadd -l data.ldif.conv)                 *
 #                                                                         *
 #**************************************************************************

##############################################################################
# CONFIGURATION - BEGIN
#
#
# only entries below this DN will be converted
my $basedn = "ou=addressbook,dc=domain,dc=xyz";
# this is the systemcharset of eGW, that was used at the time
# when the eGW-Code of your installation still was version 1.0.x or earlier
my $egw_systemcharset = "utf-8";
#
#
# CONFIGURATION - END
##############################################################################


# parameters
my $filename = $ARGV[0];
unless (-f $filename) {
	print "usage: " . $0 . " {ldif-filename}\n";
	exit 0;
}

# global objects
my $iconv_outer = Text::Iconv->new("utf-8", "iso-8859-1");
my $iconv_inner = Text::Iconv->new($egw_systemcharset, "utf-8");

# get an array of all entries
local $/;  # slurp mode
open(FOLD, "< $filename\0") || die "error opening source-file: $filename: $!";
flock(FOLD, 2);
my $file = <FOLD>;
my @old = split("\n\n",$file);
flock(FOLD, 8);
close(FOLD);

print "\nRead " . $#old . " entries from " . $filename . "\n";

# begin with conversion
my @new = ();
my $i = 0;
foreach my $oldentry (@old) {
	my $workentry = $oldentry;
	# concatenate base64 multline data
	$workentry =~ s/\n //g;
	# extract the raw DN and get it's readable form
	$workentry =~ /^(dn:[^\n]*)\n/;
	my %dn = getAttributeValue($1);
	# check, if this entry is to be converted
	my $basednregexp = regexpEscape($basedn);
	unless ($dn{'value'} =~ /^.+$basednregexp$/) {
		push(@new, $oldentry . "\n");
		next;
	}
	#
	# This entry is to be converted
	#
	my $newentry = "";
	my @attributes = split("\n", $workentry);
	foreach my $attr (@attributes) {
		my %attrib = getAttributeValue($attr);
		$attrib{'value'} = $iconv_inner->convert($iconv_outer->convert($attrib{'value'}));
		$newentry .= attrib2ldif(\%attrib);
	}
	push(@new,$newentry);
	$i++;
}
print "Converted $i entries in $basedn\n";

# write the result
open(FNEW, "> $filename" . ".conv\0") || die "error opening destination-file: $filename" . ".conv: $!";
flock(FNEW, 2);
foreach(@new) {
	print FNEW $_ . "\n";
}
flock(FNEW, 8);
close(FNEW);

print "Wrote $#new entries to $filename.conv\n\nPlease check the number of entries and have a look at\n$filename.conv, before reimporting it.\n\n";

#####################
# Subroutines
#####################

# break down an attribute in attribute-name and value
# if the value is base64, decode it.
sub getAttributeValue {
	my ($rawattr) = @_;
	my %attr = ();
	if ($rawattr =~ /^([^:]*):: (.*)/) {
		$attr{'name'} = $1;
		$attr{'value'} = decode_base64($2);
	} elsif ($rawattr =~ /^([^:]*): (.*)/) {
		$attr{'name'} = $1;
		$attr{'value'} = $2;
	} else {
		print "Error extracting data from attribute: " . $rawattr . "\n";
	}
	return %attr;
}

# escape a string for use within a regexp
sub regexpEscape {
	my ($string) = @_;
	$string =~ s/([\^\.\$\|\(\)\[\]\*\+\?\{\}])/\\$1/g;
	return $string;
}

# cahnge an attribute in suitable form for an ldif
sub attrib2ldif {
	my ($attrib) = @_;
	my ($key, $value) = ($attrib->{'name'}, $attrib->{'value'});
	# RFC2894 requires a string to be BASE64 encoded, if
	# - it begins with a char that's not a SAFE-INIT-CHAR
	# - or it contains a char that's not a SAFE-CHAR
	if ($value =~ /^[: <]/ or $value =~ /[^\x01-\x09\x0b-\x0c\x0e-\x7f]/) {
		# email-addresses can not contain unicode-characters
		if ($key eq "mail" or $key eq "phpgwMailHome") {
			print "Warning: forbidden characters in eMail-address detected: " . $value . "\n";
		}
		$value = encode_base64($value);
		$value =~ s/\n//g;
		# each line has to be no more than 77 characters long
		# including a leading space and, on the first line, the key.
		# Exceptions: dn and rdn
		unless ($key eq "dn" or $key eq "rdn") {
			my $keylen = length($key) + 3;
			my $form = substr($value, 0, 77 - $keylen);
			unless ($form eq $value) {
				my $j = 0;
				my $next = "";
				do  {
					$next = substr($value, 77 - $keylen + $j * 76, 76);
					$form .= "\n " . $next;
					$j++;
				} until (length($next) < 76);
			}
			$value = $form;
		}
		$key = $key . ":: ";
	} else {
		$key = $key . ": ";
	}
	return $key . $value . "\n";
}
