<?php

return [

    /*
     | Path to the mysqldump binary. On Hostinger it is usually on PATH; on the
     | dev machine point this at the full path under the MySQL install if needed.
     */
    'mysqldump_binary' => env('DB_DUMP_BINARY', 'mysqldump'),

    /*
     | Where the nightly backup confirmation email is sent. Leave blank to skip.
     */
    'notify_email' => env('BACKUP_NOTIFY_EMAIL'),

];
