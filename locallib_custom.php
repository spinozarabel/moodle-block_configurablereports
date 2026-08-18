<?php
// blocks/configurable_reports/locallib_custom.php

defined('MOODLE_INTERNAL') || die();

/**
 * cr_print_table_custom
 * modifications to cr_print_table function to handle sendemailparent column and functionality
 *
 * @param object $table
 * @param bool $return
 * @return string|true
 */
function cr_print_table_custom(object $table, bool $return = false) {
    global $COURSE;

    // to send  emails only to pareents the table must have a column whose heading
    //   is exactly "onlyparents"
    // to send email to student and parent the table must have a column whose heading
    //   is exactly "studentandparents"
    // The column data in both cases above will be just the Moodle user id of the user to
    //   send email to. If the checkbox is checked then id will be submitted along with
    //   the checkbox value. If not checked then the id will not be submitted.
    

    $is_sendemailonlyparents_enabled = false;
    $is_sendemailstudentandparents_enabled = false;

    // Check if the table has a specific column named "onlyparents".
    if (cr_has_specific_column($table, "onlyparents")) {
        $is_sendemailonlyparents_enabled = true;
    }

    // Check if the table has a specific column named "studentandparents".
    if (cr_has_specific_column($table, "studentandparents")) {
        $is_sendemailstudentandparents_enabled = true;
    }

    $output = '';

    if (isset($table->align)) {
        foreach ($table->align as $key => $aa) {
            if ($aa) {
                $align[$key] = ' text-align:' . fix_align_rtl($aa) . ';';  // Fix for RTL languages.
            } else {
                $align[$key] = '';
            }
        }
    }
    if (isset($table->size)) {
        foreach ($table->size as $key => $ss) {
            if ($ss) {
                $size[$key] = ' width:' . $ss . ';';
            } else {
                $size[$key] = '';
            }
        }
    }
    if (isset($table->wrap)) {
        foreach ($table->wrap as $key => $ww) {
            if ($ww) {
                $wrap[$key] = ' white-space:nowrap;';
            } else {
                $wrap[$key] = '';
            }
        }
    }

    if (empty($table->width)) {
        $table->width = '80%';
    }

    if (empty($table->tablealign)) {
        $table->tablealign = 'center';
    }

    if (!isset($table->cellpadding)) {
        $table->cellpadding = '5';
    }

    if (!isset($table->cellspacing)) {
        $table->cellspacing = '1';
    }

    if (empty($table->class)) {
        $table->class = 'generaltable';
    }

    $tableid = empty($table->id) ? '' : 'id="' . $table->id . '"';
    $output .= '<form action="send_emails_custom.php" method="post" id="sendemail">';
    $output .= '<table width="' . $table->width . '" ';
    if (!empty($table->summary)) {
        $output .= " summary=\"$table->summary\"";
    }
    $output .= " cellpadding=\"$table->cellpadding\" cellspacing=\"$table->cellspacing\"
                class=\"$table->class boxalign$table->tablealign\" $tableid>\n";

    $countcols = 0;
    $isuserid = -1;

    if (!empty($table->head)) {
        $countcols = count($table->head);
        $output .= '<thead><tr>';
        $keys = array_keys($table->head);
        $lastkey = end($keys);
        foreach ($table->head as $key => $heading) {
            if ($heading === 'onlyparents' || $heading === 'studentandparents') {
                $isuserid = $key;
            }
            if (!isset($size[$key])) {
                $size[$key] = '';
            }
            if (!isset($align[$key])) {
                $align[$key] = '';
            }
            if ($key == $lastkey) {
                $extraclass = ' lastcol';
            } else {
                $extraclass = '';
            }

            $output .= '<th style="vertical-align:top;' . $align[$key] . $size[$key] . ';white-space:normal;" class="header c' .
                $key . $extraclass . '" scope="col">' . $heading . '</th>';
        }
        $output .= '</tr></thead>' . "\n";
    }

    if (!empty($table->data)) {
        $oddeven = 1;
        $keys = array_keys($table->data);
        $lastrowkey = end($keys);
        foreach ($table->data as $key => $row) {
            $oddeven = $oddeven ? 0 : 1;
            if (!isset($table->rowclass[$key])) {
                $table->rowclass[$key] = '';
            }

            if ($key == $lastrowkey) {
                $table->rowclass[$key] .= ' lastrow';
            }

            $output .= '<tr class="r' . $oddeven . ' ' . $table->rowclass[$key] . '">' . "\n";
            if ($row === 'hr' && $countcols) {
                $output .= '<td colspan="' . $countcols . '"><div class="tabledivider"></div></td>';
            } else {  // It's a normal row of data.
                $keys2 = array_keys($row);
                $lastkey = end($keys2);

                foreach ($row as $keyouter => $item) {
                    if (!isset($size[$keyouter])) {
                        $size[$keyouter] = '';
                    }
                    if (!isset($align[$keyouter])) {
                        $align[$keyouter] = '';
                    }
                    if (!isset($wrap[$keyouter])) {
                        $wrap[$keyouter] = '';
                    }
                    if ($keyouter == $lastkey) {
                        $extraclass = ' lastcol';
                    } else {
                        $extraclass = '';
                    }
                    if ($keyouter == $isuserid) {
                        $output .= '<td style="' . $align[$keyouter] . $size[$keyouter] . $wrap[$keyouter] . '" class="cell c' .
                            $keyouter .
                            $extraclass . '"><input name="userids[]" type="checkbox" value="' . s($item) . '" checked></td>';
                    } else {
                        $output .= '<td style="' . $align[$keyouter] . $size[$keyouter] . $wrap[$keyouter] . '" class="cell c' .
                            $keyouter .
                            $extraclass . '">' . $item . '</td>';
                    }

                }
            }
            $output .= '</tr>' . "\n";
        }
    }
    $output .= '</table>' . "\n";
    $output .= '<input type="hidden" name="courseid" value="' . $COURSE->id . '">';

    // if onlyparents or studentandparents column is present then add a hidden input with name, id, and value boolean true
    if ($is_sendemailonlyparents_enabled) {
        $output .= '<input type="hidden" name="is_sendemailonlyparents_enabled" id="is_sendemailonlyparents_enabled" value="true">';
    } else {
        $output .= '<input type="hidden" name="is_sendemailonlyparents_enabled" id="is_sendemailonlyparents_enabled" value="false">';
    }
    if ($is_sendemailstudentandparents_enabled) {
        $output .= '<input type="hidden" name="is_sendemailstudentandparents_enabled" id="is_sendemailstudentandparents_enabled" value="true">';
    } else {
        $output .= '<input type="hidden" name="is_sendemailstudentandparents_enabled" id="is_sendemailstudentandparents_enabled" value="false">';
    }
    
    // This will be set to true if there is a heading onlyparents or studentandparents in the table head
    if ($isuserid != -1) {
        $output .= '<input type="submit" value="send emails">';
    }
    $output .= '</form>';

    if ($return) {
        return $output;
    }

    echo $output;

    return true;
}

