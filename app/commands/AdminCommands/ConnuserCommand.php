<?php
namespace Longman\TelegramBot\Commands\AdminCommands;

use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Exception\TelegramException;
use Synology;

class ConnuserCommand extends AdminCommand
{
    /**
     * @var string
     */
    protected $name = 'connuser';

    /**
     * @var string
     */
    protected $description = '연결된 사용자 보기';

    /**
     * @var string
     */
    protected $usage = '/connuser';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * Command execute method
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse|mixed
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();

        $result_text = [];

        $synology = new Synology(SYNOLOGY_URL);
        if ($synology->login($GLOBALS['synology_credentials'])) {
            $users = $synology->getConnectedUsers();
            $text = trim($message->getText(true));
            if (!empty($users)) {
                if (empty($text) || !is_numeric($text)) {
                    foreach ($users as $user) {
                        if (strcmp($user->from, '::1') !== 0 && // strcmp($user->from, '127.0.0.1') !== 0
                                strcmp($user->who, 'tmadmin') !== 0) { // time machine
                            $result_text[] = sprintf('*%s:* `%s`', $user->who, $user->time);
                            $result_text[] = sprintf('```' . PHP_EOL . '%s (%s - %s)```', $user->descr, $user->type, $user->from);
                        }
                    }
                } else {
                    $standard_time = strtotime(sprintf('-%d minute', $text));
                    foreach ($users as $user) {
                        $connected_time = strtotime($user->time);
                        if (strcmp($user->from, '::1') !== 0 && $connected_time > $standard_time && // strcmp($user->from, '127.0.0.1') !== 0
                                strcmp($user->who, 'tmadmin') !== 0) {
                            $result_text[] = sprintf('*%s:* `%s`', $user->who, $user->time);
                            $result_text[] = sprintf('```' . PHP_EOL . '%s (%s - %s)```', $user->descr, $user->type, $user->from);
                        }
                    }
                }
            }
            $synology->logout();
        } else {
            $result_text[] = '서버 연결에 실패했습니다.';
        }

        if ($message->getFrom()->getUsername() != $this->getTelegram()->getBotName()) {
            if (count($result_text) == 0) {
                $result_text[] = '연결된 사용자가 없습니다.';
            }
            $data = [
                'chat_id'             => $chat_id,
                'parse_mode'          => 'Markdown',
                'text'                => implode(PHP_EOL, $result_text),
            ];
            return Request::sendMessage($data);
        } else {
            if (count($result_text) > 1) {
                array_unshift($result_text, '*연결 알림*', '_--------------------------------------------------------------_');
                $data = [
                    'chat_id'             => ADMIN_ID,
                    'parse_mode'          => 'Markdown',
                    'text'                => implode(PHP_EOL, $result_text),
                ];
                return Request::sendMessage($data);
            } else {
                return Request::emptyResponse();
            }
        }
    }
}
?>
