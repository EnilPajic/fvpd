<?php


// Genetic algorithm v6.3

class GAOptimizer
{
    private $algorithm = null;
    private $training_set = null;
    private $ground_truth = [], $ground_truth_matches = [];
    private $feature_vectors = [];
    private $population = [];
    private $names = [];
    private $fitness = [];
    private $memory = [];
    private $strains = [];
    private $generation = 0, $lastname = 0, $output = false;
    private $timer = null;
    
    public function __construct ($algorithm, $training_set, $ground_truth_filename, $output = false)
        {
            $this->algorithm = $algorithm;
            $this->training_set = $training_set;
            $this->output = $output;
            $this->timer = microtime(true);
            
            // Rebuild feature vectors
            if (file_exists("feature_vectors.txt")) 
                {
                    $fv = null;
                    eval(file_get_contents("feature_vectors.txt"));
                    if ($fv) $this->feature_vectors = $fv;
                }
                else $this->feature_vectors = [];
            
            foreach($training_set as $data)
                {
                    $this->ReadGroundTruth( $ground_truth_filename, $data['path'] );
                    if (!array_key_exists($data['path'], $this->feature_vectors))// || $data['path'] == "OR/Z3/Z4")
                        {
                            print "Rebuilding feature vectors for path ".$data['path']."...\n";
                            $this->feature_vectors[$data['path']] = $algorithm->Traverse( $data['path'], $data['file'], $data['deadline'] );
                            $algorithm->Normalize( $this->feature_vectors[$data['path']] );
                            # Convert vector to values
                            foreach ( $this->feature_vectors[$data['path']] as &$fv) 
                                $fv = array_values($fv);
                        }
                }
            file_put_contents("feature_vectors.txt", "\$fv = " . var_export($this->feature_vectors, true) . ";");
        }

    // Build initial population
    public function GAInit()
        {
            $this->fitness = array_fill(0, 50, 0);
            if (file_exists("population.txt")) $this->ReadFiles();

            // Rest of population are going to be random individuals
            for ($i = count($this->population); $i < \EP\GA_POPULATION_SIZE; $i++)
                {
                    $this->population[] = $this->GetRandomIndividual();
                    $this->names[] = $this->GetName();
                    $this->fitness[] = 0; // Recalculate fitness
                }
        }
    
    // Read GA state from files
    public function ReadFiles()
        {
            if (!file_exists("population.txt")) return; // Nothing to read
            
            if($this->output) print "Reading files...\n";
            
            $pop = $names = null;
            eval(file_get_contents("population.txt"));
            if ($pop) $this->population = $pop;
            if ($names) $this->names = $names;
            else
                for ($i=0; $i<count($this->population); $i++)
                    if ($i == count($this->names))
                        $this->names[] = $this->GetName();
            
            // Non essential data
            if (file_exists("ga_state.txt"))
                {
                    $generation = $memory = $strains = $lastname = null;
                    eval(file_get_contents("ga_state.txt"));
                    if ($generation) $this->generation = $generation;
                    if ($memory) $this->memory = $memory;
                    if ($strains) $this->strains = $strains;
                    if ($lastname) $this->lastname = $lastname;
                }

            
            if (file_exists("fitness.txt"))
                {
                    $fitness = null;
                    eval(file_get_contents("fitness.txt"));
                    if ($fitness) $this->fitness = $fitness;
                    for ($i=0; $i<count($this->population); $i++)
                        if ($i >= count($this->fitness))
                            $this->fitness[] = 0;
                }
            if($this->output) print "Continuing with strain ".(count($this->strains)+1).", generation ".($this->generation+1).", memory ".count($this->memory).".\n\n";
        }
    
    // Write current GA state to files
    public function WriteFiles()
        {
            $popdata = "\$pop = " . var_export($this->population, true) . ";\n";
            $popdata .= "\$names = " . var_export($this->names, true) . ";\n";
            file_put_contents("population.txt", $popdata);
            
            file_put_contents("fitness.txt", "\$fitness = " . var_export($this->fitness, true) . ";");
            
            $state_data = "\$generation = " . var_export($this->generation, true) . ";\n";
            $state_data .= "\$memory = " . var_export($this->memory, true) . ";\n";
            $state_data .= "\$strains = " . var_export($this->strains, true) . ";\n";
            $state_data .= "\$lastname = " . var_export($this->lastname, true) . ";\n";
            file_put_contents("ga_state.txt", $state_data);
        }
    
