<?php

class BattleshipsPuzzle {

  /**
   * CONSTANTS
   */

  /**
   * Cell content constants.
   */
  public const CELL_BLANK   = 0;
  public const CELL_SINGLE  = 1;
  public const CELL_LEFT    = 2;
  public const CELL_MIDDLE  = 3;
  public const CELL_RIGHT   = 4;
  public const CELL_TOP     = 5;
  public const CELL_BOTTOM  = 6;
  public const CELL_WATER   = 7;
  public const CELL_UNKNOWN = 8;
  public const CELLS_END = [
    self::CELL_TOP,
    self::CELL_BOTTOM,
    self::CELL_LEFT,
    self::CELL_RIGHT,
  ];

  /**
   * Difficulty levels.
   */
  public const DIFFICULTY_EASY   = 'easy';
  public const DIFFICULTY_MEDIUM = 'medium';
  public const DIFFICULTY_HARD   = 'hard';

  /**
   * Directions.
   */
  public const DIRECTION_UP_LEFT    = [-1, -1];
  public const DIRECTION_UP         = [ 0, -1];
  public const DIRECTION_UP_RIGHT   = [ 1, -1];
  public const DIRECTION_RIGHT      = [ 1,  0];
  public const DIRECTION_DOWN_RIGHT = [ 1,  1];
  public const DIRECTION_DOWN       = [ 0,  1];
  public const DIRECTION_DOWN_LEFT  = [-1,  1];
  public const DIRECTION_LEFT       = [-1,  0];
  public const DIRECTIONS_CARDINAL = [
    self::DIRECTION_UP,
    self::DIRECTION_DOWN,
    self::DIRECTION_LEFT,
    self::DIRECTION_RIGHT,
  ];
  public const DIRECTIONS_DIAGONAL = [
    self::DIRECTION_UP_LEFT,
    self::DIRECTION_UP_RIGHT,
    self::DIRECTION_DOWN_LEFT,
    self::DIRECTION_DOWN_RIGHT,
  ];

  /**
   * Puzzle grid, represented as an array of arrays (rows).
   *
   * @var array
   */
  private $grid;

  /**
   * Constraints for each row and column.
   *
   * @var array
   */
  private $constraints;

  /**
   * List of row and column indexes that have already been satisfied during the
   * solving process.
   *
   * @var array
   */
  private $satisfied;

  /**
   * Array of counts for ships of each length. Element index is length - 1.
   * Element value is the number of ships of that length yet to be found in the
   * puzzle grid.
   *
   * @var array
   */
  private $shipLengths;

  /**
   * Orientations.
   */
  public const ORIENT_HORIZONTAL = 1;
  public const ORIENT_VERTICAL   = 2;

  /**
   * Constructs a Battleships Puzzle object instance.
   *
   * @param [type] $grid Array of rows, which themselves are arrays of values
   * @param [type] $constraintsRow Array of constraints for each row
   * @param [type] $constraintsCol Array of constraints for each column
   * @param [type] $fleet Array of counts for ships of each length (index + 1)
   * @return void
   */
  public function __construct($grid, $constraintsCol, $constraintsRow, $fleet) {
    $this->grid = $grid;
    $this->size = count($this->grid);
    $this->constraints = [
      'row' => $constraintsRow,
      'col' => $constraintsCol,
    ];
    // Each stores a list of ARRAY INDEXES pointing to satisfied constraints.
    $this->satisfied = [
      'rows' => [],
      'cols' => [],
    ];
    // Store the array of lengths of available ships (length = index + 1).
    $this->shipLengths = array_map(fn($n) => intval($n), $fleet);
    // Initialize array where we'll keep track of which ships were found, their
    // length, and the row and column in which they begin (TOP or LEFT piece).
    $this->shipsFound = [];
  }

  public function getGrid() {
    return $this->grid;
  }

  public function getGridString() {
    return implode(',', array_map(fn($row) => implode('', $row), $this->grid));
  }

  public function getGridHtml() {
    $html = '<table style="background:white;border-collapse:collapse;">';
    $html .= '<tr style="height:1.25rem;"><th></th>';
    // Include all column constraints up top.
    foreach ($this->constraints['col'] as $value) {
      $html .= '<th style="text-align:center;">' . $value . '</th>';
    }
    $html .= '</tr>';
    foreach ($this->grid as $row => $rowArray) {
      $html .= '<tr style="height:1.25rem;">'
            .  '<th style="text-align:center;width:1.25rem;">'
            .  $this->constraints['row'][$row] . '</th>';
      foreach ($rowArray as $col => $colValue) {
        $html .= '<td style="font-size:1rem;width:1.25rem;text-align:center;'
              .  'padding:0;border:1px solid white;line-height:1;'
              .  ($this->isValueWater($colValue) ? 'background:aqua;' : '')
              .  ($this->isValueBlank($colValue) ? 'background:gray;' : '')
              .  ($colValue === self::CELL_UNKNOWN ? 'color:purple;' : '')
              .  '">'
              .  ($colValue === self::CELL_SINGLE    ? '⬤' : '')
              .  ($this->isValueMiddleEnd($colValue) ? '■' : '')
              .  ($colValue === self::CELL_LEFT      ? '◀' : '')
              .  ($colValue === self::CELL_RIGHT     ? '▶' : '')
              .  ($colValue === self::CELL_TOP       ? '▲' : '')
              .  ($colValue === self::CELL_BOTTOM    ? '▼' : '')
              .  ($colValue === self::CELL_UNKNOWN   ? '?' : '')
              . '</td>';
      }
      $html .= '</tr>';
    }
    $html .= '</table>';
    return $html;
  }

  /**
   * Attempt to solve the puzzle represented by the grid passed in to this class
   * by looping through a set of 5 steps until no changed have been made. After,
   * if the grid still has not been solved, use backtracking and trial and error
   * to attempt to solve the puzzle by brute force.
   *
   * @return boolean Whether the puzzle has been solved or not
   */
  public function solve() {
    // Mark all cells as water in rows and columns with a constraint of "0".
    $this->markZeroRowsCols();
    // Check for any already-completed ships that we've been given at the start.
    $this->checkForCompletedShips();
    
    // Track changes to the puzzle grid.
    $changed = false;
    $num_passes = 0;
    do {
      $num_passes++;
      $previousGrid = json_encode($this->grid);
      $this->applySegmentRules();
      $this->checkSatisfiedConstraints();
      $this->resolveUnknownSegments();
      $this->resolveShipsOfUnknownLength();
      $this->tryToPlaceLargestShips();
      $currentGrid = json_encode($this->grid);
      $changed = $previousGrid !== $currentGrid;
    } while ($changed && !$this->isSolved());

    // Final check to resolve any remaining unresolved cells.
    $this->resolveUnknownSegments();

    // If the puzzle hasn't been solved after no changes are possible above, try
    // trial and error through backtracking.
    if ($this->isSolved()) {
      return true;
    }

    // Backtracking trial-and-error.
    return $this->backtrackSolve();
  }

