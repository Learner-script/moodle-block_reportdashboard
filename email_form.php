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
 * Form for editing Cobalt report dashboard block instances.
 * @package   block_reportdashboard
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Reportdashboard Email Form
 */
class block_reportdashboard_emailform extends moodleform {
    /**
     * Form definition
     */
    public function definition() {
        global $CFG;
        $mform = &$this->_form;
        $reportid = $this->_customdata['reportid'];
        $instance = $this->_customdata['instance'];

        if ($this->_customdata['AjaxForm']) {
            $mform->_attributes['id'] = 'sendemail'.$instance.'';
            $requireclass = 'sendemailreq'.$instance.'';
        } else {
            $requireclass = 'sendemailformelements';
        }
        $data = [];
        $mform->addElement('select', 'email', get_string('fullname'), $data, ['data-select2-ajax' => true,
                        'data-class' => $requireclass, 'data-element' => 'email', 'data-multiple' => true,
                        'data-ajax--url' => new moodle_url('/blocks/reportdashboard/ajax.php'),
                        'data-placeholder' => 'Select Users', 'data-minimumInputLength' => 2, ]);
        $mform->getElement('email')->setMultiple(true);
        $mform->addRule('email', get_string('user_err', 'block_reportdashboard'), 'required', null, 'client');

        $exportoptions = (new block_learnerscript\local\ls)->cr_get_export_plugins();
        $mform->addElement('select', 'format', get_string('format', 'block_reportdashboard'), $exportoptions,
                        ['class' => "export_row", "data-class" => "requireclass", 'data-element' => 'format']);

        $mform->addElement('hidden', 'reportid', $reportid);
        $mform->setType('reportid', PARAM_INT);

        $mform->addElement('hidden', 'action', 'sendemails');
        $mform->setType('action', PARAM_TEXT);

        $btnstring = get_string('send', 'block_reportdashboard');

        $this->add_action_buttons(false, $btnstring);
    }
}
