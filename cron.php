<?php
include 'basics.php';

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

    // Run user selected commands
    $commands = ['/connuser 1', '/download list all'];
    if (strcmp(date('H:i', mktime(9, 0, 0)), date('H:i', time())) === 0) {
        // latest video notifications
        foreach (array_keys($synology_video_category) as $label) {
            $commands[] = '/latestvideo ' . $label . ' 1';
        }
    }
    $telegram->runCommands($commands);
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