  /**
   * Attempt to solve the puzzle using backtracking.
   *
   * @return boolean Whether the puzzle has been solved or not
   */
  private function backtrackSolve() {
    $segments = [
      self::CELL_SINGLE,
      self::CELL_LEFT,
      self::CELL_TOP,
    ];
    for ($row = 0; $row < $this->size; $row++) {
      for ($col = 0; $col < $this->size; $col++) {
        if ($this->isBlank($row, $col)) {
          // Try placing a ship segment here.
          foreach ($segments as $segment) {
            if ($this->canPlaceSegmentInCell($row, $col, true)) {
              $this->setCellValue($row, $col, $segment);
              if ($this->solve()) {
                return true;
              }
              // Backtrack if placing this segment didn't lead to a solution.
              $this->setCellValue($row, $col, self::CELL_BLANK);
            }
          }
          // If no segment placement works, return false.
          return false;
        }
      }
    }
    // If no blank cells are left, the puzzle is solved.
    return $this->isSolved();
  }

  /**
   * Checks the entire grid to see if it has been solved. This only checks for
   * any blank cells, and returns false if any are found.
   *
   * @return boolean Whether the puzzle has been solved or not
   */
  private function isSolved() {
    for ($row = 0; $row < $this->size; $row++) {
      for ($col = 0; $col < $this->size; $col++) {
        // Any blank cells means the puzzle is not yet solved.
        if ($this->isBlank($row, $col))
          return false;
      }
    }
    // If we've reached here, the puzzle has been solved.
    return true;
  }

  /**
   * Mark every cell in each row and column with a "0" constraint as water.
   *
   * @return void
   */
  private function markZeroRowsCols() {
    // Start with rows.
    foreach ($this->constraints['row'] as $row => $count) {
      if ($count > 0) continue;
      // This row is marked "0", so mark each cell in this row as water.
      for ($col = 0; $col < $this->size; $col++) {
        $this->setCellValue($row, $col, self::CELL_WATER);
      }
      // Finally, mark constraint as satisfied.
      $this->markConstraintSatisfied($row, self::ORIENT_HORIZONTAL);
    }

    // Next, columns.
    foreach ($this->constraints['col'] as $col => $count) {
      if ($count > 0) continue;
      // This column is marked "0", so mark each cell in this column as water.
      for ($row = 0; $row < $this->size; $row++) {
        $this->setCellValue($row, $col, self::CELL_WATER);
      }
      // Finally, mark constraint as satisfied.
      $this->markConstraintSatisfied($col, self::ORIENT_VERTICAL);
    }
  }

  /**
   * Check the entire puzzle grid for intact, completed ships at the start of
   * the solving process. We only do this once, because we currently don't track
   * WHICH ships were found, only the final counts.
   *
   * @return void
   */
  private function checkForCompletedShips() {
    // Keep track of which cells we've looked at so we don't double-count ships.
    $traversedCells = [];
    for ($startRow = 0; $startRow < $this->size; $startRow++) {
      for ($startCol = 0; $startCol < $this->size; $startCol++) {
        // If we've been here before, skip ahead to next cell.
        $cellCoord = $startRow . '/' . $startCol;
        if (in_array($cellCoord, $traversedCells)) {
          continue;
        }

        // Add this cell coordinate to the traversed cells list.
        $traversedCells[] = $cellCoord;

        // Store value of cell for comparison.
        $cellValue = $this->getCellValue($startRow, $startCol);

        // If this cell is a single ship piece, then mark as found and move on.
        if ($cellValue === self::CELL_SINGLE) {
          $this->markShipLengthFound(1, $startRow, $startCol);
          continue;
        }
        
        // This cell must be an end piece or a single ship before traversing.
        if (
          !$this->isAnyEnd($startRow, $startCol) &&
          !$this->isMiddleEnd($startRow, $startCol)
        ) {
          // Anything else, we skip.
          continue;
        }

        // Keep track of how long our ship is (if any).
        $thisShipLen = 1;

        // Determine direction to traverse based on which end piece we're on.
        switch ($cellValue) {
          case self::CELL_TOP:
            $direction = self::DIRECTION_DOWN;
            $oppositeEnd = self::CELL_BOTTOM;
            break;
          case self::CELL_LEFT:
            $direction = self::DIRECTION_RIGHT;
            $oppositeEnd = self::CELL_RIGHT;
            break;
          default:
            $thisShipLen = 0;
        }

        // Placed outside the switch() to avoid conflicting `continue`s.
        if ($thisShipLen === 0) {
          // Skip this cell check, as it's not a BEGINNING end piece (LEFT/TOP).
          continue;
        }

        // Store direction deltas for row and column.
        list($deltaCol, $deltaRow) = $direction;

        // Main traversal loop.
        while (true) {

          // Get distance from the original cell to the cell we're traversing.
          $rowOffset = ($thisShipLen - 1) * $deltaRow;
          $colOffset = ($thisShipLen - 1) * $deltaCol;
          
          // Calculate the row/column cell we're currently traversing into.
          $thisRow = $startRow + $rowOffset;
          $thisCol = $startCol + $colOffset;
          $thisVal = $this->getCellValue($thisRow, $thisCol);

          // Look ahead to NEXT cell.
          $nextRow = $thisRow + $rowOffset;
          $nextCol = $thisCol = $colOffset;
          $nextVal = $this->getCellValue($nextRow, $nextCol);

          // If we're at the longest length possible, or if the next cell is
          // anything other than a middle or end piece, there's nothing to do.
          if (
            $thisShipLen === $this->getMaxShipLength() || (
              $nextVal !== self::CELL_MIDDLE &&
              $nextVal !== $oppositeEnd
            )
          ) {
            // No ship found.
            $thisShipLen = 0;
            break;
          }

          // If the next cell is the OPPOSITE END piece, then this ship is done.
          if ($this->getCellValue($nextRow, $nextCol) === $oppositeEnd) {
            // Account for the fact that we're looking ahead to next cell.
            $thisShipLen++;
            break;
          }

          // If we're this far, then we're continuing our traversal. Since we've
          // already looked at the next cell this direction, mark as traversed.
          $traversedCells[] = $nextRow . '/' . $nextCol;

        } // end while [main loop]
        
        if ($thisShipLen < 1) {
          // We couldn't finalize any ships, so can't mark any as found. Next!
          continue;
        }

        // Mark ship of this final length as found.
        $this->markShipLengthFound($thisShipLen, $startRow, $startCol);

      } // end for (col)

    } // end for (row)

  }

