<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Configurable Reports
 * A Moodle block for creating customizable reports
 * @package blocks
 * @author: Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @date: 2009
 */

function export_report($report) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/csvlib.class.php');

    $table = $report->table;
    $matrix = array();
    $filename = 'report';

    if (!empty($table->head)) {
        $countcols = count($table->head);
        $keys = array_keys($table->head);
        $lastkey = end($keys);
        foreach ($table->head as $key => $heading) {
            $matrix[0][$key] = str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($heading))));
        }
    }

    if (!empty($table->data)) {
        foreach ($table->data as $rkey => $row) {
            foreach ($row as $key => $item) {
                $matrix[$rkey + 1][$key] = str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($item))));
            }
        }
    }
    //---Start of additional code to process matrix array for marks CSV export->
    // 1st we add the new column header to the 0th header row
    $matrix[0][7] = "letter_grade";

    // now loop through the data contained in matrix array to determine the letter grade dependenent on subject.
    // while we are at it, let's replace the subject string with the required marks card string
    // for example Math Grade 8B will become Mathematics, etc.

    foreach ($matrix as $row_index => $row)
    {
      if ($row_index == 0)
      {
        // skip this loop for header row0
        continue;
      }

      $subject_description  = $row[5];

      $markspercentage      = $row[6];

      // get the subject name and letter as it must appear in marks card
      $subject_letter = get_subjectandletter($subject_description, $markspercentage);

      error_log(print_r($subject_letter,true));


      // look up the dynamic letter_grade and put this value in the matrix data for export
      $row[5] = $subject_letter[0]; // subject name as in marks card based on course description mapping
      $row[7] = $subject_letter[1]; // letter grade based on subject and custom letter grade ranges
    }

    //---end of additional code to process matrix array for marks CSV export--->

    $csvexport = new csv_export_writer();
    $csvexport->set_filename($filename);

    foreach ($matrix as $ri => $col) {
        $csvexport->add_data($col);
    }
    $csvexport->download_file();
    exit;
}

/**
**  @param string:$subject_description - holds the full subject description derived from course title
**  @param integer:$markspercentage - is the percentage marks for this siubject
**  @return array subject description as desired on marks card and letter grade for marks card ex: ["English", "A"]
*/
function get_subjectandletter($subject_description, $markspercentage):array
{
  switch (true)
  {
    case (stripos($subject_description, 'English') !== false):
        // subject description contains the word English. So return English, with the derived letter grade
        $a = [
              ["A", 100,  90],   // A
              ["B", 89,   80],   // B
              ["C", 79,   70],   // C
              ["D", 69,   60]    // D
            ];

        return ["English", get_letter($markspercentage, $a)];


    case (stripos($subject_description, 'Kannada') !== false):
        $a = [
              ["A", 100,  90],   // A
              ["B", 89,   80],   // B
              ["C", 79,   70],   // C
              ["D", 69,   60]    // D
            ];

        return ["2nd Language - Kannada", get_letter($markspercentage, $a)];


    case (stripos($subject_description, 'Math') !== false):
        $a = [
              ["A", 100,  90],   // A
              ["B", 89,   80],   // B
              ["C", 79,   70],   // C
              ["D", 69,   60]    // D
            ];

        return ["Mathematics", get_letter($markspercentage, $a)];


    case (stripos($subject_description, 'Physics') !== false):
        $a = [
              ["A", 100,  90],   // A
              ["B", 89,   80],   // B
              ["C", 79,   70],   // C
              ["D", 69,   60]    // D
            ];

        return ["Physics", get_letter($markspercentage, $a)];


    case (stripos($subject_description, 'Biology') !== false):
        $a = [
              ["A", 100,  90],   // A
              ["B", 89,   80],   // B
              ["C", 79,   70],   // C
              ["D", 69,   60]    // D
            ];

        return ["Biology", get_letter($markspercentage, $a)];


    case (stripos($subject_description, 'Chemistry') !== false):
        $a = [
              ["A", 100,  90],   // A
              ["B", 89,   80],   // B
              ["C", 79,   70],   // C
              ["D", 69,   60]    // D
            ];

        return ["Chemistry", get_letter($markspercentage, $a)];


    case (stripos($subject_description, 'Computer Science') !== false):
        $a = [
              ["A", 100,  90],   // A
              ["B", 89,   80],   // B
              ["C", 79,   70],   // C
              ["D", 69,   60]    // D
            ];

        return ["Computer Science", get_letter($markspercentage, $a)];


    case (stripos($subject_description, 'Computer Studies') !== false):
        $a = [
              ["A", 100,  90],   // A
              ["B", 89,   80],   // B
              ["C", 79,   70],   // C
              ["D", 69,   60]    // D
            ];

        return ["Computer Studies", get_letter($markspercentage, $a)];


    case (stripos($subject_description, 'Hindi') !== false):
        $a = [
              ["A", 100,  90],   // A
              ["B", 89,   80],   // B
              ["C", 79,   70],   // C
              ["D", 69,   60]    // D
            ];

        return ["Hindi", get_letter($markspercentage, $a)];


    case (stripos($subject_description, 'French') !== false):
        $a = [
              ["A", 100,  90],   // A
              ["B", 89,   80],   // B
              ["C", 79,   70],   // C
              ["D", 69,   60]    // D
            ];

        return ["French", get_letter($markspercentage, $a)];


    case (stripos($subject_description, 'Economics') !== false):
        $a = [
              ["A", 100,  90],   // A
              ["B", 89,   80],   // B
              ["C", 79,   70],   // C
              ["D", 69,   60]    // D
            ];

        return ["Economics", get_letter($markspercentage, $a)];


    case (stripos($subject_description, 'Sociology') !== false):
        $a = [
              ["A", 100,  90],   // A
              ["B", 89,   80],   // B
              ["C", 79,   70],   // C
              ["D", 69,   60]    // D
            ];

        return ["Sociology", get_letter($markspercentage, $a)];


    case (stripos($subject_description, 'Psychology') !== false):
        $a = [
              ["A", 100,  90],   // A
              ["B", 89,   80],   // B
              ["C", 79,   70],   // C
              ["D", 69,   60]    // D
            ];

        return ["Psychology", get_letter($markspercentage, $a)];

    case (stripos($subject_description, 'History') !== false):
        $a = [
              ["A", 100,  90],   // A
              ["B", 89,   80],   // B
              ["C", 79,   70],   // C
              ["D", 69,   60]    // D
            ];

        return ["History", get_letter($markspercentage, $a)];

    case (stripos($subject_description, 'Geography') !== false):
        $a = [
              ["A", 100,  90],   // A
              ["B", 89,   80],   // B
              ["C", 79,   70],   // C
              ["D", 69,   60]    // D
            ];

        return ["Geography", get_letter($markspercentage, $a)];


    default:
        // for all unknown subject mappings. Don't bother with letter grade derivation
        return [$subject_description, "Z"];

  }
}


/**
**  @param integer:$markspercentage
**  @param array:$a example:
**      $a = [
**            ["A", 100,  90],   // A
**            ["B", 89,   80],   // B
**            ["C", 79,   70],   // C
**            ["D", 69,   60]    // D
**          ];
*/
function get_letter($markspercentage, $a):string
{
  // llop through each range for the letter grade
  foreach ($a as $i => $range)
  {
      // assign this range letter grade if falls in this range
      if ($markspercentage <= $range[1] && $markspercentage >= $range[2])
      {
          return $range[0];
      }

  }
  // uncaught case so return exception letter grade
  return "Z";
}
