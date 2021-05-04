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
 * Configurable Reports ver :36_ver4 report.class under sql added functionality is mostly new class methods added
 * This is a JSON encoded array for options to direct the report.
 * We Look for a column called options in sql data. USer changes the available options before running the report.
 * For example, select_all:1 selects all JSON rows, 0 unselects all. User can then manually check/uncheck any.
 * A Moodle block for creating customizable reports
 * @package blocks
 * @author: Juan leyva <http://www.twitter.com/jleyvadelgado> Madhu Avasarala modified for JSON records expansion
 * @date: 2009 2021 Madhu
 */

defined('BLOCK_CONFIGURABLE_REPORTS_MAX_RECORDS') || define('BLOCK_CONFIGURABLE_REPORTS_MAX_RECORDS', 5000);

class report_sql extends report_base {

    private $forExport = false;

    public function setForExport(bool $isForExport) {
        $this->forExport = $isForExport;
    }

    public function isForExport() {
        return $this->forExport;
    }

    public function init() {
        $this->components = array('customsql', 'filters', 'template', 'permissions', 'calcs', 'plot');
    }

    public function prepare_sql($sql) {
        global $DB, $USER, $CFG, $COURSE;

        // Enable debug mode from SQL query.
        $this->config->debug = (strpos($sql, '%%DEBUG%%') !== false) ? true : false;

        // Pass special custom undefined variable as filter.
        // Security warning !!! can be used for sql injection.
        // Use %%FILTER_VAR%% in your sql code with caution.
        $filtervar = optional_param('filter_var', '', PARAM_RAW);
        if (!empty($filtervar)) {
            $sql = str_replace('%%FILTER_VAR%%', $filtervar, $sql);
        }

        $sql = str_replace('%%USERID%%', $USER->id, $sql);
        $sql = str_replace('%%COURSEID%%', $COURSE->id, $sql);
        $sql = str_replace('%%CATEGORYID%%', $COURSE->category, $sql);

        // See http://en.wikipedia.org/wiki/Year_2038_problem.
        $sql = str_replace(array('%%STARTTIME%%', '%%ENDTIME%%'), array('0', '2145938400'), $sql);
        $sql = str_replace('%%WWWROOT%%', $CFG->wwwroot, $sql);
        $sql = preg_replace('/%{2}[^%]+%{2}/i', '', $sql);

        $sql = str_replace('?', '[[QUESTIONMARK]]', $sql);

        return $sql;
    }

    public function execute_query($sql, $limitnum = BLOCK_CONFIGURABLE_REPORTS_MAX_RECORDS) {
        global $remotedb, $DB, $CFG;

        $sql = preg_replace('/\bprefix_(?=\w+)/i', $CFG->prefix, $sql);

        $reportlimit = get_config('block_configurable_reports', 'reportlimit');
        if (empty($reportlimit) or $reportlimit == '0') {
                $reportlimit = BLOCK_CONFIGURABLE_REPORTS_MAX_RECORDS;
        }

        $starttime = microtime(true);

        if (preg_match('/\b(INSERT|INTO|CREATE)\b/i', $sql) && !empty($CFG->block_configurable_reports_enable_sql_execution)) {
            // Run special (dangerous) queries directly.
            $results = $remotedb->execute($sql);
        } else {
            $results = $remotedb->get_recordset_sql($sql, null, 0, $reportlimit);
        }

        // Update the execution time in the DB.
        $updaterecord = $DB->get_record('block_configurable_reports', array('id' => $this->config->id));
        $updaterecord->lastexecutiontime = round((microtime(true) - $starttime) * 1000);
        $this->config->lastexecutiontime = $updaterecord->lastexecutiontime;

        $DB->update_record('block_configurable_reports', $updaterecord);

        return $results;
    }

