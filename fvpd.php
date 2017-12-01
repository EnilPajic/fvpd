<?php
require_once 'Defines.php';
require_once 'Algorithm.php';
require_once 'GAOptimizer.php';


ini_set('memory_limit', '2000M');


# Simple use case

/*
$alg = new Algorithm;

$FVECTORS = $alg->Traverse("OR2016/Z1/Z1", "main.c");
$alg->Normalize($FVECTORS);

$FVW = $alg->GetW();

$DISTANCES = $alg->CalculateDistances($FVECTORS, $FVW);

$alg->NiceOutput($DISTANCES);
*/


# GeneticAlgorithm training

function make_seed()
{
  list($usec, $sec) = explode(' ', microtime());
  return $sec + $usec * 1000000;
}
srand(make_seed());


$training_set = array(
	"OR2016/Z1/Z1",  /* "OR2016/Z1/Z2", */ "OR2016/Z1/Z3", "OR2016/Z1/Z4",
	"OR2016/Z2/Z1", "OR2016/Z2/Z2", "OR2016/Z2/Z3", "OR2016/Z2/Z4"
);

print "Initialize\n";
$alg = new Algorithm;
$gao = new GAOptimizer($alg, $training_set, "ground-truth.txt", "main.c");
#$fitness = $gao->Fitness( $alg->GetW(), true );
#print "Fitness: $fitness\n";
$gao->GAInit();
$gao->PrintPopulation();

for ($i=1; $i<1000; $i++) {
	print "\n\nGeneration $i:\n";
	$gao->Generation(true);
	$gao->PrintPopulation();
}


// -0.77,0.58,0.86,0.63,0.68,0.79,0.63,0.88,0.71,0.63,0.92,0.75,1.74,0.71,0.88,0.80,0.76,0.93,0.77,1.10,0.93,0.78,0.75,0.83,0.64,0.55,0.71,0.75,0.58,0.45,0.86,0.65,0.65,0.82,0.71,0.78,0.93,0.83,0.51,0.81,0.53,0.64,0.68,0.77,

?>
