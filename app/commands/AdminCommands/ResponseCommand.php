<?php
namespace Longman\TelegramBot\Commands\AdminCommands;

use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Exception\TelegramException;
use PDO;
use PDOException;
use Common;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\Keyboard;

class ResponseCommand extends AdminCommand
{
    /**
     * @var string
     */
    protected $name = 'response';

    /**
     * @var string
     */
    protected $description = '요청에 대한 응답 처리';

    /**
     * @var string
     */
    protected $usage = '/response <id> <status> <message>' . PHP_EOL .
            'status: 0-요청, 1-확인, 2-완료, 3-거부';

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
            if (!empty($text) && count($args = preg_split('/\s+/', $text)) > 3 &&
                    is_numeric($args[0]) && is_numeric($args[1])) {
                $id = array_shift($args);
                $status = array_shift($args);
                $response = implode(' ', $args);
                $date = Common::getTimestamp($message->getDate());

                if ($this->updateResponse($id, $status, $response, $date)) {
                    $result_text[] = '응답이 등록되었습니다.';
                } else {
                    $result_text[] = '응답 등록에 실패했습니다.';
                }

            } else {
                $this->conversation = new Conversation($user_id, $chat_id, $this->getName());

                $notes = &$this->conversation->notes;
                !is_array($notes) && $notes = [];

                $state = 0;
                if (isset($notes['state'])) {
                    $state = $notes['state'];
                }

                switch ($state) {
                    case 0:
                        if (empty($text) || !is_numeric($this->getRequestId($text))) {
                            $notes['state'] = 0;
                            $this->conversation->update();

                            $rows = $this->selectRequests();
                            if (empty($rows)) {
                                $result_text[] = '요청 목록이 존재하지 않습니다.';
                            } else {
                                $keyboard = new Keyboard([]);
                                foreach ($rows as $row) {
                                    $keyboard->addRow($row['request_message'] . ' [' . $row['id'] . ']');
                                }
                                $result_text[] = '요청을 선택하세요:';
                                $data['reply_markup'] = $keyboard
                                        ->setOneTimeKeyboard(true)
                                        ->setResizeKeyboard(true)
                                        ->setSelective(true);
                            }
                            break;
                        }

                        $notes['id'] = $this->getRequestId($text);
                        $text        = '';
                    case 1:
                        if (empty($text) || !in_array($text, ['확인', '완료', '거부', 1, 2, 3], true)) {
                            $notes['state'] = 1;
                            $this->conversation->update();

                            $result_text[] = '상태를 선택하여 주세요:';
                            $data['reply_markup'] = (new Keyboard(['확인', '완료', '거부']))
                                    ->setOneTimeKeyboard(true)
                                    ->setResizeKeyboard(true)
                                    ->setSelective(true);
                            break;
                        }

                        $notes['status'] = $text;
                        $text            = '';
                    case 2:
                        if (empty($text)) {
                            $notes['state'] = 2;
                            $this->conversation->update();

                            $result_text[] = '메시지를 입력하세요:';
                            break;
                        }

                        $notes['response'] = $text;
                        $text            = '';
                    case 3:
                        $this->conversation->update();

                        $id = $notes['id'];
                        if (is_numeric($notes['status']))
                            $status = $notes['status'];
                        else
                            $status = $this->stringToStatus($notes['status']);
                        $response = $notes['response'];
                        $date = Common::getTimestamp($message->getDate());

                        if ($this->updateResponse($id, $status, $response, $date)) {
                            $result_text[] = '응답이 등록되었습니다.';
                        } else {
                            $result_text[] = '응답 등록에 실패했습니다.';
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
        $data['parse_mode'] = 'Markdown';
        $data['text'] = implode(PHP_EOL, $result_text);
        return Request::sendMessage($data);
    }

    private function selectRequests() {
        try {
            $pdo = DB::getPdo();
            $sth = $pdo->prepare('
                SELECT `req`.`id`, `req`.`request_message`, `req`.`created_at`,
                        `req`.`processing_status`, `req`.`response_message`, `req`.`updated_at`,
                        `user`.`id` AS `user_id`, `user`.`username`, `user`.`first_name`, `user`.`last_name`
                FROM `user_request` AS `req`
                LEFT JOIN `user`
                ON `req`.`user_id` = `user`.`id`
                WHERE `req`.`processing_status` IN (0, 1)
                ORDER BY `req`.`created_at` ASC
            ');
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

    private function updateResponse($id, $status, $response, $date) {
        try {
            $pdo = DB::getPdo();
            $sth = $pdo->prepare('
                UPDATE `user_request`
                SET `processing_status` = :status, `response_message` = :response, `updated_at` = :date
                WHERE `id` = :id
            ');
            $sth->bindParam(':id', $id, PDO::PARAM_INT);
            $sth->bindParam(':status', $status, PDO::PARAM_INT);
            $sth->bindParam(':response', $response, PDO::PARAM_INT);
            $sth->bindParam(':date', $date, PDO::PARAM_STR);
            if ($sth->execute()) {
                if ($status == 2) {
                    $sth = $pdo->prepare('
                        SELECT `user_id`, `request_message`, `created_at`
                        FROM `user_request`
                        WHERE `id` = :id
                    ');
                    $sth->bindParam(':id', $id, PDO::PARAM_INT);
                    $sth->execute();

                    if (($result = $sth->fetch(PDO::FETCH_ASSOC)) !== false) {
                        // notify user
                        $notify_text = [];
                        $notify_text[] = '*요청 처리 완료 알림*';
                        $notify_text[] = '_--------------------------------------------------------------_';
                        $notify_text[] = '```';
                        $notify_text[] = sprintf('요청: %s (%s)', $result['request_message'], $result['created_at']);
                        $notify_text[] = sprintf('응답: %s (%s)', $response, $date);
                        $notify_text[] = '```';
                        $notify_data = [
                            'chat_id'             => $result['user_id'],
                            'parse_mode'          => 'Markdown',
                            'text'                => implode(PHP_EOL, $notify_text),
                        ];
                        Request::sendMessage($notify_data);
                    }
                }
                return true;
            }
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
        return false;
    }

    private function stringToStatus($string) {
        if (strcmp($string, '확인') === 0) {
            return 1;
        } else if (strcmp($string, '완료') === 0) {
            return 2;
        } else if (strcmp($string, '거부') === 0) {
            return 3;
        }
        return 0;
    }

    private function getRequestId($data) {
        preg_match('/\[[0-9]+\]/', $data, $match);
        return substr($match[0], 1, -1);
    }
}
?>
