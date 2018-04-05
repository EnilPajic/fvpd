<?php


class GAOptimizer
{
    private $algorithm = null;
    private $training_set = null;
    private $ground_truth = [];
    private $feature_vectors = [];
    private $population = [];
    private $fitness = [];
    
    public function __construct ($algorithm, $training_set, $ground_truth_filename)
        {
            $this->algorithm = $algorithm;
            $this->training_set = $training_set;
            
            if (file_exists("feature_vectors.txt")) 
                {
                    $fv = null;
                    eval(file_get_contents("feature_vectors.txt"));
                    if ($fv) $this->feature_vectors = $fv;
                }
                else $this->feature_vectors = [];
            
            foreach($training_set as $data)
                {
                    $this->ground_truth[$data['path']] = $this->ReadGroundTruth( $ground_truth_filename, $data['path'] );
                    if (!array_key_exists($data['path'], $this->feature_vectors))
                        {
                            $this->feature_vectors[$data['path']] = $algorithm->Traverse( $data['path'], $data['file'], $data['deadline'] );
                            $this->PrintFVs($data['path']);
                            $algorithm->Normalize( $this->feature_vectors[$data['path']] );
                            $this->PrintFVs($data['path']);
                            exit (0);
                            # Convert vector to values
                            foreach ( $this->feature_vectors[$data['path']] as &$fv) 
                                $fv = array_values($fv);
                        }
                }
            file_put_contents("feature_vectors.txt", "\$fv = " . var_export($this->feature_vectors, true) . ";");
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
            foreach($this->training_set as $data)
                {
                    $FlatDists = $this->algorithm->FlatDistances( $this->feature_vectors[$data['path']], $Weights, 5 );
                    $files = count($FlatDists);
                    
                    $GT = $this->ground_truth[$data['path']];
                    $GTmatches = $this->ground_truth_matches[$data['path']];
                    $found = [];
                    $founds = $i = 0;
                    foreach($GT as $file) $found[$file] = false;
                    
                    foreach ($FlatDists as $pair => $dist) {
                           list($left, $right) = explode(",", $pair);
                            if (in_array($left, $GT) && !$found[$left]) { $found[$left] = true; $founds++; }
                            if (in_array($right, $GT) && !$found[$right]) { $found[$right] = true; $founds++; }
                            $i++;
                            if ($i > count($GT)) break;
                    }
                    $fitness = $founds / count($GT);
                    if($this->output)  print $data['path']." Fitness $fitness\n";
                    
                    $total_fitness += $fitness;
                    $total_founds += $founds;
                    $total_gt += count($GT);
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
            $this->fitness = array_fill(0, 50, 0);
            if (file_exists("population.txt"))
                {
                    $pop = null;
                    eval(file_get_contents("population.txt"));
                    if ($pop) $this->population = $pop;
                    if (file_exists("fitness.txt"))
                        {
                            $fitness = null;
                            eval(file_get_contents("fitness.txt"));
                            if ($fitness) $this->fitness = $fitness;
                        }
                } else {
                    # Let first individual be algorithm default
                    $this->population[] = array_values( $this->algorithm->GetW() );
                    for ($i=1; $i < 6; $i++)
                        $this->population[] = $this->GetRandomIndividual();
                } 
            
            # If population is not full, create additional individuals as children of this individual
            $num_children = \EP\GA_POPULATION_SIZE - count($this->population);
            for ($i=0; $i < $num_children; $i++)
                {
                    //do {
                        $p1 = rand( 0, count($this->population)-1 );
                        $p2 = rand( 0, count($this->population)-1 );
                    //} while ($p1 == $p2 || array_key_exists("$p1,$p2", $children) || array_key_exists("$p2,$p1", $children));
                    $this->population[] = $this->Hibridize( $this->population[$p1], $this->population[$p2] );
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
            
            file_put_contents("population.txt", "\$pop = " . var_export($this->population, true) . ";");
            file_put_contents("fitness.txt", "\$fitness = " . var_export($this->fitness, true) . ";");
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
    
    private function PrintFVs($path)
        {
            print "                     ADD APAST ATEST    CF    CS   DEL MPAST   CHG NPAST   CPS  TSTS  TIME  SBRK  LBRK   NUM\n";
            foreach ($this->feature_vectors[$path] as $user => $fv)
                {
                    printf("[%-12s] => ", $user);
                    foreach ($fv as $feature)
                        if ($feature >= 10000)
                            printf("%5dk", $feature/1000);
                        else if ($feature == intval($feature) || $feature >= 1000)
                            printf("%6d", $feature);
                        else if ($feature >= 100)
                            printf("%6.1d", $feature);
                        else
                            printf("%6.2f", $feature);
                    print "\n";
                }
        }
}

?>
