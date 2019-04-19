<html>
<head>
  <title>Testing...</title>
  <meta id="meta" name="viewport" content="width=device-width; initial-scale=1.0" />
</head>
<body>
<?php

class Person {
    var $rawData = array();
    var $parents = array();
    var $kids = array();
    var $paths = array();
    var $generation = 1;
    var $kidsSearched = FALSE;
    var $block = -1;
    var $isTarget = FALSE;
    var $isBase = FALSE;

    function __construct(&$rawData, &$parent = null) {
        $this->rawData = &$rawData;
        $this->rawData["object"] = $this;
        if (!$parent == null){
            $this->parents[] = &$parent;
        }
    }

    function getId() {
        return ($this->rawData["Id"]);
    }

    function getWTID() {
        return ($this->rawData["Name"]);
    }

    function getName() {
        if (array_key_exists("BirthName", $this->rawData)) {
            return ($this->rawData["BirthName"]);
        } else {
            return ($this->rawData["ShortName"]);
       }
    }

    function addNewKid(&$rawData) {
        $this->kids[] = new Person($rawData, $this);
    }

    function addKid(&$personObject) {
        $this->kids[] = &$personObject;
        $personObject->parents[] = $this;
    }

    function lastPath() {
        return ($this->paths[count($this->paths)-1]);
    }
}


class Cell {
   var $colspan = 1;
   var $align = "center";
   var $person;
   var $text = "&nbsp;";
   private $column;
  
   function __construct($index) {
       $this->column = $index;
   }

   function reset() {
       $this->person = null;
       $this->text = "&nbsp;";
       $this->colspan = 1;
   }

   function endCol() {
       return $this->column + $this->colspan;
   }
}


// Recursive function to find kids and kids’ kids and kids’ kids’ kids...
function findKids(&$parent, &$people, &$path) {
    $parent->paths[] = $path;
    if(!$parent->kidsSearched){
        for ($i=0; $i<count($people); $i++){  // Loop through everyone
            if ($people[$i]["Father"] == $parent->getId() || $people[$i]["Mother"] == $parent->getId()){
                if ($people[$i]["object"] == null) {
                    $parent->addNewKid($people[$i]);  // Establish parent-child connection - kid object doesn't already exist
                } else {
                    $parent->addKid($people[$i]["object"]);  // Establish parent-child connection - kid object already exists
                }
            }
        }
        $parent->kidsSearched = TRUE;
    }
    for($i=0; $i<count($parent->kids); $i++) { // Loop through all of this person’s kids
        $parent->kids[$i]->generation = max($parent->kids[$i]->generation, $parent->generation + 1);
        if($parent->kids[$i]->isBase) { // If we’re at the base person we’ve completed another path
            $parent->kids[$i]->paths[] = $path;
            $path++;
        } else {
            findKids($parent->kids[$i], $people, $path);
            // Add all paths that were added to child to parent as well.
            for($j=$parent->lastPath(); $j<$parent->kids[$i]->lastPath(); $j++){
                $parent->paths[] = $j+1;
            }
        }
    }
}


// Recursive function to divide ancestors up into blocks
function assignBlocks(&$parent, &$blocks) {
    for ($i=0; $i<count($parent->kids); $i++) {
        if ($parent->kids[$i]->block < 0) {  // if this kid doesn't yet have a block
            if ((count($parent->kids) > 1) || (count($parent->kids[$i]->parents) > 1)) {  // if kid has one or more siblings or both parents
                // they need to be the top of a new block
                $blocks[] = array($parent->kids[$i]);
            } else { // else they are the next person in the current block
                $blocks[$parent->block][] = $parent->kids[$i];
            }
            $parent->kids[$i]->block = count($blocks) - 1;
            assignBlocks($parent->kids[$i], $blocks);
        }
    }
}


// Function to place blocks of people in the grid
function putBlockHere(&$grid, &$blocks, &$thisBlock, $generation, $left, $width) {
   // Child width can’t be greater than parent’s (passed in) width *yet*
   $thisWidth = min(count($thisBlock[0]->paths), $width);
   // Every person occupies a 1x4 block of cells
   for ($i=($generation-1)*4, $j=0; $j<count($thisBlock); $i+=4, $j++){
       if (!$thisBlock[$j]->isTarget) {  // If this is not the top ancestor
           // Print vertical line in the top cell to connect with parent
           $grid[$i][$left]->text = "|";
       }
       // Put pointer to person in 2nd cell
       $grid[$i+1][$left]->person = &$thisBlock[$j];
       if (!$thisBlock[$j]->isBase) {  // If this is not the bottom person
           // Print vertical lines in bottom two cells to connect to child(ren)
           $grid[$i+2][$left]->text = "|";
           $grid[$i+3][$left]->text = "|";
       }
       // Set width for all four cells
       for ($k=0; $k<4; $k++){
           $grid[$i+$k][$left]->colspan = $thisWidth;
       }
   }

   // Find the bottom person in this block
   $bottomOfBlock = &$thisBlock[count($thisBlock)-1];

   // Place blocks for each child
   // First child will be at the same column as parent
   for ($i=0, $nextChildColStart = $left; $i<count($bottomOfBlock->kids); $i++) {
       $lastWidth = putBlockHere($grid, $blocks, $blocks[$bottomOfBlock->kids[$i]->block], $bottomOfBlock->kids[$i]->generation, $nextChildColStart, $thisWidth);
       // Slide over for the next child’s block location
       $nextChildColStart += $lastWidth;
   }
   // return the width because it’s needed for placing siblings
   return ($thisWidth);
}


