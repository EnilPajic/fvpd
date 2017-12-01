<?php

$allstudents = json_decode(file_get_contents("allstudents.json"), true);
$filenames = array();
foreach ($allstudents['members'] as $member) {
	$student = $member['student'];
	$fn = $student['surname'] . "_" . $student['name'] . "_" . $student['studentIdNr'];
	$slova = array("č", "ć", "š", "đ", "ž", "Č", "Ć", "Š", "Đ", "Ž", " ", "-");
	$zamjene = array("c", "c", "s", "d", "z", "C", "C", "S", "D", "Z", "_", "");
	$fn = str_replace($slova, $zamjene, $fn);
	$filenames[$fn] = $student['login'];
}



files_rename("src");

function files_rename($path) {
	global $filenames;
	
	foreach(scandir($path) as $file) {
		if ($file == "." || $file == "..") continue;
		$fpath = $path . "/" . $file;
		if (is_dir($fpath)) { files_rename($fpath); continue; }
		
		$pos = strrpos($file, ".");
		$name = substr($file, 0, $pos);
		$ext = substr($file, $pos);
		if (array_key_exists($name, $filenames)) {
			$newname = $path . "/" . $filenames[$name] . $ext;
			rename($fpath, $newname);
		} else {
			print "Not renamed $file\n";
		}
	}
}

?>