  private function applySegmentRules() {
    // Iterate through each cell in the grid.
    for ($row = 0; $row < $this->size; $row++) {
      for ($col = 0; $col < $this->size; $col++) {
        // Place water in neighboring cells depending on this cell's contents.
        if ($this->isAnyShipSegment($row, $col)) {
          // Diagonal neighbors around all ship segments are set to WATER.
          $this->markCellsInDirectionsAsWater($row, $col,
            self::DIRECTIONS_DIAGONAL);
        }
        // Check for 5 segments that require water in 1+ cardinal directions.
        switch ($this->getCellValue($row, $col)) {
          // Single piece? All cells around are water.
          case self::CELL_SINGLE:
            $this->markCellsInDirectionsAsWater($row, $col,
              self::DIRECTIONS_CARDINAL
            );
            break;
          // Left piece? All but right are water; right is unresolved segment.
          case self::CELL_LEFT:
            // Mark cells above, below, and to the left as WATER.
            $this->markCellsInDirectionsAsWater($row, $col, [
              self::DIRECTION_UP,
              self::DIRECTION_DOWN,
              self::DIRECTION_LEFT,
            ]);
            // Mark cell to the right as UNKNOWN (if blank).
            $this->markCellIfBlank($row, $col + 1, self::CELL_UNKNOWN);
            break;
          // Right piece? All but left are water; left is an unresolved segment.
          case self::CELL_RIGHT:
            // Mark cells above, below, to the right as WATER.
            $this->markCellsInDirectionsAsWater($row, $col, [
              self::DIRECTION_UP,
              self::DIRECTION_DOWN,
              self::DIRECTION_RIGHT,
            ]);
            // Mark cell to the left as UNKNOWN (if blank).
            $this->markCellIfBlank($row, $col - 1, self::CELL_UNKNOWN);
            break;
          // Top piece? All but below are water; below is an unresolved segment.
          case self::CELL_TOP:
            // Mark cells to left, right, and below as WATER.
            $this->markCellsInDirectionsAsWater($row, $col, [
              self::DIRECTION_UP,
              self::DIRECTION_LEFT,
              self::DIRECTION_RIGHT,
            ]);
            // Mark cell below as UNKNOWN (if blank).
            $this->markCellIfBlank($row + 1, $col, self::CELL_UNKNOWN);
            break;
          case self::CELL_BOTTOM:
            // Mark cells to left, right, and top as WATER.
            $this->markCellsInDirectionsAsWater($row, $col, [
              self::DIRECTION_DOWN,
              self::DIRECTION_LEFT,
              self::DIRECTION_RIGHT,
            ]);
            // Mark cell above as UNKNOWN (if blank).
            $this->markCellIfBlank($row - 1, $col, self::CELL_UNKNOWN);
            break;
        }
      }
    }
  }

  /**
   * Check if any rows or columns have a constraint that is either already
   * satisfied by having the required # of cells filled with segments (and thus
   * must have water in the remaining empty cells), or CAN be satisfied by fill-
   * ing in the rest of the blank cells with ship segments.
   *
   * @return void
   */
  private function checkSatisfiedConstraints() {
    // First, check each row constraint.
    foreach ($this->constraints['row'] as $row => $count) {
      $count = intval($count);
      // If this row constraint has already been satisfied, skip to next.
      if ($this->isConstraintSatisfied($row, self::ORIENT_HORIZONTAL)) {
        continue;
      }
      // Determine if constraint has been satisfied by counting filled cells.
      $numFilledCells = count(array_filter(
        $this->grid[$row],
        fn($cell) => $this->isValueAnyShipSegment($cell)
      ));
      // Check to see if all required cells HAVE been filled with ship segments.
      if ($numFilledCells === $count) {
        // Fill in blank cells with water.
        for ($col = 0; $col < $this->size; $col++) {
          $this->markCellIfBlank($row, $col, self::CELL_WATER);
        }
        // Finally, mark constraint as satisfied.
        $this->markConstraintSatisfied($row, self::ORIENT_HORIZONTAL);
      } else {
        // Next, determine if constraint CAN be satisfied (# non-water = count).
        $numFillableCells = count(array_filter(
          $this->grid[$row],
          fn($cell) => $cell !== self::CELL_WATER
        ));
        // Check to see if all blank cells CAN be filled in with ship segments.
        if ($numFillableCells === $count - $numFilledCells) {
          // Fill in blank cells with unknown ship segments.
          for ($col = 0; $col < $this->size; $col++) {
            $this->markCellIfBlank($row, $col, self::CELL_UNKNOWN);
          }
          // Finally, mark constraint as satisfied.
          $this->markConstraintSatisfied($row, self::ORIENT_HORIZONTAL);
        }
      }
    }

    // Next, check each column constraint.
    foreach ($this->constraints['col'] as $col => $count) {
      $count = intval($count);
      // If this column constraint has already been satisfied, skip to next.
      if ($this->isConstraintSatisfied($col, self::ORIENT_VERTICAL)) {
        continue;
      }
      // Determine if constraint has been satisfied by counting filled cells.
      $numFilledCells = count(array_filter(
        $this->grid,
        fn($row) => $this->isValueAnyShipSegment($row[$col])
      ));
      // Check to see if all required cells HAVE been filled with ship segments.
      if ($numFilledCells === $count) {
        // Fill in blank cells with water.
        for ($row = 0; $row < $this->size; $row++) {
          $this->markCellIfBlank($row, $col, self::CELL_WATER);
        }
        // Finally, mark constraint as satisfied.
        $this->markConstraintSatisfied($col, self::ORIENT_VERTICAL);
      } else {
        // Next, determine if constraint CAN be satisfied (# non-water = count).
        $numFillableCells = count(array_filter(
          $this->grid,
          fn($row) => $row[$col] !== self::CELL_WATER
        ));
        // Check to see if all blank cells CAN be filled in with ship segments.
        if ($numFillableCells === $count) {
          // Fill in blank cells with unknown ship segments.
          for ($row = 0; $row < $this->size; $row++) {
            $this->markCellIfBlank($row, $col, self::CELL_UNKNOWN);
          }
          // Finally, mark constraint as satisfied.
          $this->markConstraintSatisfied($col, self::ORIENT_VERTICAL);
        }
      }
    }
  }

  private function getDirectionString($direction) {
    switch ($direction) {
      case self::DIRECTION_UP:
        return 'up';
      case self::DIRECTION_DOWN:
        return 'down';
      case self::DIRECTION_LEFT:
        return 'left';
      case self::DIRECTION_RIGHT:
        return 'right';
      case self::DIRECTION_UP_LEFT:
        return 'northwest';
      case self::DIRECTION_UP_RIGHT:
        return 'northeast';
      case self::DIRECTION_DOWN_LEFT:
        return 'southwest';
      case self::DIRECTION_DOWN_RIGHT:
        return 'southeast';
      default:
        return 'unknown';
    }
  }