// Useful debugging function -- prints one row from the grid
function printRow(&$row, $rownum) {
   echo $rownum . "<table style=\"width:100%\" border=\"1\">\n";
   echo "<tr>\n";
   for ($j=0; $j<count($row); $j++) {
       echo "<td align=\"" . $row[$j]->align . "\" colspan=\"1\">";
       if ($row[$j]->person == null){
           echo $row[$j]->text . " : " . $row[$j]->colspan;
       } else {
           echo $row[$j]->person->getName() . " : " . $row[$j]->colspan;
       }
       echo "</td>\n";
   }
   echo "</tr>\n";
   echo "</table>\n<br>";
}

// Print the chart
function printTheChart(&$grid) {
   echo "\n\n<table style=\"width:70%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n";
   for ($i=0; $i<count($grid); $i++) {
       echo "<tr><!-- ". $i . " -->\n";
       for ($j=0; $j<count($grid[$i]); ) {
           echo "<td align=\"" . $grid[$i][$j]->align . "\" colspan=\"" . $grid[$i][$j]->colspan . "\"> <!-- " . $j . " -->";
           if ($grid[$i][$j]->person == null) {
               echo $grid[$i][$j]->text;
           } else {
               echo "<a href=\"http://www.wikitree.com/wiki/" . $grid[$i][$j]->person->getWTID() . "\">";
               echo $grid[$i][$j]->person->getName();
               echo "</a>";
           }
           echo "</td>\n";
           $j += $grid[$i][$j]->colspan;
       }
       echo "</tr>\n";
   }
   echo "</table>\n<br><br><br>";
}

$targetAncestor = "Bartlett-249";
//$targetAncestor = "Bartlett-297";
$base = "Holmes-8874";
//$base = "Griffith-5239";

// Array to hold only one copy of each ancestor
$ancestors = array();

// Array to hold lengths of all paths
$currentPath = 0;

// Array to hold blocks of people whose placement relative to each other is certain
$blocks = array();

// Fetch all of the ancestors of the base person
$json = file_get_contents('https://apps.wikitree.com/api.php?action=getAncestors&key=' . $base . '&depth=10');

// Decode JSON data into PHP associative array format
$arr = json_decode($json, true);

//var_dump($arr[0]["ancestors"][1]);

// Filter out duplicates and find target ancestor and base
for ($i=0, $j=0; $i<count($arr[0]["ancestors"]); $i++){ // Loop through decoded json array
    for($k=0, $found=FALSE; $k<count($ancestors) && $found==FALSE; $k++){ // Loop through already found ancestors
        // If they match we’ve already got this one so set $found so we exit the loop
        if($arr[0]["ancestors"][$i]["Name"] == $ancestors[$k]["Name"]) $found = TRUE;
    }
    if(!$found) {
        $ancestors[$j] = &$arr[0]["ancestors"][$i];
        $ancestors[$j]["object"] = null;
        if($targetAncestor == $ancestors[$j]["Name"]) {
            $targetObject = new Person($ancestors[$j]);
            $blocks[0][0] = &$targetObject;
            $targetObject->block = 0;
            $targetObject->isTarget = TRUE;
        } else if ($base == $ancestors[$j]["Name"]) {
            $baseObject = new Person($ancestors[$j]);
            $baseObject->isBase = TRUE;
        }
        $j++;
    }
}

findKids($targetObject, $ancestors, $currentPath);

assignBlocks($targetObject, $blocks);

// for ($i=0; $i<count($blocks); $i++) {
//   echo "Block " . $i . ": <br>";
//   for ($j=0; $j<count($blocks[$i]); $j++) {
//     echo "&nbsp;&nbsp;&nbsp;&nbsp;" . $blocks[$i][$j]->getName() . " " . $blocks[$i][$j]->getWTID() . ", gen" . $blocks[$i][$j]->generation . ", path(s) ";
//     for ($k=0; $k<(count($blocks[$i][$j]->paths)); $k++) {
//         echo $blocks[$i][$j]->paths[$k] . " ";
//     }
//     echo ", block" . $blocks[$i][$j]->block . "<br>";
//   }
// }
// echo "<br>";