    public function create_report() {
        global $DB, $CFG;

        $select_all_rows = false;            // default for seleting all rows, needed in locallib print table

        $components = cr_unserialize($this->config->components);

        $filters = (isset($components['filters']['elements'])) ? $components['filters']['elements'] : array();
        $calcs = (isset($components['calcs']['elements'])) ? $components['calcs']['elements'] : array();

        $tablehead = array();
        $finalcalcs = array();
        $finaltable = array();
        $tablehead = array();

        $components = cr_unserialize($this->config->components);
        $config = (isset($components['customsql']['config'])) ? $components['customsql']['config'] : new \stdclass;
        $totalrecords = 0;

        $sql = '';
        if (isset($config->querysql)) {
            // Filters.
            $sql = $config->querysql;
            if (!empty($filters)) {
                foreach ($filters as $f) {
                    require_once($CFG->dirroot.'/blocks/configurable_reports/components/filters/'.$f['pluginname'].'/plugin.class.php');
                    $classname = 'plugin_'.$f['pluginname'];
                    $class = new $classname($this->config);
                    $sql = $class->execute($sql, $f['formdata']);
                }
            }

            $sql = $this->prepare_sql($sql);

            if ($rs = $this->execute_query($sql))
            {   // make sure records exist if not don't bother
                // we need to detect possible presence of a JSON column in the extracted SQL records.
                
                $json_options_obj   = $this->get_json_options_info($rs);
                $json_col_index     = $json_options_obj->json_col_index;
                $options_col_index  = $json_options_obj->options_col_index;

                // finally we come to section where we build the table array. (The HTML print out done in locallib)
                // we add additional columns rows due to JSON if json data exists i.e $json_col_index != null
                // we also add additional rows, 1 row for each JSON record, for same user.
                // so for every (all) users we expand SQL columns by JSON headings. We remove the JSON column.
                // for each user, we add extra rows if there are multiple JSON records.
                foreach ($rs as $row)                                       // row loop
                {
                    if (empty($finaltable))                                 // set the report's table headings                           
                    {
                        $tablehead = $this->get_table_header($row);
                    }
                    // now we are building table data row
                    $arrayrow = array_values((array) $row);                // cast to numeric array

                    // get json array the json column in this row as well as number of rows required
                    $json_data_for_this_row = $this->get_json_data_for_this_row($arrayrow);
                    $json_array             = $json_data_for_this_row->json_array;
                    $num_extra_rows         = $json_data_for_this_row->num_extra_rows;

                    // outer loop is for possibly extra rows due to JSON. If not loop is executed just once
                    for ($i=0; $i <$num_extra_rows ; $i++)          // loop for expanding rows
                    { 
                        // merge the extra json data with original array row
                        $merged_array_row = [];

                        foreach ($arrayrow as $ii => $cell)         // loop for stuffing columns
                        {
                            if ($ii === $json_col_index)            // strict comparison otherwise null == 0
                            {
                                foreach ($json_array[$i] as $key => $value) // loop for expanding columns for json added
                                {         
                                    $merged_array_row[] = $value;
                                }
                            }
                            else if ($ii === $options_col_index)     // is this an options column? if so suppress it
                            {
                                // do nothing, so ignore this heading in the table
                            }
                            else                                            // normal data, use data as is. original code
                            {
                                $merged_array_row[] = $cell;
                            }
                        }
                        unset($cell);

                        foreach ($merged_array_row as $ii => $cell)         // some formatting, original code
                        {
                            
                                if (!$this->isForExport()) 
                                {
                                    $cell = format_text($cell, FORMAT_HTML, array('trusted' => true, 'noclean' => true, 'para' => false));
                                }
                                $merged_array_row[$ii] = str_replace('[[QUESTIONMARK]]', '?', $cell);
                            
                        }
                        $totalrecords++;                                    // increment table row count
                        $finaltable[] = $merged_array_row;                  // add just formed row to array of rows
                    }
                    
                    
                }                                                           // end of loop to build next table rows
            }
        }
        $this->sql = $sql;
        $this->totalrecords = $totalrecords;

        // Calcs.

        $finalcalcs = $this->get_calcs($finaltable, $tablehead);

        $table = new \stdclass;
        $table->id = 'reporttable';
        $table->data = $finaltable;
        $table->head = $tablehead;
        $table->reportid = $this->config->id;

        // added by  Madhu: Store JSON information in $table class for later use ------------------>
        if ($json_col_index)                                        // Store JSON information in $table class for later use
        {
            $table->formaction              = "delete_items";
            $table->json_present            = true;
            $table->json_options_obj        = $json_options_obj;
        }
        else
        {                                                           // this is the original functional code
            $table->formaction = "sendemail";
            $table->json_present = false;
        }
        //----------------------------------------------------------------------------------------->
        $calcs = new \html_table();
        $calcs->id = 'calcstable';
        $calcs->data = array($finalcalcs);
        $calcs->head = $tablehead;

        if (!$this->finalreport) {
            $this->finalreport = new \stdClass;
        }
        $this->finalreport->table = $table;
        $this->finalreport->calcs = $calcs;

        return true;
    }   // end of function create_report

