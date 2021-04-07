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

function export_report($report)
{
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
    // define default grade array from site-wide letter ranges from SriToni
    $default_letters_array = [
                                ["A",   100,    93],
                                ["A-",  92.99,  90],
                                ["B+",  89.99,  87],
                                ["B",   86.99,  83],
                                ["B-",  82.99,  80],
                                ["C+",  79.99,  77],
                                ["C",   76.99,  73],
                                ["C-",  72.99,  70],
                                ["D+",  69.99,  67],
                                ["D",   66.99,  60],
                                ["F",   59.99,   0],
                             ];

    //1st we read in the google csv published file containing the subjects sort order
    $googlesheeturl  = get_config('block_configurable_reports', 'googlesheeturl');
  	if (empty($googlesheeturl))
      {
          echo nl2br("Empty config setting for Google Published CSV file URL, please set in config: "  . "\n");
  		    error_log("Empty config setting for Google Published CSV file URL in plugin configurable_reports, please set in config");
          return;
      }
    // this lists the subjects columnwise, with header being classsection.
    // Classsection is to be contained in every subject course name to be included in marks report
    $subjects_sortorder = csv_to_associative_array($googlesheeturl);




    // 1st we add the new column header to the 0th header row
    $matrix[0][8] = "letter_grade";
    $matrix[0][9] = "sort_order";

    $subject_letters_array_courseid = [];

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

      $subject_courseid     = $matrix[$row_index][7];

      $subject_description  = $matrix[$row_index][5];

      $markspercentage      = $matrix[$row_index][6];

      $class_section        = $matrix[$row_index][4];

      if (empty($subject_letters_array_courseid[$subject_courseid]))
      {
        // we have not yet attempted to get possibly overridden letter grades for this $subject_courseid
        // so get the array if it exists. If not ause the sitewide default deletters array
        $temp_array = get_subject_letter_array($subject_courseid);

        if (!empty($temp_array))
        {
          $subject_letters_array_courseid[$subject_courseid] = $temp_array;
        }
        else
        {
          $subject_letters_array_courseid[$subject_courseid] = $default_letters_array;
        }


      }

      // get the subject name and letter and order as it must appear in marks card
      $subject_letter = get_subjectname_letter_order($subject_description, $markspercentage,
                                                     $class_section, $subjects_sortorder,
                                                     $subject_courseid, $subject_letters_array_courseid);



      // look up the dynamic letter_grade and put this value in the matrix data for export
      $matrix[$row_index][5] = $subject_letter[0]; // subject name as in marks card based on course description mapping
      $matrix[$row_index][8] = $subject_letter[1]; // dynamic letter grade, new column added
      $matrix[$row_index][9] = $subject_letter[2]; // sort order for listing. New column added
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
**  @param string:$class_section - this is the class and section, for example: 8B
**  @param array:$subjects_sortorder derived from google sheet published as CSV
**  @param array:$subject_letters_array_courseid is the letter_range_array indexed by courseid.
**  @return array subject description as desired on marks card and letter grade and sort order for marks card ex: ["English", "A", 3]
*/
function get_subjectname_letter_order($subject_description, $markspercentage,
                                      $class_section, $subjects_sortorder,
                                      $subject_courseid, $subject_letters_array_courseid):array
{

  // get the overridden/default letter ranges for this course based on course id.
  $a   = $subject_letters_array_courseid[$subject_courseid];

  // based on the class cection, extract the column of subjects' officila list in their desired listing order
  $subjects_official_list = array_column($subjects_sortorder, $class_section);

  // this row pertains to which subject? try to match to each subject and see the match
  switch (true)
  {
    case (stripos($subject_description, 'English') !== false && stripos($subject_description, 'Literature') === false):
        // subject description contains the word English but not Literature so this is the English subject course
        // we need to determine the subject dependent letter grade if not alreay done and also its sort order

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'English') !== false && stripos($subject, 'Literature') === false)
          {
            // we found our subject: English, not literature in english
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }

        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];


    // Literature in English
    case (stripos($subject_description, 'Literature') !== false  && stripos($subject_description, 'English') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'Literature') !== false && stripos($subject, 'English') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }

        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];

    // Hindi
    case (stripos($subject_description, 'Hindi') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'Hindi') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }

        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];

    // Kannada
    case (stripos($subject_description, 'Kannada') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'Kannada') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }

        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];

    // French
    case (stripos($subject_description, 'French') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'French') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }
        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];

    // History
    case (stripos($subject_description, 'History') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'History') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }
        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];


    // Mathematics
    case (stripos($subject_description, 'Math') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'Math') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }
        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];

    // Physics
    case (stripos($subject_description, 'Physics') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'Physics') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }
        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];


    // Biology
    case (stripos($subject_description, 'Biology') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'Biology') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }
        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];


    // Chemistry
    case (stripos($subject_description, 'Chemistry') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'Chemistry') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }
        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];


    // Computer Science / Computer Studies
    case (stripos($subject_description, 'Computer') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'Computer') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }
        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];


    // Economics
    case (stripos($subject_description, 'Econom') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'Econom') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }
        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];


    // Sociology
    case (stripos($subject_description, 'Sociology') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'Sociology') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }
        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];


    // Psychology
    case (stripos($subject_description, 'Psychology') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'Psychology') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }
        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];


    // Geography
    case (stripos($subject_description, 'Geography') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'Geography') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }
        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];


    // Business
    case (stripos($subject_description, 'Business') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'Business') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }
        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];


    // Art and Design
    case (stripos($subject_description, 'Art and Design') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'Art and Design') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }
        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];


    // Music
    case (stripos($subject_description, 'Music') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'Music') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }
        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];



    // Music
    case (stripos($subject_description, 'Environment') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'Environment') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }
        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];


        // Physical Education
        case (stripos($subject_description, 'Physical Education') !== false):

            foreach ($subjects_official_list as $key => $subject)
            {
              if (stripos($subject, 'Physical Education') !== false)
              {
                // we found our element.
                $subject_listing  = $subject;
                $sort_order       = $key;
                // get out of loop
                break;
              }
            }
            return [$subject_listing, get_letter($markspercentage, $a), $sort_order];



    // Accounts
    case (stripos($subject_description, 'Account') !== false):

        foreach ($subjects_official_list as $key => $subject)
        {
          if (stripos($subject, 'Account') !== false)
          {
            // we found our element.
            $subject_listing  = $subject;
            $sort_order       = $key;
            // get out of loop
            break;
          }
        }
        return [$subject_listing, get_letter($markspercentage, $a), $sort_order];





    default:
        // for all unknown subject mappings. Just return the original with a fixed grade letter and list at end
        return [$subject_description, "Z", 100];

  }     // switch end


}       // function end


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
      if ($markspercentage <= $range[1]  && $markspercentage >= $range[2])
      {
          return $range[0];
      }

  }
  // uncaught case so return exception letter grade
  return "Z";
}