  /**
   * Check each unresolved cell to see if any of its 4 cardinally adjacent
   * neighbors to see if it can be resolved to water, middle piece, or end cap.
   *
   * @return void
   */
  private function resolveUnknownSegments() {
    $traversedCells = [];

    for ($row = 0; $row < $this->size; $row++) {
      for ($col = 0; $col < $this->size; $col++) {
        if (in_array($row . '/' . $col, $traversedCells)) {
          continue;
        }

        $traversedCells[] = $row . '/' . $col;

        // Cell must be unresolved ship segment for this check.
        if ($this->getCellValue($row, $col) !== self::CELL_UNKNOWN) continue;
        // Check each of the 4 cardinal directions from this cell.
        foreach (self::DIRECTIONS_CARDINAL as $direction) {
          $fwdCol = $col + $direction[0];
          $fwdRow = $row + $direction[1];
          // Is this cell any kind of ship segment?
          if ($this->isAnyShipSegment($fwdRow, $fwdCol)) {
            // Look at cell in opposite direction.
            $revCol = $col - $direction[0];
            $revRow = $row - $direction[1];
            // Is this opposite cell water or out of bounds?
            if (
              !$this->isValidCell($revRow, $revCol) ||
               $this->isWater($revRow, $revCol)
            ) {
              // Mark original cell as an endcap in opposite direction
              $this->setCellValue(
                $row, $col,
                $this->getEndPieceFacingAwayFrom($direction)
              );
            }
            // Otherwise, is this opposite cell also a ship segment?
            else if ($this->isAnyShipSegment($revRow, $revCol)) {
              // If so, mark our original cell as a middle piece.
              $this->setCellValue($row, $col, self::CELL_MIDDLE);
              // We know ship's orientation - mark perpendicular cells w/ water.
              $perpDirs = $this->getPerpendicularDirections($direction);
              $this->markCellsInDirectionsAsWater($row, $col, $perpDirs);
            }
          }
          // Otherwise, is this cell either a water cell or blank?
          else if (
            $this->isWater($fwdRow, $fwdCol) &&
            $this->isValidCell($fwdRow, $fwdCol)
          ) {
            // Check the cell in the opposite direction.
            $revCol = $col - $direction[0];
            $revRow = $row - $direction[1];
            // Is this opposite cell any kind of ship segment?
            if (
              $this->isValidCell($revRow, $revCol) &&
              $this->isAnyShipSegment($revRow, $revCol)
            ) {
              // Mark original cell as an endcap (facing same as our direction).
              $this->setCellValue(
                $row, $col,
                $this->getEndPieceFacingToward($direction)
              );
            }
            // If this cell is water or out of bounds there's nothing we can do.
          }
        }
      }
    }
  }

  /**
   * Starting at each cell with a left or top end piece, count the unresolved
   * cells in the direction it faces until we reach water or the grid edge. Mark
   * all unresolved cells in the center as middle pieces and the final unknown
   * cell in the chain as a right or bottom end piece (depending on direction).
   *
   * @return void
   */
  private function resolveShipsOfUnknownLength() {
    for ($startRow = 0; $startRow < $this->size; $startRow++) {

      for ($startCol = 0; $startCol < $this->size; $startCol++) {
        $cellCoord = $startRow . '/' . $startCol;

        // If this cell is the start of an already-found ship, skip ahead.
        if ($this->isCellInFoundShip($startRow, $startCol)) {
          continue;
        }

        // Keep track of how long our ship is (if any).
        $thisShipLen = 1;

        $direction = false; // set only if we're traversing
        $cellValue = $this->getCellValue($startRow, $startCol);

        // If this cell is middle piece segment, we can't do anything just yet.
        // Another iteration will catch this if part of a larger, resolved ship.
        if ($this->isValueMiddleEnd($cellValue)) {
          continue;
        }
        
        // If this is an unresolved segment, see if we can resolve to end piece.
        if ($this->isValueUnknownShipSegment($cellValue)) {
          // Only change and continue if surrounded (entirely/partly) by water.
          // Look up.
          $cellAbove = $this->getCellValueInDir(
            $startRow, $startCol, self::DIRECTION_UP
          );
          $isCellAboveBlocked = ($cellAbove === false) ||
            $this->isValueWater($cellAbove);
          $isCellAboveSegment = $this->isValueAnyShipSegment($cellAbove);
          // Look down.
          $cellBelow = $this->getCellValueInDir(
            $startRow, $startCol, self::DIRECTION_DOWN
          );
          $isCellBelowBlocked = ($cellBelow === false) ||
            $this->isValueWater($cellBelow);
          $isCellBelowSegment = $this->isValueAnyShipSegment($cellBelow);
          // Look to left.
          $cellLeft  = $this->getCellValueInDir(
            $startRow, $startCol, self::DIRECTION_LEFT
          );
          $isCellLeftBlocked = ($cellLeft === false) ||
            $this->isValueWater($cellLeft);
          $isCellLeftSegment  = $this->isValueAnyShipSegment($cellLeft);
          // Look to right.
          $cellRight = $this->getCellValueInDir(
            $startRow, $startCol, self::DIRECTION_RIGHT
          );
          $isCellRightBlocked = ($cellRight === false) ||
            $this->isValueWater($cellRight);
          $isCellRightSegment = $this->isValueAnyShipSegment($cellRight);
          // Now look behind you!

          // Just kidding. So, can we resolve this to an end piece of some sort?
          
          // Is this a single-cell ship?
          if (
            $isCellAboveBlocked && $isCellLeftBlocked &&
            $isCellRightBlocked && $isCellBelowBlocked
          ) {
            // Resolve cell to single ship segment.
            $this->setCellValue($startRow, $startCol, self::CELL_SINGLE);
            // We're done with this traversal, so move on to next cell.
            break;
          }
          
          // Is this a left end piece?
          if (
            $isCellAboveBlocked && $isCellLeftBlocked &&
            $isCellRightSegment && $isCellBelowBlocked
          ) {
            // Resolve cell to left end segment.
            $this->setCellValue($startRow, $startCol, self::CELL_LEFT);
            // We're done with this cell, so move on to next.
            continue;
          }
          
          // Is this a top end piece?
          if (
            $isCellAboveBlocked && $isCellLeftBlocked &&
            $isCellRightBlocked && $isCellBelowSegment
          ) {
            // Resolve cell to top end segment.
            $this->setCellValue($startRow, $startCol, self::CELL_TOP);
            // We're done with this cell, so move on to next.
            continue;
          }

          // We can't resolve this segment to anything, so move on.
          continue;

        } elseif ($this->isAnyEnd($startRow, $startCol)) {
          
          // Determine direction to traverse based on which end piece we're on.
          switch ($cellValue) {
            case self::CELL_TOP:
              $direction = self::DIRECTION_DOWN;
              $oppositeEnd = self::CELL_BOTTOM;
              break;
            case self::CELL_LEFT:
              $direction = self::DIRECTION_RIGHT;
              $oppositeEnd = self::CELL_RIGHT;
              break;
            default:
              $thisShipLen = 0;
          }

        } else {

          // We can't do anything with this cell.
          continue;

        } // end if (this cell value)

        // If we weren't given a direction (i.e. BOTTOM or RIGHT piece), skip.
        if ($thisShipLen === 0) {
          continue;
        }

        list($deltaCol, $deltaRow) = $direction;

        // Main loop where we traverse cells until stopped by edge, water, etc.
        while (true) {

          // Get distance from the original cell to the cell we're traversing.
          $rowOffset = ($thisShipLen - 1) * $deltaRow;
          $colOffset = ($thisShipLen - 1) * $deltaCol;
          
          // Calculate the row/column cell we're currently traversing into.
          $thisRow = $startRow + $rowOffset;
          $thisCol = $startCol + $colOffset;

          // Look ahead to NEXT cell.
          $nextRow = $thisRow + $rowOffset;
          $nextCol = $thisCol + $colOffset;

          // If we are at the longest length and can't go any further, OR if the
          // next cell is water or outside the grid boundary, stop traversing.
          if (
            $thisShipLen === $this->getMaxShipLength() || // counter maxed out?
            !$this->isValidCell($nextRow, $nextCol) ||    // invalid cell pos.?
             $this->isWater($nextRow, $nextCol)           // filled with water?
          ) {
            // No next cell. Set current cell to opposite end piece.
            $this->setCellValue($thisRow, $thisCol, $oppositeEnd);
            break; // we've found a ship!
          }
          
          // If the next cell is the OPPOSITE END piece, this ship's been found.
          if ($this->getCellValue($nextRow, $nextCol) === $oppositeEnd) {
            // Set current cell to a middle segment if not already a segment.
            if (!$this->isAnyShipSegment($thisRow, $thisCol)) {
              $this->setCellValue($thisRow, $thisCol, self::CELL_MIDDLE);
            }
            $thisShipLen++; // looking at next cell, so account for this
            // Mark final cell as END segment.
            $this->setCellValue($nextRow, $nextCol, $oppositeEnd);
            break; // we've found a ship!
          }

          // If next cell is blank, then we can't resolve this path as a ship.
          if ($this->isBlank($nextRow, $nextCol)) {
            // Set length to zero to denote lack of ship here.
            $thisShipLen = 0;
            break; // stop looking
          }

          // If next cell is MIDDLE or UNRESOLVED segment, mark MID and move on.
          if (
            $this->isMiddleEnd($nextRow, $nextCol) ||
            $this->isUnknownShipSegment($nextRow, $nextCol)
          ) {
            // Set current cell to a middle segment if not already a segment.
            if (!$this->isAnyShipSegment($thisRow, $thisCol)) {
              $this->setCellValue($thisRow, $thisCol, self::CELL_MIDDLE);
            }
            // Continue this iteration after if() block.
          }

          // Increment ship length for each valid segment found.
          $thisShipLen++;

        } // end while [main loop]
        
        if ($thisShipLen < 1) {
          // We couldn't finalize any ships, so can't mark any as found. Next!
          continue;
        }

        // Mark ship of this final length as found.
        $this->markShipLengthFound($thisShipLen, $startRow, $startCol);
        
      } // end for (column)

    } // end for (row)

  }

