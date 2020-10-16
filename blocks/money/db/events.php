<?php

defined('MOODLE_INTERNAL') || die();

$observers = array(
    array(
        'eventname'   => '\mod_bigbluebuttonbn\event\meeting_created',
        'callback'    => 'block_money\observer::meeting_created',
    ),
);