    // Get name for next randomly generated individual
    public function GetName()
        {
            static $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ01234567890abcdefghijklmnopqrstuvwxyz";
            $i = $this->lastname++;
            $name = ""; $len = strlen($chars);
            do
                {
                    $name .= $chars[$i%$len];
                    $i = intval($i/$len);
                }
            while ($i > 0);
            return $name;
        }

    public function ReadGroundTruth($filepath, $curr_homework)
        {
            # Since GT lists all similar homeworks we will simply flatten the list of plagiators
            $is_hw = false;
            $GT = $GTmatches = array();
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
            
                    if ($is_hw) {
                        $matches = explode(",", $line);
                        foreach($matches as $file) { 
                            $GT[] = $file;
                            $tmp = $matches;
                            unset($tmp[array_search($file,$tmp)]);
                            $GTmatches[$file] = $tmp;
                        }
                    }
                }
            $this->ground_truth[$curr_homework] = $GT;
            $this->ground_truth_matches[$curr_homework] = $GTmatches;
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

    public function Fitness($Weights)
        {
            $total_fitness = 0;
            $total_founds = $total_gt = 0;
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
            for ($i=0; $i < \EP\GENOME_SIZE; $i++) {
                if (\EP\SKIP_DYNAMIC && $i < \EP\NR_DYNAMIC_FEATURES) $individual[] = 0; else 
                $individual[] = (rand() / getrandmax());
            }
            //$this->RescaleIndividual($individual);
            return $individual;
        }

    # Execute one generation
    public function Generation()
        {
            $this->generation++;
            print "\n\nGeneration " . $this->generation . ":\n";
            
            // Calculate fitness for all members of population
            $i = 0;
            $avg = 0;
            $fitness = [];
            foreach($this->population as $individual)
                {
                    if ($this->fitness[$i] == 0) 
                        $fit = $this->Fitness($individual);
                    else 
                        $fit = $this->fitness[$i];
                    if($this->output) print "Individual $i (". $this->names[$i].") fitness $fit\n";
                    $fitness[$i++] = $fit;
                    $avg += $fit;
                    file_put_contents("fitness.txt", "\$fitness = " . var_export($fitness, true) . ";");
                }
            $this->fitness = $fitness;
            
            $avg /= count($this->population);
            if($this->output) print "\nAverage generation fitness: $avg\n";
            arsort($this->fitness);
            
            // Commit current generation to memory
            $this->AddToMemory();
            
            // Select progenitors for next generation
            $newpop = $newfitness = $newnames = [];
            $i = 0;
            foreach($this->fitness as $k => $v)
                {
                    $newpop[] = $this->population[$k];
                    $newfitness[] = $v;
                    $newnames[] = $this->names[$k];
                    if($this->output) print "Individual $k (" . $this->names[$k] . ") becomes ".($i++)." (fitness $v)\n";
                    if (count($newpop) == \EP\GA_PROGENITORS_SIZE) break;
                }
            
            // Find maximum distance between all individuals in PROGENITORS
            $radius = $this->PopulationRadius($newpop);
            if($this->output) print "Generation radius is $radius\n\n";
            
            // If population is too concentrated, start a new strain
            if ($radius < \EP\GA_STRAIN_LIMIT)
                {
                    $this->NewStrain($newpop, $newnames, $newfitness);
                }
            
            // Create offspring for next generation
            $children = [];
            $num_children = \EP\GA_POPULATION_SIZE - count($newpop);
            for ($i=0; $i < $num_children; $i++)
                {
                    do {
                        $p1 = rand( 0, count($newpop)-1 );
                        $p2 = rand( 0, count($newpop)-1 );
                    } while ($p1 == $p2 || array_key_exists("$p1,$p2", $children) || array_key_exists("$p2,$p1", $children));
                    $children["$p1,$p2"] = $this->Hibridize( $newpop[$p1], $newpop[$p2] );
                    if ($this->IsInMemory($children["$p1,$p2"]))
                        {
                            if ($this->output) "Found duplicate in memory for $p1,$p2\n";
                            unset($children["$p1,$p2"]);
                            $i--;
                            continue;
                        } 
                    $newnames[] = GAOptimizer::getChildName($newnames[$p1], $newnames[$p2]);
                    $newfitness[] = 0;
                }
            
            $this->population = array_merge( $newpop, array_values($children) );
            $this->names = $newnames;
            $this->fitness = $newfitness;
            
            $this->WriteFiles();
        }
        
