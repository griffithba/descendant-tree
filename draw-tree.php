<html>
<head>
 <title>Descendant Tree</title>
 <meta id="meta" name="viewport" content="width=device-width; initial-scale=1.0" />
 <style>a {text-decoration: none}</style>
</head>
<body>
<?php

$classes = array();
$debug;
if (isset($_POST["debug"]) && $_POST["debug"] == "on") {
    $debug = TRUE;
} else {
    $debug = FALSE;
}

function debug() {
    global $debug;
    return ($debug);
}

// an individual in the tree
class Person {
    // raw person data as downloaded from WikiTree
    var $rawData = array();
    // parents of this person
    var $parents = array();
    // kids of this person
    var $kids = array();
    // generation this person is part of (if multiple, this is the latest)
    var $generation = 0;
    // whether this person's kids have already been searched
    var $kidsSearched = FALSE;
    // whether this is the target ancestor (top of the chart)
    var $isTarget = FALSE;
    // whether this is the base person (bottom of the chart)
    var $isBase = FALSE;

    function __construct(&$rawData = null, &$parent = null) {
        $this->rawData = &$rawData;
        $this->rawData["object"] = $this;
        if ($parent != null){
            $this->parents[] = &$parent;
        }
        if ($rawData != null){
            global $classes;
            $classes[] = $this->getWTID();
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

    function getDates() {
        if (isset($this->rawData["BirthDate"]))
            $birth = substr($this->rawData["BirthDate"], 0, 4);
        if (!isset($birth) || $birth == "0000")
            $birth = "&nbsp;";
        if (isset($this->rawData["DeathDate"]))
            $death = substr($this->rawData["DeathDate"], 0, 4);
        if (!isset($death) || $death == "0000")
            $death = "&nbsp;";
        return ($birth . "-" . $death);
    }

    function getThumb() {
        if (array_key_exists("PhotoData", $this->rawData))
            return ("<img src=\"http://www.wikitree.com/" . $this->rawData["PhotoData"]["url"] . "\" style=\"vertical-align:top\">\n");
    }

    function addKid(&$rawData) {
        if ($rawData["object"] == null) {  // If there isn't already an object
            $this->kids[] = new Person($rawData, $this);
            $kidObject = &$this->kids[count($this->kids) - 1];
        } else {  // object already exists
            $kidObject = &$rawData["object"];
            $this->kids[] = &$kidObject;
            $kidObject->parents[] = &$this;
        }
    }
}


// a string of people that goes from the target ancestor to the base person
class Path {
    // array of persons in this path
    var $persons = array();
    // index of path in array
    var $index;
    // family groups this path is part of
    var $families = array();
    // neighbor paths to the left and right
    var $left;
    var $right;
    // paths that must be next to this path
    var $mustBeNeighbor = array(null, null);

    function __construct($index = -1) {
        $this->index = $index;
    }

    // returns the path to the left of this one
    function &left() {
        return ($this->left);
    }

    // returns the path to the right of this one
    function &right() {
        return ($this->right);
    }

    // returns this path (so paths and families can be treated the same way)
    function &leftPath() {
        return $this;
    }

    // returns this path (so paths and families can be treated the same way)
    function &rightPath() {
        return $this;
    }

    // returns true if the path to the left of this one must be next to this one
    function leftLocked() {
        for($i=0; $i<2; $i++) {
            if ($this->mustBeNeighbor[$i] == $this->left) {
                //echo "&nbsp;&nbsp;Left side of path " . $this->index . " is locked.<br>";
                return TRUE;
            }
        }
        return FALSE;
    }

    // returns true if the path to the right of this one must be next to this one
    function rightLocked() {
        for($i=0; $i<2; $i++) {
            if ($this->mustBeNeighbor[$i] == $this->right) {
                //echo "&nbsp;&nbsp;Right side of path " . $this->index . " is locked.<br>";
                return TRUE;
            }
        }
        return FALSE;
    }

    // returns true if the paths to the left and right of this one must both be next to this one
    function neighborsLocked() {
        return ($this->rightLocked() && $this->leftLocked());
    }

    // returns true if this path is locked to a path outside of its smallest family
    function lockedToEdgeOfFamily() {
        for($i=0; $i<2 && $this->mustBeNeighbor[$i]!=null; $i++) {
            if (!in_array($this->mustBeNeighbor[$i], $this->families[count($this->families)-2]->paths)) {
                if (debug()) echo "&nbsp;&nbsp;Path " . $this->index . " is locked to the edge of a family already.<br>/n";
                return TRUE;
            }
        }
        return FALSE;
    }

    function addFamily(&$family) {
        for ($i=0; $i<count($this->families); $i++) {
            if ($this->families[$i]->index > $family->index){
                array_splice($this->families, $i, 0, array($family));
//                if (debug()) $this->printFamilies();
                return;
            }
        }
        // if we got here this new family has a higher index number than all of the others
        $this->families[] = &$family;
//        $this->printFamilies();
    }

    function printFamilies() {
        echo "--Families that Path " . $this->index . " is part of: ";
        for ($i=0; $i<count($this->families); $i++) {
            echo $this->families[$i]->index . " ";
        }
        echo "<br>";
    }

    function isPath(){
        return TRUE;
    }
    function isFamily(){
        return FALSE;
    }
}


// a collection of paths which must be grouped together because of sibling relationships
class Family {
    // the paths that make up the family
    var $paths = array();
    // index of this family in the array of families
    var $index;

    function __construct(&$path, $index) {
        $this->paths[0] = &$path;
        $this->index = $index;
        if ($this->index != -1)
            $path->addFamily($this);
    }

    // returns the left-most path in the family
    function &leftPath() {
        $leftSide = &$this->paths[0];
        while (in_array($leftSide->left(), $this->paths, TRUE)) {
            $leftSide = &$leftSide->left();
        }
        return ($leftSide);
    }

    // returns the right-most path in the family
    function &rightPath() {
        $rightSide = &$this->paths[count($this->paths) - 1];
        while (in_array($rightSide->right(), $this->paths, TRUE)) {
            $rightSide = &$rightSide->right();
        }
        return ($rightSide);
    }

    // returns the path to the right of the family
    function &right() {
        return ($this->rightPath()->right());
    }

    // returns the path to the left of the family
    function &left() {
        return ($this->leftPath()->left());
    }

    // returns true if the left side of the family is locked to its left neighbor
    function leftLocked() {
        return ($this->leftPath()->leftLocked());
    }

    // returns true if the right side of the family is locked to its right neighbor
    function rightLocked() {
        return ($this->rightPath()->rightLocked());
    }

    // returns true if both sides of the family are locked
    function neighborsLocked() {
        return ($this->rightLocked() && $this->leftLocked());
    }

    function isPath(){
        return FALSE;
    }
    function isFamily(){
        return TRUE;
    }
}


// A cell in the html table 
class Cell {
    // how many columns wide
    var $colspan = 1;
    // how the contents of the cell are aligned
    var $align = "center";
    // link to the person to show in the cell (if this cell contains a person)
    var $person;
    // text that will show in the cell if there isn't a person
    var $text = "&nbsp;";
    // column that the cell is in (used by endCol() )
    private $column;
 
    function __construct($index) {
        $this->column = $index;
    }

    // reset the contenets of the cell
    function reset() {
        $this->person = null;
        $this->text = "&nbsp;";
        $this->colspan = 1;
    }

    // returns the end column (actually, the start of the next column)
    function endCol() {
        return $this->column + $this->colspan;
    }
}


// Recursive function to find kids and kids’ kids and kids’ kids’ kids...
// It also puts together all of the paths between the base person and target person and groups the paths into families.
function findKids(&$parent, &$people, &$currentPathIndex, &$paths, &$families, $parentFamilyIndex) {
    $paths[$currentPathIndex]->persons[] = &$parent;
    if(!$parent->kidsSearched){
        for ($i=0; $i<count($people); $i++){  // Loop through everyone
            if ($people[$i]["Father"] == $parent->getId() || $people[$i]["Mother"] == $parent->getId()){
                $parent->addKid($people[$i]);  // Establish parent-child connection
            }
        }
        $parent->kidsSearched = TRUE;
    }
    $currentFamilyIndex = $parentFamilyIndex;
    $parentPath = clone $paths[$currentPathIndex];  // temp copy of parent's path to use for kids after first
    if (count($parent->kids) > 1) {
        $families[] = new Family($paths[$currentPathIndex], count($families));
        $currentFamilyIndex = count($families) - 1;
        if (debug()) echo "Added path " . $currentPathIndex . " to new family " . $currentFamilyIndex . "<br>";
    }
    for($i=0; $i<count($parent->kids); $i++) { // Loop through all of this person’s kids
        if ($i > 0) {
            $paths[$currentPathIndex] = clone $parentPath;
            $paths[$currentPathIndex]->index = $currentPathIndex;
            $families[$currentFamilyIndex]->paths[] = &$paths[$currentPathIndex];
            $paths[$currentPathIndex]->addFamily($families[$currentFamilyIndex]);
            if (debug()) echo "Added path " . $currentPathIndex . " to family " . $currentFamilyIndex . "<br>";
        }
        $parent->kids[$i]->generation = max($parent->kids[$i]->generation, $parent->generation + 1);
        if($parent->kids[$i]->isBase) { // If we’re at the base person we’ve completed another path
            $paths[$currentPathIndex]->persons[] = &$parent->kids[$i];
            $currentPathIndex++;
        } else {
            findKids($parent->kids[$i], $people, $currentPathIndex, $paths, $families, $currentFamilyIndex);
        }
    }
    if ($currentFamilyIndex != $parentFamilyIndex && $parentFamilyIndex >= 0) {
        for ($i=0; $i<count($families[$currentFamilyIndex]->paths); $i++) {
            if (!in_array($families[$currentFamilyIndex]->paths[$i], $families[$parentFamilyIndex]->paths)) {
                $families[$parentFamilyIndex]->paths[] = &$families[$currentFamilyIndex]->paths[$i];
            }
        }
    }
}


// Evaluate which paths must be placed next to each other
function findNeighbors (&$families) {
    for ($i=0; $i<count($families); $i++) {
        if (count($families[$i]->paths) == 2) {
            $families[$i]->paths[0]->mustBeNeighbor[0] = &$families[$i]->paths[1];
            $families[$i]->paths[1]->mustBeNeighbor[0] = &$families[$i]->paths[0];
//            if (debug()) echo "Path " . $families[$i]->paths[0]->index . " must be next to " . $families[$i]->paths[1]->index . "<br>";
        }
    }
}


// attempt to shift two paths next to each other  
function consolidate(&$path1, &$path2) {
    // path1 will always be left of path2 upon entering here
    // first figure out the first family that the two paths do not share.
    $index1stUnique = 0;
    for ($i=0; $i<min(count($path1->families), count($path2->families)); $i++) {
        if ($path1->families[$i] != $path2->families[$i]) {
            $index1stUnique = $i;
            break;
        }
    }
    if (debug()) echo "&nbsp;&nbsp;Families " . $path1->families[$index1stUnique]->index . " and " . $path2->families[$index1stUnique]->index . " are at index " . $index1stUnique . "<br>\n";

    // make sure neither path is in a non-shared family that has their neighbors on both sides locked down
    for ($i=$index1stUnique; $i<count($path1->families)-1; $i++) {
        if ($path1->families[$i]->neighborsLocked()) {
            if (debug()) echo "&nbsp;&nbsp;They can't because path " . $path1->index . " is in family " . $path1->families[$i]->index . " that's already got both sides locked down.<br>\n";
            return(-1);
        }
        // also make sure it's not in a family below the 1st unique which is already locked down to a path outside the 1st unique family.
        if ($i > $index1stUnique) {  // this will check every family below 1st unique, but really only the 1st below needs to be checked
            for ($j=0; $j<2 && $path1->families[$i]->leftPath()->mustBeNeighbor[$j] != null; $j++) {
                if (!in_array($path1->families[$i]->leftPath()->mustBeNeighbor[$j], $path1->families[$index1stUnique]->paths)) {
                    if (debug()) echo "&nbsp;&nbsp;Path " . $path1->index . " is part of family " . $path1->families[$i]->index . " that is locked to the edge of family " . $path1->families[$index1stUnique]->index . " already.<br>\n";
                    return(-1);
                }
            }
            for ($j=0; $j<2 && $path1->families[$i]->rightPath()->mustBeNeighbor[$j] != null; $j++) {
                if (!in_array($path1->families[$i]->rightPath()->mustBeNeighbor[$j], $path1->families[$index1stUnique]->paths)) {
                    if (debug()) echo "&nbsp;&nbsp;Path " . $path1->index . " is part of a family that is locked to the edge of a family already.<br>\n";
                    return(-1);
                }
            }
        }
    }
    for ($i=$index1stUnique; $i<count($path2->families)-1; $i++) {
        if ($path2->families[$i]->neighborsLocked()) {
            if (debug()) echo "&nbsp;&nbsp;They can't because path " . $path2->index . " is in family " . $path2->families[$i]->index . " that's already got both sides locked down.<br>\n";
            return(-1);
        }
        // also make sure it's not in a family below the 1st unique which is already locked down to a path outside the 1st unique family.
        if ($i > $index1stUnique) {  // this will check every family below 1st unique, but really only the 1st below needs to be checked
            for ($j=0; $j<2 && $path2->families[$i]->leftPath()->mustBeNeighbor[$j] != null; $j++) {
                if (!in_array($path2->families[$i]->leftPath()->mustBeNeighbor[$j], $path2->families[$index1stUnique]->paths)) {
                    if (debug()) echo "&nbsp;&nbsp;Path " . $path2->index . " is part of a family that is locked to the edge of a family already.<br>\n";
                    return(-1);
                }
            }
            for ($j=0; $j<2 && $path2->families[$i]->rightPath()->mustBeNeighbor[$j] != null; $j++) {
                if (!in_array($path2->families[$i]->rightPath()->mustBeNeighbor[$j], $path2->families[$index1stUnique]->paths)) {
                    if (debug()) echo "&nbsp;&nbsp;Path " . $path2->index . " is part of a family that is locked to the edge of a family already.<br>\n";
                    return(-1);
                }
            }
        }
    }

    // make sure neither path is already locked down to a path outside of its smallest family, unless they're in the same family
    if (($path1->lockedToEdgeOfFamily() || $path2->lockedToEdgeOfFamily()) && ($path1->families[$index1stUnique]->isFamily() || $path2->families[$index1stUnique]->isFamily())) {
        if (debug()) echo "&nbsp;&nbsp;They can't because at least one is already locked to the edge of its family.<br>\n";
        return(-1);
    }

    // if they are in the same family, make sure they aren't each locked down to opposite sides of it
    if ($path1->families[$index1stUnique]->isPath() && $path2->families[$index1stUnique]->isPath()) {
        for ($i=0; $i<2 && $path1->mustBeNeighbor[$i] != null; $i++) {
            if (!in_array($path1->mustBeNeighbor[$i], $path1->families[$index1stUnique-1]->paths)) {
                // path1 is locked to one side of the family, now see if path2 is locked to the other
                for ($j=0; $j<2 && $path2->mustBeNeighbor[$j] != null; $j++) {
                    if (!in_array($path2->mustBeNeighbor[$j], $path2->families[$index1stUnique-1]->paths)) {
                        if (debug()) echo "Paths are each locked down to opposite sides of a family.<br>\n";
                        return(-1);
                    }
                }
            }
        }
    }

    //
    // move the two families together
    //
    for ($i=$index1stUnique, $j=0;
         $i < max(count($path1->families), count($path2->families));
         $i++, $j++) {
        $path1FamilyIndex = min($i, count($path1->families) - 1);
        $path2FamilyIndex = min($i, count($path2->families) - 1);

        if (debug()) {
            if ($path1->families[$path1FamilyIndex]->isFamily()) {
                echo "Family ";
            } else {
                echo "Path ";
            }
            echo $path1->families[$path1FamilyIndex]->index . " ";
            if ($path2->families[$path2FamilyIndex]->isFamily()) {
                echo "Family ";
            } else {
                echo "Path ";
            }
            echo $path2->families[$path2FamilyIndex]->index . "<br>\n";
        }

        $group1LeftExtent = &$path1->families[$path1FamilyIndex]->leftPath();
        $group1RightExtent = &$path1->families[$path1FamilyIndex]->rightPath();
          
        $group1LeftNeighbor = &$group1LeftExtent->left();
        $group1RightNeighbor = &$group1RightExtent->right();

        // if a family is locked down on the side facing the other family, then reverse the family. 
        if ($path1->families[$path1FamilyIndex]->rightLocked()) {
            while ($group1RightExtent->rightLocked() ||
                   ((count($group1RightExtent->right()->families) > $path1FamilyIndex) &&
                    ($group1RightExtent->families[$path1FamilyIndex] == $group1RightExtent->right()->families[$path1FamilyIndex]))) {
                $group1RightExtent = &$group1RightExtent->right();
                if (debug()) echo "(1)Bumped left family right extent over 1, to " . $group1RightExtent->index . ".<br>\n";
                if ($group1RightExtent === $path2) {
                    if (debug()) echo "Groups of paths are too closely tied together.<br>\n";
                    return(-1);
                }
            }
            $group1LeftNeighbor = &$group1LeftExtent->left();
            $group1RightNeighbor = &$group1RightExtent->right();
  
//            if (debug()) checkPaths($path1);

            $group1LeftNeighbor->right = &$group1RightExtent;
            for ($thisPath=&$group1RightExtent->left(); $thisPath!==$group1LeftExtent; $thisPath=&$thisPath->right()) {
                $tempPath = &$thisPath->right();
                $thisPath->right = &$thisPath->left();
                $thisPath->left = &$tempPath;
            }
            $tempPath = &$group1RightExtent->left();
            $group1RightExtent->left = &$group1LeftNeighbor;
            $group1RightExtent->right = &$tempPath;
            $group1RightNeighbor->left = &$group1LeftExtent;
            $group1LeftExtent->left = &$group1LeftExtent->right();
            $group1LeftExtent->right = &$group1RightNeighbor;

            if (debug()) echo "Reversed the order of family " . $path1->families[$path1FamilyIndex]->index . ".<br>\n";
//            if (debug()) checkPaths($path1);

            $group1LeftExtent = &$path1->families[$path1FamilyIndex]->leftPath();
            $group1RightExtent = &$path1->families[$path1FamilyIndex]->rightPath();
            $group1LeftNeighbor = &$group1LeftExtent->left();
            $group1RightNeighbor = &$group1RightExtent->right();
        }

        $group2LeftExtent = &$path2->families[$path2FamilyIndex]->leftPath();
        $group2RightExtent = &$path2->families[$path2FamilyIndex]->rightPath();
          
        $group2RightNeighbor = &$group2RightExtent->right();
        $group2LeftNeighbor = &$group2LeftExtent->left();

        if ($path2->families[$path2FamilyIndex]->leftLocked()) {
            while ($group2LeftExtent->leftLocked() ||
                   ((count($group2LeftExtent->left()->families) > $path2FamilyIndex) &&
                    ($group2LeftExtent->families[$path2FamilyIndex] == $group2LeftExtent->left()->families[$path2FamilyIndex]))) {
                $group2LeftExtent = &$group2LeftExtent->left();
                if (debug()) echo "(1)Bumped right family left extent over 1, to " . $group2LeftExtent->index . ".<br>\n";
                if ($group2LeftExtent === $path1) {
                    if (debug()) echo "Groups of paths are too closely tied together.<br>\n";
                    return(-1);
                }
            }
            $group2RightNeighbor = &$group2RightExtent->right();
            $group2LeftNeighbor = &$group2LeftExtent->left();
  
            $group2RightNeighbor->left = &$group2LeftExtent;
            for ($thisPath=&$group2LeftExtent->right(); $thisPath!=$group2RightExtent; $thisPath=&$thisPath->left()) {
                $tempPath = &$thisPath->left();
                $thisPath->left = &$thisPath->right();
                $thisPath->right = &$tempPath;
            }
            $tempPath = &$group2LeftExtent->right();
            $group2LeftExtent->right = &$group2RightNeighbor;
            $group2LeftExtent->left = &$tempPath;
            $group2LeftNeighbor->right = &$group2RightExtent;
            $tempPath = &$group2RightExtent->left();
            $group2RightExtent->right = &$tempPath;
            $group2RightExtent->left = &$group2LeftNeighbor;

            if (debug()) echo "Reversed the order of family " . $path2->families[$path2FamilyIndex]->index . ".<br>\n";
            $group2RightExtent = &$path2->families[$path2FamilyIndex]->rightPath();
            $group2LeftExtent = &$path2->families[$path2FamilyIndex]->leftPath();
            $group2RightNeighbor = &$group2RightExtent->right();
            $group2LeftNeighbor = &$group2LeftExtent->left();

//            if (debug()) checkPaths($path1);
        }

        // if the left is tied to its left neighbor by lock or by family tie
        while ($group1LeftExtent->leftLocked() ||
               ((count($group1LeftExtent->left()->families) > $path1FamilyIndex) &&
                ($group1LeftExtent->families[$path1FamilyIndex] == $group1LeftExtent->left()->families[$path1FamilyIndex]))) {
            $group1LeftExtent = &$group1LeftExtent->left();
            if (debug()) echo "(2)Bumped left family left extent over 1, to " . $group1LeftExtent->index . ".<br>\n";
        }

        $group1LeftNeighbor = &$group1LeftExtent->left();

        // if the right is tied to its right neighbor by lock or by family tie
        while ($group2RightExtent->rightLocked() ||
               ((count($group2RightExtent->right()->families) > $path2FamilyIndex) &&
                ($group2RightExtent->families[$path2FamilyIndex] == $group2RightExtent->right()->families[$path2FamilyIndex]))) {
            $group2RightExtent = &$group2RightExtent->right();
            if (debug()) echo "(2)Bumped right family right extent over 1, to " . $group2RightExtent->index . ".<br>\n";
        }

        $group2RightNeighbor = &$group2RightExtent->right();

        // if they're not already next to each other
        if ($group1RightNeighbor != $group2LeftExtent) {
            // if the right path isn't already next to the left family
            if ($path1->families[$index1stUnique]->rightPath()->right() != $path2) {
                // move it left
                $group1RightExtent->right = &$group2LeftExtent;
                $group2LeftExtent->left = &$group1RightExtent;
                $group1RightNeighbor->left = &$group2RightExtent;
                $group2RightExtent->right = &$group1RightNeighbor;
                $group2LeftNeighbor->right = &$group2RightNeighbor;
                $group2RightNeighbor->left = &$group2LeftNeighbor;
            } else if ($path2->families[$index1stUnique]->leftPath()->left() != $path1) {
                // else if the left path isn't already next to the right family move it right
                $group1RightExtent->right = &$group2LeftExtent;
                $group2LeftExtent->left = &$group1RightExtent;
                $group1RightNeighbor->left = &$group1LeftNeighbor;
                $group1LeftNeighbor->right = &$group1RightNeighbor;
                $group2LeftNeighbor->right = &$group1LeftExtent;
                $group1LeftExtent->left = &$group2LeftNeighbor;
            }
//            if (debug()) checkPaths($path1);
        }
    }

    //
    // lock the paths to each other
    //
    if ($path1->right() == $path2 && $path2->left() == $path1) {
        if ($path1->mustBeNeighbor[0] == null) {
            $path1->mustBeNeighbor[0] = &$path2;
        } else {
            $path1->mustBeNeighbor[1] = &$path2;
        }
        
        if ($path2->mustBeNeighbor[0] == null) {
            $path2->mustBeNeighbor[0] = &$path1;
        } else {
            $path2->mustBeNeighbor[1] = &$path1;
        }
    }

    return (0);
}


function checkPaths (&$startPath) {
    // check that pointers still agree in both directions
    $tempPath = &$startPath->right();
    while ($tempPath !== $startPath) {
        if ($tempPath->left()->right() !== $tempPath) {
            echo "INDICES ARE MESSED UP!<br>";
            echo "Path " . $tempPath->index . " says Path " . $tempPath->left()->index . " is on its left, but Path " . $tempPath->left()->index . 
                " says Path " . $tempPath->left()->right()->index . " is on its right!<br>\n";
            return;
        }
        $tempPath = &$tempPath->right();
    }
    echo " Paths indices are good<br>\n";
}


// Place all of the people from a path into a given column in the table
function placePath (&$grid, &$path, $col) {
    // each generation has four rows: a vertical line, name and info, and two more vertical lines
    for ($j=0; $j<count($path->persons); $j++) {
        if ($path->persons[$j] == "dummy") continue;
        $nameRow = $path->persons[$j]->generation * 4 - 3;
//        $nameRow = ($j+1) * 4 - 3;
        if (!$path->persons[$j]->isTarget) {
            // vertical line above everyone except the top person
            $grid[$nameRow-1][$col]->text = "|";
        }
        $grid[$nameRow][$col]->person = &$path->persons[$j];
        if (!$path->persons[$j]->isBase) {
            // two vertical lines below everyone except the bottom person
            $grid[$nameRow+1][$col]->text = "|";
            $grid[$nameRow+2][$col]->text = "|";
        }
    }
}



// Print the chart
function printTheChart(&$grid) {
    if (!debug()) echo "\n\n<table style=\"width:95%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n";
    for ($i=0; $i<count($grid); $i++) {
        echo "<tr>\n";
        for ($j=0; $j<count($grid[$i]); ) {
            echo "<td align=\"" . $grid[$i][$j]->align . "\" colspan=\"" . $grid[$i][$j]->colspan . "\">";
            if ($grid[$i][$j]->person == null) {
                echo $grid[$i][$j]->text;
            } else {
                echo "<div class=\"" . $grid[$i][$j]->person->getWTID() . "\">";
                echo "<a href=\"http://www.wikitree.com/wiki/" . $grid[$i][$j]->person->getWTID() . "\">";
                //echo $grid[$i][$j]->person->getThumb();
                echo $grid[$i][$j]->person->getName();
                echo "<br>";
                echo $grid[$i][$j]->person->getDates();
                echo "</a></div>";
            }
            echo "</td>\n";
            $j += $grid[$i][$j]->colspan;
        }
        echo "</tr>\n";
    }
    echo "</table>\n<br><br>";
}


//$targetAncestor = "Bartlett-249";
//$targetAncestor = "Bartlett-297";
//$targetAncestor = "Doty-42";
//$targetAncestor = "Cooke-36";
$targetAncestor = $_POST["target"];
$base = $_POST["base"];
//$base = "Holmes-8874";
//$base = "Griffith-5239";

// Array to hold only one copy of each ancestor
$ancestors = array();

// To keep track of current path as tree is being formed
$currentPathIndex = 0;

// Array to hold paths through the tree
$paths = array();

// Array to hold groups of paths that are connected by siblings
$families = array();

// Fetch all of the ancestors of the base person
$json = file_get_contents('https://apps.wikitree.com/api.php?action=getAncestors&key=' . $base . '&depth=10');

// Decode JSON data into PHP associative array format
$arr = json_decode($json, true);

//if (debug()) var_dump($arr[0]["ancestors"][5]);
//if (debug()) echo "<br><br>";

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
            $targetObject->generation = 1;
            $targetObject->isTarget = TRUE;
        } else if ($base == $ancestors[$j]["Name"]) {
            $baseObject = new Person($ancestors[$j]);
            $baseObject->isBase = TRUE;
        }
        $j++;
    }
}

