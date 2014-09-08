<?php

$wpdf['depth'] = 3;
$wpdf['verbose'] = 1;
$wpdf['internal'] = 0;
$wpdf['output_directory'] = "generated";
$wpdf['pdflatex'] = "/usr/bin/pdflatex";
$wpdf['templates'] = "extensions/wiki2pdf/main";
$wpdf['documentclass'] = "[11pt,a4paper]{report}";
$wpdf['usepackage'] =  array ( "[utf8]{inputenc}", "{german}", "{palatino}", "{graphics}", "{graphicx}",
			       "{longtable}", "{fancyhdr}", "{textcomp}", "{tabularx}", "{hyperref}", "{ltxtable}" );
$wpdf['layout'] = array ( "\\oddsidemargin0cm", "\\textwidth15.5cm", "\\topmargin-1cm", "\\textheight24cm",
                          "\\linespread{1.5}", "\\setlength{\\parindent}{0pt}", "\\setlength{\\parskip}{0pt}",
			  "\\pagestyle{fancy}", "\\fancyhead[L]{\\chaptermark}",
			  "\\fancyhead[L]{\\scalebox{0.1}[0.1]{\\includegraphics{logo.pdf}}}",
			  "\\fancyfoot[C]{\\thepage}" );



set_include_path( get_include_path()
                . PATH_SEPARATOR . 'extensions/wiki2pdf'
                . PATH_SEPARATOR . 'extensions/wiki2xml'
                );

?>