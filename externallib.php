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
 * stuff for local_ws_enrolcohort.
 *
 * @package     local_ws_enrolcohort
 * @author      Donald Barrett <donald.barrett@learningworks.co.nz>
 * @copyright   2018 onwards, LearningWorks ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// No direct access.
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/externallib.php");

use \local_ws_enrolcohort\tools as tools;
use \local_ws_enrolcohort\responses as responses;

class local_ws_enrolcohort_external extends external_api {

    /**
     * Constants that define the query strings i.e. https://example.url?querystring[key]=value&querystring[key]=value etcera, etcetera.
     */
    const QUERYSTRING_INSTANCE  = 'instance';
    const QUERYSTRING_COURSE    = 'course';

    /**
     * Constants that define group creation modes. Create group is already defined. Values are as per the add instance mform.
     */
    const COHORT_GROUP_CREATE_NONE = 0;
    const COHORT_GROUP_CREATE_NEW = -1;

    /**
     * The value that says to the webservice function get_instances() to get all cohort enrolment instances.
     */
    const GET_INSTANCES_COURSEID_ALL = -1;

    /**
     * Constants that map the customint field names to the name of the fields.
     */
    const FIELD_GROUP   = 'customint2';
    const FIELD_COHORT  = 'customint1';

    /**
     * A constant that defines a webservice function call that has errors.
     */
    const WEBSERVICE_FUNCTION_CALL_HAS_ERRORS_ID = -1;

    /// <editor-fold desc="Other non webservice function specific functions that can be used internally.">

