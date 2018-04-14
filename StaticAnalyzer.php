<?php

class StaticAnalyzer
{
    private static $regex_mcomment = '/\/\*(?:.|[\r\n])*?\*\//';
    private static $regex_comment = '/\/\/.*/'; #'/\/\/.*/';
    private static $regex_word = '/(\w+)/i';
    private static $regex_loop = '/\b(for|while|goto)\b/i';
    private static $regex_branching = '/\b(if|else\s+if|else|switch|case|default)\b/i';
    private static $regex_string = '/"(.*?[^\\\\])"/i';
    private static $regex_charliteral = '/\'(.*?[^\\\\])\'/i';
    private static $regex_plusminus = '/(?:\+=|[^\+]\+[^\+]|\-=|[^\-]\-[^\-])/i';
    private static $regex_putadijeli = '/(\*=|\*|\/=|\/)/i'; # GREŠKA broji deklaracije pointera
    private static $regex_modulaincdec = '/(?:%=|%|\+\+|\-\-)/i';
    private static $regex_iili = '/(&&|\|\||\band\b|\bor\b)/i';
    private static $regex_istirazliciti = '/(==|\!=|not_eq)/i';
    private static $regex_notneg = '/(?:~|![^=]|\bnot\b|\bcompl\b)/i';
    private static $regex_compare = '/(?:>=|>|<=|<)/i'; # GREŠKA: računa i #include <stdio.h> kao 2 operatora
    private static $regex_rijetkiop = '/(?:&=|\|=|(?<!&)&(?!&)|(?<!\|)\|(?!\|)|\^=|\^|&=|\.\*|\-\>|\*\*\*\*|\*\*\*|\.\.\.|~)/i'; # GREŠKA: broji & u scanf-u
    private static $regex_include = '/#\h*include\h*/i';
    private static $regex_trycatch = '/\btry\b/i';
    private static $regex_tipovi = '/(?:(?:unsigned\s+|signed\s+)?(?:\blong\s+long\s+int\b|\blong\s+long\b|\blong\b|\bchar\b|\bshort\s+int\b|\bshort\b|\bint\b|\bbool\b))|\blong double\b|\bdouble\b|\bfloat\b|\bFILE\b|\bunsigned\b|\bsigned\b|\bvoid\b/';
    private static $regex_fun = '/([a-zA-Z0-9_>]+)(?<!else)\s*\*{0,5}\s+[a-zA-Z0-9_>]+\s*?\(.*\)\s*{/i';
    private static $regex_nizovi = '/[a-zA-Z0-9_>\]]+(?<!else)(?:\**\s+\**\s*|\*+)[a-zA-Z0-9_>]+\s*\[\s*(?:\d{0,12}|.*?)\s*\]/i'; # Niz iza zareza: int x, A[5], B[5], y=0, C[5];
    private static $regex_svibrojevi = '/\d{1,50}/i';
    private static $regex_brojevi = '/\b\d+\b/i';

