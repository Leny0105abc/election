<?php

return [

    /*
    | Timezone used for every date/time shown to people. Database timestamps
    | are always stored in UTC (config/app.php timezone stays UTC).
    */
    'display_timezone' => env('APP_TIMEZONE', 'Asia/Manila'),

    /*
    | Grade levels offered by the school (Junior + Senior High School).
    */
    'grade_levels' => [7, 8, 9, 10, 11, 12],

    /*
    | Length of the numeric PIN generated for each student.
    */
    'pin_length' => (int) env('SCHOOLVOTE_PIN_LENGTH', 6),

    /*
    | bcrypt cost for PIN hashes. 10 keeps bulk generation of a full grade
    | level fast enough for shared hosting while still being slow to brute force.
    */
    'pin_hash_rounds' => (int) env('SCHOOLVOTE_PIN_ROUNDS', 10),

    /*
    | When true, staff accounts must enable two-factor authentication before
    | using the admin area. Strongly recommended in production.
    */
    'require_staff_two_factor' => (bool) env('SCHOOLVOTE_REQUIRE_STAFF_2FA', false),

    /*
    | Students are signed out after this many minutes without activity.
    */
    'student_idle_timeout' => (int) env('SCHOOLVOTE_STUDENT_IDLE_MINUTES', 10),

    /*
    | Student login throttling. Many students share one school Wi-Fi IP,
    | so the per-IP limit is generous while the per-ID limit is strict.
    */
    'student_login_attempts_per_id' => 5,
    'student_login_attempts_per_ip' => 120,

    /*
    | Candidate photo / logo upload limits (kilobytes).
    */
    'max_image_kb' => 2048,

];
