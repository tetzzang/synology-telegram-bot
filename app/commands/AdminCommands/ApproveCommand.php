<?php
namespace Longman\TelegramBot\Commands\AdminCommands;

use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Exception\TelegramException;
use PDO;
use PDOException;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\Chat;
use Longman\TelegramBot\Entities\Keyboard;

class ApproveCommand extends AdminCommand
{
    /**
     * @var string
     */
    protected $name = 'approve';

    /**
     * @var string
     */
    protected $description = '봇 사용 승인 설정';

    /**
     * @var string
     */
    protected $usage = '/approve <chat_id> <value>' . PHP_EOL .
            'value: 1-요청, 2-승인, 3-거부';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * If this command needs mysql
     *
     * @var boolean
     */
    protected $need_mysql = true;

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
        $user_id = $message->getFrom()->getId();
        $text = trim($message->getText(true));

        $data = [
            'chat_id' => $chat_id,
        ];
        $result_text = [];

        if (DB::isDbConnected()) {
            $pdo = DB::getPdo();

            if (!empty($text) && count($args = preg_split('/\s+/', $text)) === 2 &&
                    (is_numeric($args[0]) && in_array($args[1], [0, 1, 2, 3, '승인', '거부'], true))) {
                try {
                    $id = $args[0];
                    if (is_numeric($args[1]))
                        $approval = $args[0];
                    else
                        $approval = (strcmp($notes['approval'], '승인') === 0) ? 2 : 3;
                    $sth = $pdo->prepare('
                        UPDATE `' . TB_CHAT . '`
                        SET `approval_of_bot_use` = :approval
                        WHERE `id` = :id
                    ');
                    $sth->bindParam(':id', $id, PDO::PARAM_INT);
                    $sth->bindParam(':approval', $approval, PDO::PARAM_INT);
                    if ($sth->execute()) {
                        if ($approval === 2) {
                            // notify user
                            $notify_text = [];
                            $notify_text[] = '봇 사용 승인이 완료되었습니다.';
                            $notify_text[] = '사용 가능한 명령어는 /help 를 통해서 확인 가능합니다.';

                            $notify_data = [
                                'chat_id'             => $id,
                                'text'                => implode(PHP_EOL, $notify_text),
                            ];
                            Request::sendMessage($notify_data);
                        }

                        $result_text[] = '변경되었습니다.';
                    } else {
                        $result_text[] = '변경에 실패했습니다.';
                    }
                } catch (PDOException $e) {
                    throw new TelegramException($e->getMessage());
                }

            } else {
                $this->conversation = new Conversation($user_id, $chat_id, $this->getName());

                $notes = &$this->conversation->notes;
                !is_array($notes) && $notes = [];

                $state = 0;
                if (isset($notes['state'])) {
                    $state = $notes['state'];
                }

                if ($state === 0 && $message->getCommand() !== $this->name) {
                    $text = '[' . substr($message->getCommand(), strlen($this->name)) . ']';
                }

                switch ($state) {
                    case 0:
                        if (empty($text) || !is_numeric($this->getChatId($text))) {
                            $notes['state'] = 0;
                            $this->conversation->update();

                            $results = DB::selectChats(
                                true, //Select groups (group chat)
                                true, //Select supergroups (super group chat)
                                true, //Select users (single chat)
                                null, //'yyyy-mm-dd hh:mm:ss' date range from
                                null, //'yyyy-mm-dd hh:mm:ss' date range to
                                null, //Specific chat_id to select
                                null  //Text to search in user/group name
                            );

                            if (is_array($results)) {
                                $keyboard = new Keyboard([]);
                                $requests = 0;
                                foreach ($results as $result) {
                                    if ($result['approval_of_bot_use'] == 1) {
                                        $result['id'] = $result['chat_id'];
                                        $chat         = new Chat($result);

                                        if ($chat->isPrivateChat()) {
                                            $keyboard->addRow('P ' . $chat->tryMention() . ' [' . $chat->getId() . ']');
                                        } elseif ($chat->isSuperGroup()) {
                                            $keyboard->addRow('S ' . $chat->getTitle() . ' [' . $chat->getId() . ']');
                                        } elseif ($chat->isGroupChat()) {
                                            $keyboard->addRow('G ' . $chat->getTitle() . ' [' . $chat->getId() . ']');
                                        }
                                        $requests++;
                                    }
                                }
                                if ($requests > 0) {
                                    $result_text[] = '채팅을 선택하세요:';
                                    $data['reply_markup'] = $keyboard
                                            ->setOneTimeKeyboard(true)
                                            ->setResizeKeyboard(true)
                                            ->setSelective(true);
                                } else {
                                    $result_text[] = '승인 요청이 없습니다.';
                                    $this->conversation->stop();
                                }
                            }
                            break;
                        }

                        $notes['chat_id'] = $this->getChatId($text);
                        $text             = '';
                    case 1:
                        if (empty($text) || !in_array($text, ['승인', '거부', 2, 3], true)) {
                            $notes['state'] = 1;
                            $this->conversation->update();

                            $result_text[] = '승인 또는 거부를 선택하여 주세요:';
                            $data['reply_markup'] = (new Keyboard(['승인', '거부']))
                                    ->setOneTimeKeyboard(true)
                                    ->setResizeKeyboard(true)
                                    ->setSelective(true);
                            break;
                        }

                        $notes['approval'] = $text;
                        $text              = '';
                    case 2:
                        $this->conversation->update();
                        try {
                            $id = $notes['chat_id'];
                            if (is_numeric($notes['approval']))
                                $approval = $notes['approval'];
                            else
                                $approval = (strcmp($notes['approval'], '승인') === 0) ? 2 : 3;
                            $sth = $pdo->prepare('
                                UPDATE `' . TB_CHAT . '`
                                SET `approval_of_bot_use` = :approval
                                WHERE `id` = :id
                            ');
                            $sth->bindParam(':id', $id, PDO::PARAM_INT);
                            $sth->bindParam(':approval', $approval, PDO::PARAM_INT);
                            if ($sth->execute()) {
                                if ($approval === 2) {
                                    // notify user
                                    $notify_text = [];
                                    $notify_text[] = '봇 사용 승인이 완료되었습니다.';
                                    $notify_text[] = '사용 가능한 명령어는 /help 를 통해서 확인 가능합니다.';

                                    $notify_data = [
                                        'chat_id'             => $id,
                                        'text'                => implode(PHP_EOL, $notify_text),
                                    ];
                                    Request::sendMessage($notify_data);
                                }

                                $result_text[] = '변경되었습니다.';
                            } else {
                                $result_text[] = '변경에 실패했습니다.';
                            }
                        } catch (PDOException $e) {
                            throw new TelegramException($e->getMessage());
                        }

                        $this->conversation->stop();
                        break;
                }
            }
        } else {
            $result_text[] = '오류가 발생했습니다.';
        }
        if (empty($data['reply_markup'])) {
            $data['reply_markup'] = Keyboard::remove(['selective' => true]);
        }
        $data['text'] = implode(PHP_EOL, $result_text);
        return Request::sendMessage($data);
    }

    private function getChatId($data) {
        preg_match('/\[[0-9]+\]/', $data, $match);
        return substr($match[0], 1, -1);
    }
}
?>
