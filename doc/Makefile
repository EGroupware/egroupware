
TOP = index

all :	$(TOP).ps

$(TOP).tex:	$(TOP).lyx
	lyx --export latex $(TOP).lyx

$(TOP).dvi:	$(TOP).tex
	latex $(TOP).tex

$(TOP).ps:	$(TOP).dvi
	dvips -o $@ $(TOP).dvi

clean:
	rm ./$(TOP).toc; rm ./$(TOP).aux; rm ./$(TOP).tex; rm ./$(TOP).log

 
