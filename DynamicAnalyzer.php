<?php

class DynamicAnalyzer
{
    private $COMPILED = 0, $TESTED = 0, $COMPILEDSUCC = 0, $AVGTEST = 0.0;
    private $MODIFIED = 0, $ADDED = 0, $DELETED = 0, $TIME = 0;
    private $PASTE = 0, $MAXPASTE = 0, $AVGPASTE = 0, $SPEED = 0;

    private $stats = null;
    private $dirname = null;
    private $fullpath = null;
    public function __construct ($stats, $path)
        {
            $this->stats = $stats;
            $this->dirname = pathinfo($path, PATHINFO_DIRNAME);
            $this->fullpath = $path;
            if ($stats == null)
                throw new Exception("Stats file je null!", 5);
            if (!array_key_exists($path, $stats))
                return; #throw new Exception("Path ($path) ne postoji u stats fajlu!", 6);
            if (!array_key_exists($this->dirname, $stats))
                return; #throw new Exception("Path ($this->dirname) ne postoji u stats fajlu!", 6);
            if (count ($stats[$path]) == 0)
                return; #throw new Exception("Path ($path) je prazan!?", 7);

            $this->COMPILED = intval($stats[$this->dirname]['builds']);
            $this->COMPILEDSUCC = intval($stats[$this->dirname]['builds_succeeded']);
            $this->TESTED = intval($stats[$this->dirname]['testings']);
            $this->TIME = intval($stats[$path]['total_time']);
            $events = $stats[$this->dirname]['events']; #na nivou foldera/zadatka
            $testcnt = 0;
            foreach ($events as $evt)
                if (trim($evt['text']) === 'ran tests')
                    {
                        $txt = trim($evt['test_results']);
                        $txt = explode("/", $txt);
                        $proslo = doubleval ($txt[0]);
                        $ukupno = doubleval ($txt[1]);

                        $this->AVGTEST = ($this->AVGTEST * $testcnt + $proslo / $ukupno) / doubleval($testcnt + 1);
                        $testcnt++;
                    }
            $events = $stats[$path]['events'];
            $first = true; $time = null;
            $diff = 0; $cnt = 0;
            foreach ($events as $evt)
                {
                    if ($evt['text'] === 'modified' && array_key_exists('diff', $evt) && count($evt['diff']) > 0)
                        {
                            $arr = $evt['diff'];
                            $changed = 0; $chlen = 0;
                            foreach ($arr as $k => $val)
                                {
                                    foreach ($val as $v)
                                            if (trim($v) !== "")
                                                {
                                                    $changed++; $chlen += strlen($v);
                                                    if ($k === 'remove_lines')
                                                        $this->DELETED++;
                                                    else if ($k === 'add_lines')
                                                        $this->ADDED++;
                                                    else if ($k === 'change')
                                                        $this->MODIFIED++;
                                                    if ($k === 'add_lines' || $k === 'change')
                                                        {
                                                            if ($first) {$first = false; $time = $evt['time'];}
                                                            else
                                                                {
                                                                    $diff += doubleval(abs ($evt['time'] - $time));
                                                                    $cnt++;
                                                                }
                                                        }
                                                }
                                }
                            if ($changed > 4)
                                {
                                    $this->PASTE++;
                                    $this->AVGPASTE += $chlen;
                                    if ($chlen > $this->MAXPASTE)
                                        $this->MAXPASTE = $chlen;
                                }
                        }
                }
            if ($cnt > 0) $this->SPEED = doubleval($diff) / doubleval($cnt);
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