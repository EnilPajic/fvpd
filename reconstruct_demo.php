<?php

require_once 'Reconstruct.php';

//$username = "acomor1";
//$filename = "OR/T7/Z2/main.c";

//$username = "jc15167";
//$filename = "OR/Z3/Z2/main.c";

//$username = "esacic1";
//$filename = "OR/T8/Z2/main.c";

$username = "lrizvanovi1";
$filename = "OR/T8/Z3/main.c";

$r = new Reconstruct($username, true);
$stats = $r->ReadStats();
$sleep_sec = 0.2;

if (!array_key_exists($filename, $stats)) {
	print "ERROR: '$filename' not found in stats for '$username'\n";
	return 1;
}

end($stats[$filename]['events']);
$end = key($stats[$filename]['events']);
for ($i=1; $i<=$end; $i++) {
	if (!array_key_exists($i-1, $stats[$filename]['events'])) continue;
	if (!$r->TryReconstruct($filename, $filename, "+$i")) {
		print "Došlo je do greške u koraku $i\n";
		break;
	}
	system('clear');
	if (array_key_exists($i-1, $stats[$filename]['events']) && array_key_exists('time', $stats[$filename]['events'][$i-1]))
		$time = $stats[$filename]['events'][$i-1]['time'];
	print "*** (v$i) " . date("d.m.Y H:i:s\n", $time);
	print join("", $r->GetFile());
	$end = $r->GetTotalEvents();
	usleep(1000000*$sleep_sec);
}

print "\n\nSUMMARY OF CODE REPLACE EVENTS:\n";
list ($events, $matches) = $r->GetCodeReplaceEvents();
$printed = false;
foreach($events as $id => $code) {
	$printed = true;
	print "- Event $id (" . date("d.m.Y H:i:s", $stats[$filename]['events'][$id]['time']) . ") ";
	if (array_key_exists($id, $matches)) print " - matches ".$matches[$id];
	print "\n";
}
if (!$printed) print "-- No events detected\n";
print "\n";

printf

?>