    private function Hibridize($parent1, $parent2, $mean = false)
        {
            $child = [];
            $mutation_gene_probability = \EP\GA_MUTATION_PROBABILITY;

            // Randomly cross genes from both parents
            for ($i = 0; $i < count($parent1); $i++) 
                {
                    if ($mean) $child[] = ($parent1[$i] + $parent2[$i]) / 2;
                    else if (rand() / getrandmax() > 0.5) $child[] = $parent1[$i]; else $child[] = $parent2[$i];
                }

            // Mutate a number of genes
            for ($i = 0; $i < \EP\GA_MUTATION_GENES; $i++) 
                {
                   if (\EP\SKIP_DYNAMIC)
                       $pos = rand(\EP\NR_DYNAMIC_FEATURES, count($child)-1);
                   else
                       $pos = rand(0, count($child)-1);
                   $f = $child[$pos];
                   $mutation_type = rand() / getrandmax();
                   
                   if ($mutation_type < 1*$mutation_gene_probability) $f += $f * (rand()/getrandmax());
                   else if ($mutation_type < 2*$mutation_gene_probability) $f -= $f * (rand()/getrandmax());
                   else if ($mutation_type < 3.5*$mutation_gene_probability) $f *= 2;
                   else if ($mutation_type < 4*$mutation_gene_probability) $f /= 2;
                   else if ($mutation_type < 4.5*$mutation_gene_probability) $f = (rand() / getrandmax()) * 2 - 1;
                   else if ($mutation_type < 5*$mutation_gene_probability) $f = 0;
                   
                   $child[$pos] = $f;
                }

            //$this->RescaleIndividual($child);
            return $child;
        }
        
    private function Mutate($fv)
        {
            $mutation_gene_probability = \EP\GA_MUTATION_PROBABILITY;
            for ($i = 0; $i < count($parent1); $i++) 
                {
                    if (rand() / getrandmax() < $mutation_gene_probability*2) $fv[$i] += 0.1;
                    else if (rand() / getrandmax() < $mutation_gene_probability*2) $fv[$i] -= 0.1;
                    else if (rand() / getrandmax() < $mutation_gene_probability) $fv[$i] *= 2;
                    else if (rand() / getrandmax() < $mutation_gene_probability) $fv[$i] /= 2;
                    else if (rand() / getrandmax() < $mutation_gene_probability / 2) $fv[$i] = (rand() / getrandmax()) * 2 - 1;
                    else if (rand() / getrandmax() < $mutation_gene_probability / 2) $fv[$i] = 0;
                }
            return $fv;
        }

