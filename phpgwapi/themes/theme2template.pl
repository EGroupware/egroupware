#!/usr/bin/perl
# Takes a theme file and parses out the properties
# and makes a template file from it.
# Use it like so:
# theme2template.pl < infile > outfile
# by Stephan
# $Id$

$t=localtime;
print << 'EOF';
# template file for making phpGroupWare themes using template2theme.pl

EOF
while( $_ = <STDIN> ) {
  chomp($_);
  next unless ( $_ =~ /\$GLOBALS\[\'phpgw_info\'\]\[\'theme\'\]\[\'(.*)\'\].*=.*\'(.*)\'.*/ );
  print '$1=$2\n';
}
