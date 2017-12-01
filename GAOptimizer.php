<?php


class GAOptimizer
{
    private $algorithm = null;
    private $training_set = null;
    private $ground_truth = [];
    private $feature_vectors = [];
    private $population = [];
    private $fitness = [];
    
    public function __construct ($algorithm, $training_set, $ground_truth_filename, $homework_filename)
        {
            $this->algorithm = $algorithm;
            $this->training_set = $training_set;
            
            foreach($training_set as $curr_homework)
                {
                    $this->ground_truth[$curr_homework] = $this->ReadGroundTruth($ground_truth_filename, $curr_homework);
                    $this->feature_vectors[$curr_homework] = $algorithm->Traverse($curr_homework, $homework_filename);
                    $algorithm->Normalize( $this->feature_vectors[$curr_homework] );
                    # Convert vector to values
                    foreach ( $this->feature_vectors[$curr_homework] as &$fv) 
                        $fv = array_values($fv);
                }
        }
    
    public function ReadGroundTruth($filepath, $curr_homework)
        {
            # Since GT lists all similar homeworks we will simply flatten the list of plagiators
            $is_hw = false;
            $GT = array();
            foreach(file($filepath) as $line) 
                {
                    $line = trim($line);
                    if (strlen($line) < 3) continue;
                    if (substr($line, 0, 2) == "- ") 
                        {
                            $path = substr($line, 2);
                            $is_hw = ($path == $curr_homework);
                            continue;
                        }
            
                    if ($is_hw) 
                        foreach(explode(",", $line) as $file) 
                            $GT[] = $file;
                }
            return $GT;
        }
   
    # Evaluate given set of weights and find optimal threshold
    private function EvaluateOne($DISTANCES, $GT, &$BestThreshold, &$BestFP, &$BestFN)
        {
            # Flatten and sort distances vector
            $FVKEYS = array_keys($DISTANCES);
            $FlatDists = array();
            foreach ($DISTANCES as $k => $v)
                foreach ($v as $kk => $vv)
                    $FlatDists[$k . "," . $kk] = $vv;
            asort($FlatDists);
        
            # Start from the top
            $BestThreshold = 0;
            $BestFP = 0;
            $FP = [];
            $BestFN = $FN = count($GT);
            $step = 0.01;
            $eps = 0.00001;
            $found = [];
            foreach($GT as $file) $found[$file] = false;
        
            for ($i=0; $i<1; $i+=$step)
                {
                    foreach($FlatDists as $pair => $dist)
                        {
                            list($left, $right) = explode(",", $pair);
                            if (($dist < $i || abs($dist-$i) < $eps) && $dist > $i-$step)
                                {
                                    if (in_array($left, $GT) && !$found[$left]) { $found[$left] = true; $FN--; }
                                    if (in_array($right, $GT) && !$found[$right]) { $found[$right] = true; $FN--; }
                                    if (!in_array($left, $GT) && !in_array($left, $FP))
                                        $FP[] = $left;
                                    if (!in_array($right, $GT) && !in_array($right, $FP))
                                        $FP[] = $right;
                                }
                            else if ($dist > $i-$step)
                                break;
                        }
                    //print "Threshold $i FP ".count($FP)." FN $FN\n";
                    $quality = $FN + count($FP);
                    $best = $BestFP + $BestFN;
                    if ($quality < $best || $i == 0)
                        {
                             $BestFP = count($FP);
                             $BestFN = $FN;
                             $BestThreshold = $i;
                        }
                }
        }

    public function Fitness($Weights, $output = false)
        {
            $total_fitness = 0;
            #return rand() / getrandmax();
            foreach($this->training_set as $hw)
                {
                    $Distances = $this->algorithm->CalculateDistances( $this->feature_vectors[$hw], $Weights );
                    
                    $threshold = $FP = $FN = 0;
                    $this->EvaluateOne( $Distances, $this->ground_truth[$hw], $threshold, $FP, $FN);
                    $fitness = 1 - ($FP + $FN) / count( $this->ground_truth[$hw] );
                    if ($output) print "$hw Best threshold $threshold (FP $FP, FN $FN) Fitness $fitness\n";
                    $total_fitness += $fitness;
                }
            return $total_fitness / count($this->training_set);
        }
    
