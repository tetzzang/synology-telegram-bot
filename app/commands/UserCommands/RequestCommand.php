<?php
namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Exception\TelegramException;
use PDO;
use PDOException;
use Common;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\Keyboard;

class RequestCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'request';

    /**
     * @var string
     */
    protected $description = '비디오/문의 요청 또는 요청 목록 보기';

    /**
     * @var string
     */
    protected $usage = '/request <message 또는 list 또는 cancel>';

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

        if (Common::isApproved($chat_id)) {
            if (DB::isDbConnected()) {
                $this->conversation = new Conversation($user_id, $chat_id, $this->getName());

                $notes = &$this->conversation->notes;
                !is_array($notes) && $notes = [];

                $state = 0;
                if (isset($notes['state'])) {
                    $state = $notes['state'];
                }

                switch ($state) {
                    case 0:
                        if (empty($text)) {
                            $notes['state'] = 0;
                            $this->conversation->update();

                            $result_text[] = 'List - 요청목록 확인' . PHP_EOL .
                                    'Cancel - 요청 취소' . PHP_EOL .
                                    'List, Cancel 외 모든 메시지 입력은 요청으로 처리됩니다.' . PHP_EOL .
                                    'List, Cancel을 선택하거나 메시지를 입력하세요:';
                            $data['reply_markup'] = (new Keyboard(['List', 'Cancel']))
                                    ->setOneTimeKeyboard(true)
                                    ->setResizeKeyboard(true)
                                    ->setSelective(true);

                        } else if (strcmp(strtolower($text), 'list') === 0) {
                            $rows = $this->selectRequests(($this->telegram->isAdmin()) ? null : $user_id);
                            if (empty($rows)) {
                                $result_text[] = '요청 목록이 존재하지 않습니다.';
                            } else {
                                foreach ($rows as $row) {
                                    $result_text[] = sprintf('*번호: % 3d, 상태: %s*', $row['id'], $this->statusToString($row['processing_status']));
                                    if ($this->telegram->isAdmin()) {
                                        $result_text[] = sprintf('%s (@%s - /whois%d)', $row['first_name'] . ' ' . $row['last_name'], $row['username'], $row['user_id']);
                                    }
                                    $result_text[] = '```';
                                    $result_text[] = sprintf('요청: %s (%s)', $row['request_message'], $row['created_at']);
                                    if (!empty($row['response_message'])) {
                                        $result_text[] = sprintf('응답: %s (%s)', $row['response_message'], $row['updated_at']);
                                    }
                                    $result_text[] = '```';
                                }
                            }

                            $this->conversation->stop();

                        } else if (strcmp(strtolower($text), 'cancel') === 0) {
                            $notes['state'] = 1;
                            $this->conversation->update();

                            $rows = $this->selectRequests(($this->telegram->isAdmin()) ? null : $user_id);
                            if (empty($rows)) {
                                $result_text[] = '요청 목록이 존재하지 않습니다.';
                            } else {
                                $keyboard = new Keyboard([]);
                                foreach ($rows as $row) {
                                    $keyboard->addRow($row['request_message'] . ' [' . $row['id'] . ']');
                                }
                                $result_text[] = '취소할 요청을 선택하세요:';
                                $data['reply_markup'] = $keyboard
                                        ->setOneTimeKeyboard(true)
                                        ->setResizeKeyboard(true)
                                        ->setSelective(true);
                            }

                        } else {
                            try {
                                $date = Common::getTimestamp($message->getDate());
                                $pdo = DB::getPdo();
                                $sth = $pdo->prepare('
                                    INSERT INTO `user_request`
                                    (`user_id`, `request_message`, `created_at`, `updated_at`)
                                    VALUES
                                    (:user_id, :request_message, :date, :date)
                                ');
                                $sth->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                                $sth->bindParam(':request_message', $text, PDO::PARAM_STR);
                                $sth->bindParam(':date', $date, PDO::PARAM_STR);
                                if ($sth->execute()) {
                                    // notify admin
                                    $notify_text = [];
                                    $notify_text[] = '*사용자 요청 알림*';
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
                                    $notify_text[] = '```';
                                    $notify_text[] = $text;
                                    $notify_text[] = '```';
                                    $notify_data = [
                                        'chat_id'             => ADMIN_ID,
                                        'parse_mode'          => 'Markdown',
                                        'text'                => implode(PHP_EOL, $notify_text),
                                    ];
                                    Request::sendMessage($notify_data);

                                    $result_text[] = '요청이 등록되었습니다.';
                                } else {
                                    $result_text[] = '요청에 실패했습니다.';
                                }
                            } catch (PDOException $e) {
                                throw new TelegramException($e->getMessage());
                            }

                            $this->conversation->stop();
                        }
                        break;

                    // only cancel
                    case 1:
                        if (!empty($text) || is_numeric($this->getRequestId($text))) {
                            try {
                                $id = $this->getRequestId($text);
                                $status = 4;
                                $date = Common::getTimestamp($message->getDate());
                                $pdo = DB::getPdo();
                                $sth = $pdo->prepare('
                                    UPDATE `user_request`
                                    SET `processing_status` = :status, `updated_at` = :date
                                    WHERE `id` = :id
                                ');
                                $sth->bindParam(':id', $id, PDO::PARAM_INT);
                                $sth->bindParam(':status', $status, PDO::PARAM_INT);
                                $sth->bindParam(':date', $date, PDO::PARAM_STR);
                                if ($sth->execute()) {
                                    $result_text[] = '요청이 삭제되었습니다.';
                                } else {
                                    $result_text[] = '요청 삭제에 실패했습니다.';
                                }
                            } catch (PDOException $e) {
                                throw new TelegramException($e->getMessage());
                            }
                        }

                        $this->conversation->stop();
                        break;
                }
            } else {
                $result_text[] = '오류가 발생했습니다.';
            }
        } else {
            $result_text[] = '봇이 승인되지 않았습니다.' . PHP_EOL . '/approval';
        }

        if (empty($data['reply_markup'])) {
            $data['reply_markup'] = Keyboard::remove(['selective' => true]);
        }
        $data['parse_mode'] = 'Markdown';
        $data['text'] = implode(PHP_EOL, $result_text);
        return Request::sendMessage($data);
    }

    private function selectRequests($user_id) {
        try {
            $query = '
                SELECT `req`.`id`, `req`.`request_message`, `req`.`created_at`,
                        `req`.`processing_status`, `req`.`response_message`, `req`.`updated_at`,
                        `user`.`id` AS `user_id`, `user`.`username`, `user`.`first_name`, `user`.`last_name`
                FROM `user_request` AS `req`
                LEFT JOIN `user`
                ON `req`.`user_id` = `user`.`id`
            ';
            $where  = [];
            $tokens = [];

            if (!empty($user_id)) {
                $where[] = '`user`.`id` = :user_id';
                $tokens[':user_id'] = $message->getFrom()->getId();

                $where[] = '`req`.`processing_status` != 4';
            }
            if (!empty($where)) {
                $query .= ' WHERE ' . implode(' AND ', $where);
            }
            $query .= ' ORDER BY `req`.`created_at` ASC';

            $pdo = DB::getPdo();
            $sth = $pdo->prepare($query);
            $sth->execute($tokens);

            $rows = $sth->fetchAll(PDO::FETCH_ASSOC);
            if ($sth->rowCount() > 0) {
                return $rows;
            }
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
        return null;
    }

    private function statusToString($status) {
        switch ($status) {
            case 1:
                return '확인';
            case 2:
                return '완료';
            case 3:
                return '거부';
            case 4:
                return '취소';
            case 0:
            default:
                return '요청';
        }
    }

    private function getRequestId($data) {
        preg_match('/\[[0-9]+\]/', $data, $match);
        return substr($match[0], 1, -1);
    }
}

/*
DB structure

CREATE TABLE IF NOT EXISTS `user_request` (
	`id` bigint UNSIGNED AUTO_INCREMENT COMMENT 'Unique request identifier',
	`user_id` bigint COMMENT 'Unique user identifier',
	`request_message` TEXT COMMENT 'For request messages, the actual UTF-8 text of the message max message length 4096 char utf8mb4',
	`response_message` TEXT COMMENT 'For response messages, the actual UTF-8 text of the message max message length 4096 char utf8mb4',
	`processing_status` tinyint(1) DEFAULT 0 COMMENT 'For request processing status',
	`created_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date creation',
	`updated_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date update',

	PRIMARY KEY (`id`),

	FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
      ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/
?>