$paths[$currentPathIndex] = new Path($currentPathIndex);
findKids($targetObject, $ancestors, $currentPathIndex, $paths, $families, -1);

$width = count($paths);
$depth = $baseObject->generation;

// echo "<br>" . $width . " wide by " . $depth . " deep<br>";
// echo "<br>";

$leftMargin = new Path(-1);
$rightMargin = new Path(-2);
$leftMargin->left = &$rightMargin;
$leftMargin->mustBeNeighbor[0] = &$rightMargin;
$rightMargin->right = &$leftMargin;
$rightMargin->mustBeNeighbor[0] = &$leftMargin;

// create linked list of paths
for ($i=0; $i<$width; $i++) {
    if ($i > 0) {
        $paths[$i]->left = &$paths[$i-1];
    } else {
        $leftMargin->right = &$paths[$i];
        $paths[$i]->left = &$leftMargin;
    }
    if ($i < $width-1) {
        $paths[$i]->right = &$paths[$i+1];
    } else {
        $rightMargin->left = &$paths[$i];
        $paths[$i]->right = &$rightMargin;
    }

    // While we're here, insert spaces where needed so people line up between paths
    for ($j=2; $j<$depth; $j++) {
        if ($paths[$i]->persons[$j]->generation > $j + 1) {
//            echo "Inserting a space into path " . $paths[$i]->index . "<br>";
            for ($k=0; $k<($paths[$i]->persons[$j]->generation - ($j + 1)); $k++) {
                //$dummy = new Person;
                array_splice($paths[$i]->persons, $j, 0, array("dummy"));
//                echo "Space inserted<br>";
                $j++;
                if ($j>=$depth) break;
            }
            if ($j<$depth) break;
        }
    }

    // Also while we're here, each path must have as it's last family, itself
    $paths[$i]->families[count($paths[$i]->families)] = &$paths[$i];
}

