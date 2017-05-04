<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\AdminCommands;

use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Entities\Message;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;

/**
 * Admin "/sendtoall" command
 */
class SendtoallCommand extends AdminCommand
{
    /**
     * @var string
     */
    protected $name = 'sendtoall';

    /**
     * @var string
     */
    protected $description = '모든 봇의 사용자에게 메시지 보내기';

    /**
     * @var string
     */
    protected $usage = '/sendtoall <message to send>';

    /**
     * @var string
     */
    protected $version = '1.3.0';

    /**
     * @var bool
     */
    protected $need_mysql = true;

    /**
     * Execute command
     *
     * @return boolean
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        $message = $this->getMessage();

        $chat_id = $message->getChat()->getId();
        $text    = $message->getText(true);

        if ($text === '') {
            $text = '보낼 메시지를 작성하십시오: /sendtoall <message>';
        } else {
            $results = Request::sendToActiveChats(
                'sendMessage', //callback function to execute (see Request.php methods)
                ['text' => $text], //Param to evaluate the request
                true, //Send to groups (group chat)
                true, //Send to super groups chats (super group chat)
                true, //Send to users (single chat)
                null, //'yyyy-mm-dd hh:mm:ss' date range from
                null  //'yyyy-mm-dd hh:mm:ss' date range to
            );

            $total  = 0;
            $failed = 0;

            $text = '수신자에게 보낼 메시지입니다:' . PHP_EOL;

            /** @var ServerResponse $result */
            foreach ($results as $result) {
                $name = '';
                $type = '';
                if ($result->isOk()) {
                    $status = '✔️';

                    /** @var Message $message */
                    $message = $result->getResult();
                    $chat    = $message->getChat();
                    if ($chat->isPrivateChat()) {
                        $name = $chat->getFirstName();
                        $type = '사용자';
                    } else {
                        $name = $chat->getTitle();
                        $type = '채팅';
                    }
                } else {
                    $status = '✖️';
                    ++$failed;
                }
                ++$total;

                $text .= $total . ') ' . $status . ' ' . $type . ' ' . $name . PHP_EOL;
            }
            $text .= '전송 됨: ' . ($total - $failed) . '/' . $total . PHP_EOL;

            if ($total === 0) {
                $text = '사용자 또는 채팅이 없습니다.';
            }
        }

        $data = [
            'chat_id' => $chat_id,
            'text'    => $text,
        ];

        return Request::sendMessage($data);
    }
}