  /**
   * Look around the board for the number of possible configurations for ships
   * of the maximum possible length. If this number matches the number of ships
   * of this length yet to be found, place each ship and mark all remaining
   * ships of this length as found.
   *
   * @return void
   */
  private function tryToPlaceLargestShips() {
    $maxLength = $this->getMaxShipLength();

    if ($maxLength < 1) {
      // We've found all ships already.
      return;
    }
    
    $shipsOfLength = $this->shipLengths[$maxLength - 1];
    $possibleFits = [];
    $oHorizontal = self::ORIENT_HORIZONTAL;
    $oVertical   = self::ORIENT_VERTICAL;

    // Go through each valid starting cell and try both directions (down/right).
    for ($row = 0; $row < $this->size; $row++) {

      for ($col = 0; $col < $this->size; $col++) {

        // Must be a blank cell.
        $cellValue = $this->getCellValue($row, $col);
        if (
          !$this->isValueBlank($cellValue) &&
          !$this->isValueUnknownShipSegment($cellValue)
        ) {
          continue;
        }

        // Make sure we haven't already placed a ship here.
        if ($this->isCellInFoundShip($row, $col)) {
          continue;
        }

        // Determine whether we need to look in both directions or just one.
        if ($maxLength === 1) {

          // Only check one direction.
          if (
            $this->getConstraint($row, $oHorizontal) >= $shipsOfLength &&
            $this->getConstraint($row, $oVertical) >= $shipsOfLength &&
            $this->canShipFitHere($row, $col, $maxLength)
          ) {
            $possibleFits[] = [$row, $col, false]; // no orientation if single
          }

        }
        else {

          // Try both directions.
          if (
            $this->getConstraint($row, $oHorizontal) >= $shipsOfLength &&
            $this->canShipFitHere($row, $col, $maxLength, $oHorizontal)
          ) {
            $possibleFits[] = [$row, $col, $oHorizontal];
          }
          if (
            $this->getConstraint($col, $oVertical) >= $shipsOfLength &&
            $this->canShipFitHere($row, $col, $maxLength, $oVertical)
          ) {
            $possibleFits[] = [$row, $col, $oVertical];
          }

        }
      }
    }

    // If at the end we have the same number of fits as we have ships, fit them.
    if (count($possibleFits) === $shipsOfLength) {
      foreach ($possibleFits as $index => list($startRow, $startCol, $orientation)) {
        $this->placeShip($startRow, $startCol, $maxLength, $orientation);
      }
      // Mark EACH ship of this length as found (i.e. set its value to 0).
      for ($i = 0; $i < count($possibleFits); $i++) {
        $fit = $possibleFits[$i];
        $this->markShipLengthFound($maxLength, $fit[0], $fit[1]);
      }
    }
  }