    /**
     * All the webservices functions defined in this external lib return the same stuff.
     * This is some dry code.
     *
     * @return external_single_structure
     */
    private static function webservice_function_returns() {
        return new external_single_structure([
            'id'        => new external_value(PARAM_INT, 'The id of the enrolment instance'),
            'code'      => new external_value(PARAM_INT, 'HTTP status code'),
            'message'   => new external_value(PARAM_TEXT, 'Human readable response message'),
            'errors'    => new external_multiple_structure(
                new external_single_structure(
                    [
                        'object'    => new external_value(PARAM_TEXT, 'The object that failed'),
                        'id'        => new external_value(PARAM_INT, 'The id of the failed object'),
                        'message'   => new external_value(PARAM_TEXT, 'Human readable response message')
                    ],
                    'component errors',
                    VALUE_OPTIONAL
                )
            ),
            'data' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'object'    => new external_value(PARAM_TEXT, 'The object this is describing'),
                        'id'        => new external_value(PARAM_INT, 'The id of the object', VALUE_OPTIONAL),
                        'name'      => new external_value(PARAM_TEXT, 'The name of the object', VALUE_OPTIONAL),
                        'courseid'  => new external_value(PARAM_INT, 'The id of the related course', VALUE_OPTIONAL),
                        'cohortid'  => new external_value(PARAM_INT, 'The id of the cohort', VALUE_OPTIONAL),
                        'roleid'    => new external_value(PARAM_INT, 'The id of the related role', VALUE_OPTIONAL),
                        'groupid'   => new external_value(PARAM_INT, 'The id of the group', VALUE_OPTIONAL),
                        'idnumber'  => new external_value(PARAM_RAW, 'The idnumber of the object', VALUE_OPTIONAL),
                        'shortname' => new external_value(PARAM_TEXT, 'The shortname of the object', VALUE_OPTIONAL),
                        'status'    => new external_value(PARAM_INT, 'The status of the object', VALUE_OPTIONAL),
                        'active'    => new external_value(PARAM_TEXT, 'Enrolment instance is active or not', VALUE_OPTIONAL),
                        'visible'   => new external_value(PARAM_INT, 'The visibility of the object', VALUE_OPTIONAL),
                        'format'    => new external_value(PARAM_PLUGIN, 'The course format', VALUE_OPTIONAL)
                    ],
                    'extra details',
                    VALUE_OPTIONAL
                )
            )
        ]);
    }

    /**
     * This function gets assignable roles for a course context.
     *
     * @param null $context
     * @return array
     */
    private static function get_assignable_roles($context = null) {
        return $context instanceof \context_course ? get_assignable_roles($context) : [];
    }

    /// </editor-fold>

    /// <editor-fold desc="Functions for add_instance().">

    /**
     * Gets the default value for an add_instance() parameter. Use properly. No error checking happens.
     *
     * @param string $parametername
     * @return mixed
     */
    private static function add_instance_get_parameter_default_value($parametername = '') {
        // Just ask for the right things and one shall receive. We shan't be making any mistakes.
        return self::add_instance_parameters()->keys[self::QUERYSTRING_INSTANCE]->keys[$parametername]->default;
    }

    /**
     * Returns description of the add_instance function parameters.
     *
     * @return external_function_parameters
     */
    public static function add_instance_parameters() {
        return new external_function_parameters([
            self::QUERYSTRING_INSTANCE => new external_single_structure([
                'courseid'  => new external_value(PARAM_INT, 'The id of the course.', VALUE_REQUIRED),
                'cohortid'  => new external_value(PARAM_INT, 'The id of the cohort.', VALUE_REQUIRED),
                'roleid'    => new external_value(PARAM_INT, 'The id of an existing role to assign users.', VALUE_REQUIRED),
                'groupid'   => new external_value(PARAM_INT, 'The id of a group to add users to.', VALUE_OPTIONAL, self::COHORT_GROUP_CREATE_NONE),
                'name'      => new external_value(PARAM_TEXT, 'The name of the cohort enrolment instance.', VALUE_OPTIONAL, ''),
                'status'    => new external_value(PARAM_INT, 'The status of the enrolment method.', VALUE_OPTIONAL, ENROL_INSTANCE_ENABLED)
            ])
        ]);
    }

    /**
     * Returns description of the add_instance() function return value.
     *
     * @return external_single_structure
     */
    public static function add_instance_returns() {
        return self::webservice_function_returns();
    }

    /**
     * Adds a cohort enrolment instance to a given course.
     *
     * @param $params
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function add_instance($params) {
        global $CFG, $DB, $SITE;

        require_once("{$CFG->dirroot}/cohort/lib.php");

        // Check the call for parameters.
        $params = self::validate_parameters(self::add_instance_parameters(), [self::QUERYSTRING_INSTANCE => $params]);

        // In case of errors.
        $errors = [];

        // Other data.
        $extradata = [];

        // Get the course.
        $courseid = $params[self::QUERYSTRING_INSTANCE]['courseid'];

        // Initial context.
        $context = null;

        // Validate the course. This is required.
        if ($courseid == $SITE->id) {
            $errors[] = (new responses\error($courseid, 'course', 'courseissite'))->to_array();

            // Set the context to system for validation.
            $context = \context_system::instance();
        } else if (!$DB->record_exists('course', ['id' => $courseid])) {
            $errors[] = (new responses\error($courseid, 'course', 'coursenotexists'))->to_array();
        } else {
            // Set the context to course for validation.
            $context = \context_course::instance($courseid);

            // Add info to the response object.
            $extradata[] = (new responses\course($courseid))->to_array();
        }

        // Get the cohort. This is required
        $cohortid = $params[self::QUERYSTRING_INSTANCE]['cohortid'];

        // Validate the cohort. This is required.
        if (!$DB->record_exists('cohort', ['id' => $cohortid])) {
            $errors[] = (new responses\error($cohortid, 'cohort', 'cohortnotexists'))->to_array();
        } else if ($context instanceof \context_system) {
            // Add some info to the response object.
            $extradata[] = (new responses\cohort($cohortid))->to_array();

            $errors[] = (new responses\error($cohortid, 'cohortsite', 'cohortnotavailableatcontext'))->to_array();
        } else if ($context instanceof \context_course) {
            // Add some info to the response object.
            $extradata[] = (new responses\cohort($cohortid))->to_array();

            // Get the available cohorts.
            $availablecohorts = cohort_get_available_cohorts($context);
            if (empty($availablecohorts) || !isset($availablecohorts[$cohortid])) {
                $errors[] = (new responses\error($cohortid, 'cohort', 'cohortnotavailableatcontext'))->to_array();
            }
        } else if (is_null($context)) {
            $errors[] = (new responses\error($cohortid, 'cohort', 'cohortnullcontext'))->to_array();
        }

        // Get the role.
        $roleid = $params[self::QUERYSTRING_INSTANCE]['roleid'];

        // Validate the role. This is required.
        $assignableroles = self::get_assignable_roles($context);

        if (!$DB->record_exists('role', ['id' => $roleid])) {
            // Role doesn't exist.
            $errors[] = (new responses\error($roleid, 'role', 'rolenotexists'))->to_array();
        } else if (empty($assignableroles) || !isset($assignableroles[$roleid])) {
            // Role is not assignable at this context.
            $errors[] = (new responses\error($roleid, 'role', 'rolenotassignablehere'))->to_array();

            // Role exists. Add some info to the response object.
            $extradata[] = (new responses\role($roleid))->to_array();
        }

        // Get the group.
        $groupid = $params[self::QUERYSTRING_INSTANCE]['groupid'];

        // Validate the role. This is optional.
        if (!is_null($groupid)) {
            $groupcreatemodes = [self::COHORT_GROUP_CREATE_NONE, self::COHORT_GROUP_CREATE_NEW];
            if (!in_array($groupid, $groupcreatemodes) && !$DB->record_exists('groups', ['courseid' => $courseid, 'id' => $groupid])) {
                // Provided group id doesn't exist for this course.
                $errors[] = (new responses\error($groupid, 'group', 'groupnotexists'))->to_array();
            }
        } else {
            // Get the default value specified for the parameter groupid.
            $groupid = self::add_instance_get_parameter_default_value('groupid');
        }

        // Validate the name of the cohort enrolment instance. This is optional.
        $name = $params[self::QUERYSTRING_INSTANCE]['name'];

        if (is_null($name)) {
            // Get the default value for the name.
            $name = self::add_instance_get_parameter_default_value('name');
        }

        // Check the users capabilities to ensure that they can do this.
        if ($context instanceof \context_course) {
            // Check that the user has the required capabilities for the course context.
            $requiredcapabilities = ['moodle/cohort:view', 'moodle/course:managegroups', 'moodle/role:assign'];
            foreach ($requiredcapabilities as $requiredcapability) {
                if (!has_capability($requiredcapability, $context)) {
                    $errors[] = (new responses\error(null, 'capability', 'usermissingrequiredcapability', $requiredcapability))->to_array();
                }
            }

            // Check that the user has the capability to enrol config (cohort and moodle course level).
            $anycapability = ['moodle/course:enrolconfig', 'enrol/cohort:config'];
            if (!has_any_capability($anycapability, $context)) {
                $errors[] = (new responses\error(null, 'capability', 'usermissinganycapability', '\''.implode('\', \'', $anycapability).'\''))->to_array();
            }
        }

        // Validate the status and set to a default.
        $status = $params[self::QUERYSTRING_INSTANCE]['status'];

        if (!is_null($status) && !in_array($status, [ENROL_INSTANCE_ENABLED, ENROL_INSTANCE_DISABLED])) {
            $errors[] = (new responses\error(null, 'status', 'statusinvalid', $status))->to_array();
        } else {
            // Set status to the default.
            $status = self::add_instance_get_parameter_default_value('status');
        }

        // This is the important one. Check if the cohort enrolment instance is available for use.
        $cohortenrolment = enrol_get_plugin('cohort');
        if (!$cohortenrolment) {
            $errors[] = (new responses\error(null, 'enrol_plugin', 'enrolmentmethodnotavailable'))->to_array();
        }

        // Prepare the data to be returned as the response.
        $extradata[] = [
            'object'    => 'data',
            'cohortid'  => $cohortid,
            'roleid'    => $roleid,
            'groupid'   => $groupid,
            'name'      => $name,
            'status'    => $status
        ];

        // Set the HTTP status code.
        $code = empty($errors) ? 201 : 400;

        // Set the response message.
        if ($code == 201) {
            $message = tools::get_string('addinstance:201');
        } else if ($code == 400) {
            $message = tools::get_string('addinstance:400');
        } else {
            $message = tools::get_string('unknownstatuscode', $code);
        }

        // The initial response. The field id will be filled in later.
        $response = [
            'code'      => $code,
            'message'   => $message,
            'errors'    => $errors,
            'data'      => $extradata
        ];

        if (!empty($errors)) {
            // Return now due to errors.
            $response['id'] = self::WEBSERVICE_FUNCTION_CALL_HAS_ERRORS_ID;

            return $response;
        }

        // Get the full course object.
        $course = $DB->get_record('course', ['id' => $courseid]);

        // Add data about the course to the response.
        $extradata[] = (new responses\course($courseid))->to_array();

        // Add data about the cohort.
        $extradata[] = (new responses\cohort($cohortid))->to_array();

        // Prepare the fields.
        $fields = [
            'name'              => $name,
            'status'            => $status,
            'roleid'            => $roleid,
            'id'                => 0,
            'courseid'          => $courseid,
            'type'              => 'cohort',
            self::FIELD_COHORT  => $cohortid,
            self::FIELD_GROUP   => $groupid
        ];

        // Before creation ensure that there isn't an instance already synced with this role.
        $sqlwhere = "roleid = :roleid AND customint1 = :customint1 AND courseid = :courseid AND enrol = 'cohort' AND id <> :id";
        $sqlparams = [
            'roleid'            => $roleid,
            self::FIELD_COHORT  => $cohortid,
            'courseid'          => $courseid,
            'id'                => $fields['id']
        ];

        if ($DB->record_exists_select('enrol', $sqlwhere, $sqlparams)) {
            // Don't add instance. Send an error response.
            $instance = $DB->get_record_select('enrol', $sqlwhere, $sqlparams);
            $errors[] = (new responses\error($instance->id, 'instance', 'instanceexists'))->to_array();

            // Add detail about the enrolment instance.

            $extradata[] = (new responses\enrol(
                $instance->id,
                'enrol',
                $instance->name,
                $instance->status,
                $instance->roleid,
                $instance->courseid,
                $instance->{self::FIELD_COHORT},
                $instance->{self::FIELD_GROUP}
            ))->to_array();

            $response['id']         = $fields['id'];
            $response['code']       = 400;
            $response['errors']     = $errors;
            $response['message']    = tools::get_string("addinstance:{$response['code']}");
            $response['data']       = $extradata;

            return $response;
        }

        // After all that hard work we can now add the instance.
        $response['id'] = $cohortenrolment->add_instance($course, $fields);

        // Get the enrolment instance.
        $realenrolinstance = $DB->get_record('enrol', ['id' => $response['id']]);
        if (empty($realenrolinstance->name)) {
            $enrolinstancename = $cohortenrolment->get_instance_name($realenrolinstance).' - '.tools::get_string('instanceusingdefaultname');
        } else {
            $enrolinstancename = $realenrolinstance->name;
        }

        // Add data about the group to the response.
        $realgroupid = $DB->get_field('enrol', self::FIELD_GROUP, ['id' => $response['id']]);
        $extradata[] = (new responses\group($realgroupid, 'group', $courseid))->to_array();

        // Add data about the enrolment instance to the response.
        $extradata[] = (new responses\enrol(
            $response['id'],'enrol', $enrolinstancename, $status, $roleid, $courseid, $cohortid, $realgroupid
        ))->to_array();

        // Add the additional extra data to the response.
        $response['data'] = $extradata;

        // Return some data.
        return $response;
    }

    /// </editor-fold>

    /// <editor-fold desc="Functions for update_instance().">

    /**
     * Returns description of the update_instance() function parameters.
     *
     * @return external_function_parameters
     */
    public static function update_instance_parameters() {
        return new external_function_parameters([
            self::QUERYSTRING_INSTANCE => new external_single_structure([
                'id'        => new external_value(PARAM_INT, 'The id of the enrolment instance.', VALUE_REQUIRED),
                'name'      => new external_value(PARAM_TEXT, 'The name you want to give the enrolment instance.', VALUE_OPTIONAL),
                'status'    => new external_value(PARAM_INT, 'The status of the enrolment method.', VALUE_OPTIONAL),
                'roleid'    => new external_value(PARAM_INT, 'The id of an existing role to assign users.', VALUE_OPTIONAL),
                'groupid'   => new external_value(PARAM_INT, 'The id of a group to add users to.', VALUE_OPTIONAL)
            ])
        ]);
    }

    /**
     * Returns description of the update_instance function return value.
     *
     * @return external_single_structure
     */
    public static function update_instance_returns() {
        return self::webservice_function_returns();
    }

    /**
     * Updates an existing cohort enrolment instance.
     *
     * @param $params
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function update_instance($params) {
        global $DB, $SITE;

        // Check the call for parameters.
        $params = self::validate_parameters(self::update_instance_parameters(), [self::QUERYSTRING_INSTANCE => $params]);

        // A place for errors.
        $errors = [];

        // Other data.
        $extradata = [];

        // A place to put the updated enrolment data.
        $data = new \stdClass();

        // Preset the context.
        $context = null;

        // Get the enrolment instance id.
        $id = $params[self::QUERYSTRING_INSTANCE]['id'];

        // Validate the enrolment instance.
        $sqlwhere = "enrol = 'cohort' AND id = :id AND courseid <> :courseid";
        $sqlparams = ['id' => $id, 'courseid' => $SITE->id];

        $enrolmentinstance = null;

        if (!$DB->record_exists_select('enrol', $sqlwhere, $sqlparams)) {
            $errors[] = (new responses\error($id, 'instanceid', 'instancenotexists'))->to_array();
        } else {
            $enrolmentinstance = $DB->get_record_select('enrol', $sqlwhere, $sqlparams);
            $context = \context_course::instance($enrolmentinstance->courseid);
        }

        // Get the enrolment instance name.
        $name = $params[self::QUERYSTRING_INSTANCE]['name'];

        if (!empty($name)) {
            $data->name = $name;
        }

        // Get the enrolment instance status.
        $status = $params[self::QUERYSTRING_INSTANCE]['status'];

        // Validate the enrolment instance status.
        if (!is_null($status) && !in_array($status, [ENROL_INSTANCE_ENABLED, ENROL_INSTANCE_DISABLED])) {
            $errors[] = (new responses\error(null, 'status', 'statusinvalid', $status))->to_array();
        } else if (!is_null($status) && in_array($status, [ENROL_INSTANCE_ENABLED, ENROL_INSTANCE_DISABLED])) {
            $data->status = $status;
        }

        // Get the enrolment instance role id.
        $roleid = $params[self::QUERYSTRING_INSTANCE]['roleid'];

        if (!empty($roleid)) {
            // Validate the role. This is required.
            $assignableroles = self::get_assignable_roles($context);

            if (!$DB->record_exists('role', ['id' => $roleid])) {
                // Role doesn't exist.
                $errors[] = (new responses\error($roleid, 'role', 'rolenotexists'))->to_array();
            } else if (empty($assignableroles) || !isset($assignableroles[$roleid])) {
                // Role is not assignable at this context.
                $errors[] = (new responses\error($roleid, 'role', 'rolenotassignablehere'))->to_array();

                // Role exists. Add some info to the response object.
                $extradata[] = (new responses\role($roleid))->to_array();
            } else {
                $data->roleid = $roleid;
            }
        }

        // Get the group id.
        $groupid = $params[self::QUERYSTRING_INSTANCE]['groupid'];

        // Validate the group id.
        if (!empty($groupid)) {
            $groupcreatemodes = [self::COHORT_GROUP_CREATE_NONE, self::COHORT_GROUP_CREATE_NEW];
            $groupexistsforcourse = $DB->record_exists('groups', ['courseid' => $enrolmentinstance->courseid, 'id' => $groupid]);
            if (!in_array($groupid, $groupcreatemodes) && !is_null($enrolmentinstance) && !$groupexistsforcourse) {
                // Provided group id doesn't exist for this course.
                $errors[] = (new responses\error($groupid, 'group', 'groupnotexists'))->to_array();
            } else {
                $data->{self::FIELD_GROUP} = $groupid;
            }
        }

        // Return the supplied parameters.
        $extradata[] = [
            'id'        => $id,
            'object'    => 'params',
            'roleid'    => $roleid,
            'name'      => $name,
            'status'    => $status,
            'groupid'   => $groupid
        ];

        // Check for things that are the same.
        if ($enrolmentinstance instanceof \stdClass && $context instanceof \context_course) {
            foreach ($data as $property => $value) {
                if (isset($enrolmentinstance->$property) && $enrolmentinstance->$property == $value) {
                    // Removing unnecessary updation data.
                    unset($data->$property);
                } else if (isset($enrolmentinstance->$property) && $enrolmentinstance->$property != $value) {
                    // Add role detail to the response object.
                    if (\core_text::strtolower($property) == 'roleid') {
                        $extradata[] = (new responses\role($value))->to_array();
                    }
                }
            }
        }

        // This is the important one. Check if the cohort enrolment instance is available for use.
        $cohortenrolment = enrol_get_plugin('cohort');
        if (!$cohortenrolment) {
            $errors[] = (new responses\error(null, 'enrol_plugin', 'enrolmentmethodnotavailable'))->to_array();
        }

        // The HTTP response code.
        $code = empty($errors) ? 200 : 400;

        // Response message.
        if (empty((array)$data) && $code != 400) {
            $message = tools::get_string('updateinstance:nochange');
        } else if ($code == 200) {
            $message = tools::get_string('updateinstance:200');

            // Add the cohort id to the data.
            $data->{self::FIELD_COHORT} = $enrolmentinstance->{self::FIELD_COHORT};

            $previousgroupid = $enrolmentinstance->{self::FIELD_GROUP};

            // Haven't even updated the enrolment instance and we are celebrating.
            $cohortenrolment->update_instance($enrolmentinstance, $data);

            // Add the course to the response object.
            $extradata[] = (new responses\course($enrolmentinstance->courseid))->to_array();

            // Add the cohort detail to the response object.
            $extradata[] = (new responses\cohort($enrolmentinstance->{self::FIELD_COHORT}))->to_array();

            // Get the new group and add to the response object.
            if ($groupid == COHORT_CREATE_GROUP) {
                $extradata[] = (new responses\group($data->{self::FIELD_GROUP}, 'group', $enrolmentinstance->courseid))->to_array();
            } else if ($groupid != $previousgroupid) {
                $extradata[] = (new responses\group($groupid, 'group', $enrolmentinstance->courseid))->to_array();
            }

            // Add detail about the enrolment instance.
            $extradata[] = (new responses\enrol(
                $id,
                'enrol',
                $enrolmentinstance->name,
                $enrolmentinstance->status,
                $enrolmentinstance->roleid,
                $enrolmentinstance->courseid,
                $enrolmentinstance->{self::FIELD_COHORT},
                $enrolmentinstance->{self::FIELD_GROUP}
            ))->to_array();
        } else if ($code == 400) {
            $message = tools::get_string('updateinstance:400');
        } else {
            // Unknown.
            $message = tools::get_string('unknownstatuscode', $code);
        }

        // Prepare the response.
        $response = [
            'id'        => $id,
            'code'      => $code,
            'message'   => $message,
            'errors'    => $errors,
            'data'      => $extradata
        ];

        return $response;
    }

    /// </editor-fold>

    /// <editor-fold desc="Functions for delete_instance(). TODO: All of this stuff.">

    /**
     * Returns description of the delete_instance() function parameters.
     *
     * @return external_function_parameters
     */
    public static function delete_instance_parameters() {
        return new external_function_parameters([
            self::QUERYSTRING_INSTANCE => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'The id of the enrolment instance to delete.', VALUE_REQUIRED)
            ])
        ]);
    }

    /**
     * Returns description of the delete_instance() function return value.
     *
     * @return external_single_structure
     */
    public static function delete_instance_returns() {
        return self::webservice_function_returns();
    }

    public static function delete_instance($params) {
        // Check the parameters.
        $params = self::validate_parameters(self::delete_instance_parameters(), [self::QUERYSTRING_INSTANCE => $params]);

        // A place for the errors and extra data.
        $errors = [];
        $extradata = [];

        // Get the enrolment instance id.
        $id = $params[self::QUERYSTRING_INSTANCE]['id'];

        // Validate the enrolment instance id.

        // Set the HTTP response code.
        $code = empty($errors) ? 200 : 400;

        // Get the response message.
        $message = tools::get_string("deleteinstance:{$code}");

        // Prepare the response and then send it.
        $response = [
            'id'        => $id,
            'code'      => $code,
            'message'   => $message,
            'errors'    => $errors,
            'data'      => $extradata
        ];

        return $response;
    }

    /// </editor-fold>

    /// <editor-fold desc="Functions for get_instances().">

    /**
     * Returns description of the get_instances() function return value.
     *
     * @return external_function_parameters
     */
    public static function get_instances_parameters() {
        return new external_function_parameters([
            self::QUERYSTRING_COURSE => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'The id of a course to get enrolment instances for.', VALUE_REQUIRED)
            ])
        ]);
    }

    /**
     * Returns description of the get_instances() function return value.
     *
     * @return external_single_structure
     */
    public static function get_instances_returns() {
        // Definition of extra details for course in get enrolment instance/s.
        $coursedetails = new external_single_structure(
            [
                'object'    => new external_value(PARAM_TEXT, 'The type of object'),
                'id'        => new external_value(PARAM_INT, 'The id of the course'),
                'idnumber'  => new external_value(PARAM_RAW, 'The idnumber of the course'),
                'name'      => new external_value(PARAM_TEXT, 'The name of the course'),
                'shortname' => new external_value(PARAM_TEXT, 'The shortname of the course'),
                'visible'   => new external_value(PARAM_INT, 'The visibility of the course'),
                'format'    => new external_value(PARAM_PLUGIN, 'The course format')
            ],
            'More detail about the course associated to a cohort enrolment instance',
            VALUE_OPTIONAL
        );

        // Definition of extra details for cohort in get enrolment instance/s.
        $cohortdetails = new external_single_structure(
            [
                'object'    => new external_value(PARAM_TEXT, 'The type of object'),
                'id'        => new external_value(PARAM_INT, 'The id of the cohort'),
                'idnumber'  => new external_value(PARAM_RAW, 'The idnumber of the cohort'),
                'name'      => new external_value(PARAM_TEXT, 'The name of the cohort'),
                'visible'   => new external_value(PARAM_INT, 'The visibility of the cohort')
            ],
            'More detail about the course associated to a cohort enrolment instance',
            VALUE_OPTIONAL
        );

        // Definition of extra details for role in get enrolment instance/s.
        $roledetails = new external_single_structure(
            [
                'object'    => new external_value(PARAM_TEXT, 'The type of object'),
                'id'        => new external_value(PARAM_INT, 'The id of the role'),
                'shortname' => new external_value(PARAM_TEXT, 'The shortname of the role'),
            ],
            'More detail about the course associated to a cohort enrolment instance',
            VALUE_OPTIONAL
        );

        // Definition of extra details for role in get enrolment instance/s.
        $groupdetails = new external_single_structure(
            [
                'object'    => new external_value(PARAM_TEXT, 'The type of object'),
                'id'        => new external_value(PARAM_INT, 'The id of the group'),
                'courseid'  => new external_value(PARAM_INT, 'The id of the course this group belongs to'),
                'name'      => new external_value(PARAM_TEXT, 'The name of the group'),
            ],
            'More detail about the course associated to a cohort enrolment instance',
            VALUE_OPTIONAL
        );

        return new external_single_structure([
            'id'        => new external_value(PARAM_INT, 'The id of the enrolment instance'),
            'code'      => new external_value(PARAM_INT, 'HTTP status code'),
            'message'   => new external_value(PARAM_TEXT, 'Human readable response message'),
            'errors'    => new external_multiple_structure(
                new external_single_structure(
                    [
                        'object'    => new external_value(PARAM_TEXT, 'The object that failed'),
                        'id'        => new external_value(PARAM_INT, 'The id of the failed object'),
                        'message'   => new external_value(PARAM_TEXT, 'Human readable response message')
                    ],
                    'component errors',
                    VALUE_OPTIONAL
                )
            ),
            'data' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'object'    => new external_value(PARAM_TEXT, 'The object this is describing'),
                        'id'        => new external_value(PARAM_INT, 'The id of the object', VALUE_OPTIONAL),
                        'name'      => new external_value(PARAM_TEXT, 'The name of the object', VALUE_OPTIONAL),
                        'courseid'  => new external_value(PARAM_INT, 'The id of the related course', VALUE_OPTIONAL),
                        'cohortid'  => new external_value(PARAM_INT, 'The id of the cohort', VALUE_OPTIONAL),
                        'roleid'    => new external_value(PARAM_INT, 'The id of the related role', VALUE_OPTIONAL),
                        'groupid'   => new external_value(PARAM_INT, 'The id of the group', VALUE_OPTIONAL),
                        'idnumber'  => new external_value(PARAM_RAW, 'The idnumber of the object', VALUE_OPTIONAL),
                        'shortname' => new external_value(PARAM_TEXT, 'The shortname of the object', VALUE_OPTIONAL),
                        'status'    => new external_value(PARAM_INT, 'The status of the object', VALUE_OPTIONAL),
                        'active'    => new external_value(PARAM_TEXT, 'Enrolment instance is active or not', VALUE_OPTIONAL),
                        'visible'   => new external_value(PARAM_INT, 'The visibility of the object', VALUE_OPTIONAL),
                        'format'    => new external_value(PARAM_PLUGIN, 'The course format', VALUE_OPTIONAL),
                        'course'    => $coursedetails,
                        'cohort'    => $cohortdetails,
                        'role'      => $roledetails,
                        'group'     => $groupdetails
                    ],
                    'extra details',
                    VALUE_OPTIONAL
                )
            )
        ]);
    }

    /**
     * Gets the enrolment instances for a course or the entire site.
     *
     * @param $params
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function get_instances($params) {
        global $DB, $SITE;

        // Check the parameters.
        $params = self::validate_parameters(self::get_instances_parameters(), [self::QUERYSTRING_COURSE => $params]);

        // A place for the errors and extra data.
        $errors = [];
        $extradata = [];

        // Get the courseid aka id.
        $courseid = $params[self::QUERYSTRING_COURSE]['id'];

        // Validate the courseid.
        if ($courseid == $SITE->id) {
            $errors[] = (new responses\error(-1, 'course', 'getinstances:courseissite'))->to_array();
        } else if (!$DB->record_exists('course', ['id' => $courseid]) && $courseid != -1) {
            $errors[] = (new responses\error($courseid, 'course', 'coursenotexists'))->to_array();
        }

        // Set the HTTP status code.
        $code = empty($errors) ? 200 : 400;

        // Get the response message based on the HTTP status code and get things if 200.
        if ($code == 200) {
            // Get the courses.
            if ($courseid == self::GET_INSTANCES_COURSEID_ALL) {
                $courses = $DB->get_records('course');
            } else {
                $courses = $DB->get_records('course', ['id' => $courseid]);
            }

            $foundsitecourse = false;
            $numberofenrolmentmethods = 0;

            foreach ($courses as $course) {
                // Skip the site course.
                if ($course->id == $SITE->id) {
                    $foundsitecourse = true;
                    continue;
                }

                // Skip courses that don't have cohort enrolment methods.
                if (!$DB->record_exists('enrol', ['enrol' => 'cohort', 'courseid' => $course->id])) {
                    continue;
                }

                // Get the cohort enrolment instances for this course.
                $enrolmentinstances = $DB->get_records('enrol', ['enrol' => 'cohort', 'courseid' => $course->id]);

                foreach ($enrolmentinstances as $enrolmentinstance) {
                    // Make some info about the enrolment instance.
                    $ei = new responses\enrol(
                        $enrolmentinstance->id,
                        'enrol',
                        $enrolmentinstance->name,
                        $enrolmentinstance->status,
                        $enrolmentinstance->roleid,
                        $enrolmentinstance->courseid,
                        $enrolmentinstance->{self::FIELD_COHORT},
                        $enrolmentinstance->{self::FIELD_GROUP}
                    );

                    // Get other details about the enrolment instance.
                    $ei->set_course((new responses\course($enrolmentinstance->courseid))->to_array());
                    $ei->set_cohort((new responses\cohort($enrolmentinstance->{self::FIELD_COHORT}))->to_array());
                    $ei->set_role((new responses\role($enrolmentinstance->roleid))->to_array());
                    $ei->set_group((new responses\group($enrolmentinstance->{self::FIELD_GROUP}))->to_array());

                    $extradata[] = $ei->to_array();

                    $numberofenrolmentmethods += 1;
                }
            }

            // Count things and make langstring placeholders.
            $numberofcourses = count($courses);
            if ($foundsitecourse) {
                $numberofcourses -= 1;
            }

            $a = (object)['courseid' => $courseid, 'numberofenrolmentinstances' => $numberofenrolmentmethods, 'numberofcourses' => $numberofcourses];

            if ($courseid == self::GET_INSTANCES_COURSEID_ALL) {
                $message = tools::get_string('getinstances:200', $a);
            } else {
                $message = tools::get_string('getinstance:200', $a);
            }
        } else if ($code == 400) {
            $message = tools::get_string('getinstances:400');
        } else {
            $message = tools::get_string('unknownstatuscode', $code);
        }

        // Prepare the response.
        $response = [
            'id'        => $courseid,
            'code'      => $code,
            'message'   => $message,
            'errors'    => $errors,
            'data'      => $extradata
        ];

        return $response;
    }

    /// </editor-fold>
}
