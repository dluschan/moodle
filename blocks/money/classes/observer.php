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
 * Event observer.
 *
 * @package    block_money
 * @copyright  2020 Dmitry Luschan <dluschan@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_money;

defined('MOODLE_INTERNAL') || die();

class observer {
    public static function meeting_created(\core\event\base $event)
    {
        //insert into mdl_block_money_period (period, comment) values('one-time', 'одно занятие');
        //insert into mdl_block_money_currency_code (numeric_code, alphabetic_code, minor_unit, entity) values(643, 'RUB', 2, 'Russian ruble');
        //insert into mdl_block_money_currency_sign (numeric_code, sign) values(643, '₽');
        //insert into mdl_block_money_tariff (periodid, currencyid, amount) values(1, 643, 250000);
        //insert into mdl_block_money_paid_course (courseid, tariffid) values(2, 2);
        //insert into mdl_block_money_cash_account (customerid, currencyid, amount) values(4, 643, 300000);
        global $DB;
        
        $paid_course_request = "SELECT currency.numeric_code AS currencyid, tariff.amount AS amount, sign.sign
                                  FROM mdl_block_money_paid_course   AS course
                                  JOIN mdl_block_money_tariff        AS tariff   ON course.tariffid = tariff.id
                                  JOIN mdl_block_money_currency_code AS currency ON tariff.currencyid = currency.numeric_code
                                  JOIN mdl_block_money_period        AS period   ON tariff.periodid = period.id
                                  JOIN mdl_block_money_currency_sign AS sign     ON currency.numeric_code = sign.numeric_code
                                 WHERE course.courseid = :courseid;";
        
        $paid_course = $DB->get_record_sql($paid_course_request, ['courseid' => $event->courseid]);

        if ($paid_course) {
            $lesson = new \stdClass();

            $lesson->courseid = $event->courseid;
            $lesson->cmid = $event->contextinstanceid;
            $lesson->currencyid = $paid_course->currencyid;
            $lesson->amount = $paid_course->amount;
            $lesson->date = $event->timecreated;

            $DB->insert_record('block_money_paid_lesson', $lesson);
            $users_request = "SELECT mdl_user.id AS id
                                FROM mdl_user
                                JOIN mdl_user_enrolments ON mdl_user_enrolments.userid = mdl_user.id
                                JOIN mdl_enrol           ON mdl_enrol.id = mdl_user_enrolments.enrolid
                                JOIN mdl_role            ON mdl_role.id = mdl_enrol.roleid
                               WHERE mdl_role.shortname = 'student'
                                     AND mdl_enrol.courseid = :courseid;";
            $check_money_request = "SELECT *
                                      FROM mdl_block_money_cash_account AS account
                                     WHERE account.customerid = :customerid
                                           AND account.currencyid = :currencyid
                                           AND account.amount >= :amount";
            $students = $DB->get_records_sql($users_request, ['courseid' => $event->courseid]);
            
            foreach ($students as $student) {
                $student_order = new \stdClass();

                $student_order->customerid = $student->id;
                $student_order->courseid = $event->courseid;
                $student_order->cmid = $event->contextinstanceid;
                $student_order->currencyid = $paid_course->currencyid;
                $student_order->amount = $paid_course->amount;
                $student_order->date = $event->timecreated;
                
                $account = $DB->get_record_sql($check_money_request, ['customerid' => $student->id, 'currencyid' => $paid_course->currencyid, 'amount' => $paid_course->amount]);

                if ($account) {
                    $DB->insert_record('block_money_payment', $student_order);
                    $account->amount = $account->amount - $paid_course->amount;
                    $DB->update_record('block_money_cash_account', $account);
                }
                else {
                    $DB->insert_record('block_money_credit', $student_order);
                }
            }
        }
    }
}