    # Initialize GeneticAlgorithm
    private function RescaleIndividual(&$individual)
        {
            $scale_coef = 13 / array_sum($individual);
            foreach($individual as &$gene) $gene *= $scale_coef;
        }
    private function GetRandomIndividual()
        {
            $individual = [];
            for ($i=0; $i < \EP\GENOME_SIZE; $i++)
                $individual[] = (rand() / getrandmax()) * 2 - 1;
            //$this->RescaleIndividual($individual);
            return $individual;
        }
    public function GAInit()
        {
            # Let first individual be algorithm default
           /* $this->population[] = array_values( $this->algorithm->GetW() );
            $this->fitness[] = 0.13183899013641;
            # Last pass optimal
            $this->population[] = array ( -0.77,0.58,0.86,0.63,0.68,0.79,0.63,0.88,0.71,0.63,0.92,0.75, 1.74, 0.71, 0.88,0.80,0.76, 0.93,0.77,1.10,0.93,0.78,0.75,
            0.83,0.64,0.55,0.71,0.75,0.58,0.45,0.86,0.65,0.65,0.82,0.71,0.78,0.93,0.83,0.51,0.81,0.53,0.64,0.68,0.77 );
            $this->fitness[] = 0.14797835031975;
            # Zeros on high variance coeff's
            $this->population[] = array ( -0.77,0.58,0.86, 0, 0.68,0.79,0.63, 0, 0.71,0.63,0.92, 0, 1.74, 0.71, 0.88,0.80,0.76, 0.93,0.77,1.10,0.93,0.78,0.75,
            0.83,0.64,0.55,0.71,0.75,0.58,0.45,0.86,0.65,0.65,0.82,0.71,0.78, 0, 0.83,0.51,0.81,0.53,0.64,0.68,0.77 );
            $this->fitness[] = 0.15656024461593;
            # Misterious individual 22
            $this->population[] = array (-0.37,0.65,1.05,0.10,0.48,0.49,0.61,0.42,0.20,0.91,0.37,0.66,0.47,0.17,0.41,0.81,0.55,0.76,0.65,0.50,0.40,0.25,0.08,0.00,0.53, 0.07,0.02,0.25, 0.01,0.63,0.23,0.64,0.37,0.51,0.42,0.85,1.00,0.45,0.14,0.01,1.01,0.44,1.02,0.48 );
            $this->fitness[] = 0.13989914564675;*/
            
            $this->population[] = array ( -0.19, -0.03, 0.19, -0.60, 2.36,0.00,0.20,-0.33,0.36,2.06,-0.10,-0.20,2.04,0.10,0.38,5.90,5.88,0.56,0.57,2.90,0.00,1.76,-0.03,0.20,3.46,0.20,0.20,0.10,2.72,2.20,6.68,0.45,1.00,6.76,0.40,8.00,0.10,1.13,-0.72,6.08,0.45,0.43,0.00,-0.85);
            $this->population[] = array ( -0.19, -0.03, -0.01, -0.50, 1.28,0.05,0.10,0.30,0.18,2.06,-0.10,-0.20,1.94,0.00,0.38,5.80,5.98,0.46,0.57,2.90,-0.10,1.76,0.38,0.20,3.46,0.30,0.00,0.00,2.72,0.00,6.68,0.45,0.90,6.76,0.40,8.00,0.10,1.23,-1.02,6.08,0.55,0.22,0.04,-0.85);
$this->population[] = array ( -0.29, 0.02, 0.24, -0.20, 0.00,0.74,0.10,0.00,0.45,1.13,-0.40,-0.10,1.94,0.10,0.38,6.10,5.98,-0.02,0.52,3.30,0.00,1.96,0.77,0.10,1.58,0.40,0.05,0.00,2.52,1.10,6.78,0.45,1.00,13.72,0.40,8.00,0.05,1.13,-0.82,1.52,-0.05,0.43,0.00,0.00);
$this->population[] = array ( -0.05, -0.03, 0.19, -0.60, 1.28,1.48,0.20,-0.23,0.26,2.06,-0.10,-0.10,1.94,0.10,0.38,5.80,5.88,0.56,1.14,3.10,0.00,2.06,0.07,0.10,3.36,0.60,0.10,0.10,2.72,0.00,6.68,0.35,1.00,6.56,0.40,8.10,0.00,1.13,-0.62,12.56,-0.02,0.43,-0.02,1.54);
$this->population[] = array ( -0.15, 0.07, 0.29, 0.10, 2.26,-0.66,0.10,-0.12,0.35,0.93,-0.10,0.00,1.94,0.10,0.38,6.00,5.98,0.86,0.42,3.30,0.00,1.86,1.45,0.10,3.36,0.30,0.00,0.00,2.52,1.20,6.68,0.45,0.90,6.96,-0.05,8.10,0.05,1.13,-0.82,3.14,-0.05,0.33,0.00,1.54);
$this->population[] = array ( -0.19, -0.03, 0.09, -0.60, 1.28,0.05,0.20,0.20,0.36,2.06,0.00,-0.10,1.84,0.24,0.38,5.90,5.98,0.56,0.00,2.80,0.00,1.86,0.38,0.20,6.72,0.30,0.00,0.00,2.72,0.00,6.58,0.63,-0.06,6.76,0.80,8.10,0.10,1.23,-0.92,12.46,0.45,0.23,0.08,-0.75);
$this->population[] = array ( -0.15, 0.02, 0.19, -0.30, 4.42,1.58,0.10,-0.12,0.45,1.03,-0.20,0.00,1.94,0.10,0.38,5.70,5.98,0.76,0.52,3.30,0.00,1.96,0.67,0.10,3.36,0.50,0.00,0.10,2.62,2.40,6.78,0.45,0.80,3.43,0.40,8.10,0.10,1.03,-0.82,6.28,-0.05,0.43,0.00,1.54);
$this->population[] = array ( -0.25, 0.17, 0.29, 0.00, -0.59,-0.66,-0.69,-0.10,0.45,2.06,0.10,0.20,1.94,0.10,0.38,5.90,5.98,0.56,1.03,3.20,0.00,1.96,1.45,0.10,3.36,0.40,0.00,0.10,5.04,1.20,6.68,0.44,0.45,7.06,-0.03,8.10,0.15,1.13,-0.62,3.24,-0.07,0.23,-0.10,1.64);
$this->population[] = array ( -0.10, -0.13, -0.01, 0.00, 2.36,0.74,0.98,0.10,0.26,1.13,-0.10,-0.10,1.84,0.14,0.38,5.90,5.68,0.56,0.42,2.80,-0.10,1.86,0.38,0.00,1.48,0.10,0.00,0.00,2.62,1.20,6.88,0.36,1.00,6.86,0.40,8.00,0.02,1.13,-0.62,6.08,0.10,0.33,0.00,-0.42);
$this->population[] = array ( -0.15, 0.07, -0.36, -0.10, 2.06,0.84,0.10,-0.30,0.45,0.93,-0.05,0.00,1.84,0.00,0.38,6.10,5.98,0.18,0.32,3.30,0.00,0.93,1.45,0.20,3.36,0.40,0.10,0.00,2.52,1.30,6.68,0.23,1.00,6.96,0.05,8.20,0.15,1.13,-0.72,3.24,-0.15,0.33,0.00,1.54);
$this->fitness[] =  0.27389313506003;
$this->fitness[] =  0.27381374792868;
$this->fitness[] =  0.27323247509225;
$this->fitness[] =  0.272682481307;
$this->fitness[] =  0.27260309417565;
$this->fitness[] =  0.27254026176749;
$this->fitness[] = 0.27215342278167;
$this->fitness[] =  0.27188468425628;
$this->fitness[] = 0.27187193087456;
$this->fitness[] = 0.27105319856392;

            
            /*for ($i=3; $i < 6; $i++)
                {
                    $this->population[] = $this->GetRandomIndividual();
                    $this->fitness[] = 0;
                }*/
            
            $num_children = \EP\GA_POPULATION_SIZE - count($this->population);
            for ($i=0; $i < $num_children; $i++)
                {
                    //do {
                        $p1 = rand( 0, count($this->population)-1 );
                        $p2 = rand( 0, count($this->population)-1 );
                    //} while ($p1 == $p2 || array_key_exists("$p1,$p2", $children) || array_key_exists("$p2,$p1", $children));
                    $this->population[] = $this->Hibridize( $this->population[$p1], $this->population[$p2] );
                     $this->fitness[] = 0;
                }
            
        }

