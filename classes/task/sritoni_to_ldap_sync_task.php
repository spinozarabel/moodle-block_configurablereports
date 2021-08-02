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
 * A scheduled task for CAS user sync.
 *
 */
namespace block_configurable_reports\task;

/**
 * A scheduled task class for CAS user sync.
 *
 * @copyright  2015 Vadim Dvorovenko <Vadimon@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sritoni_to_ldap_sync_task extends \core\task\scheduled_task 
{

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() 
    {
        return "Sritoni to LDAP sync";
    }

    /**
     * Run users sync.
     */
    public function execute() 
    {
        global $CFG, $DB;

        require_once("../../config.php");
        require_once($CFG->dirroot."/blocks/configurable_reports/locallib.php");

        $id         = 130;
        $download   = 1;
        $format     = "sync";
        $courseid   = 1;

        $report = $DB->get_record('block_configurable_reports', ['id' => $id]);

        require_once($CFG->dirroot.'/blocks/configurable_reports/report.class.php');
        require_once($CFG->dirroot.'/blocks/configurable_reports/reports/'.$report->type.'/report.class.php');

        $reportclassname = 'report_'.$report->type;
        $reportclass = new $reportclassname($report);

        $reportclass->setForExport(true);

        $reportclass->create_report();

        //core_php_time_limit::raise();
        //raise_memory_limit(MEMORY_EXTRA);
        $exportplugin = $CFG->dirroot.'/blocks/configurable_reports/export/'.$format.'/export.php';
        if (file_exists($exportplugin)) 
        {
            require_once($exportplugin);
            export_report($reportclass->finalreport);
        }

    }
}