  /**
   * Check to see if a ship of a given length will fit in the grid, starting at
   * a cell at the given row and column and moving in the direction matching the
   * given orientation. Starting at the first cell, count each cell that is
   * either blank or unresolved. If we reach the end of the count, then the ship
   * can fit in the cell. If we break free, the ship won't fit.
   *
   * @param int $startRow Row number we're checking
   * @param int $startCol Column number we're checking
   * @param int $length Length of ship we're trying to fit
   * @param int $orientation Direction in which we are looking
   * @return boolean Whether the ship can fit here or not
   */
  private function canShipFitHere($startRow, $startCol, $length, $orientation = false) {
    // Must be a valid cell to begin with.
    if (!$this->isValidCell($startRow, $startCol)) return false;

    $oHorizontal = self::ORIENT_HORIZONTAL;
    $oVertical   = self::ORIENT_VERTICAL;

    // If we're trying to place a single ship, just check cell & neighbors.
    if ($length === 1) {
      // Orientation doesn't matter, so we ignore it. Check neighboring cells.
      // If neighbor is neither water, blank, nor out of bounds, ship won't fit.
      $directions = array_merge(
        self::DIRECTIONS_CARDINAL,
        self::DIRECTIONS_DIAGONAL
      );
      foreach ($directions as $direction) {
        list($deltaRow, $deltaCol) = $direction;
        $newRow = $startRow + $deltaRow;
        $newCol = $startCol + $deltaCol;
        if (
          $this->isValidCell($newRow, $newCol) &&
          !$this->isWater($newRow, $newCol) &&
          !$this->isBlank($newRow, $newCol)
        ) {
          // Cell is valid and is neither water nor blank, so ship won't fit.
          return false;
        }
      }

      // We've checked all 8 directions and haven't hit issues; ship will fit!
      return true;
    }

    // Otherwise, we're trying to place a ship that takes up multiple cells.
    
    // As long as we can place segment in this cell (allowing end pieces where
    // appropriate), keep incrementing our ship length counter.
    $isHorizontal = $orientation === $oHorizontal;
    $rowMult = $isHorizontal ? 0 : 1;
    $colMult = $isHorizontal ? 1 : 0;

    // Check each cell along the path.
    for ($i = 1; $i <= $length; $i++) {
      $shipLength = $i; // keep track
      $thisRow = $startRow + ($rowMult * $shipLength);
      $thisCol = $startCol + ($colMult * $shipLength);

      if (!$this->canPlaceSegmentInCell($thisRow, $thisCol, (
        $shipLength === $length - 1 || $shipLength === 0
      ), $orientation)) {
        // Found a cell in which we can't place a segment.
        return false;
      }
      // Compare against perpendicular constraint to make sure this is valid.
      if (!$this->canPlaceSegmentInCell($thisRow, $thisCol, (
        $shipLength === $length - 1 || $shipLength === 0
      ), $orientation) === ($oHorizontal ? $oVertical : $oHorizontal)) {
        return false;
      }
    }
    
    // Now we must check to see if placing ship here would violate a constraint.
    $startPos = $isHorizontal ? $startRow : $startCol;

    // Count the segments in this row/col OUTSIDE our proposed ship placement.
    // $numSegments = $this->countSegmentsInRowCol($startCol, $orientation);
    $numSegments = 0;
    for ($i = 0; $i < $this->size; $i++) {
      if ($i >= $startPos && $i < ($startPos + $length)) continue;
      $numSegments++;
    }
    
    $constraint = $this->getConstraint($startPos, $orientation);
    if ($numSegments > $constraint) {
      // There would be too many segments in this row/col, so ship will not fit.
      return false;
    }

    // This ship will fit only if we were able to look through all needed cells.
    return true;
  }

  private function getMaxShipLength() {
    for ($i = count($this->shipLengths); $i > 0; $i--) {
      // $i is the iteration counter (i.e. length of ship). Index is $i - 1.
      if ($this->shipLengths[$i - 1] > 0) {
        // Return this length.
        return $i;
      }
    }
    return 0;
  }

  private function getNumShipsLeftOfLength($length) {
    return $this->shipLengths[$length - 1] ?: 0;
  }

  private function markShipLengthFound($length, $startRow, $startCol) {
    // Make sure this is a valid length.
    if ($length < 1 || $length > $this->getMaxShipLength()) return;
    // Decrement array index (length - 1) by 1.
    $this->shipLengths[$length - 1]--;
    // Store this ship and its starting coordinates.
    $this->shipsFound[$length][] = $startRow . '/' . $startCol;
  }

  private function isValidConstraint($rowOrCol = 0, $orientation) {
    return (
        $orientation === self::ORIENT_HORIZONTAL ||
        $orientation === self::ORIENT_VERTICAL
      ) &&
      is_numeric($rowOrCol) &&
      $rowOrCol >= 0 &&
      $rowOrCol < $this->size;
  }

  private function countSegmentsInRowCol($rowOrCol = 0, $orientation) {
    if (!$this->isValidConstraint($rowOrCol, $orientation)) {
      return 0; // nothing to count
    }

    // Determine which constraints to pull from, and which value to read.
    $isHorizontal = $orientation === self::ORIENT_HORIZONTAL;
    $constraint = $this->constraints[$isHorizontal ? 'row' : 'col'][$rowOrCol];
    
    // Begin counting each cell that has a ship segment of some kind.
    $numFilledCells = 0;
    for ($i = 0; $i < $this->size; $i++) {
      $thisRow = $rowOrCol + ($isHorizontal ?  0 : $i);
      $thisCol = $rowOrCol + ($isHorizontal ? $i :  0);
      // This has a ship segment; increment our counter.
      if ($this->isAnyShipSegment($thisRow, $thisCol)) {
        $numFilledCells++;
      }
    }
    
    // We now have our count.
    return $numFilledCells;
  }

  private function getConstraint($rowOrCol, $orientation) {
    if (!$this->isValidConstraint($rowOrCol, $orientation)) {
      return 0; // nothing to read
    }

    $isHorizontal = $orientation === self::ORIENT_HORIZONTAL;
    
    // Return the `$rowOrCol`th element in the row/col constraints array.
    return $this->constraints[$isHorizontal ? 'row' : 'col'][$rowOrCol];
  }

  /**
   * Place a ship of given length at the cell in the given row and column, in
   * the given direction.
   *
   * @param int $startRow Row number at which to start
   * @param int $startCol Column number at which to start
   * @param int $length Length of ship to place
   * @param int $orientation Direction in which to place ship
   * @return void
   */
  private function placeShip($startRow, $startCol, $length, $orientation) {
    // If we're placing a single ship, we need only set one cell's value.
    if ($length === 1) {
      $this->setCellValue($startRow, $startCol, self::CELL_SINGLE);
      return;
    }
    
    // Otherwise, determine the start and end ship segment types.
    $startValue = $orientation === self::ORIENT_HORIZONTAL
      ? self::CELL_LEFT  : self::CELL_TOP;
    $endValue   = $orientation === self::ORIENT_HORIZONTAL
      ? self::CELL_RIGHT : self::CELL_BOTTOM;
    
    // Mark all cells in the ship's path as middle or end pieces.
    for ($i = 0; $i < $length; $i++) {
      $newRow = $startRow + ($orientation === self::ORIENT_VERTICAL ? $i :  0);
      $newCol = $startCol + ($orientation === self::ORIENT_VERTICAL ? 0  : $i);

      switch ($i) {
        case 0:
          // First cell, so use the start segment.
          $this->setCellValue($newRow, $newCol, $startValue);
          break;
        case $length - 1:
          // Last cell, so use the end segment.
          $this->setCellValue($newRow, $newCol, $endValue);
          break;
        default:
          // Anything else gets a middle segment.
          $this->setCellValue($newRow, $newCol, self::CELL_MIDDLE);
      }
    }
  }

