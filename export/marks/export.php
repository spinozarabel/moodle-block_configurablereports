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
    $matrix[0]["letter_grade"] = "letter_grade";

    // now loop through the data contained in matrix array to determine the letter grade dependenent on subject.
    // while we are at it, let's replace the subject string with the required marks card string
    // for example Math Grade 8B will become Mathematics, etc.
    foreach ($matrix as $row_index => $row)
    {
      // get the subject name from key subject. Look up the mapping
      $subject = get_name_of_subject($matrix[$row_index]["subject"]);

      $markspercentage = $matrix[$row_index]["markspercentage"];

      // look up the dynamic letter_grade and put this value in the matrix data for export
      $matrix[$row_index]["letter_grade"] = get_lettergrade($subject, $markspercentage);
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

function get_lettergrade($subject, $markspercentage)
{
  switch ($subject)
  {
    case ("English"):
      switch (true)
        {
          case ($markspercentage <= 100 && $markspercentage >= 90):
            return "A";
          break;

          case ($markspercentage < 90 && $markspercentage >= 80):
            return "B";
          break;

          case ($markspercentage < 80 && $markspercentage >= 70):
            return "C";
          break;

          default:
            return "F";
          break;
        }
      break;

      case ("2nd Language - Kannada"):
        switch (true)
          {
            case ($markspercentage <= 100 && $markspercentage >= 90):
              return "A";
            break;

            case ($markspercentage < 90 && $markspercentage >= 80):
              return "B";
            break;

            case ($markspercentage < 80 && $markspercentage >= 70):
              return "C";
            break;

            default:
              return "F";
            break;
          }
        break;

    default:
      switch (true)
        {
          case ($markspercentage <= 100 && $markspercentage >= 90):
            return "A";
          break;

          case ($markspercentage < 90 && $markspercentage >= 80):
            return "B";
          break;

          case ($markspercentage < 80 && $markspercentage >= 70):
            return "C";
          break;

          default:
            return "F";
          break;
        }
    break;
  }
}

function get_name_of_subject($subject_description)
{
  switch (true)
  {
    case (stripos($subject_description, 'Math') !== false):
      return "Mathematics";
    break;

    case (stripos($subject_description, 'English') !== false):
      return "English";
    break;

    case (stripos($subject_description, 'Kannada') !== false):
      return "2nd Language - Kannada";
    break;

    case (stripos($subject_description, 'Physics') !== false):
      return "Physics";
    break;

    case (stripos($subject_description, 'Chemistry') !== false):
      return "Chemistry";
    break;

    case (stripos($subject_description, 'Biology') !== false):
      return "Biology";
    break;

    case (stripos($subject_description, 'computer') !== false):
      return "Computer Studies";
    break;

    case (stripos($subject_description, 'History') !== false):
      return "History";
    break;

    case (stripos($subject_description, 'Geography') !== false):
      return "Geography";
    break;

    case (stripos($subject_description, 'Economics') !== false):
      return "Economics";
    break;

    case (stripos($subject_description, 'Sociology') !== false):
      return "Sociology";
      break;

    case (stripos($subject_description, 'Psychology') !== false):
      return "Psychology";
    break;

    default:
      return $subject_description;
    break;
  }
}
