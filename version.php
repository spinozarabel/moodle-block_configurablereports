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
 * Version details
 *
 * Configurable Reports - A Moodle block for creating customizable reports
 *
 * @package     block_configurable_reports
 * @author:     Juan leyva <http://www.twitter.com/jleyvadelgado>, modified by Madhu Avasarala 11/03/2018
 * @date:       2013-09-07
 *
 * @copyright  Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
// bumped version, changed settings names, replaced razorpay with pg for payment gateway
$plugin->version = 2019101901;  // Plugin version updated Oct 19, 2019
$plugin->requires = 2015111600; // require Moodle version (3.0).
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '3.7.0';
$plugin->component = 'block_configurable_reports'; // Full name of the plugin (used for diagnostics)
$plugin->cron      = 86400; // = Once in 24h, Set min time between cron executions.
                            // Should probably be at night to off load CPU load.