    # Execute one generation
    public function PrintPopulation()
        {
            for ($i = 0; $i < count($this->population); $i++)
                {
                    print "Individual $i (" . $this->names[$i] . "): ";
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

    public function DumpFV($login, $path)
        {
            print "$path - $login - ";
            $i=0;
            foreach($this->feature_vectors[$path][$login] as $feat)
                printf("%d=%5.2f ", $i++, $feat); 
            print "\n";
        }

    public function DebugDistance($individual, $login1, $login2, $path)
        {
            $i = 0; $sum = 0;
                foreach ($this->feature_vectors[$path][$login1] as $k => $v)
                    {
                        if (\EP\SKIP_DYNAMIC && ++$i < \EP\NR_DYNAMIC_FEATURES) continue;
                        $wi = $this->population[$individual][$k];
                        $y = $this->feature_vectors[$path][$login2][$k];
                        //$sum += $this->Signum($wi) * $wi * $wi * (($v - $y) * ($v - $y));
                        if ($wi < 0)
                            $sum -= $wi * $wi * (($v - $y) * ($v - $y));
                        else if ($wi > 0)
                            $sum += $wi * $wi * (($v - $y) * ($v - $y));
                            print "i ".($i-1)." Sum = $sum\n";
                    }
                    print $this->algorithm->Signum($sum) * sqrt ( abs( $sum ) ) . "\n";
             printf("%5.2f", $this->algorithm->Distance($this->feature_vectors[$path][$login1], $this->feature_vectors[$path][$login2], $this->population[$individual])); 
            print "\n";
        }

    public function DebugFitness($individual, $path, $printDistances = false)
        {
            $FlatDists = $this->algorithm->FlatDistances( $this->feature_vectors[$path], $this->population[$individual], 5 );
            if ($printDistances) $this->algorithm->NiceOutputFlat($FlatDists);
            $files = count($FlatDists);
            
            $GT = $this->ground_truth[$path];
            $found = [];
            $founds = $i = 0;
            foreach($GT as $file) $found[$file] = false;
            
            print "Total files: $files\n";
            foreach ($FlatDists as $pair => $dist) 
                {
                    print "$pair\n";
                    list($left, $right) = explode(",", $pair);
                    if (in_array($left, $GT) && !$found[$left]) { $found[$left] = true; $founds++; print "Found $left dist $dist (right $right)\n"; }
                    if (in_array($right, $GT) && !$found[$right]) { $found[$right] = true; $founds++; print "Found $right dist $dist (left $left)\n"; }
                    $i++;
                    if ($i > count($GT)) break;
                }
            foreach($GT as $nf)
                {
                    if ($found[$nf]) continue;
                    print "NOT found $nf ";
                    $max = -1; $maxr = "";
                    foreach ($FlatDists as $pair => $dist) {
                        list($left, $right) = explode(",", $pair);
                        if ($left != $nf) continue;
                        if ($dist < $max || $max == -1) { $max = $dist; $maxr = $right; }
                    }
                    if ($max == -1) print " - no match\n";
                    else print " (dist $max right $maxr)\n";
                }
            print "Stopping after $i\n";
            $fitness = $founds / count($GT);
            print "Found $founds of ".count($GT)." (".round($fitness*100, 2)."%)\n";
            print $path." Fitness $fitness\n";

        }

    // Gives Euclidean distance between two individuals in genome
    public static function GenomeDistance($ind1, $ind2)
        {
            $dist = 0;
            for ($i=0; $i<count($ind1); $i++)
                $dist += ($ind1[$i] - $ind2[$i]) * ($ind1[$i] - $ind2[$i]);
            return sqrt($dist);
        }
    
    // Adds all items in current population to memory
    public function AddToMemory()
        {
            // Progenitors are already in memory from previous run(s)
            for ($i=\EP\GA_PROGENITORS_SIZE; $i<count($this->population); $i++)
                {
                    if ($this->IsInMemory($this->population[$i])) {
                        if($this->output) print "NOT adding to memory individual $i because it's already there.\n";
                        continue;
                    }
                    $item = array("name" => $this->names[$i], "genome" => $this->population[$i], "fitness" => $this->fitness[$i]);
                    $this->memory[] = $item;
                }
        }
    
    // Check if individual already exists in memory
    public function IsInMemory($ind)
        {
            foreach($this->memory as $item)
                if (GAOptimizer::GenomeDistance($ind, $item['genome']) < \EP\GA_MIN_INDIVIDUAL_DISTANCE) return true;
            return false;
        }
    
    // Start a new strain
    public function NewStrain(&$population, &$names, &$fitness)
        {
            print "\n\nEnd of strain ".(count($this->strains)+1)." at generation ".$this->generation."\n\n";
            $strain = array( "population" => $population, "names" => $names, "fitness" => $fitness, "generations" => $this->generation );
            $this->strains[] = $strain;
            $this->generation = 0;
            
            $nstrains = count($this->strains);
            if ($nstrains % 4 == 3) {
                $population = $names = $fitness = [];
                print "Starting meta-strain ".(count($this->strains)+1).", generation 1\n\n";
                $takeFromEach = 5;
                
                // Add the meta-meta strains
                if ($nstrains % 16 == 15) {
                    for ($i=3; $i<$nstrains; $i+=4) {
                        for ($j=0; $j<5; $j++) {
                            $population[] = $this->strains[$i]['population'][$j];
                            $names[] = $this->strains[$i]['names'][$j];
                            $fitness[] = $this->strains[$i]['fitness'][$j];
                        }
                    }
                    $takeFromEach = 2;
                }
                
                for ($i=$nstrains-3; $i<$nstrains; $i++) {
                    for ($j=0; $j<$takeFromEach; $j++) {
                        $population[] = $this->strains[$i]['population'][$j];
                        $names[] = $this->strains[$i]['names'][$j];
                        $fitness[] = $this->strains[$i]['fitness'][$j];
                    }
                }
                
                // Use mean hibridization for some children
                $children = [];
                $num_children = 10;
                for ($i=0; $i < $num_children; $i++)
                {
                    do {
                        $p1 = rand( 0, count($population)-1 );
                        $p2 = rand( 0, count($population)-1 );
                    } while ($p1 == $p2 || array_key_exists("$p1,$p2", $children) || array_key_exists("$p2,$p1", $children));
                    $children["$p1,$p2"] = $this->Hibridize( $population[$p1], $population[$p2], true );
                    if ($this->IsInMemory($children["$p1,$p2"]))
                        {
                            if ($this->output) "Found duplicate in memory for $p1,$p2\n";
                            unset($children["$p1,$p2"]);
                            $i--;
                            continue;
                        } 
                    $names[] = GAOptimizer::getChildName($names[$p1], $names[$p2]);
                    $fitness[] = 0;
                }
                $population = array_merge( $population, array_values($children) );
                 
            } else {
           
                print "Starting strain ".(count($this->strains)+1).", generation 1\n\n";
                
                // Add some random members to population
                $population = $names = $fitness = [];
                for ($i=0; $i<\EP\GA_POPULATION_SIZE; $i++) {
                    $population[] = $this->GetRandomIndividual();
                    $names[] = $this->GetName();
                    $fitness[] = 0;
                }

            }
        }

    // Get name for child given names for parents
    public static function getChildName($p1, $p2)
        {
            $result_ar = [];
            $parts = explode(",", $p1);
            foreach($parts as $part) {
                $nc = explode("-", $part);
                if (count($nc)==2) $count = $nc[1]; else $count = 1;
                $result_ar[$nc[0]] = $count;
            }
            $parts = explode(",", $p2);
            foreach($parts as $part) {
               $nc = explode("-", $part);
                if (count($nc)==2) $count = $nc[1]; else $count = 1;
                if (array_key_exists($nc[0], $result_ar)) $result_ar[$nc[0]] += $count;
                else $result_ar[$nc[0]] = $count;
            }
            
            $result = "";
            foreach($result_ar as $name => $count) {
                if ($result !== "") $result .= ",";
                if ($count > 1)
                    $result .= "$name-$count";
                else
                    $result .= $name;
            }
            return $result;
        }

    // Calculate normalized radius (maximum distance between two members) of a population
    public function PopulationRadius($population)
        {
            // Renormalize all members
            for ($i=0; $i<\EP\GENOME_SIZE; $i++) {
                $max=$min=0; $first=true;
                foreach($this->memory as $item) {
                    if ($first || $item['genome'][$i] > $max) $max = $item['genome'][$i];
                    if ($first || $item['genome'][$i] < $min) $min = $item['genome'][$i];
                    $first = false;
                }
                if ($max != $min)
                    foreach($population as &$individual)
                        $individual[$i] = ($individual[$i] - $min) / ($max - $min);
            }
            
            $maxdist = 0;
            foreach($population as $ind1)
                foreach($population as $ind2) {
                    $dist = GAOptimizer::GenomeDistance($ind1, $ind2);
                    if ($dist > $maxdist) $maxdist = $dist;
                }
            return $maxdist;
        }
}

?>
