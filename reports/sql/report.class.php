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
            {
                // we need information about new number of columns and their headings from non-empty JSON entries
                $json_col_index 	= null;

                foreach ($rs as $row)
                {
                    // see if there is any json encoded column at all in the 1st place
                    $keys_row = array_keys((array) $row);
                    $vals_row = array_values((array) $row);

                    foreach($keys_row as $ii => $colname)
                    {
                      if ($json_col_index)
                      {
                        // not 1st time, already found index, no need to search for index
                        break;
                      }
                      if (stripos($colname, 'json') !== false)
                      {
                        // this column is a JSON encoded column, get its index
                        $json_col_index 	= $ii;
                        break;
                      }
                    }
                    if ($json_col_index)
                    {
                      // see if thie row's json encoded value exists
                      if (!empty($vals_row[$json_col_index]))
                      {
                        // we have found non-empty JSON encoded value, derive the headings from the keys
                        $json_array 	= json_decode($vals_row[$json_col_index], true);
                        $json_headings	= array_keys($json_array);
                      }
                      else
                      {
                        // empty json values for this row, look in next row
                        continue;
                      }
                    }
                    else
                    {
                      // no json in headings so no need to look in subsequent rows
                      break;
                    }
                }
                // if json_headings are empty, no valid json data for all rows so no need to expand new json columns
                if (empty($json_headings))
                {
                $json_col_index 	= null;
                }

                unset ($row);
                unset ($json_array);

                error_log("JSON column index is: $json_col_index");
                error_log(print_r($json_headings, true));

                foreach ($rs as $row) {
                    if (empty($finaltable)) {
                        foreach ($row as $colname => $value) {
                            $tablehead[] = $colname;
                        }
                    }
                    $arrayrow = array_values((array) $row);
                    foreach ($arrayrow as $ii => $cell) {
                        if (!$this->isForExport()) {
                            $cell = format_text($cell, FORMAT_HTML, array('trusted' => true, 'noclean' => true, 'para' => false));
                        }
                        $arrayrow[$ii] = str_replace('[[QUESTIONMARK]]', '?', $cell);
                    }
                    $totalrecords++;
                    $finaltable[] = $arrayrow;
                }
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
    }

}