findNeighbors($families);

if (debug()) {
    $grid = array();
    for ($j=0; $j<$width; $j++) {
        for ($i=0; $i<$depth*4; $i++) {
            $grid[$i][$j] = new Cell($j);
        }
    }

    echo "\n\n<table style=\"width:95%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n<tr>\n";
    // Place people in grid by path
    for ($thisPath=&$leftMargin->right, $col=0;
        $col<$width;
        $thisPath=&$thisPath->right, $col++) {
        placePath($grid, $thisPath, $col);
        echo "<td align=\"center\">" . $thisPath->index;
        if ($thisPath->mustBeNeighbor[0] != null) {
            echo " (" . $thisPath->mustBeNeighbor[0]->index;
            if ($thisPath->mustBeNeighbor[1] != null) {
                echo ", " . $thisPath->mustBeNeighbor[1]->index;
            }
            echo ")";
        }
        echo "</td>\n";
    }
    echo "</tr>\n";

    printTheChart($grid);
}

// Rearrange paths for efficiency
for ($row=2; $row<$depth-1; $row++) { // skip the first generation since there's just one person
    for ($currentPath = &$leftMargin->right;
         $currentPath->right !== $leftMargin;
         $currentPath = &$currentPath->right) {
        if ($currentPath->neighborsLocked() || $currentPath->persons[$row] == "dummy") continue;
//        if (debug()) echo "Checking Path " . $currentPath->index . " as left path.<br>";
        $nameBreak = FALSE;
        for ($checkPath = &$currentPath->right; 
             $checkPath->right !== $leftMargin;
             $checkPath = &$checkPath->right) {
            if ($checkPath->persons[$row] == "dummy") {
                $nameBreak = TRUE;
                continue;
            }
//            if (debug()) echo "Checking Path " . $checkPath->index . " as right path.<br>";
            if ($currentPath->persons[$row] === $checkPath->persons[$row]) {
                if ($nameBreak) {
                    if ($checkPath->neighborsLocked()) continue;
                    // These are duplicates so try to move the paths next to each other.
                    if (debug()) echo "<br>Paths " . $currentPath->index . " and " . $checkPath->index . " should go next to each other if possible (generation " . $row;
                    if (debug()) echo " " . $currentPath->persons[$row]->getWTID() . ")<br>\n";
                    $status = consolidate($currentPath, $checkPath);
                    if ($status == 0 && debug()) {
                        echo "\n\n<table style=\"width:95%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n<tr>\n";
                        $grid = array();
                        for ($j=0; $j<$width; $j++) {
                            for ($i=0; $i<$depth*4; $i++) {
                                $grid[$i][$j] = new Cell($j);
                            }
                        }
                        // Place people in grid by path
                        for ($thisPath=&$leftMargin->right, $col=0;
                             $col<$width;
                             $thisPath=&$thisPath->right, $col++) {
                            placePath($grid, $thisPath, $col);
                            echo "<td align=\"center\">" . $thisPath->index;
                            if ($thisPath->mustBeNeighbor[0] != null) {
                                echo " (" . $thisPath->mustBeNeighbor[0]->index;
                                if ($thisPath->mustBeNeighbor[1] != null) {
                                    echo ", " . $thisPath->mustBeNeighbor[1]->index;
                                }
                                echo ")";
                            }
                            echo "</td>\n";
                        }
                        echo "</tr>\n";
                        printTheChart($grid);
                    }
                } else {
                    // Lock these two together if they aren't already, lest they be torn asunder later.
//                    echo "Attempting to lock together adjacent Paths " . $currentPath->index . " and " . $checkPath->index . " in generation " . $row . ".<br>\n";
                    if ($currentPath->mustBeNeighbor[0] !== $checkPath && $currentPath->mustBeNeighbor[1] !== $checkPath &&
                        $currentPath->persons[$row-1] !== $checkPath->persons[$row-1]) {
                        if ($currentPath->mustBeNeighbor[0] == null) {
                            $currentPath->mustBeNeighbor[0] = &$checkPath;
                        } else if ($currentPath->mustBeNeighbor[1] == null) {
                            $currentPath->mustBeNeighbor[1] = &$checkPath;
                        }
                        if ($checkPath->mustBeNeighbor[0] == null) {
                            $checkPath->mustBeNeighbor[0] = &$currentPath;
                        } else if ($checkPath->mustBeNeighbor[1] == null) {
                            $checkPath->mustBeNeighbor[1] = &$currentPath;
                        }
                        if (debug()) echo "Locked together adjacent Paths " . $currentPath->index . " and " . $checkPath->index . " in generation " . $row . ".<br>\n";
                    }                    
                    $currentPath = &$checkPath;
                }
            } else {
                $nameBreak = TRUE;
            }
        }
    }
}

