
TOP = index

all :	$(TOP).ps $(TOP).txt

$(TOP).tex:	$(TOP).lyx
	lyx --export latex $(TOP).lyx

$(TOP).dvi:	$(TOP).tex
	latex $(TOP).tex

$(TOP).ps:	$(TOP).dvi
	dvips -o $@ $(TOP).dvi

$(TOP).txt:	$(TOP).lyx
	lyx --export text $(TOP).lyx

clean:
	rm ./$(TOP).toc; rm ./$(TOP).aux; rm ./$(TOP).tex; rm ./$(TOP).log

 
