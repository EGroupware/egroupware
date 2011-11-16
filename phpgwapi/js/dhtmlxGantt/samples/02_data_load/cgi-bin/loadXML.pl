#!C:/Perl/bin/perl.exe -w
use CGI;
use IO::File;
use Fcntl qw(:DEFAULT :flock);
use File::Temp qw/ tempfile tempdir /;

#DATA
@qstring = split(/&/,$ENV{'QUERY_STRING'});
@filename = split(/=/,$qstring[0]);

$docroot  = "$ENV{'DOCUMENT_ROOT'}";
$dataDir  = "$docroot/data/";
$tmp = "";

open(FH,$dataDir.@filename[1]);
while($line = <FH>)
{
 	$tmp = $tmp.$line;
}
close(FH);
 
print "Content-type: text/xml\n\n";
print $tmp; 

exit;
