<?php

$ext = ".c";

foreach(file("ground-truth.txt") as $line) {
	$line = trim($line);
	if (strlen($line) < 3) continue;
	if (substr($line, 0, 2) == "- ") {
		$path = substr($line, 2);
		$seen = array();
		continue;
	}
	
	foreach(explode(",", $line) as $file) {
		if (in_array($file, $seen))
			print "Seen $path/$file\n";
		else
			$seen[] = $file;
		$fullpath = "src/$path/$file$ext";
		if (!file_exists($fullpath))
			print "Unknown file $fullpath\n";
	}
}

print "Done.\n";

?>