  /**
   * HELPER METHODS
   */

  private function isCellInFoundShip($row, $col) {
    // Check each element in $this->shipsFound[] (key=length) for row & column.
    foreach ($this->shipsFound as $length => $shipsFound) {
      if (in_array($row . '/' . $col, $shipsFound)) {
        return true;
      }
    }
    // If we're here, then the cell wasn't found in any completed ships.
    return false;
  }

  private function getCellValueInDir($row, $col, $direction) {
    if (!is_array($direction) || count($direction) !== 2) return false;
    // Extract X and Y delta values from direction variable.
    list($deltaCol, $deltaRow) = $direction;
    // Apply offset when retrieving value.
    return $this->getCellValue($row + $deltaRow, $col + $deltaCol);
  }

   /**
    * Determine if we are allowed to place any kind of ship segment in the cell
    * at the given row and column. In other words, is this cell either blank, a
    * middle piece, or unresolved? If so, then we can place a ship segment here.
    *
    * @param int $row Row number we're looking at
    * @param int $col Column number we're looking at
    * @param boolean $allowEndpiece Whether to allow endpieces in this cell
    * @param int|boolean $orientation Direction in which this segment's ship is
    *   being placed (default is false, which means none was passed; ignore it)
    * @return boolean Whether a ship segment can be placed here
    */
  private function canPlaceSegmentInCell(
    $row = 0,
    $col = 0,
    $allowEndpiece = false,
    $orientation = false
  ) {
    // If this isn't a valid cell, we can't place a segment here, now can we?
    if (!$this->isValidCell($row, $col)) return false;
    $cellValue = $this->getCellValue($row, $col);
    if ($this->isValueWater($cellValue)) {
      return false;
    }
    // Cell must be blank or unresolved, or (if allowed) middle or end piece.
    $allowedValues = [
      self::CELL_BLANK,
      self::CELL_UNKNOWN,
    ];
    if ($allowEndpiece) {
      // Endpieces are allowed.
      if ($orientation === false || $orientation === self::ORIENT_HORIZONTAL) {
        // If we're placing across (or ignoring), include left/right as allowed.
        $allowedValues[] = self::CELL_LEFT;
        $allowedValues[] = self::CELL_RIGHT;
      }
      if ($orientation === false || $orientation === self::ORIENT_VERTICAL) {
        // if we're placing downward (or ignoring), include up/down as allowed.
        $allowedValues[] = self::CELL_TOP;
        $allowedValues[] = self::CELL_BOTTOM;
      }
    } else {
      // Endpieces not allowed, so cell can be a middle piece.
      $allowedValues[] = self::CELL_MIDDLE;
    }

    // Next we check diagonal neighbors, which cannot already have a segment.
    $directionsToCheck = self::DIRECTIONS_DIAGONAL;

    // If orientation was passed, include the cardinal cells as well.
    if ($orientation !== false) {
      // If going horizontal, cells above and below cannot be ship segments.
      if ($orientation === self::ORIENT_HORIZONTAL) {
        $directionsToCheck[] = self::DIRECTION_UP;
        $directionsToCheck[] = self::DIRECTION_DOWN;
      }
      // If going vertical, cells to left and right cannot be ship segments.
      if ($orientation === self::ORIENT_VERTICAL) {
        $directionsToCheck[] = self::DIRECTION_LEFT;
        $directionsToCheck[] = self::DIRECTION_RIGHT;
      }
    }
    
    // Check cells in question for segments, and fail if any are found.
    foreach ($directionsToCheck as $direction) {
      // Split directions into row and column differences.
      list($deltaCol, $deltaRow) = $direction; // stored as X/Y coordinates
      $nextRow = $row + $deltaRow;
      $nextCol = $col + $deltaCol;
      
      // If this cell is filled with segment, this placement isn't allowed.
      if ($this->isAnyShipSegment($nextRow, $nextCol)) {
        return false;
      }

      // Additional check for invalid adjacent placements.
      $adjacents = self::DIRECTIONS_CARDINAL;
      foreach ($adjacents as $direction) {
        list($deltaCol, $deltaRow) = $direction;
        $adjRow = $row + $deltaRow;
        $adjCol = $row + $deltaCol;
        $adjValue = $this->getCellValue($adjRow, $adjCol);
        if ($adjValue === false) continue;

        // Check for invalid adjacent placements.
        if ($this->isValueKnownShipSegment($adjValue)) {
          if ($cellValue === self::CELL_SINGLE || $adjValue === self::CELL_SINGLE) {
            // A single ship segment cannot be next to any other segment.
            return false;
          }
          if ($this->isValueAnyEnd($cellValue) || $this->isValueAnyEnd($adjValue)) {
            // Allow opposite adjacent endpieces if they form valid 2-cell ship.
            if (!(
              ($cellValue === self::CELL_TOP && $adjValue === self::CELL_BOTTOM) ||
              ($cellValue === self::CELL_BOTTOM && $adjValue === self::CELL_TOP) ||
              ($cellValue === self::CELL_LEFT && $adjValue === self::CELL_RIGHT) ||
              ($cellValue === self::CELL_RIGHT && $adjValue === self::CELL_LEFT)
            )) {
              return false;
            }
          }
        }
      }
    }

    // Finally, as long as value is in allowed list, we're good to go.
    return in_array($cellValue, $allowedValues);
  }
   
  /**
   * Determine if the cell at a given row and column coordinate is any ship seg-
   * ment, known or unknown.
   *
   * @param int $row Row number to check
   * @param int $col Column number to check
   * @return boolean Whether cell at this row/col is known/unknown ship segment
   */
  private function isAnyShipSegment($row, $col) {
    return $this->isKnownShipSegment($row, $col)
        || $this->isUnknownShipSegment($row, $col);
  }

  /**
   * Determine if a given value is any ship segment, known or unknown.
   *
   * @param int $value Value to check
   * @return boolean Whether this value is a known/unknown ship segment
   */
  private function isValueAnyShipSegment($value) {
    return $this->isValueKnownShipSegment($value)
        || $this->isValueUnknownShipSegment($value);
  }

  /**
   * Determine if the cell at a given row and column coordinate is a known ship
   * segment (i.e. neither water, blank, nor unresolved segment).
   *
   * @param int $row Row number to check
   * @param int $col Column number to check
   * @return boolean Whether cell at this row/column is a known ship segment
   */
  private function isKnownShipSegment($row, $col) {
    if (!$this->isValidCell($row, $col)) return false;
    $knownSegments = [
      self::CELL_SINGLE,
      self::CELL_LEFT,
      self::CELL_MIDDLE,
      self::CELL_RIGHT,
      self::CELL_TOP,
      self::CELL_BOTTOM,
    ];
    return in_array($this->getCellValue($row, $col), $knownSegments);
  }