    # Execute one generation
    public function Generation($output = false)
        {
            $i = 0;
            $avg = 0;
            $fitness = [];
            foreach($this->population as $individual)
                {
                    if ($this->fitness[$i] == 0) 
                        $fit = $this->Fitness($individual, $output);
                    else 
                        $fit = $this->fitness[$i];
                    if ($output) print "Individual $i fitness $fit\n";
                    $fitness[$i++] = $fit;
                    $avg += $fit;
                }
            $this->fitness = $fitness;
            
            $avg /= count($this->population);
            if ($output) print "Average generation fitness: $avg\n";
            arsort($this->fitness);
            
            $newpop = [];
            $newfitness = [];
            $i = 0;
            foreach($this->fitness as $k => $v)
                {
                    $newpop[] = $this->population[$k];
                    $newfitness[] = $v;
                    if ($output) print "Individual $k becomes ".($i++)." (fitness $v)\n";
                    if (count($newpop) == \EP\GA_PROGENITORS_SIZE) break;
                }
            
            $children = [];
            $num_children = \EP\GA_POPULATION_SIZE - \EP\GA_PROGENITORS_SIZE;
            for ($i=0; $i < $num_children; $i++)
                {
                    do {
                        $p1 = rand( 0, count($newpop)-1 );
                        $p2 = rand( 0, count($newpop)-1 );
                    } while ($p1 == $p2 || array_key_exists("$p1,$p2", $children) || array_key_exists("$p2,$p1", $children));
                    $children["$p1,$p2"] = $this->Hibridize( $newpop[$p1], $newpop[$p2] );
                    $newfitness[] = 0;
                }
            
            $this->population = array_merge( $newpop, array_values($children) );
            $this->fitness = $newfitness;
        }
        
