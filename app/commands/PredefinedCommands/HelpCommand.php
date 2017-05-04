<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\Command;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Request;

/**
 * User "/help" command
 */
class HelpCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'help';

    /**
     * @var string
     */
    protected $description = '봇 명렁어 도움말 보기';

    /**
     * @var string
     */
    protected $usage = '/help 또는 /help <command>';

    /**
     * @var string
     */
    protected $version = '1.1.0';

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

        $message_id = $message->getMessageId();
        $command    = trim($message->getText(true));

        //Only get enabled Admin and User commands
        /** @var Command[] $command_objs */
        $command_objs = array_filter($this->telegram->getCommandsList(), function ($command_obj) {
            /** @var Command $command_obj */
            return !$command_obj->isSystemCommand() && $command_obj->isEnabled();
        });

        //If no command parameter is passed, show the list
        if ($command === '') {
            $text = sprintf(
                '%s v. %s' . PHP_EOL . PHP_EOL . '명령어 목록:' . PHP_EOL,
                $this->telegram->getBotName(),
                $this->telegram->getVersion()
            );

            foreach ($command_objs as $command) {
                if (!$command->showInHelp()) {
                    continue;
                }

                $text .= sprintf(
                    '/%s - %s' . PHP_EOL,
                    $command->getName(),
                    $command->getDescription()
                );
            }

            $text .= PHP_EOL . '명령어에 대한 도움말을 보려면 다음을 입력하십시오:' . PHP_EOL . '/help <command> (예: /help whoami)';
        } else {
            $command = str_replace('/', '', $command);
            if (isset($command_objs[$command])) {
                /** @var Command $command_obj */
                $command_obj = $command_objs[$command];
                $text = sprintf(
                    '명령어: %s v%s' . PHP_EOL .
                    '설명: %s' . PHP_EOL .
                    '사용법: %s',
                    $command_obj->getName(),
                    $command_obj->getVersion(),
                    $command_obj->getDescription(),
                    $command_obj->getUsage()
                );
            } else {
                $text = '/' . $command . ' 명령어를 찾을 수 없습니다.';
            }
        }

        $data = [
            'chat_id'             => $chat_id,
            'reply_to_message_id' => $message_id,
            'text'                => $text,
        ];

        return Request::sendMessage($data);
    }
}