  /**
   * Determine if a given value is a known ship segment.
   *
   * @param int $value Value to check
   * @return boolean Whether this value is a known ship segment
   */
  private function isValueKnownShipSegment($value) {
    $knownSegments = [
      self::CELL_SINGLE,
      self::CELL_LEFT,
      self::CELL_MIDDLE,
      self::CELL_RIGHT,
      self::CELL_TOP,
      self::CELL_BOTTOM,
    ];
    return in_array($value, $knownSegments);
  }

  /**
   * Determine if the cell at a given row and column coordinate is an unknown
   * ship segment (i.e. we known there's a ship segment there, just not which
   * type it is).
   *
   * @param int $row Row number to check
   * @param int $col Column number to check
   * @return boolean Whether cell at this row/column is an unknown ship segment
   */
  private function isUnknownShipSegment($row, $col) {
    return $this->isValidCell($row, $col) &&
           $this->getCellValue($row, $col) === self::CELL_UNKNOWN;
  }

  /**
   * Determine if a given value is an unknown ship segment.
   *
   * @param int $value Value to check
   * @return boolean Whether this value is an unknown (unresolved) ship segment
   */
  private function isValueUnknownShipSegment($value) {
    return $value === self::CELL_UNKNOWN;
  }

  private function isAnyEnd($row, $col) {
    return in_array($this->getCellValue($row, $col), self::CELLS_END);
  }

  private function isValueAnyEnd($value) {
    return in_array($value, self::CELLS_END);
  }

  private function isWater($row, $col) {
    return $this->getCellValue($row, $col) === self::CELL_WATER;
  }

  private function isValueWater($value) {
    return $value === self::CELL_WATER;
  }

  private function isBlank($row, $col) {
    return $this->getCellValue($row, $col) === self::CELL_BLANK;
  }

  private function isValueBlank($value) {
    return $value === self::CELL_BLANK;
  }

  private function isMiddleEnd($row, $col) {
    return $this->getCellValue($row, $col) === self::CELL_MIDDLE;
  }

  private function isValueMiddleEnd($value) {
    return $value === self::CELL_MIDDLE;
  }

  private function getOppositeDirection($direction) {
    switch ($direction) {
      case self::DIRECTION_UP:
        return self::DIRECTION_DOWN;
      case self::DIRECTION_DOWN:
        return self::DIRECTION_UP;
      case self::DIRECTION_LEFT:
        return self::DIRECTION_RIGHT;
      case self::DIRECTION_RIGHT:
        return self::DIRECTION_LEFT;
      default:
        return false;
    }
  }

  private function getPerpendicularDirections($direction) {
    switch ($direction) {
      case self::DIRECTION_UP:
      case self::DIRECTION_DOWN:
        return [
          self::DIRECTION_LEFT,
          self::DIRECTION_RIGHT,
        ];
      case self::DIRECTION_LEFT:
      case self::DIRECTION_RIGHT:
        return [
          self::DIRECTION_UP,
          self::DIRECTION_DOWN,
        ];
      default:
        return false;
    }
  }

  private function getEndPieceFacingToward($direction) {
    switch ($direction) {
      case self::DIRECTION_UP:
        return self::CELL_TOP;
      case self::DIRECTION_DOWN:
        return self::CELL_BOTTOM;
      case self::DIRECTION_LEFT:
        return self::CELL_LEFT;
      case self::DIRECTION_RIGHT:
        return self::CELL_RIGHT;
      default:
        return false;
    }
  }

  private function getEndPieceFacingAwayFrom($direction) {
    switch ($direction) {
      case self::DIRECTION_UP:
        return self::CELL_BOTTOM;
      case self::DIRECTION_DOWN:
        return self::CELL_TOP;
      case self::DIRECTION_LEFT:
        return self::CELL_RIGHT;
      case self::DIRECTION_RIGHT:
        return self::CELL_LEFT;
      default:
        return false;
    }
  }

  /**
   * Mark the cells in each of the given directions as water.
   *
   * @param int $row Row number of cell
   * @param int $col Column number of cell
   * @param array $directions Each of the directions in which we look
   * @return void
   */
  private function markCellsInDirectionsAsWater($row, $col, $directions = []) {
    if (empty($directions) || !$directions) return;
    // Iterate through each direction passed in.
    foreach ($directions as $direction) {
      // Extract the values to add to the X and Y coordinates (i.e. row & col).
      $deltaCol = $direction[0]; // x coordinate
      $deltaRow = $direction[1]; // y coordinate
      // Calculate new row and column values.
      $newCol = $col + $deltaCol;
      $newRow = $row + $deltaRow;
      // Mark this cell as water.
      $this->markCellIfBlank($newRow, $newCol, self::CELL_WATER);
    }
  }

  /**
   * Assign a value to a given cell, if blank.
   *
   * @param int $row Row number of cell
   * @param int $col Column number of cell
   * @param int $value Value to assign to cell
   * @return void
   */
  private function markCellIfBlank($row, $col, $value) {
    // Cell must exist.
    if (!$this->isValidCell($row, $col)) return;
    // Cell must also be blank.
    if ($this->getCellValue($row, $col) !== self::CELL_BLANK) return;
    // Assign the value.
    $this->setCellValue($row, $col, $value);
  }

  private function setCellValue($row, $col, $value) {
    $this->grid[$row][$col] = $value;
  }

  private function getCellValue($row, $col) {
    return $this->isValidCell($row, $col) ? $this->grid[$row][$col] : false;
  }

  private function markConstraintSatisfied($index, $orientation) {
    switch ($orientation) {
      case self::ORIENT_HORIZONTAL:
        $this->satisfied['rows'][] = $index;
        break;
      case self::ORIENT_VERTICAL:
        $this->satisfied['cols'][] = $index;
        break;
      default;
        return;
    }
  }

  private function isValidCell($row, $col) {
    return $row >= 0 && $row < $this->size
        && $col >= 0 && $col < $this->size;
  }

  private function isValidCellInDir($row, $col, $direction) {
    if (!is_array($direction) || count($direction) !== 2) return false;
    // Extract X and Y delta values from direction variable.
    list($deltaCol, $deltaRow) = $direction;
    // Apply offset when retrieving value.
    $newRow = $row + $deltaRow;
    $newCol = $col + $deltaCol;
    return $this->isValidCell($newRow, $newCol);
  }

  private function isConstraintSatisfied($index, $orientation) {
    return (
      $orientation === self::ORIENT_VERTICAL &&
      in_array($index, $this->satisfied['cols'])
    ) || (
      $orientation === self::ORIENT_HORIZONTAL &&
      in_array($index, $this->satisfied['rows'])
    );
  }
  
}
