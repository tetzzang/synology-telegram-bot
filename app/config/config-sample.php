<?php
// Secret key required for set and unset
define('SECRET', 'secret_key');

// Bot webhook url
define('HOOK_URL', 'https://yourhostname/bot/hook.php');

// Bot Info
define('BOT_TOKEN', 'bot_token');
define('BOT_NAME', 'my_bot_name');

// Bot DB credentials
$mysql_credentials = [
    'host'     => 'localhost',
    'user'     => 'db_user',
    'password' => 'db_passwd',
    'database' => 'db_name',
];

// Commands
define('COMMANDS_PATH', dirname(__DIR__) . '/commands/');

// Admin chat id
define('ADMIN_ID', xxxxxxxx);

// synology address
define('SYNOLOGY_URL', 'http://localhost:5000');

// Synology credentials
$synology_credentials = [
    'account'   => 'admin',
    'password'  => 'password',
];

// Synology video directory path
define('SYNOLOGY_VIDEO_PATH', '/var/services/video/');

// Synology video sub directory name
// 'label'      => 'real directory name'
$synology_video_category = [
    'Movie'     => 'movie',
    'Animation' => 'animation',
    'TV-Show'    => 'TV show',
    'TV-Drama'   => 'TV drama',
];
$hard_scan_video_category = [
    'TV-Show',
]
?>