/**
 * This routine is attributed to https://github.com/rap2hpoutre/csv-to-associative-array
  *
 * The items in the 1st line (column headers) become the fields of the array
 * each line of the CSV file is parsed into a sub-array using these fields
 * The 1st index of the array is an integer pointing to these sub arrays
 * The 1st row of the CSV file is ignored and index 0 points to 2nd line of CSV file
 * This is the example data:
 *
 * grade1,grade2,grade3
 * grade2,grade3,grade4
 * 10000,20000,30000
 *
 * This is the associative array
 * Array
 *(
 *  [0] => Array
 *		(
 *			[grade1] => grade2
 *          [grade2] => grade3
 *			[grade3] => grade4
 *		)
 *  [1] => Array
 *      (
 *          [grade1] => 10000
 *          [grade2] => 20000
 *          [grade3] => 30000
 *      )
 * )
 */
function csv_to_associative_array($file, $delimiter = ',', $enclosure = '"')
{
    if (($handle = fopen($file, "r")) !== false)
    {
        $headers = fgetcsv($handle, 0, $delimiter, $enclosure);
        $lines = [];
        while (($data = fgetcsv($handle, 0, $delimiter, $enclosure)) !== false)
        {
            $current = [];
            $i = 0;
            foreach ($headers as $header)
            {
                $current[$header] = $data[$i++];
            }
            $lines[] = $current;
        }
        fclose($handle);
        return $lines;
	}
}      // function



/**
**    @param string:$subject_courseid is the course id of the subject which belongs to this row for this student
**    @return array:$letter_range_array which consists of record id, followed by letter followed by lower boundary
*/
function get_subject_letter_array($subject_courseid):array
{
  global $DB, $CFG;

  $letter_range_array = [];

  $sql = "SELECT gl.*
          FROM {grade_letters} gl
            JOIN {context} ctx ON ctx.id = gl.contextid
            JOIN {course} c ON c.id = ctx.instanceid
                     WHERE c.id = {$subject_courseid}";
  if ($letter_records = $DB->get_records_sql($sql))
  {
    // these overridden grade letter records do exist. They list F at index 0
    foreach ($letter_records AS $index => $letter_record)
    {

        $letter_range_array[$index] = [$letter_record->letter, "upper bound", $letter_record->lowerboundary];

    }


    // fill in the upper bound place holder
    foreach ($letter_records AS $index => $letter_record)
    {
      if (empty($index-1))
      {
        // this is the highest letter grade and so its upper bound is 100
        $letter_range_array[$index][1] = 100;
      }
      else
      {
        // get the upper range from the previous grade lower range and subtract 0.01 to prevent overlap
        $letter_range_array[$index][1] = floatval($letter_range_array[$index-1][2]) - 0.01;
      }
    }

    error_log("overridden Letter range array for course id: $subject_courseid");
    error_log(print_r($letter_range_array, true));

    unset($letter_records);

    return $letter_range_array;
  }

  //error_log("using default letter array for courseid: $subject_courseid");
  return [];
}
