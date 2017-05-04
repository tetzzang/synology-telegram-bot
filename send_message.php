<?php
include 'basics.php';

$data = [
    'chat_id'   => $_REQUEST['chat_id'],
    'text'      => $_REQUEST['text'],
];
$telegram = new \Longman\TelegramBot\Telegram(BOT_TOKEN, BOT_NAME);
\Longman\TelegramBot\Request::sendMessage($data);
?>
