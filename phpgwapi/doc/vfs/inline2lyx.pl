#!/usr/bin/perl
#Created by Jason Wies (Zone, zone@users.sourceforge.net)
#Copyright 2001 Jason Wies
#Released under GNU Public License

#Converts HeaderDoc style inline comments to LyX style LaTeX
#Usage: ./inline2lyx.pl file Title Author Date Abstract

if (!@ARGV[0])
{
	print "Usage: ./inline2lyx.pl file Title Author Date Abstract\n";
	exit;
}

$output .= '\lyxformat 2.16
\textclass linuxdoc
\language default
\inputencoding latin1
\fontscheme default
\graphics default
\paperfontsize default
\spacing single
\papersize Default
\paperpackage a4
\use_geometry 0
\use_amsmath 0
\paperorientation portrait
\secnumdepth 2
\tocdepth 2
\paragraph_separation indent
\defskip medskip
\quotes_language english
\quotes_times 2
\papercolumns 1
\papersides 1
\paperpagestyle default

\layout Title
\added_space_top vfill \added_space_bottom vfill
' . @ARGV[1] . '
\layout Author

' . @ARGV[2] . '

\layout Date

' . @ARGV[3] . '

\layout Abstract

' . @ARGV[4] . '

\layout Section

' . @ARGV[1];

$file = `cat @ARGV[0]`;

@lines = split ('\n', $file);

foreach $line (@lines)
{
	undef $start;
	undef $class;
	undef $function;
	undef $abstract;
	undef $param;
	undef $result;
	undef $discussion;
	undef $end;
	undef $layout;

	if ($line =~ /\/\*\!/)
	{
		$in = 1;
		$start = 1;
	}

	if ($looking && $line =~ /function/)
	{
		$layout = "verbatim";
		undef $looking;
	}
	elsif (!$in)
	{
		goto next;
	}

	if ($line =~ /\@(class)/)
	{
		$layout = "subsection";
		$name = $1;
		$class = 1;
	}
	if ($line =~ /\@(function)/)
	{
		$layout = "subsection";
		$name = $1;
		$function = 1;
	}
	if ($line =~ /\@(abstract)/)
	{
		$layout = "standard";
		$name = $1;
		$abstract = 1;
	}
	if ($line =~ /\@(description)/)
	{
		$layout = "standard";
		$name = $1;
		$description = 1;
	}
	if ($line =~ /\@(param)/)
	{
		$layout = "standard";
		$name = $1;
		$param = 1;
	}
	if ($line =~ /\@(result)/)
	{
		$layout = "standard";
		$name = $1;
		$result = 1;
	}
	if ($line =~ /\@(discussion)/)
	{
		$layout = "standard";
		$name = $1;
		$discussion = 1;
	}
	if ($line =~ /\*\// && $in)
	{
		undef $in;
		$looking = 1;
		$end = 1;
	}

	if ($layout)
	{
		$output .= "\n\n" . '\layout ' . ucfirst ($layout);
		$line =~ s/\@function//;
		$line =~ s/\@//;
		$data = ucfirst ($line);
		if (!$function && !$class)
		{
			$data =~ s/$name/$name:/;
		}
		$output .= "\n$data";
		if ($function || $class)
		{
			$output .= "\n" . '\begin_inset LatexCommand \label{sec:' . "$data" . '}' . "\n\n" . '\end_inset';
		}
	}
	elsif ($in && !$start)
	{
		$output .= '\layout Standard' . "\n$line";
	}

	next:
}

$output .= "\n" . '\the_end';

print $output;
