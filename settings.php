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
 * Configurable Reports a Moodle block for creating customizable reports
 *
 * @copyright  2020 Juan Leyva <juan@moodle.com>
 * @package    block_configurable_reports
 * @author     Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(
        new admin_setting_configtext(
            'block_configurable_reports/dbhost', get_string('dbhost', 'block_configurable_reports'),
            get_string('dbhostinfo', 'block_configurable_reports'), '', PARAM_URL, 30
        )
    );
    $settings->add(
        new admin_setting_configtext(
            'block_configurable_reports/dbname', get_string('dbname', 'block_configurable_reports'),
            get_string('dbnameinfo', 'block_configurable_reports'), '', PARAM_RAW, 30
        )
    );
    $settings->add(
        new admin_setting_configpasswordunmask(
            'block_configurable_reports/dbuser', get_string('dbuser', 'block_configurable_reports'),
            get_string('dbuserinfo', 'block_configurable_reports'), '', PARAM_RAW, 30
        )
    );
    $settings->add(
        new admin_setting_configpasswordunmask(
            'block_configurable_reports/dbpass', get_string('dbpass', 'block_configurable_reports'),
            get_string('dbpassinfo', 'block_configurable_reports'), '', PARAM_RAW, 30
        )
    );

    $settings->add(
        new admin_setting_configtime(
            'block_configurable_reports/cron_hour',
            'cron_minute',
            get_string('executeat', 'block_configurable_reports'),
            get_string('executeatinfo', 'block_configurable_reports'),
            ['h' => 0, 'm' => 0]
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'block_configurable_reports/sqlsecurity', get_string('sqlsecurity', 'block_configurable_reports'),
            get_string('sqlsecurityinfo', 'block_configurable_reports'), 1
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'block_configurable_reports/crrepository',
            get_string('crrepository', 'block_configurable_reports'),
            get_string('crrepositoryinfo', 'block_configurable_reports'),
            'jleyva/moodle-configurable_reports_repository',
            PARAM_URL,
            40
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'block_configurable_reports/sharedsqlrepository',
            get_string('sharedsqlrepository', 'block_configurable_reports'),
            get_string('sharedsqlrepositoryinfo', 'block_configurable_reports'),
            'jleyva/moodle-custom_sql_report_queries',
            PARAM_URL,
            40
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'block_configurable_reports/sqlsyntaxhighlight', get_string('sqlsyntaxhighlight', 'block_configurable_reports'),
            get_string('sqlsyntaxhighlightinfo', 'block_configurable_reports'), 1
        )
    );

    $reporttableoptions = ['html' => 'Simple', 'jquery' => 'jQuery', 'datatables' => 'DataTables JS'];
    $settings->add(
        new admin_setting_configselect(
            'block_configurable_reports/reporttableui', get_string('reporttableui', 'block_configurable_reports'),
            get_string('reporttableuiinfo', 'block_configurable_reports'), 'datatables', $reporttableoptions
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'block_configurable_reports/reportlimit', get_string('reportlimit', 'block_configurable_reports'),
            get_string('reportlimitinfo', 'block_configurable_reports'), '5000', PARAM_INT, 6
        )
    );


    $settings->add(new admin_setting_configtext('block_configurable_reports/allowedsqlusers', get_string('allowedsqlusers', 'block_configurable_reports'),
        get_string('allowedsqlusersinfo', 'block_configurable_reports'), '', PARAM_TEXT));
    // csv delimiters used in get_delimiter() of moodle lib/csvlib.class.php
    $csvdelimiteroptions= array('cfg'=>'cfg','colon'=>'colon','comma'=>'comma','semicolon'=>'semicolon','tab'=>'tab');
    $settings->add(new admin_setting_configselect('block_configurable_reports/csvdelimiter', get_string('csvdelimiter', 'block_configurable_reports'), 
        get_string('csvdelimiterinfo', 'block_configurable_reports'), 'cfg', $csvdelimiteroptions));
    $settings->add(
        new admin_setting_configtext(
            'block_configurable_reports/allowedsqlusers', get_string('allowedsqlusers', 'block_configurable_reports'),
            get_string('allowedsqlusersinfo', 'block_configurable_reports'), '', PARAM_TEXT
        )
    );

    // LDAP settings
	$settings->add(new admin_setting_configpasswordunmask('block_configurable_reports/ldap_server', 'LDAP URL',
    'ldaps://example.com', '', PARAM_RAW, 40));

    $settings->add(new admin_setting_configpasswordunmask('block_configurable_reports/ldap_admin', 'LDAP Admin',
        'cn=admin,dc=example,dc=edu,dc=in', '', PARAM_RAW, 40));

    $settings->add(new admin_setting_configpasswordunmask('block_configurable_reports/ldap_password', 'LDAP Admin Password',
        'Enter password for LDAP Admin Account', '', PARAM_RAW, 40));

    $settings->add(new admin_setting_configtext('block_configurable_reports/ldap_tree', 'LDAP Tree',
        'dc=example,dc=edu,dc=in', '', PARAM_RAW, 40));

    // setting to control deletion of LDAP users doing LDAP Sync
    $settings->add(new admin_setting_configcheckbox('block_configurable_reports/flag_delete_users', 'Check for YES',
        'Check box to delete LDAP users during Sync, leave unchecked NOT to delete LDAP users during Sync', 1));

    // setting to control Modification of LDAP users doing LDAP Sync
    $settings->add(new admin_setting_configcheckbox('block_configurable_reports/flag_mod_users', 'Check for YES',
        'Check box to Modify Existing LDAP users during Sync, leave unchecked to NOT Modify LDAP users during Sync', 1));

    // added setting for URL of published google CSV containing subject sort order
    $settings->add(new admin_setting_configpasswordunmask('block_configurable_reports/url_subject_sortorder', 'URL of subject sort order',
        'Enter full path of published Google CSV containing subject sort order', '', PARAM_URL, 80));
}
