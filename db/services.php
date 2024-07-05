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
 * Web service for mod assign
 * @package    block_reportdashboard
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$functions = [
'block_reportdashboard_userlist' => [
        'classname' => 'block_reportdashboard_external',
        'methodname' => 'userlist',
        'classpath' => 'blocks/reportdashboard/externallib.php',
        'description' => 'case userlist',
        'ajax' => true,
],
'block_reportdashboard_reportlist' => [
        'classname' => 'block_reportdashboard_external',
        'methodname' => 'reportlist',
        'classpath' => 'blocks/reportdashboard/externallib.php',
        'description' => 'case reportlist',
        'ajax' => true,
    ],
'block_reportdashboard_sendemails' => [
        'classname' => 'block_reportdashboard_external',
        'methodname' => 'sendemails',
        'classpath' => 'blocks/reportdashboard/externallib.php',
        'description' => 'case sendemails',
        'ajax' => true,
    ],
];
