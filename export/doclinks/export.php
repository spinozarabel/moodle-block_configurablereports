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
    display_doclinks($record, $matrix);
    //--- end of additional code to process matrix array for documentlinks----->

    $csvexport = new csv_export_writer();
    $csvexport->set_filename($filename);

    foreach ($matrix as $ri => $col) {
        $csvexport->add_data($col);
    }
    $csvexport->download_file();
    exit;
}

/**
**  @param object:$record
**  @param array:$matrix
*/
function display_doclinks($record, $matrix)
{
  // define table and heading
  ?>
      <style>
      table {
      border-collapse: collapse;
      }
      th, td {
      border: 1px solid orange;
      padding: 10px;
      text-align: left;
      }
      </style>
      <table style="width:100%">
      <tr>
        <th>username</th>
        <th>fullname</th>
        <th>id</th>
        <th>idnumber</th>
        <th>docname</th>
        <th>documentlink</th>
      </tr>
  <?php

  array_walk($csv, function(&$a) use ($csv)
  {
    $a = array_combine($csv[0], $a);
  });
  array_shift($csv); # remove column header
// find number of entries extracted from CSV into array
  $csvcount = count($csv);

  foreach ($csv as $key => $row)
		{
      // this is the unique sritoni idnumber assigned by school
			$username 		= $row["username"];

      // this is the fullname of student
			$fullname 		= $row["fullname"];

      // this is the moodle id of user in user table
			$userid 		  = $row["id"];

      // this is the moodle id of user in user table
			$idnumber 		= $row["idnumber"];

      // this is the JSON encoded document links of this user
			$json_documentlinks = $row["json_documentlinks"];

      // decode json string into an associative array
      $doclinks_arr  = json_decode($json_documentlinks, true);

      foreach ($doclinks_arr as $docname => $doclink)
      {
        // print out multiple row for same user but with different doclinks info in the table
        ?>
                <tr>
                    <td><?php echo htmlspecialchars($username); ?></td>
                    <td><?php echo htmlspecialchars($fullname); ?></td>
                    <td><?php echo htmlspecialchars($userid); ?></td>
                    <td><?php echo htmlspecialchars($idnumber); ?></td>
                    <td><?php echo htmlspecialchars($docname); ?></td>
                    <td><?php echo htmlspecialchars($doclink); ?></td>
                </tr>
        <?php
      }
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
