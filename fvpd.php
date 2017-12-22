<?php
require_once 'Defines.php';
require_once 'Algorithm.php';
require_once 'GAOptimizer.php';


ini_set('memory_limit', '2000M');


# Case 1: Get one feature vector
/*
$alg = new Algorithm;
$FVECTOR = $alg->GetFV("apezo1", \EP\SOURCES_PATH . DIRECTORY_SEPARATOR . "OR2016/Z1/Z1/aalihodzic2.c", "OR2016/Z1/Z1/main.c", mktime(59,59,23,11,03,2016));
print_r($FVECTOR);
*/

# Case 2: Traverse a tree and calculate distances for one homework
/*
$alg = new Algorithm;

$FVECTORS = $alg->Traverse("OR2016/Z1/Z1", "main.c", mktime(59,59,23,11,03,2016));
$alg->Normalize($FVECTORS);

$FVW = $alg->GetW();

$DISTANCES = $alg->CalculateDistances($FVECTORS, $FVW);

$alg->NiceOutput($DISTANCES);
*/


# Case 3: GeneticAlgorithm training

function make_seed()
{
  list($usec, $sec) = explode(' ', microtime());
  return $sec + $usec * 1000000;
}
srand(make_seed());


$training_set = array(
    array("path" => "OR2016/Z1/Z1", "file" => "main.c", "deadline" => mktime(59,59,23,11,03,2016)),
    // array("path" => "OR2016/Z1/Z2", "file" => "main.c", "deadline" => mktime(59,59,23,11,03,2016)),
    array("path" => "OR2016/Z1/Z3", "file" => "main.c", "deadline" => mktime(59,59,23,11,03,2016)),
    array("path" => "OR2016/Z1/Z4", "file" => "main.c", "deadline" => mktime(59,59,23,11,03,2016)),
    array("path" => "OR2016/Z2/Z1", "file" => "main.c", "deadline" => mktime(59,59,23,11,17,2016)),
    array("path" => "OR2016/Z2/Z2", "file" => "main.c", "deadline" => mktime(59,59,23,11,17,2016)),
    array("path" => "OR2016/Z2/Z3", "file" => "main.c", "deadline" => mktime(59,59,23,11,17,2016)),
    array("path" => "OR2016/Z2/Z4", "file" => "main.c", "deadline" => mktime(59,59,23,11,17,2016))
);

print "Initialize\n";
$alg = new Algorithm;
$gao = new GAOptimizer($alg, $training_set, "ground-truth.txt");
#$fitness = $gao->Fitness( $alg->GetW(), true );
#print "Fitness: $fitness\n";
$gao->GAInit();
$gao->PrintPopulation();

for ($i=1; $i<1000; $i++) {
	print "\n\nGeneration $i:\n";
	$gao->Generation(true);
	$gao->PrintPopulation();
}


?>
