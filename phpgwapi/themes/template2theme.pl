#!/usr/bin/perl
# Takes a simple text file of properties and turns them
# into a themes files.
# Use it like so:
# template2theme.pl < infile > outfile
# by Stephan
# $Id$
$wrap='$phpgw_info['theme']['_KEY_']= '_VAL_'';
print '<?\n';
print << 'EOF';
# phpGroupWare Theme file
EOF

while( $_ = <STDIN> ) {
  next unless ( $_ =~ /^\s*(\w+)\s*=(.+)/ );
  $k=$1;
  $v=$2;
  my $foo = $wrap;
  $foo =~ s/_KEY_/$k/;
  $foo =~ s/_VAL_/$v/;
  print '$foo;\n';
}
print '?>';
