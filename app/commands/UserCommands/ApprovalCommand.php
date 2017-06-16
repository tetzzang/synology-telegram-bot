<?php
namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Exception\TelegramException;
use PDO;
use PDOException;
use Longman\TelegramBot\Conversation;

class ApprovalCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'approval';

    /**
     * @var string
     */
    protected $description = '봇 사용 승인 요청';

    /**
     * @var string
     */
    protected $usage = '/approval <message>';

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

        $result_text = [];

        if (DB::isDbConnected()) {
            try {
                $pdo = DB::getPdo();

                $sth = $pdo->prepare('
                    SELECT `approval_of_bot_use`
                    FROM `' . TB_CHAT . '`
                    WHERE `id` = :id
                ');
                $sth->bindParam(':id', $chat_id, PDO::PARAM_INT);
                $sth->execute();

                $result = $sth->fetch(PDO::FETCH_ASSOC);
                switch ($result['approval_of_bot_use']) {
                    case 0: {
                        $this->conversation = new Conversation($user_id, $chat_id, $this->getName());

                        if (empty($text)) {
                            $result_text[] = '당신은 누구입니까? 메시지를 남겨주세요:';
                        } else {
                            $approval = 1;
                            $sth = $pdo->prepare('
                                UPDATE `' . TB_CHAT . '`
                                SET `approval_of_bot_use` = :approval
                                WHERE `id` = :id
                            ');
                            $sth->bindParam(':id', $chat_id, PDO::PARAM_INT);
                            $sth->bindParam(':approval', $approval, PDO::PARAM_INT);
                            if ($sth->execute()) {
                                // notify admin
                                $notify_text = [];
                                $notify_text[] = '*봇 사용 승인 요청 알림*';
                                $notify_text[] = '_--------------------------------------------------------------_';

                                $chat = $message->getChat();
                                $whois = '/whois' . str_replace('-', 'g', $chat->getId());
                                if ($chat->isPrivateChat()) {
                                    $notify_text[] = '- P ' . $chat->tryMention() . ' \[' . $whois . ']';
                                } elseif ($chat->isSuperGroup()) {
                                    $notify_text[] = '- S ' . $chat->getTitle() . ' \[' . $whois . ']';
                                } elseif ($chat->isGroupChat()) {
                                    $notify_text[] = '- G ' . $chat->getTitle() . ' \[' . $whois . ']';
                                }
                                $notify_text[] = 'Message: ' . $text;
                                $notify_text[] = PHP_EOL . '/approve' . str_replace('-', 'g', $chat->getId());
                                $notify_data = [
                                    'chat_id'             => ADMIN_ID,
                                    'parse_mode'          => 'Markdown',
                                    'text'                => implode(PHP_EOL, $notify_text),
                                ];
                                Request::sendMessage($notify_data);

                                $result_text[] = '승인 요청이 완료되었습니다.';
                            } else {
                                $result_text[] = '승인 요청에 실패하였습니다.';
                            }

                            $this->conversation->stop();
                        }
                        break;
                    }
                    case 1: {
                        $result_text[] = '이미 승인 요청을 하였습니다.';
                        break;
                    }
                    case 2: {
                        $result_text[] = '이미 승인 완료 되었습니다.';
                        break;
                    }
                    case 3: {
                        $result_text[] = '승인 요청이 거부되었습니다.';
                        break;
                    }
                }
            } catch (PDOException $e) {
                throw new TelegramException($e->getMessage());
            }
        } else {
            $result_text[] = '오류가 발생했습니다.';
        }

        $data = [
            'chat_id'             => $chat_id,
            'text'                => implode(PHP_EOL, $result_text),
        ];
        return Request::sendMessage($data);
    }
}
?>
