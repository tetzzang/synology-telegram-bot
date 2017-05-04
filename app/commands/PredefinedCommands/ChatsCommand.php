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
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\Chat;
use Longman\TelegramBot\Request;

class ChatsCommand extends AdminCommand
{
    /**
     * @var string
     */
    protected $name = 'chats';

    /**
     * @var string
     */
    protected $description = '봇이 저장한 모든 채팅 리스트 또는 채팅 검색';

    /**
     * @var string
     */
    protected $usage = '/chats, /chats * or /chats <search string>';

    /**
     * @var string
     */
    protected $version = '1.1.0';

    /**
     * @var bool
     */
    protected $need_mysql = true;

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
        $text    = trim($message->getText(true));

        $results = DB::selectChats(
            true, //Select groups (group chat)
            true, //Select supergroups (super group chat)
            true, //Select users (single chat)
            null, //'yyyy-mm-dd hh:mm:ss' date range from
            null, //'yyyy-mm-dd hh:mm:ss' date range to
            null, //Specific chat_id to select
            ($text === '' || $text === '*') ? null : $text //Text to search in user/group name
        );

        $user_chats        = 0;
        $group_chats       = 0;
        $super_group_chats = 0;

        if ($text === '') {
            $text_back = '';
        } elseif ($text === '*') {
            $text_back = '모든 봇 채팅 목록:' . PHP_EOL;
        } else {
            $text_back = '채팅 검색 결과:' . PHP_EOL;
        }

        if (is_array($results)) {
            foreach ($results as $result) {
                //Initialize a chat object
                $result['id'] = $result['chat_id'];
                $chat         = new Chat($result);

                $whois = $chat->getId();
                if ($this->telegram->getCommandObject('whois')) {
                    // We can't use '-' in command because part of it will become unclickable
                    $whois = '/whois' . str_replace('-', 'g', $chat->getId());
                }

                if ($chat->isPrivateChat()) {
                    if ($text !== '') {
                        $text_back .= '- P ' . $chat->tryMention() . ' [' . $whois . ']: ' .
                                $this->approvalOfBotUseToString($result['approval_of_bot_use']) . ', ' .
                                $this->latestVideoNotificationToString($result['latest_video_notification']) . PHP_EOL;
                    }

                    ++$user_chats;
                } elseif ($chat->isSuperGroup()) {
                    if ($text !== '') {
                        $text_back .= '- S ' . $chat->getTitle() . ' [' . $whois . ']: ' .
                                $this->approvalOfBotUseToString($result['approval_of_bot_use']) . ', ' .
                                $this->latestVideoNotificationToString($result['latest_video_notification']) . PHP_EOL;
                    }

                    ++$super_group_chats;
                } elseif ($chat->isGroupChat()) {
                    if ($text !== '') {
                        $text_back .= '- G ' . $chat->getTitle() . ' [' . $whois . ']: ' .
                                $this->approvalOfBotUseToString($result['approval_of_bot_use']) . ', ' .
                                $this->latestVideoNotificationToString($result['latest_video_notification']) . PHP_EOL;
                    }

                    ++$group_chats;
                }
            }
        }

        if (($user_chats + $group_chats + $super_group_chats) === 0) {
            $text_back = '채팅 없음';
        } else {
            $text_back .= PHP_EOL . '비공개: ' . $user_chats;
            $text_back .= PHP_EOL . '그룹: ' . $group_chats;
            $text_back .= PHP_EOL . '슈퍼 그룹: ' . $super_group_chats;
            $text_back .= PHP_EOL . '총: ' . ($user_chats + $group_chats + $super_group_chats);

            if ($text === '') {
                $text_back .= PHP_EOL . PHP_EOL . '모든 채팅 목록: /' . $this->name . ' *' . PHP_EOL . '채팅 검색: /' . $this->name . ' <검색어>';
            }
        }

        $data = [
            'chat_id' => $chat_id,
            'text'    => $text_back,
        ];

        return Request::sendMessage($data);
    }

    private function approvalOfBotUseToString($approval) {
        $prefix = '승인 ';
        switch ($approval) {
            case 0:
                return $prefix . '안됨';
            case 1:
                return $prefix . '요청';
            case 2:
                return $prefix . '완료';
            case 3:
                return $prefix . '거부';
        }
    }

    private function latestVideoNotificationToString($notification) {
        $prefix = '최신비디오알림 ';
        switch ($notification) {
            case 0:
                return $prefix . 'Off';
            case 1:
                return $prefix . 'On';
        }
    }
}
