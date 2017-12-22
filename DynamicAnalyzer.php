<?php

require_once 'Defines.php';
require_once 'Reconstruct.php';

class DynamicAnalyzer
{
    private $COMPILED = 0, $TESTED = 0, $COMPILEDSUCC = 0, $AVGTEST = 0.0;
    private $MODIFIED = 0, $ADDED = 0, $DELETED = 0, $TIME = 0;
    private $PASTE = 0, $MAXPASTE = 0, $AVGPASTE = 0, $SPEED = 0;
    private $SHORTBREAKS = 0, $LONGBREAKS = 0;

    private static $PASTE_LIMIT = 5; // Maximum number of lines that represent a paste event
    private static $TYPE_LIMIT = 15; // < 15 second = typing
    private static $SMALL_BREAK_LIMIT = 300; // < 300 seconds = 5 minutes = small break
    private static $LONG_BREAK_LIMIT = 900; // < 900 seconds = 15 minutes = long break

    private $stats = null;
    private $dirname = null;
    private $fullpath = null;
    public function __construct ($username, $path, $deadline)
        {
            $r = new Reconstruct($username, \EP\RECONSTRUCT_USE_GOTO);
            $stats = $r->ReadStats();
            
            if ($stats == null)
                throw new Exception("Stats file je null!", 5);
            if (!array_key_exists($path, $stats))
                throw new Exception("Path ($path) ne postoji u stats fajlu $username!", 6);
            if (count ($stats[$path]) == 0)
                throw new Exception("Path ($path) je prazan!?", 7);
            
            // Filter events from before when editor was empty
            $start = 0;
            $r->TryReconstruct($path, $deadline);
            $cutoff = $r->GetRelevantStats(); // This returns events just for $path
            if (array_key_exists('events', $cutoff) && count($cutoff['events'])>0)
                $start = reset($cutoff['events'])['time'];
            
            $stats = Reconstruct::StatsDeadline( $stats, $deadline, $start );
            $this->stats = $stats;
            
            $this->dirname = pathinfo($path, PATHINFO_DIRNAME);
            $this->fullpath = $path;
            
            if (!array_key_exists($this->dirname, $stats))
                throw new Exception("Path ($this->dirname) ne postoji u stats fajlu!", 6);

            $this->COMPILED = intval($stats[$this->dirname]['builds']);
            $this->COMPILEDSUCC = intval($stats[$this->dirname]['builds_succeeded']);
            $this->TESTED = intval($stats[$this->dirname]['testings']);
            $this->TIME = intval($stats[$path]['total_time']);
            $events = $stats[$this->dirname]['events']; #na nivou foldera/zadatka
            $testcnt = 0;
            $total_tests = 0;
            foreach ($events as $evt)
                if (trim($evt['text']) === 'ran tests')
                    {
                        $txt = trim($evt['test_results']);
                        $txt = explode("/", $txt);
                        $proslo = doubleval ($txt[0]);
                        $total_tests = doubleval($txt[1]);
                        if ($total_tests > 0) 
                            $this->AVGTEST += $proslo / $total_tests;
                        $testcnt++;
                    }
            if ($testcnt > 0) $this->AVGTEST /= $testcnt;
            $events = $stats[$path]['events'];
            $first = true; $previous = null;
            $total_cps = $typing_events = 0;
            foreach ($events as $evt)
                {
                    if ($evt['text'] === 'modified' && array_key_exists('diff', $evt) && count($evt['diff']) > 0)
                        {
                            $arr = $evt['diff'];
                            $changed = 0; $chlen = 0;
                            
                            // Avoid warnings
                            $add = $remove = $change = [];
                            if (array_key_exists('add_lines', $arr)) $add = $arr['add_lines'];
                            if (array_key_exists('remove_lines', $arr)) $remove = $arr['remove_lines'];
                            if (array_key_exists('change', $arr)) $change = $arr['change'];
                            
                            // Handle code reformatting
                            if (!empty($add) && !empty($remove))
                                {
                                    $add = Reconstruct::CleanupCode($add);
                                    $remove = Reconstruct::CleanupCode($remove);
                                    foreach($add as $keya => $addline)
                                        foreach($remove as $keyr => $removeline)
                                            if ($addline == $removeline)
                                                {
                                                    unset($add[$keya]);
                                                    unset($remove[$keyr]);
                                                    break;
                                                }
                                }
                                
                            
                            $this->ADDED += count($add);
                            $this->DELETED += count($remove);
                            $this->MODIFIED += count($change);
                            
                            // Detect paste
                            $lines_changed = count($add) + count($change);
                            if ($lines_changed > self::$PASTE_LIMIT)
                                {
                                    $change_length = 0;
                                    foreach($add as $line) $change_length += strlen($line);
                                    foreach($change as $line) $change_length += strlen($line);
                                    
                                    $this->PASTE++;
                                    $this->AVGPASTE += $change_length;
                                    if ($change_length > $this->MAXPASTE)
                                        $this->MAXPASTE = $change_length;
                                }
                            
                            // Detect typing speed and short/long breaks
                            else if (!$first) 
                                {
                                    $time_interval = abs ($evt['time'] - $previous['time']);
                                    if ($time_interval == 0) $time_interval = 1;
                                    
                                    if ($time_interval < self::$TYPE_LIMIT && (!empty($add) || !empty($change)))
                                        {
                                            $diff_length = 0;
                                            foreach($add as $line) $diff_length += strlen($line);
                                            foreach($change as $no => $line)
                                                {
                                                    if ($previous['text'] == "created")
                                                        {
                                                            $code = explode("\n", $previous['content']);
                                                            foreach ($code as &$line) $line .= "\n";
                                                            if (count($code) > $no-1)
                                                                $diff_length += abs(strlen($line) - strlen($code[$no-1]));
                                                            else
                                                                $diff_length += strlen($line);
                                                        }
                                                    else if (array_key_exists('change', $previous['diff']) && array_key_exists($no, $previous['diff']['change']))
                                                        $diff_length += abs(strlen($line) - strlen($previous['diff']['change'][$no]));
                                                    else if (array_key_exists('add_lines', $previous['diff']) && array_key_exists($no, $previous['diff']['add_lines']))
                                                        $diff_length += abs(strlen($line) - strlen($previous['diff']['add_lines'][$no]));
                                                    else
                                                        $diff_length += strlen($line);
                                                }
                                            
                                            $cps = $diff_length / $time_interval;
                                            $total_cps += $cps;
                                            $typing_events++;
                                        }
                                        else if ($time_interval >= self::$TYPE_LIMIT && $time_interval < self::$SMALL_BREAK_LIMIT)
                                            $this->SHORTBREAKS++;
                                        else if ($time_interval >= self::$SMALL_BREAK_LIMIT && $time_interval < self::$LONG_BREAK_LIMIT)
                                            $this->LONGBREAKS++;
                                }
                        }
                                
                     
                    // Previous event
                    $first = false;
                    $previous = $evt;
                }
                
            if ($typing_events > 0) $this->SPEED = doubleval($total_cps) / doubleval($typing_events);
            else $this->SPEED = 0;
            if ($this->PASTE === 0) $this->AVGPASTE  = 0;
            else $this->AVGPASTE = doubleval($this->AVGPASTE) / doubleval($this->PASTE);

        }


