<?php
/**
 * Copy this file to config/secrets.php and fill it in. secrets.php is listed in
 * .gitignore: it never goes to GitHub and `git pull` on the server never
 * overwrites it. API keys (iKhokha, Pudo, Groq, ImageKit, Meta) are NOT kept
 * here — they are entered in Admin → Integrations and stored encrypted in the
 * database.
 */
return [
    // Production database (cPanel → MySQL Databases)
    'db_host' => 'localhost',
    'db_name' => 'wyimzdyd_faircart',
    'db_user' => 'wyimzdyd_nexus',
    'db_pass' => '',

    // Outgoing mail: the cPanel mailbox (cPanel → Email Accounts → Connect Devices)
    'smtp_host'   => 'mail.faircart.co.za',
    'smtp_port'   => 465,
    'smtp_secure' => 'ssl',            // 'ssl' on 465, 'tls' on 587
    'smtp_user'   => 'admin@faircart.co.za',
    'smtp_pass'   => '',
    'admin_email' => 'admin@faircart.co.za', // where new-order alerts go
];
