<?php
include 'basics.php';

if (!isset($_GET['secret']) || $_GET['secret'] !== SECRET) {
    die("I'm safe =)");
}

try {
    // Create Telegram API object
    $telegram = new Longman\TelegramBot\Telegram(BOT_TOKEN, BOT_NAME);

    // Delete webhook
    $result = $telegram->deleteWebhook();
    if ($result->isOk()) {
        echo $result->getDescription();
    }
} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    echo $e;
}
?>
