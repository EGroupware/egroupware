#!/usr/bin/perl

# This program is distributed under the GPL.
# get the license from http://www.fsf.org
# this message must be attached to any redistributions
# smoser@brickies.net

sub getType;
sub usage;

if (!@ARGV) { usage(); }

for($i=(@ARGV-1);$i>=0;$i--) {
   if (@ARGV[$i] eq "-D" || @ARGV[$i] eq "--u2d") {
      $target="dos";
      splice(@ARGV,$i,1);
      next;
   }
   if (@ARGV[$i] eq "-U" || @ARGV[$i] eq "--d2u") {

      $target="unix";
      splice(@ARGV,$i,1);
      next;
   }
   if (@ARGV[$i] eq "-q") {
      $quite=1;
      splice(@ARGV,$i,1);
      next;
   }
   if (@ARGV[$i] =~ m/^-/) {
      usage(@ARGV[$i]);
   }
}


foreach $elem (@ARGV) {
   if (!($origtype=getType($elem))) {
      if (!$quite) {
         print STDERR "bad file $elem\n";
      }
      next;
   }
   if (! -s $elem) { 
      if (!$quite) { print STDERR "file $elem is empty\n"; }
      next;
   }
   $currtarget=$target;
   if ($currtarget eq "") {
      if ($origtype eq "dos") {
         $currtarget="unix";
      } elsif ($origtype eq "unix") {
         $currtarget="dos";
      } else {
         print "BAD ERROR, filetype was \"$origtype\" trying to change it to \"$currtarget\"\n";
         exit(0);
      }
      if (!$quite) { print STDERR "changing $elem to $currtarget\n"; }
   }
   # should be sane and safe at this point
   if ($origtype eq $currtarget) {
      if (!$quite) {
         print STDERR "warning, skipping $elem\n";
      }
      next;
   }
   open(READ,"<$elem") || die "couldn't open $elem for reading\n";
   @contents=<READ>;
   close(READ);
   if ($currtarget eq "dos") {
      for($i=0;$i<@contents;$i++) {
         @contents[$i]=~s/\x0A$/\x0D\x0A/;
      }
   } elsif ($currtarget eq "unix") {
      for($i=0;$i<@contents;$i++) {
         @contents[$i]=~s/\x0D\x0A$/\x0A/;
      }
   } else {
      print "BAD ERROR, confused on $elem, origtype=\"$origtype\" target=\"$currtarget\"\n";
      exit(0);
   }
   open(WRITE,">$elem") || die "couldn't open $elem for writing\n";
   foreach $elem (@contents) {
      print WRITE $elem;
   }
}
exit(1);



sub getType {
      # hopefully, this function returns 0 on failure, "dos" for a unix file
      # "unix" for file that is in unix style
      my $filename = shift;
      my $type;
      if (! -e $filename) { print "$elem bad -e\n";}
      if (! -w $filename) { print "$elem bad -w\n";}
      if (! -f $filename) { print "$elem bad -f\n";}
      if (! -r $filename) { print "$elem bad -r\n";}

      if (!(-w $filename && -e $filename && -f $filename && -r $filename )) {
         return 0;
      } else {
         open(READ,"<$filename"); 
         $line=<READ>;
         $type="";
         if ($line=~m/\x0D\x0A$/) {
            #print "had <LF><CR>\n";

            $type="dos";
         } elsif ($line=~m/\x0A$/) {
            #print "had LF\n";
            $type="unix";
         } elsif (! -s $filename) {
               return "unix";
         } else {
            # whats going on here
            # $type=0;
            if (!$quite) { print "warning, can't tell what $elem was. probably binary\n";}
            $type=0;
         }
         close(READ);
         return $type;
      }
}

sub usage {
   my $invalid = shift;
   if ($invalid ne "") {
      print "$invalid: invalid argument\n";
   }
   print "usage: $0 [--d2u|--u2d|-D|-U] file1 file2 ...\n";
   print "\t--d2u, -U\t change files to UNIX format\n";
   print "\t--u2d, -D\t change files to DOS format\n";
   print "- when no options are given, format will be detected and changed\n";
   print "- avoid using with binary files\n";
   exit(0);
}