$width = count($targetObject->paths);
$depth = $baseObject->generation;
// echo "<br>" . $width . " wide by " . $depth . " deep<br>";
// echo "<br>";

$grid = array();
for ($i=0; $i<$depth*4; $i++) {
    for ($j=0; $j<$width; $j++) {
        $grid[$i][$j] = new Cell($j);
    }
}
 
$dummy = putBlockHere($grid, $blocks, $blocks[$targetObject->block], $targetObject->generation, 0, count($targetObject->paths));

printTheChart($grid);
 
// Consolidate adjacent duplicate persons
for ($i=1; $i<$depth*4; $i+=4) { // start at 1st row with name (very 1st is blank), jump by 4 rows (names every 4 rows)
    for ($j=0; $grid[$i][$j]->endCol() < $width; ) {
        if (($grid[$i][$j]->person != null) && ($grid[$i][$j]->person == $grid[$i][$grid[$i][$j]->endCol()]->person)) {
            for ($k=-1; $k<3; $k++) { 
                // if ($k==0) printRow($grid[$i+$k], $i+$k);
                // combine vertical blocks of 4 cells into 1 vertical block of 4 cells
                $grid[$i+$k][$grid[$i+$k][$j]->endCol()] = new Cell($grid[$i+$k][$j]->endCol());  // wipe out what was there (cell won't be printed anyway)
                $grid[$i+$k][$j]->colspan += $grid[$i+$k][$grid[$i+$k][$j]->endCol()]->colspan;  // increase colspan by colspan of covered cell
                // if ($k==0) printRow($grid[$i+$k], $i+$k);
            }
            if($grid[$i][$j]->colspan > $grid[$i-4][$j]->colspan) { // if child span is bigger than parent span
                // Draw horizontal line linking parents
                $grid[$i-2][$j]->align = "right";
                $grid[$i-2][$j]->text = "<hr width=\"50%\" align=\"right\">";  // first cell
                for ($k=1; $k<$grid[$i][$j]->colspan; ) {
                    if ($grid[$i-2][$j+$k]->endCol() < $grid[$i][$j]->endCol()) {
                        $grid[$i-2][$j+$k]->text = "<hr width=\"100%\">";  // middle cells
                    } else {
                        $grid[$i-2][$j+$k]->align = "left";
                        $grid[$i-2][$j+$k]->text = "<hr width=\"50%\" align=\"left\">";  //last cell
                    }
                    $k+= $grid[$i][$j+$k]->colspan;
                }
            }
        } else {
            $j += $grid[$i][$j]->colspan;
        }
    }
}

printTheChart($grid);

// Draw horizontal lines linking siblings
for ($i=5; $i<$depth*4; $i+=4) {  // start with 2nd row of 2nd generation (contains names)
    for ($j=0; $grid[$i][$j]->endCol() < $width; ) {
        // if child span is smaller than parent span
        if ($grid[$i][$j]->colspan < $grid[$i-4][$j]->colspan) {
            // Draw horizontal line linking siblings, in last row of previous generation
            $grid[$i-2][$j]->colspan = $grid[$i][$j]->colspan;
            $grid[$i-2][$j]->align = "right";
            $grid[$i-2][$j]->text = "<hr width=\"50%\" align=\"right\">";  // left-most cell
            for ($k=1; $k<$grid[$i-3][$j]->colspan; ) {
                $grid[$i-2][$j+$k]->colspan = $grid[$i][$j+$k]->colspan;
                if ($j + $k + $grid[$i-2][$j+$k]->colspan < $j + $grid[$i-3][$j]->colspan) {
                    $grid[$i-2][$j+$k]->text = "<hr width=\"100%\">";  // middle cell(s)
                } else {
                    $grid[$i-2][$j+$k]->align = "left";
                    $grid[$i-2][$j+$k]->text = "<hr width=\"50%\" align=\"left\">";  // right-most cell
                }
                $k += $grid[$i][$j+$k]->colspan;
            }
        }
        $j += $grid[$i][$j]->colspan;
    }
}

printTheChart($grid);
 
// fill in gaps in vertical lines
for ($i=0; $i<$depth*4-1; $i++) {
    for ($j=0; $j < $width; ) {
        if ($grid[$i][$j]->text[0] == "|" && $grid[$i+1][$j]->text == "&nbsp;" && $grid[$i+1][$j]->person == null) {
            $grid[$i+1][$j]->text = "|";
        }
        $j += $grid[$i][$j]->colspan;
    }
}
 
printTheChart($grid);
 

?>
</body>
</html>




