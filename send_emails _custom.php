<?php
//
// Customised by MA for conditionally send emails to parents
// using email id data present in specific user profile fields

// Email form added to enable email to selected users.
require_once('../../config.php');

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/formslib.php');

// get the hidden values in the form sent by cr_print_table_custom
$userids_array                     = optional_param_array('userids', [], PARAM_INT);
$courseid                          = optional_param('courseid', 0, PARAM_INT);
$is_sendemailonlyparents_enabled   = optional_param('is_sendemailonlyparents_enabled', false, PARAM_BOOL);
$is_sendemailstudentandparents_enabled = optional_param('is_sendemailstudentandparents_enabled', false, PARAM_BOOL);


require_login();
global $PAGE, $USER, $DB, $COURSE;
$context = context_course::instance($COURSE->id);
$PAGE->set_context($context);

if (!has_capability('block/configurable_reports:managereports', $context) &&
    !has_capability('block/configurable_reports:manageownreports', $context)) {
    throw new moodle_exception('badpermissions');
}

/**
 * Class sendemail_form
 *
 * @package   block_configurable_reports
 * @author    Juan leyva <http://www.twitter.com/jleyvadelgado>
 */
class sendemail_form extends moodleform {

    /**
     * Form definition
     */
    public function definition(): void {
        global $COURSE;

        $mform =& $this->_form;
        $context = context_course::instance($COURSE->id);
        $editoroptions = [
            'trusttext' => true,
            'subdirs' => true,
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'context' => $context,
        ];

        $mform->addElement('hidden', 'usersids', $this->_customdata['usersids']);
        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);

        // add hidden elements onlyparents and studentandparents to pass
        $mform->addElement('hidden', 'onlyparents', $this->_customdata['onlyparents']);
        $mform->addElement('hidden', 'studentandparents', $this->_customdata['studentandparents']);

        $mform->addElement('text', 'subject', get_string('email_subject', 'block_configurable_reports'));
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', null, 'required');

        $mform->addElement('editor', 'content', get_string('email_message', 'block_configurable_reports'), null, $editoroptions);

        $buttons = [];
        $buttons[] =& $mform->createElement('submit', 'send', get_string('email_send', 'block_configurable_reports'));
        $buttons[] =& $mform->createElement('cancel');

        $mform->addGroup($buttons, 'buttons', get_string('actions'), [' '], false);
    }

}

$unique_userids = array_unique($userids_array);
$userids_string = implode(',', $unique_userids);

$form = new sendemail_form(null, [
    'usersids' => $userids_string,
    'courseid' => $courseid,
    'is_sendemailonlyparents_enabled' => $is_sendemailonlyparents_enabled,
    'is_sendemailstudentandparents_enabled' => $is_sendemailstudentandparents_enabled,
]);

if ($form->is_cancelled()) {

    redirect(new moodle_url('/course/view.php?id=' . $data->courseid));

} else if ($data = $form->get_data()) {

    // Include the user profile library
    require_once($CFG->dirroot . '/user/profile/lib.php');

    $is_sendemailonlyparents_enabled = $data->is_sendemailonlyparents_enabled;
    $is_sendemailstudentandparents_enabled = $data->is_sendemailstudentandparents_enabled;

    foreach (explode(',', $data->usersids) as $userid) {

        // initialize mother and father objects
        $mother = null;
        $father = null;

        $userid = (int) $userid;

        if (empty($userid)) continue;

        // Get the Moodle User object given the moodle user id
        $abouttosenduser = $DB->get_record('user', ['id' => $userid]);

        if ($abouttosenduser) {
            profile_load_custom_fields($abouttosenduser);
            // We can access the parents email ids using the shortnames
            // e.g. motheremail, fatheremail
            // form a uminimum required ser object for use with email_to_user()
            // for the purpose of passing the correct user object with parents email id
            $mothers_email = $abouttosenduser->profile_field_motheremail;

            if (!empty($mothers_email)) {
                $mother = cr_form_parent_user_object($mothers_email, $abouttosenduser);
            }

            $fathers_email = $abouttosenduser->profile_field_fatheremail;

            if (!empty($fathers_email)) {
                $father = cr_form_parent_user_object($fathers_email, $abouttosenduser);
            }

            // Depending on the flags, send mail to student and parents or parents only
            if ($is_sendemailonlyparents_enabled) {
                if ($mother) {
                    email_to_user($mother, $USER, $data->subject, format_text($data->content['text']), $data->content['text']);
                }
                if ($father) {
                    email_to_user($father, $USER, $data->subject, format_text($data->content['text']), $data->content['text']);
                }
            }

            if ($is_sendemailstudentandparents_enabled) {
                if ($mother) {
                    email_to_user($mother, $USER, $data->subject, format_text($data->content['text']), $data->content['text']);
                }
                if ($father) {
                    email_to_user($father, $USER, $data->subject, format_text($data->content['text']), $data->content['text']);
                }
                email_to_user($abouttosenduser, $USER, $data->subject, format_text($data->content['text']), $data->content['text']);
            }
        }
    }
    // After emails were sent... go back to where you came from.
    redirect(new moodle_url('/course/view.php?id=' . $data->courseid));
}

$PAGE->set_title(get_string('email', 'questionnaire'));
$PAGE->set_heading(format_string($COURSE->fullname));
$PAGE->navbar->add(get_string('email', 'questionnaire'));

echo $OUTPUT->header();

echo html_writer::start_tag('div', ['class' => 'no-overflow']);
$form->display();
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
