<?php

#prilagodjena verzija ovog fajla: https://github.com/vljubovic/c9etf/blob/master/lib/reconstruct.php
#by Vedran Ljubovic

require_once 'Defines.php';

class Reconstruct
{
    private static $REPLACE_LIMIT = 3; // Maximum number of lines remaining in replace event e.g. hello world
    private static $DEBUG = false;
    
    private $incgoto = false;
    private $stats = null;
    private $file = null;
    private $username, $filename;
    private $lastEvent = 0, $totalEvents = 0;
    private $codeReplace = [], $codeReplaceMatch = [];
    
    public function __construct ($username, $include_goto = false)
        {
            $this->incgoto = $include_goto;
            $this->username = $username;
        }
    public function GetFile () {return $this->file;}
    public function GetStats () {return $this->stats;}
    public function GetTotalEvents () {return $this->totalEvents;}
    public function GetCodeReplaceEvents () {return array ( $this->codeReplace, $this->codeReplaceMatch ); }
    public function ReadStats() 
        {
            $username_efn = self::escape_filename($this->username);
            $stat_file = \EP\STATS_PATH . DIRECTORY_SEPARATOR . "$username_efn.stats";
            $stats = null;
            if (file_exists($stat_file)) eval(file_get_contents($stat_file));
            $this->stats = $stats;
            if ($this->stats == NULL) {
                $this->stats = array(
                    "global_events" => array(),
                    "last_update_rev" => 0
                );
            }
            // Stats file can reference other files to be included
            if(!$this->incgoto) return;
            foreach ($this->stats as $key => $value)
                if (is_array($value) && array_key_exists("goto", $value)) 
                    {
                        $goto_path = $stat_file = \EP\STATS_PATH . DIRECTORY_SEPARATOR .  $value['goto'];
                        $stats_goto = null;
                        eval(file_get_contents($goto_path));
                        if ($stats_goto == null) continue;
                        foreach($stats_goto as $ks => $vs)
                            $this->stats[$ks] = $vs;
                        $stats_goto = null;
                    }
            return $this->stats;
        }

    public function GetRelevantStats () 
        {
            $stats = $this->stats[$this->filename];
            
            // Find index of 'created' event
            $idxCreated = 0;
            for (; $idxCreated < $this->totalEvents; $idxCreated++)
                if ($stats['events'][$idxCreated]['text'] == "created")
                    break;
            
            foreach($this->codeReplace as $idx => $code)
                {
                    if (array_key_exists($idx, $this->codeReplaceMatch))
                        {
                            $this->lastEvent = 0;
                            $this->ReconstructFileForward("+$idx");
                            $before = $this->file;
                            
                            $afterIdx = $this->codeReplaceMatch[$idx];
                            $this->ReconstructFileForward("+" . ($afterIdx+1));
                            $after = $this->file;
                            
                            unset($stats['events'][$afterIdx]['diff']['remove_lines']);
                            unset($stats['events'][$afterIdx]['diff']['add_lines']);
                            unset($stats['events'][$afterIdx]['diff']['change']);
                            
                            // Cleanup diff
                            foreach($before as $key1 => $line1) {
                                foreach($after as $key2 => $line2) {
                                    if ($line1 == $line2) {
                                        unset($before[$key1]);
                                        unset($after[$key2]);
                                        break;
                                    }
                                }
                            }
                            foreach($before as $key1 => &$line1) chop($line1);
                            foreach($after as $key2 => &$line2) chop($line2);
                            
                            if (!empty($before)) $stats['events'][$afterIdx]['diff']['remove_lines'] = $before;
                            if (!empty($after)) $stats['events'][$afterIdx]['diff']['add_lines'] = $after;
                            
                            array_splice($stats['events'], $idx, $afterIdx-$idx);
                        }
                    else
                        {
                            $idx+=2;
                            $time = $stats['events'][$idx]['time'];
                            $this->lastEvent = 0;
                            $this->ReconstructFileForward("+$idx");
                            $file = join("", $this->file);
                            
                            array_splice($stats['events'], $idxCreated+1, $idx-1);
                            $stats['events'][$idxCreated]['time'] = $time;
                            $stats['events'][$idxCreated]['content'] = $file;
                        }
                }
            
            return $stats;
        }
    public function TryReconstruct($realpath, $c9path, $timestamp)
        {
            if (intval($timestamp) < 100 && $timestamp[0] != "+") $timestamp = strtotime($timestamp);
            //list($s, $this->file) = self::reconstruct_file($this->username, $realpath, $c9path, $timestamp);
            // return $s;
            $this->filename = $c9path;
            return Reconstruct::ReconstructFileForward ($timestamp);
        }

