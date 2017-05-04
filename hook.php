<?php
include 'basics.php';

// Set the lower and upper limit of valid Telegram IPs.
// https://core.telegram.org/bots/webhooks#the-short-version
$telegram_ip_lower = '149.154.167.197';
$telegram_ip_upper = '149.154.167.233';

// Get the real IP.
$ip = $_SERVER['REMOTE_ADDR'];
foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR'] as $key) {
    $addr = @$_SERVER[$key];
    if (filter_var($addr, FILTER_VALIDATE_IP)) {
        $ip = $addr;
    }
}

// Make sure the IP is valid.
$lower_dec = (float) sprintf("%u", ip2long($telegram_ip_lower));
$upper_dec = (float) sprintf("%u", ip2long($telegram_ip_upper));
$ip_dec    = (float) sprintf("%u", ip2long($ip));
if ($ip_dec < $lower_dec || $ip_dec > $upper_dec) {
    die("Hmm, I don't trust you...");
}

try {
    // Create Telegram API object
    $telegram = new \Longman\TelegramBot\Telegram(BOT_TOKEN, BOT_NAME);

    // Error, Debug and Raw Update logging
    //\Longman\TelegramBot\TelegramLog::initialize();
    //\Longman\TelegramBot\TelegramLog::initErrorLog(__DIR__ . '/log/' . BOT_NAME . '_error.log');
    //\Longman\TelegramBot\TelegramLog::initDebugLog(__DIR__ . '/log/' . BOT_NAME . '_debug.log');
    //\Longman\TelegramBot\TelegramLog::initUpdateLog(__DIR__ . '/log/' . BOT_NAME . '_update.log');

    // Enable MySQL
    $telegram->enableMySql($mysql_credentials);
    // Enable MySQL with table prefix
    //$telegram->enableMySql($mysql_credentials, BOT_NAME . '_');

    // Add an additional commands path
    $telegram->addCommandsPath(COMMANDS_PATH);

    // Enable admin user(s)
    $telegram->enableAdmin(ADMIN_ID);
    //$telegram->enableAdmins([your_telegram_id, other_telegram_id]);

    // Add the channel you want to manage
    //$telegram->setCommandConfig('sendtochannel', ['your_channel' => '@type_here_your_channel']);

    // Here you can set some command specific parameters,
    // for example, google geocode/timezone api key for /date command:
    //$telegram->setCommandConfig('date', ['google_api_key' => 'your_google_api_key_here']);

    // Set custom Upload and Download path
    $telegram->setDownloadPath(__DIR__ . '/download');
    $telegram->setUploadPath(__DIR__ . '/upload');

    // Botan.io integration
    // Second argument are options
    //$telegram->enableBotan('your_token');
    //$telegram->enableBotan('your_token', ['timeout' => 3]);

    // Requests Limiter (tries to prevent reaching Telegram API limits)
    $telegram->enableLimiter();

    // Handle telegram webhook request
    $telegram->handle();
} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    // Silence is golden!
    // echo $e;
    // Log telegram errors
    Longman\TelegramBot\TelegramLog::error($e);
} catch (Longman\TelegramBot\Exception\TelegramLogException $e) {
    // Silence is golden!
    // Uncomment this to catch log initilization errors
    // echo $e;
}
?>