    private function Hibridize($parent1, $parent2)
        {
            $child = [];
            $mutation_gene_probability = \EP\GA_MUTATION_PROBABILITY;
            for ($i = 0; $i < count($parent1); $i++) 
                {
                    if (rand() / getrandmax() > 0.5) $f = $parent1[$i]; else $f = $parent2[$i];
                    //$f = ($parent1[$i] + $parent2[$i]) / 2;
                    
                    if (rand() / getrandmax() < $mutation_gene_probability*2) $f += 0.1;
                    else if (rand() / getrandmax() < $mutation_gene_probability*2) $f -= 0.1;
                    else if (rand() / getrandmax() < $mutation_gene_probability) $f *= 2;
                    else if (rand() / getrandmax() < $mutation_gene_probability) $f /= 2;
                    else if (rand() / getrandmax() < $mutation_gene_probability / 2) $f = (rand() / getrandmax()) * 2 - 1;
                    else if (rand() / getrandmax() < $mutation_gene_probability / 2) $f = 0;
                    
                    /* 
                     $test = rand() / getrandmax();
                    if ($test < $mutation_gene_probability*2) $f += 0.1;
                    else if ($test < $mutation_gene_probability*4) $f -= 0.1;
                    else if ($test < $mutation_gene_probability*5) $f *= 2;
                    else if ($test < $mutation_gene_probability*6) $f /= 2;
                    else if ($test < $mutation_gene_probability*6.5) $f = (rand() / getrandmax()) * 2 - 1;
                    else if ($test < $mutation_gene_probability*7) $f = 0;
                    */
                    $child[] = $f;
                }
            //$this->RescaleIndividual($child);
            return $child;
        }

    # Execute one generation
    public function PrintPopulation()
        {
            for ($i = 0; $i < count($this->population); $i++)
                {
                    print "Individual $i: ";
                    foreach($this->population[$i] as $w)
                        printf("%.2f,", $w);
                    print "\n";
                }
        }
}

?>