    private $file = "";
    private $LOC = 0, $SLOC = 0, $SLOCZ = 0;
    private $WORD = 0, $BLOCK = 0, $LOOP = 0, $BRANCH = 0;
    private $LITERAL = 0, $PLUSMINUS = 0, $MULDIV = 0, $MODULOINCDEC = 0;
    private $ORAND = 0, $SAME = 0, $NOT = 0, $COMP = 0, $RARE = 0, $INCLUDE = 0;
    private $TRY = 0, $TYPES = 0, $FUN = 0, $NUMBRACE = 0, $NUMCOMMA = 0;
    private $MAIN = 0, $AVGLINE = 0, $CHARS = 0, $INDEXING = 0, $ALLNUMBERS = 0;
    private $MCOMMENT = 0, $SCOMMENT = 0, $ARRAYS = 0, $NUMBERS = 0, $STRINGS = 0;
    public function __construct ($file, $stripstring = true)
        {
            $this->file = $file;

            //$file = preg_replace(self::$regex_mcomment, '', $file, -1, $this->MCOMMENT); #izbacimo multiline komentare
            // Multiline
            $k = strpos($file, "/*");
            while ($k) {
                $l = strpos($file, "*/", $k+2);
                if (!$l) break;
                $this->MCOMMENT ++;
                $file = substr($file, 0, $k) . substr($file, $l+2);
                $k = strpos($file, "/*", $k);
            }
            
            $file = preg_replace(self::$regex_comment, '', $file, -1, $this->SCOMMENT); #izbacimo // komentare
            if ($stripstring)
                $file = preg_replace(self::$regex_string, '', $file, -1, $this->STRINGS); #izbacimo string literale

            $file = preg_replace(self::$regex_charliteral, '', $file, -1, $this->LITERAL);
            $lines = explode("\n", $file);
            $this->ARRAYS = preg_match_all(self::$regex_nizovi, $file);
            $this->TRY = preg_match_all(self::$regex_trycatch, $file);
            $this->SAME = preg_match_all(self::$regex_istirazliciti, $file);
            $this->NOT = preg_match_all(self::$regex_notneg, $file);
            $this->PLUSMINUS = preg_match_all(self::$regex_plusminus, $file);
            $this->MULDIV = preg_match_all(self::$regex_putadijeli, $file);
            $this->MODULOINCDEC = preg_match_all(self::$regex_modulaincdec, $file);
            $this->ORAND = preg_match_all(self::$regex_iili, $file);
            $this->COMP = preg_match_all(self::$regex_compare, $file);
            $this->RARE = preg_match_all(self::$regex_rijetkiop, $file);
            $this->WORD = preg_match_all(self::$regex_word, $file);
            $this->NUMBERS = preg_match_all(self::$regex_brojevi, $file);
            $this->ALLNUMBERS = preg_match_all(self::$regex_svibrojevi, $file);
            $this->LOOP= preg_match_all(self::$regex_loop, $file);
            $this->BRANCH = preg_match_all(self::$regex_branching, $file);
            $this->INCLUDE = preg_match_all(self::$regex_include, $file);
            $this->FUN = preg_match_all(self::$regex_fun, $file);
            $this->TYPES = preg_match_all(self::$regex_tipovi, $file);

            //za main
            preg_match ('/\bint\s+main\s*\(/', $file, $match, PREG_OFFSET_CAPTURE);
            if ($match != null)
                {
                    $br_open = 1;
                    $idx = $match[0][1];
                    while ($idx < strlen($file) && $file[$idx] !== "{")
                        $idx++;
                    for ($i = $idx; $i < strlen($file); ++$i)
                        {
                            if (!ctype_space($file[$i]))
                                $this->MAIN++;
                            if ($file[$i] == "{") $br_open++;
                            else if ($file[$i] == "}") $br_open--;
                            if ($br_open === 0) break; //kraj maina
                        }
                }
            //ostalo
            foreach ($lines as $br => $content)
            {
                $this->LOC++;
                $trimmed = trim ($content);
                if ($trimmed === "" || $trimmed == "{" || $trimmed == "}")
                    {
                        if ($trimmed == "}")
                            $this->BLOCK++;
                    }
                else
                    {
                        $this->SLOC++;
                        $this->AVGLINE += strlen($content);
                        for ($k = 0; $k < strlen($content); ++$k)
                            {
                                $c = $content[$k];
                                if ($c === "}")
                                    $this->BLOCK++;
                                if ($c === "]" && $content[$k+1] != "[")
                                    $this->INDEXING++;
                                else if ($c === ",")
                                    $this->NUMCOMMA++;
                                else if ($c === ")")
                                    $this->NUMBRACE++;
                                else if ($c === ";")
                                    $this->SLOCZ++;
                                else if (!ctype_space($c))
                                    $this->CHARS++;
                            }
                    }

            }
            $this->INDEXING -= $this->ARRAYS; #prvobitno je u indexiranja ubrojana i deklaracija nizova
            if ($this->SLOC === 0)
                $this->AVGLINE = 0;
            else $this->AVGLINE = doubleval($this->AVGLINE) / doubleval($this->SLOC);

        }

    public function GetALLNUMBERS ()
        {
            return $this->ALLNUMBERS;
        }

    public function GetFile ()
        {
            return $this->file;
        }

    public function GetLOC ()
        {
            return $this->LOC;
        }

    public function GetSLOC ()
        {
            return $this->SLOC;
        }

    public function GetSLOCZ ()
        {
            return $this->SLOCZ;
        }

    public function GetWORD ()
        {
            return $this->WORD;
        }

    public function GetBLOCK ()
        {
            return $this->BLOCK;
        }

    public function GetLOOP ()
        {
            return $this->LOOP;
        }

    public function GetBRANCH ()
        {
            return $this->BRANCH;
        }

    public function GetLITERAL ()
        {
            return $this->LITERAL;
        }

    public function GetPLUSMINUS ()
        {
            return $this->PLUSMINUS;
        }

    public function GetMULDIV ()
        {
            return $this->MULDIV;
        }

    public function GetMODULOINCDEC ()
        {
            return $this->MODULOINCDEC;
        }

    public function GetORAND ()
        {
            return $this->ORAND;
        }

    public function GetSAME ()
        {
            return $this->SAME;
        }

    public function GetNOT ()
        {
            return $this->NOT;
        }

    public function GetCOMP ()
        {
            return $this->COMP;
        }

    public function GetRARE ()
        {
            return $this->RARE;
        }

    public function GetINCLUDE ()
        {
            return $this->INCLUDE;
        }

    public function GetTRY ()
        {
            return $this->TRY;
        }

    public function GetTYPES ()
        {
            return $this->TYPES;
        }

    public function GetFUN ()
        {
            return $this->FUN;
        }

    public function GetNUMBRACE ()
        {
            return $this->NUMBRACE;
        }

    public function GetNUMCOMMA ()
        {
            return $this->NUMCOMMA;
        }

    public function GetMAIN ()
        {
            return $this->MAIN;
        }

    public function GetAVGLINE ()
        {
            return $this->AVGLINE;
        }

    public function GetCHARS ()
        {
            return $this->CHARS;
        }

    public function GetINDEXING ()
        {
            return $this->INDEXING;
        }

    public function GetMCOMMENT ()
        {
            return $this->MCOMMENT;
        }

    public function GetSCOMMENT ()
        {
            return $this->SCOMMENT;
        }

    public function GetARRAYS ()
        {
            return $this->ARRAYS;
        }

    public function GetNUMBERS ()
        {
            return $this->NUMBERS;
        }

    public function GetSTRINGS ()
        {
            return $this->STRINGS;
        }

}
