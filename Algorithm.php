<?php

require_once 'Defines.php';
require_once 'StaticAnalyzer.php';
require_once 'DynamicAnalyzer.php';

class Algorithm
{

# Scan directory and read all files, return array of feature vectors
public function Traverse ($curr_homework, $homework_file, $deadline)
    {
        $FVECTORS = [];
        $path = \EP\SOURCES_PATH . DIRECTORY_SEPARATOR . $curr_homework;
        foreach(scandir($path) as $file)
            {
                $fpath = $path . DIRECTORY_SEPARATOR . $file;
                if (is_dir($fpath)) continue;
                
                $pos = strrpos($file, ".");
                $username = substr($file, 0, $pos);
                print "User $username...\n";
                $FVECTORS[$username] = $this->GetFV($username, $fpath, $curr_homework . "/" . $homework_file, $deadline);
                if (empty($FVECTORS[$username])) unset($FVECTORS[$username]);
            }
        return $FVECTORS;
    }

# Get feature vector for user
public function GetFV ($username, $realpath, $c9path, $deadline)
    {
        try {
            $d = new DynamicAnalyzer ($username, $c9path, $deadline);
        } catch (Exception $e) {
            print "Exception: " . $e->getMessage() . "\n";
            $reflector = new ReflectionClass("DynamicAnalyzer");
            $d = $reflector->newInstanceWithoutConstructor();
        } 
        $str = "";
        if (file_exists($realpath))
            $str = file_get_contents($realpath);
        $s = new StaticAnalyzer($str);
        return [
            #dinamicki dio
            \EP\LINES_ADDED => $d->GetADDED(),                  #broj dodavanja
            \EP\AVG_PASTE => $d->GetAVGPASTE(),                 #duzina prosjecnog pastea
            \EP\AVG_TEST => $d->GetAVGTEST(),                   #prosjecan broj uspjesnih testova
            \EP\COMPILED_FAILS => $d->GetCOMPILED(),            #broj neuspjesnih kompajliranja
            \EP\COMPILED_SUCCESS => $d->GetCOMPILEDSUCC(),      #broj uspjecnih kompajliranja
            \EP\LINES_DELETED => $d->GetDELETED(),              #broj brisanja
            \EP\MAX_PASTE_LEN => $d->GetMAXPASTE(),             #duzina najduzeg pastea
            \EP\LINES_MODIFIED => $d->GetMODIFIED(),            #broj izmijenjenih linija (bez dodavanja i brisanja)
            \EP\NUM_PASTES => $d->GetPASTE(),                   #broj pasteova
            \EP\TS_SPEED => $d->GetSPEED(),                     #brzina promjena (razlika između timestampova)
            \EP\TESTINGS => $d->GetTESTED(),                    #broj testiranja
            \EP\WORK_TIME => $d->GetTIME(),                     #vrijeme pisanja kôda
            \EP\SHORT_BREAKS => $d->GetSHORTBREAKS(),                     #pauze 15-300 sekundi
            \EP\LONG_BREAKS => $d->GetLONGBREAKS(),                     #pauze 300-900 sekundi
            #staticki dio
            \EP\ALL_NUMBERS => $s->GetALLNUMBERS(),             #broj brojeva (svi, i ab1)
            \EP\NUM_ARRAYS => $s->GetARRAYS(),                  #broj nizova
            \EP\AVG_LINE_LEN => $s->GetAVGLINE(),               #prosjecna duzina linije
            \EP\NUM_BLOCKS => $s->GetBLOCK(),                   #broj blokova ({..})
            \EP\NUM_BRANCHES => $s->GetBRANCH(),                #broj naredbi za grananje (if, else [if], switch, goto)
            \EP\NUM_CHARS => $s->GetCHARS(),                    #broj znakova koji nisu bjeline
            \EP\COMP_OP => $s->GetCOMP(),                       #broj relacionih operatora (>, <, <=, >=)
            \EP\NUM_FUN => $s->GetFUN(),                        #broj funkcija
            \EP\NUM_INCLUDE => $s->GetINCLUDE(),                #broj #include naredbi
            \EP\NUM_ARRAY_ACCESS => $s->GetINDEXING(),          #broj prisupa nizovima
            \EP\NUM_LITERALS => $s->GetLITERAL(),               #broj znakovnih literala ('a', '\0'...)
            \EP\LOC => $s->GetLOC(),                            #broj fizickih linija kôda
            \EP\NUM_LOOPS => $s->GetLOOP(),                     #broj petlji (for, while, do while, "goto")
            \EP\MAIN_LEN => $s->GetMAIN(),                      #duzina znakova main funkcije
            \EP\NUM_MLCOMMENTS => $s->GetMCOMMENT(),            #broj multilinijskih komentara
            \EP\NUM_MODULO_INCDEC_OP => $s->GetMODULOINCDEC(),  #broj modulo i inc/dec operatora (%, ++, --)
            \EP\MULDIV_OP => $s->GetMULDIV(),                   #broj * i / operatora (i /=, *=)
            \EP\NEG_OP => $s->GetNOT(),                         #broj operatora za negaciju (!, ~, compl, not)
            \EP\NUMBERS => $s->GetNUMBERS(),                    #broj "pravih" brojeva (123, 43, ali NE abc1)
            \EP\NUM_BRACES => $s->GetNUMBRACE(),                #broj zagrada
            \EP\NUM_COMMAS => $s->GetNUMCOMMA(),                #broj zareza
            \EP\PLUSMINUS_OP => $s->GetPLUSMINUS(),             #broj + i - operatora (i += i -=)
            \EP\RARE_OP => $s->GetRARE(),                       #broj rijetkih op (&, ->, |=, ...)
            \EP\EQUAL_OP => $s->GetSAME(),                      #broj operatora == i != (i not_eq)
            \EP\NUM_SLCOMMENTS => $s->GetSCOMMENT(),            #broj jednolinijskih komentara
            \EP\SLOC => $s->GetSLOC(),                          #broj logickih linija
            \EP\SLOC_CMD => $s->GetSLOCZ(),                     #broj naredbi ("pravih" linija)
            \EP\ORAND_OP => $s->GetORAND(),                     #broj && i || operatora
            \EP\NUM_STRING => $s->GetSTRINGS(),                 #broj stringovnih literala ("abc")
            \EP\NUM_TRYS => $s->GetTRY(),                       #broj try-catch blokova
            \EP\NUM_VARS => $s->GetTYPES(),                     #priblizan broj primitivnih varijabli
            \EP\NUM_WORDS => $s->GetWORD()                      #broj regex rijeci
        ];
    }
# Get default weights
public function GetW ()
    {
        $W = [];
        #dinamicki dio
        $W[\EP\LINES_ADDED] = -1.0;           #broj dodavanja						-0.59 - -0.77		-0.25 (outliers: 0.35*2, -0.40*1)
        $W[\EP\AVG_PASTE] = 1.0;             #duzina prosjecnog pastea					0.51 - 0.58		0 - 0.34
        $W[\EP\AVG_TEST] = 1.0;              #prosjecan broj uspjesnih testova				0.86 - 1.41		0 - 0.19 (outliers: 0.58*2, 0.38*2, 0.84, 0.74)
        $W[\EP\COMPILED_FAILS] = 1.0;        #broj neuspjesnih kompajliranja				0.31 - 0.75		-0.70 - 0 (većinom 0)
        $W[\EP\COMPILED_SUCCESS] = 1.0;      #broj uspjecnih kompajliranja				0.68 - 0.70		0 - 0.30 (outliers: -0.59*2, 4.42*2, 1.28)
        $W[\EP\LINES_DELETED] = 1.0;         #broj brisanja						0.77 - 0.79		0.73 - 0.94
        $W[\EP\MAX_PASTE_LEN] = 1.0;         #duzina najduzeg pastea					0.63 - 0.67		0 - 0.10 (outliers: -0.69*4 - slabi)
        $W[\EP\LINES_MODIFIED] = 1.0;        #broj izmijenjenih linija (bez dodavanja i brisanja)	0.65 - 0.88		-0.10 - 0.20
        $W[\EP\NUM_PASTES] = 1.0;            #broj pasteova						0.71 - 0.73		0.23 - 0.45 (outliers: 0.11, 0.55)
        $W[\EP\TS_SPEED] = 1.0;              #brzina promjena (razlika između timestampova)		0.63 - 0.64		1.13 - 2.06 (bliže 1.13)
        $W[\EP\TESTINGS] = 1.0;              #broj testiranja						0.88 - 0.92		-0.40 - 0.10
        $W[\EP\WORK_TIME] = 1.0;             #vrijeme pisanja kôda					0.56 - 0.75		-0.30 - 0 (centar na -0.10, outliers: +0.10*2)
        $W[\EP\SHORT_BREAKS] = 1.0;             #pauze 15-300 sekundi
        $W[\EP\LONG_BREAKS] = 1.0;             #pauze 300-900 sekundi
        $W[\EP\TIME_FROM_DEADLINE] = 1.0;    #koliko sati prije isteka roka je poceo
        #staticki dio
        $W[\EP\ALL_NUMBERS] = 1.0;           #broj brojeva (svi, i ab1)					1.74			2.04 (outliers: 1.94)
        $W[\EP\NUM_ARRAYS] = 1.0;            #broj nizova						0.71 - 0.73		0.10
        $W[\EP\AVG_LINE_LEN] = 1.0;          #prosjecna duzina linije					0.88			0.38
        $W[\EP\NUM_BLOCKS] = 1.0;            #broj blokova ({..})					0.80 			5.80 - 5.90 (outliers: 6.10)
        $W[\EP\NUM_BRANCHES] = 1.0;          #broj naredbi za grananje (if, else [if], switch, goto)	0.76			5.88 - 5.98 (outliers: 5.78, 6.08)
        $W[\EP\NUM_CHARS] = 1.0;             #broj znakova koji nisu bjeline				0.93			0.56 (outliers: 0.28*2)
        $W[\EP\COMP_OP] = 1.0;               #broj relacionih operatora (>, <, <=, >=)			0.75 - 0.77		0.42 - 0.52 (outliers: 0.00*2)
        $W[\EP\NUM_FUN] = 1.0;               #broj funkcija						1.10 - 1.12		2.90 - 3.30 (outliers: 5.80)
        $W[\EP\NUM_INCLUDE] = 1.0;           #broj #include naredbi					0.82 - 0.95		0.00
        $W[\EP\NUM_ARRAY_ACCESS] = 1.0;      #broj prisupa nizovima					0.78 - 0.80		1.86 - 1.96
        $W[\EP\NUM_LITERALS] = 1.0;          #broj znakovnih literala ('a', '\0'...)			0.75			0.77 (outliers: 0.00*5)
        $W[\EP\LOC] = 1.0;                   #broj fizickih linija kôda					0.83			0.10
        $W[\EP\NUM_LOOPS] = 1.0;             #broj petlji (for, while, do while, "goto")		0.64 - 0.66		3.36 - 3.56
        $W[\EP\MAIN_LEN] = 1.0;              #duzina znakova main funkcije				0.55			0.10 - 0.30 (outliers: -0.44)
        $W[\EP\NUM_MLCOMMENTS] = 1.0;        #broj multilinijskih komentara				0.71 - 0.73		0.05
        $W[\EP\NUM_MODULO_INCDEC_OP] = 1.0;  #broj modulo i inc/dec operatora (%, ++, --)		0.75 - 0.76		0.00 - 0.10 (outliers: -0.10*2)
        $W[\EP\MULDIV_OP] = 1.0;             #broj * i / operatora (i /=, *=)				0.58			0.63 - 2.60 (outliers: 5.04*3, 2.92, 2.82)
        $W[\EP\NEG_OP] = 1.0;                #broj operatora za negaciju (!, ~, compl, not)		0.45			2.10 - 2.30 (outliers: 2.40*2 - slabi)
        $W[\EP\NUMBERS] = 1.0;               #broj "pravih" brojeva (123, 43, ali NE abc1)		0.67 - 0.86		6.58 (outliers, 6.68, 6.48)
        $W[\EP\NUM_BRACES] = 1.0;            #broj zagrada						0.65 			0.25 - 0.45
        $W[\EP\NUM_COMMAS] = 1.0;            #broj zareza						0.65			1.00 (outliers: 0.90*3 - slabi)
        $W[\EP\PLUSMINUS_OP] = 1.0;          #broj + i - operatora (i += i -=)				0.82			6.76 (outliers: 7.06, 6.96*2)
        $W[\EP\RARE_OP] = 1.0;               #broj rijetkih op (&, ->, |=, ...)				0.71			0.40 - 0.50
        $W[\EP\EQUAL_OP] = 1.0;              #broj operatora == i != (i not_eq)				0.78			8.00 - 8.10
        $W[\EP\NUM_SLCOMMENTS] = 1.0;        #broj jednolinijskih komentara				0.93 - 1.04		0.05 - 0.10 (outliers: 0.00)
        $W[\EP\SLOC] = 1.0;                  #broj logickih linija					0.83			1.13 (outliers: 1.23*2 - slabi)
        $W[\EP\SLOC_CMD] = 1.0;              #broj naredbi ("pravih" linija)				0.50 - 0.51		0.00 - 0.10
        $W[\EP\ORAND_OP] = 1.0;              #broj && i || operatora					0.80 - 0.81		6.08 - 6.28
        $W[\EP\NUM_STRING] = 1.0;            #broj stringovnih literala ("abc")				0.53			0.45 (outliers: -0.05*3)
        $W[\EP\NUM_TRYS] = 1.0;              #broj try-catch blokova					0.64 - 0.66		0.00 - 0.46
        $W[\EP\NUM_VARS] = 1.0;              #priblizan broj primitivnih varijabli			0.67 - 0.68		0.00
        $W[\EP\NUM_WORDS] = 1.0;             #broj regex rijeci						0.76 - 0.77		-1.70 - -1.80 (outliers: -0.85*2, -0.65 - slabi)
        return $W;
    }
private function MinMax ($val, $min, $max, $A = 0.1, $B = 0.9)
    {
        if ($val == 0 || $max - $min == 0) return 0;
        $val = doubleval($val);
        return $A + (($val - $min) / ($max - $min)) * ($B - $A);
    }
private function Fix (&$a, $b, $min = false)
    {
        foreach ($a as $k => &$v)
                if ($min && $b[$k] < $v)
                    $v = $b[$k];
                else if (!$min && $b[$k] > $v)
                    $v = $b[$k];

    }
# Min-Max normalization of feature vector
public function Normalize (&$FVECTOR)
    {
        $minarr = reset($FVECTOR);
        $maxarr = reset($FVECTOR);
        foreach ($FVECTOR as $v)
            {
                $this->Fix($minarr, $v, true);
                $this->Fix($maxarr, $v, false);
            }
        foreach ($FVECTOR as &$VEC)
                foreach ($VEC as $p => &$X)
                        $X = $this->MinMax($X, doubleval($minarr[$p]), doubleval($maxarr[$p]));
    }
function Signum ($x) {return ($x < 0 ? -1 : ($x > 0 ? 1 : 0));}
public function Distance ($v1, $v2, $w, $dist = \EP\DEFAULT_DISTANCE)
    {
        $sum = 0.0;
        if ($dist == 1) #Euclid
            {
                $dynamic1 = false; $dynamic2 = false;
                $count1 = 0; $count2 = 0; $i=0;
                foreach ($v1 as $k => $v)
                    {
                        if (\EP\SKIP_DYNAMIC && ++$i < \EP\NR_DYNAMIC_FEATURES) continue;
                        $wi = $w[$k];
                        $y = $v2[$k];
                        //$sum += $this->Signum($wi) * $wi * $wi * (($v - $y) * ($v - $y));
                        if ($wi < 0)
                            $sum -= $wi * $wi * (($v - $y) * ($v - $y));
                        else if ($wi > 0)
                            $sum += $wi * $wi * (($v - $y) * ($v - $y));
                    }
                return $this->Signum($sum) * sqrt ( abs( $sum ) );
            }
        else if ($dist == 2) #Manhattan
            {
                foreach ($v1 as $k => $v)
                    $sum += $w[$k] * abs ($v - $v[$k]);
                return $sum;
            }
        else if ($dist == 3) #Cosine
            {
                $sumxx = 0.0; $sumxy = 0.0; $sumyy = 0.0;
                foreach ($v1 as $k => $v)
                    {
                        $sumxx = $w[$k] * $v * $v;
                        $sumyy = $v2[$k] * $v2[$k];
                        $sumxy = $v * $v2[$k];
                    }
                return 1.0 - ($sumxy / sqrt($sumxx * $sumyy));
            }
        throw new Exception("Unknown distance");
    }
# Calculate distances for all feature vectors, returns matrix of distances
public function CalculateDistances($FVECTORS, $FVW)
    {
        $DISTANCES = [];
        $FVKEYS = array_keys($FVECTORS);
        $FVLEN = count ($FVECTORS);
        for ($i = 0; $i < $FVLEN; ++$i)
            for ($j = 0; $j < $FVLEN; ++$j) {
                if ($i==$j) continue;
                $DISTANCES[$FVKEYS[$i]][$FVKEYS[$j]] = $this->Distance($FVECTORS[$FVKEYS[$i]], $FVECTORS[$FVKEYS[$j]], $FVW);
            }
        return $DISTANCES;
    }
# Prints out distances < threshold    
public function NiceOutput($DISTANCES, $threshold = \EP\DEFAULT_THRESHOLD)
    {
        foreach ($DISTANCES as $k => $v)
            {
                foreach ($v as $kk => $vv)
                    {
                        if ($vv < $threshold)
                            echo "$k - $kk: <b>"; printf("%5.2f", $vv); echo "</b><br>";
                    
                    } #TODO malo bolje ovo formatirati + skalirati distance
                echo "<br>\n";
            }
    }

}


?>
