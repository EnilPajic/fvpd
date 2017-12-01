<?php

#prilagodjena verzija ovog fajla: https://github.com/vljubovic/c9etf/blob/master/lib/reconstruct.php
#by Vedran Ljubovic

require_once 'Defines.php';

class Reconstruct
{
    private $incgoto = false;
    private $stats = null;
    private $file = null;
    private $username, $filename, $timestamp;
    public function __construct ($username, $include_goto = false)
        {
            $this->incgoto = $include_goto;
            $this->username = $username;
        }
    public function GetFile () {return $this->file;}
    public function GetStats () {return $this->stats;}
    public function ReadStats() {self::read_stats($this->username); return $this->stats;}

    public function TryReconstruct($realpath, $c9path, $timestamp)
        {
            if (intval($timestamp) < 100) $timestamp = strtotime($timestamp);
            list($s, $this->file) = self::reconstruct_file($this->username, $realpath, $c9path, $timestamp);
            return $s;
        }
    private function read_stats($username) {

        $username_efn = self::escape_filename($username);
        $stat_file = \EP\STATS_PATH . DIRECTORY_SEPARATOR . "$username_efn.stats";
        $this->stats = NULL;
        $stats = null;
        if (file_exists($stat_file))
            {eval(file_get_contents($stat_file));}
        $this->stats = $stats;
        if ($this->stats == NULL) {
            $this->stats = array(
                "global_events" => array(),
                "last_update_rev" => 0
            );
        }
        // Stats file can reference other files to be included
        if(!$this->incgoto) return;
        foreach ($this->stats as $key => $value) {
            if (is_array($value) && array_key_exists("goto", $value)) {
                $goto_path = $stat_file = \EP\STATS_PATH . DIRECTORY_SEPARATOR . "$username_efn" . DIRECTORY_SEPARATOR . $value['goto'];
                $stats_goto = null;
                eval(file_get_contents($goto_path));
                if ($stats_goto == null) continue;
                foreach($stats_goto as $ks => $vs)
                    $this->stats[$ks] = $vs;
                $stats_goto = null;
            }
        }
    }

    private static function  escape_filename($raw) {
	    return preg_replace('/[^A-Za-z0-9_\-]/', '_', $raw);
    }
    private function reconstruct_file($username, $realpath, $c9path, $timestamp) {

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

        // We reconstruct the file backwards from its current state
        for ($i=$evtcount-1; $i>=0; $i--) {
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

}
