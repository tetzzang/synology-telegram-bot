<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Request;

/**
 * New chat member command
 */
class NewchatmemberCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'Newchatmember';

    /**
     * @var string
     */
    protected $description = 'New Chat Member';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * Command execute method
     *
     * @return mixed
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        $message = $this->getMessage();

        $chat_id = $message->getChat()->getId();
        $member  = $message->getNewChatMember();
        $text    = '안녕하세요!' . PHP_EOL . '봇을 사용하려면 /approval 명령을 사용하여 승인 요청을 먼저 하십시오!';

        if (!$message->botAddedInChat()) {
            $text = '안녕하세요. ' . $member->tryMention() . '!' . PHP_EOL . '봇에 대한 사용법을 확인하시려면 /help를 입력하세요.';
        }

        $data = [
            'chat_id' => $chat_id,
            'text'    => $text,
        ];

        return Request::sendMessage($data);
    }
}