    private static function  escape_filename($raw) {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $raw);
    }
    private function reconstruct_file($username, $realpath, $c9path, $timestamp) 
        {
            $status = 0;
            if (!array_key_exists($c9path, $this->stats))
                return [-1, ""]; #nema ga u logovima

            if (!file_exists($realpath)) {
                $status = 1;
                $work_file = array();
            } else
                $work_file = file($realpath);

            $file_log = $this->stats[$c9path]['events'];
            $evtcount = count($file_log);
            
            $end = 0;
            if ($timestamp[0] == "+") $end = $evtcount-intval($timestamp);

            // We reconstruct the file backwards from its current state
            for ($i = $evtcount-1; $i >= $end; $i--) {
                //print "$i,";
                if ($file_log[$i]['time'] < $timestamp) break;
                if ($i < -$timestamp) break;
                if ($file_log[$i]['text'] != "modified") continue;

                if (array_key_exists("change", $file_log[$i]['diff']))
                    foreach($file_log[$i]['diff']['change'] as $lineno => $text) {
                        // Editing last line - special case!
                        if ($lineno-1 == count($work_file)) $lineno--;
                        // Since php arrays are associative, we must initialize missing members in correct order
                        if ($lineno-1 > count($work_file)) {
                            if ($lineno == 2) $lineno=1;
                            else {
                                for ($j=count($work_file); $j<$lineno; $j++)
                                    $work_file[$j] = "\n";
                            }
                        }
                        $work_file[$lineno-1] = $text . "\n";
                    }
                if (array_key_exists("add_lines", $file_log[$i]['diff'])) {
                    $offset=1;
                    foreach($file_log[$i]['diff']['add_lines'] as $lineno => $text) {
                        if ($offset == 0 && $lineno == 0) $offset=1;
                        if ($lineno-$offset > count($work_file))
                            for ($j=count($work_file); $j<$lineno-$offset+1; $j++)
                                $work_file[$j] = "\n";
                        array_splice($work_file, $lineno-$offset, 1);
                        $offset++;
                    }
                }
                if (array_key_exists("remove_lines", $file_log[$i]['diff'])) {
                    $offset=-1;
                    foreach($file_log[$i]['diff']['remove_lines'] as $lineno => $text) {
                        if ($lineno+$offset > count($work_file))
                            for ($j=count($work_file); $j<$lineno+$offset+1; $j++)
                                $work_file[$j] = "\n";
                        if ($text == "false" || $text === false) $text = "";
                        array_splice($work_file, $lineno+$offset, 0, $text . "\n");
                    }
                }
            }

            return [$status, join ("", $work_file)];
        }

    public function ReconstructFileForward ($timestamp) 
        {
            if (!array_key_exists($this->filename, $this->stats))
                return false; #nema ga u logovima
            
            $file_log = $this->stats[$this->filename]['events'];
            $this->totalEvents = count($file_log);
            $offset = 0;
            
            $end = $this->totalEvents;
            if ($timestamp[0] == "+") $end = intval($timestamp);
            
            // We will reconstruct the file forwards from initial create
            for ($i=$this->lastEvent; $i<$end; $i++) 
                {
                    if (!array_key_exists($i, $file_log)) continue;
                    //print "$i,";
                    if ($timestamp[0] != "+" && $file_log[$i]['time'] > $timestamp) break;
                    if ($i < -$timestamp) break;
                    
                    if ($file_log[$i]['text'] == "created") 
                        {
                            $this->file = explode("\n", $file_log[$i]['content']);
                            foreach($this->file as &$line) $line .= "\n";
                        }
                    
                    if ($file_log[$i]['text'] != "modified") continue;
                    
                    if (array_key_exists("change", $file_log[$i]['diff']))
                            foreach($file_log[$i]['diff']['change'] as $lineno => $text) 
                                {
                                    // Editing last line - special case!
                                    if ($lineno-1 == count($this->file)) $lineno--;
                                    // Since php arrays are associative, we must initialize missing members in correct order
                                    if ($lineno-1 > count($this->file)) 
                                        {
                                            if ($lineno == 2) $lineno=1;
                                            else {
                                                    for ($j=count($this->file); $j<$lineno; $j++)
                                                            $this->file[$j] = "\n";
                                            }
                                        }
                                    $this->file[$lineno-1] = $text . "\n";
                                }
                    
                    $hasRemove = array_key_exists("remove_lines", $file_log[$i]['diff']);
                    $hasAdd = array_key_exists("add_lines", $file_log[$i]['diff']);
                    
                    // Detect code-replace events
                    $isCodeReplace = false;
                    $removeCount = 0;
                    if ($hasRemove) $removeCount = count($file_log[$i]['diff']['remove_lines']);

                    if (count($this->file) - $removeCount < self::$REPLACE_LIMIT )
                        {
                            if ($removeCount > 5) $this->codeReplace[$i-1] = $this->file;
                            $isCodeReplace = true;
                            if (self::$DEBUG) print "CodeReplace: removed $removeCount from ".count($this->file)."\n";
                        }
                    
                    // Create a combined sorted array
                    $lines = array();
                    if ($hasRemove)
                        foreach($file_log[$i]['diff']['remove_lines'] as $lineno => $text)
                            $lines[$lineno][] = "-".$text;
                    if ($hasAdd)
                        foreach($file_log[$i]['diff']['add_lines'] as $lineno => $text)
                            $lines[$lineno][] = "+".$text;
                    
                    ksort($lines);
                    
                    $offset = -1; $lineRemoved=-1;
                    foreach($lines as $lineno => $spec) {
                        foreach($spec as $entry) {
                            $text = substr($entry,1) . "\n";
                            
                            if ($entry[0] == '-' && $lineRemoved == $lineno-1) $offset--; // Contiguous removal
                            
                            if ($entry[0] == '-' && $lineno+$offset < count($this->file) && $this->file[$lineno+$offset] == $text) {
                                if (self::$DEBUG) print "Izbacujem liniju $lineno (ok)\n";
                                array_splice($this->file, $lineno+$offset, 1);
                                $lineRemoved=$lineno;
                                
                                // Ako je izbačena pretposljednja linija u fajlu, a posljednja je prazna, trebalo je i nju izbaciti (bug u svn logu)
                                if ($lineno+$offset == count($this->file)-1 && $this->file[$lineno+$offset] == "\n")
                                    array_splice($this->file, $lineno+$offset, 1);
                            } else if ($entry[0] == '-') {
                                if (self::$DEBUG) print "Izbacivanje nije ok ($lineno treba biti '".chop($text)."' a glasi '".chop($this->file[$lineno+$offset])."')\n";
                                if ($lineno+$offset > 0 && $this->file[$lineno+$offset-1] == $text) {
                                    if (self::$DEBUG) print "Korigujem -1\n";
                                    $offset--;
                                    array_splice($this->file, $lineno+$offset, 1);
                                }
                                else if ($this->file[$lineno+$offset+1] == $text) {
                                    if (self::$DEBUG) print "Korigujem +1\n";
                                    $offset++;
                                    array_splice($this->file, $lineno+$offset, 1);
                                }
                            } else {
                                if ($lineno+$offset < 0) $offset = -$lineno;
                                if (self::$DEBUG) print "Ubacujem liniju $lineno ('".chop($text)."')\n";
                                array_splice($this->file, $lineno+$offset, 0, $text);
                                $lineRemoved=-1;
                            }
                        }
                    }
                    
                    if ($isCodeReplace && count($this->file) == 2 && empty(chop($this->file[1])))
                        array_splice($this->file, 1, 1);
                            
                    // Check if we are infact reverting to an older version (a.k.a accidental delete / reformat events)
                    if ($isCodeReplace)
                        {
                            foreach($this->codeReplace as $eventId => $code) {
                                $linesDiff = Reconstruct::EquivalentCode($code, $this->file);
                                if (self::$DEBUG) print "isCodeReplace Vraćen broj ".$linesDiff." eventid $eventId i $i\n";
                                if ($linesDiff < 3) {
                                    if ($eventId == $i-1)
                                        unset($this->codeReplace[$eventId]); // Reformat event
                                    else
                                        $this->codeReplaceMatch[$eventId] = $i;
                                }
                            }
                        }
                      
                }
            
            $this->lastEvent = $end;
            return true;
        }

    // Check if two blocks of code are equivalent in case of reformat
    public static function EquivalentCode ($code1, $code2)
        {
            $code1 = Reconstruct::CleanupCode($code1);
            $code2 = Reconstruct::CleanupCode($code2);
            foreach($code1 as $key1 => $line1) 
                {
                    foreach($code2 as $key2 => $line2)
                        if ($line1 == $line2)
                            {
                                unset($code1[$key1]);
                                unset($code2[$key2]);
                                break;
                            }
                    
                }
            return count($code1) + count($code2);
        }

    // Cleanup a block of code to detect reformat event
    public static function CleanupCode ($code)
        {
            foreach($code as $key => &$line) 
                {
                    $line = Reconstruct::CleanupLineReformat($line);
                    if (empty($line)) unset($code[$key]);
                }
            return $code;
        }

    // Cleanup string line to detect reformat event
    private static function CleanupLineReformat ($txt)
        {
            $txt = str_replace("{", "", $txt);
            $txt = str_replace("}", "", $txt);
            $txt = preg_replace("/\s+/", " ", $txt);
            $txt = preg_replace("/(\w) (\W)/", "$1$2", $txt);
            $txt = preg_replace("/(\W) (\w)/", "$1$2", $txt);
            $txt = preg_replace("/(\W) (\W)/", "$1$2", $txt);
            return trim($txt);
        }
}