/**
 * Checks if the table header contains the special column.
 *
 * @param object $table The table object passed to cr_print_table.
 * @param string $column_name The name of the column to check.
 * @return bool
 */
function cr_has_specific_column(object $table, string $column_name = "sendemailparent") : bool {
    if (empty($table->head) || !is_array($table->head)) {
        return false;
    }
    foreach ($table->head as $heading) {
        if (strtolower(trim(strip_tags($heading))) === $column_name) {
            return true;
        }
    }
    return false;
}


/**
 *  Given the parent email, and user object of student
 *  Form the minimum user object needed for emailing
 */
function cr_form_parent_user_object(string $parent_email, object $student_user) : ? object {
    // validate the mail
    $email = trim($parent_email);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // form the minimum user object for use with email_to_user()
        $parent_user = new stdclass();
        $parent_user->id = -99;
        $parent_user->firstname = 'Parent of';
        $parent_user->lastname = ' ' . $student_user->firstname . ' ' . $student_user->lastname;
        $parent_user->email = $email;
        $parent_user->firstnamephonetic = 'Parent';
        $parent_user->lastnamephonetic = ' ' . $student_user->firstname . ' ' . $student_user->lastname;
        $parent_user->middlename = ' ';
        $parent_user->alternatename = ' ';
        $parent_user->icq = ' ';
        $parent_user->aim = ' ';
        $parent_user->yahoo = ' ';
        $parent_user->skype = ' ';
        $parent_user->msn = ' ';
        
        $parent_user->mailformat = 1;  // HTML format
        $parent_user->maildisplay = true;
        $parent_user->suspended = 0;
        $parent_user->deleted = 0;

        return $parent_user;
    }

    return null;
}