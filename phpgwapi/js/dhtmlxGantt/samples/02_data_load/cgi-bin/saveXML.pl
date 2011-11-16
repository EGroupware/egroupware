#!C:/Perl/bin/perl.exe -w
use CGI;
use IO::File;
use Fcntl qw(:DEFAULT :flock);
use File::Temp qw/ tempfile tempdir /;

#sub URLDecode {
#	my $s = shift; 
#	$s =~tr /+/ /; 
#	$s =~s /%([0-9A-Fa-f]{2})/chr(hex($1))/esg; 
#	return $s
#}
#DATA
#@qstring = split(/&/,$ENV{'QUERY_STRING'});
#@p1 = split(/=/,$qstring[0]);
#@p2 = split(/=/,$qstring[1]);

$docroot  = "$ENV{'DOCUMENT_ROOT'}";
$dataDir  = "$docroot/data";

if(!(-e $dataDir))
{
  createDataDir(); 
}

sub createDataDir
{
 	mkdir $dataDir,0777;
}

sub GetFormInput {  
     (*fval) = @_ if @_ ;  
	 local ($buf);
	 if ($ENV{'REQUEST_METHOD'} eq 'POST'){    
	   read(STDIN,$buf,$ENV{'CONTENT_LENGTH'});
	 }else{
	    $buf=$ENV{'QUERY_STRING'};
	 }
	   
	 if ($buf eq ""){    
	 	  return 0 ;
	 }else{
	     @fval=split(/&/,$buf);
		 foreach $i (0 .. $#fval){
		 ($name,$val)=split (/=/,$fval[$i],2);
		  $val=~tr/+/ /;
		  $val=~ s/%(..)/pack("c",hex($1))/ge;
		  $name=~tr/+/ /;
		  $name=~ s/%(..)/pack("c",hex($1))/ge;
		  if (!defined($field{$name})){
	          $field{$name}=$val;
		  }else{
	          $field{$name} .= ",$val";       
		  }
	   }
    }  
 return 1;
}

&GetFormInput;
$value = $field{'data'};
$fileName = $field{'filename'};
 
open(FH,">","$dataDir/$fileName");
  print FH "<?xml version='1.0' encoding='UTF-8'?>".$value;
close(FH);

print "Content-type: text/html\n\n";
exit;