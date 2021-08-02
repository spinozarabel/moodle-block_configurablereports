<?php

/**
 * Definition of auth_cas tasks.
 *
 */

defined('MOODLE_INTERNAL') || die();

$tasks = array(
                array(
                    'classname' => 'block_configurable_reports\task\sritoni_to_ldap_sync_task',
                    'blocking'  => 0,
                    'minute'    => '0',
                    'hour'      => '*/1',
                    'day'       => '*',
                    'month'     => '*',
                    'dayofweek' => '*',
                )
        );