$grid = array();
for ($j=0; $j<$width; $j++) {
    for ($i=0; $i<$depth*4; $i++) {
        $grid[$i][$j] = new Cell($j);
    }
}

if (debug()) echo "\n\n<table style=\"width:95%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n<tr>\n";
// Place people in grid by path
for ($thisPath=&$leftMargin->right, $col=0;
     $col<$width;
     $thisPath=&$thisPath->right, $col++) {
    placePath($grid, $thisPath, $col);
    if (debug()) {
        echo "<td align=\"center\">" . $thisPath->index;
        if ($thisPath->mustBeNeighbor[0] != null) {
            echo " (" . $thisPath->mustBeNeighbor[0]->index;
            if ($thisPath->mustBeNeighbor[1] != null) {
                echo ", " . $thisPath->mustBeNeighbor[1]->index;
            }
            echo ")";
        }
        echo "</td>\n";
    }
}
if (debug()) {
    echo "</tr>\n";
    printTheChart($grid);
}

// Consolidate adjacent duplicate persons
for ($i=1; $i<$depth*4; $i+=4) { // start at 1st row with name (very 1st is blank), jump by 4 rows (names every 4 rows)
    for ($j=0; $grid[$i][$j]->endCol() < $width; ) {
        if (/*($grid[$i][$j]->person != null) && */($grid[$i][$j]->person == $grid[$i][$grid[$i][$j]->endCol()]->person)) {
            for ($k=-1; $k<3; $k++) {
                // if ($k==0) printRow($grid[$i+$k], $i+$k);
                // combine vertical blocks of 4 cells into 1 vertical block of 4 cells
                $grid[$i+$k][$grid[$i+$k][$j]->endCol()] = new Cell($grid[$i+$k][$j]->endCol());  // wipe out what was there (cell won't be printed anyway)
                $grid[$i+$k][$j]->colspan += $grid[$i+$k][$grid[$i+$k][$j]->endCol()]->colspan;  // increase colspan by colspan of covered cell
                // if ($k==0) printRow($grid[$i+$k], $i+$k);
            }
            if (($grid[$i][$j]->person != null) && !$grid[$i][$j]->person->isTarget) {
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
            }
        } else {
            $j += $grid[$i][$j]->colspan;
        }
    }
}

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