    public function GetCOMPILED ()
        {
            return $this->COMPILED;
        }

    public function GetTESTED ()
        {
            return $this->TESTED;
        }

    public function GetCOMPILEDSUCC ()
        {
            return $this->COMPILEDSUCC;
        }

    public function GetAVGTEST ()
        {
            return $this->AVGTEST;
        }

    public function GetMODIFIED ()
        {
            return $this->MODIFIED;
        }

    public function GetADDED ()
        {
            return $this->ADDED;
        }

    public function GetDELETED ()
        {
            return $this->DELETED;
        }

    public function GetTIME ()
        {
            return $this->TIME;
        }

    public function GetPASTE ()
        {
            return $this->PASTE;
        }

    public function GetMAXPASTE ()
        {
            return $this->MAXPASTE;
        }

    public function GetAVGPASTE ()
        {
            return $this->AVGPASTE;
        }

    public function GetSPEED ()
        {
            return $this->SPEED;
        }

    public function GetSHORTBREAKS ()
        {
            return $this->SHORTBREAKS;
        }

    public function GetLONGBREAKS ()
        {
            return $this->LONGBREAKS;
        }

    public function GetStats ()
        {
            return $this->stats;
        }

    public function GetDirname ()
        {
            return $this->dirname;
        }

    public function GetFullpath ()
        {
            return $this->fullpath;
        }



}