     /** 
      *  @param object:$rs is an array of rows in object format
      *  @return object:$json_options_obj
      *  This function return an object that contains JSON column information
      *  as well as options column information
      */
    public function get_json_options_info($rs)
    {
        $json_col_index 	= null;                                                  // defaultInitialize

        $json_options_obj   = new \stdClass;                                            

        foreach ($rs as $row)                                                         // look for JSON, option columns               
        {               
            $keys_row = array_keys((array)      $row);                                  
            $vals_row = array_values((array)    $row);                                  

            foreach($keys_row as $ii => $colname)                                     // check each column of this row
            {   
                if (stripos($colname, 'json') !== false)
                {
                // this column is a JSON encoded column, store its column index
                $json_col_index 	= $ii;
                $json_options_obj->json_col_index = $json_col_index;

                // all JSON columns have to be named as json_shortname (of profile field)
                // extract user profile field's shortname by looking after the "_" character
                $shortname_profile_field = substr($colname, strpos($colname, "_") + 1); 
                $json_options_obj->shortname_profile_field = $shortname_profile_field;
                }

                if ($colname === 'options')
                {
                    // this column conntains directives for report as a JSON array
                    $options_col_index   = $ii;                 // save this for later use

                    $options_json        = $vals_row[$ii];      // get the JSON coded array
                    $options_json_notags = strip_tags(html_entity_decode($options_json));   // strip all tags
                    $options_array       = json_decode($options_json_notags, true);          // decode to associative array

                    switch (true) 
                    {
                        case ($options_array["select_all"] == "1"):
                            $select_all_rows = true;
                            break;

                        
                        default:
                            //
                            break;
                    }

                    $json_options_obj->options_col_index = $options_col_index;
                    $json_options_obj->select_all_rows = $select_all_rows;
                }

            }
            // finished with column index finding
            // now check if we have valid json data to extract json column headings from
            // A row can contain blank JSON data so may have to loop rows to find non-empty JSON data
            if ($json_col_index)
            {
                // see if this row has valid json data at column just found
                if (!empty($vals_row[$json_col_index]))
                {
                    // we have found non-empty string, (still may not be valid json data)
                    $json_val_html  = $vals_row[$json_col_index]; // possible html tags present
                    // remove tags etc.
                    $json_notags    = strip_tags(html_entity_decode($json_val_html));   // strip all tags
                    $json_array 	= json_decode($json_notags, true);                  // decode to associative array

                    // lets get the JSON headings from the 0th row
                    if (!empty($json_array))
                    {
                        $num_json_cols  = count($json_array[0]);        // since array is not empty check 0th element
                        $json_headings  = array_keys($json_array[0]);   // get the new column headers from JSON Keys

                        $json_options_obj->num_json_cols = $num_json_cols;
                        $json_options_obj->json_headings = $json_headings;

                        break;                                          // get out of this loop 1 over rows
                    }
                    else
                    {   // this row does not have valid json data, look in next row in loop 1
                        continue;
                    }
                }
                else
                {   // empty string found for this row, look in next row in loop 1
                    continue;
                }
            }
            else
            {   // no json encoded column extracted in SQL records so get out of loop 1
                break;
            }
        }   // end of foreach row loop 1

        $this->json_options_obj = $json_options_obj;

        return $json_options_obj;

    }   // end of function definition

    /**
     *  @param object:$row
     *  @return array:$table_header_arr
     */

    public function get_table_header($row)
    {
        $table_header_arr = [];

        // we reset an index for counting in column loop
        $i = 0;

        foreach ($row as $colname => $value)                
        {
            // Is the column heading a JSON encoded one?
            if ($i === $this->json_options_obj->json_col_index)        
            {
                // yes. expand with json headings
                foreach ($this->json_options_obj->json_headings as $json_heading)
                {                                          
                    $table_header_arr[] = $json_heading;
                }
            }
            else if ($i === $this->json_options_obj->options_col_index)    // is this the options column?
            {
                // do nothing, so ignore 'options' heading in the table
            }
            else                                            // not a json_encoded or options column, use as is
            {
                $table_header_arr[] = $colname;
            }
            $i +=1;                            
        }
        return $table_header_arr;
    }   // end of function definition

    /**
     *  @param array:$arrayrow a numerically indexed current row array
     *  @param object:$json_data_for_this_row 
     */
    public function get_json_data_for_this_row($arrayrow)
    {
        $json_data_for_this_row = new \stdClass;

        // ensure at least one loop so that original code functionality for non-json
        $num_extra_rows = 1;

        if (isset($this->json_options_obj->json_col_index))
        {
            $json_col_index = $this->json_options_obj->json_col_index;
            $json_val_html  = $arrayrow[$json_col_index] ?? []; // get the json string in this row if exists

            // remove tags etc.
            $json_notags    = strip_tags(html_entity_decode($json_val_html));

            // decode json string into associative array. Main array is numeric indexed though.
            $json_array 	= json_decode($json_notags, true);

            // if array is empty we still need one row to display blank json data
            if (empty($json_array))
            {
                $num_extra_rows = 1;
            }
            else 
            {
                $num_extra_rows = count($json_array);
            }
        }

        $json_data_for_this_row->json_array     = $json_array;
        $json_data_for_this_row->num_extra_rows = $num_extra_rows;

        return $json_data_for_this_row;
    } // end of class definition

}   // end of class definition