// fill in gaps in vertical lines
for ($i=0; $i<$depth*4-1; $i++) {
    for ($j=0; $j < $width; ) {
        if ($grid[$i][$j]->text[0] == "|" && $grid[$i+1][$j]->text == "&nbsp;" && $grid[$i+1][$j]->person == null) {
            $grid[$i+1][$j]->text = "|";
        }
        $j += $grid[$i][$j]->colspan;
    }
}

echo $baseObject->getName() . " is descended from " . $targetObject->getName() . " " . $width . " different ways.<br>\n";
echo "<br>\n";
if (debug()) echo "\n\n<table style=\"width:95%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n<tr>\n";
printTheChart($grid);

echo "\n\n<script>\n";
echo "var classes = [";
for ($i=0; $i<count($classes); $i++) {
    if ($i != 0) {
        echo ", ";
    }
    echo "\"" . $classes[$i] . "\"";
}
echo "];";

?>

var elms = {};
var n = {}, nclasses = classes.length;
function changeColor(classname, color) {
  var curN = n[classname];
  for (var i=0; i < curN; i++) {
    elms[classname][i].style.backgroundColor = color;
  }
}
for(var k = 0; k < nclasses; k ++) {
  var curClass = classes[k];
  elms[curClass] = document.getElementsByClassName(curClass);
  n[curClass] = elms[curClass].length;
  var curN = n[curClass];
  for(var i = 0; i < curN; i ++) {
     elms[curClass][i].onmouseover = function() {
        changeColor(this.className, "yellow");
     };
     elms[curClass][i].onmouseout = function() {
        changeColor(this.className, "white");
     };
  }
};
</script>

</body>
</html>



