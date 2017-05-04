<?php
namespace Longman\TelegramBot\Commands\AdminCommands;

use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Exception\TelegramException;
use PDO;
use PDOException;
use Longman\TelegramBot\TelegramLog;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\Keyboard;
use WakeOnLan;

class WolCommand extends AdminCommand
{
    /**
     * @var string
     */
    protected $name = 'wol';

    /**
     * @var string
     */
    protected $description = 'WOL 신호 보내기 또는 장치 관리';

    /**
     * @var string
     */
    protected $usage = '/wol';

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
            $this->conversation = new Conversation($user_id, $chat_id, $this->getName());

            $notes = &$this->conversation->notes;
            !is_array($notes) && $notes = [];

            $state = 0;
            if (isset($notes['state'])) {
                $state = $notes['state'];
            }

            switch ($state) {
                case 0:
                    if (!empty($text) && ($mac = $this->hasMac($text)) !== false) {
                        $wol = new WakeOnLan();
                        $mac = $wol->checkMac($mac);
                        if ($mac !== false) {
                            $result = $wol->wake($mac);
                            if ($result === true) {
                                $result_text[] = 'WOL 신호가 전송되었습니다.';
                            } else {
                                $result_text[] = $result;
                            }
                        } else {
                            $result_text[] = 'Mac address 형식이 올바르지 않습니다.';
                        }
                        $this->conversation->stop();
                        break;

                    } else if (empty($text) || !in_array(strtolower($text), ['add', 'delete'], true)) {
                        $notes['state'] = 0;
                        $this->conversation->update();

                        $keyboard = new Keyboard(['Add', 'Delete']);

                        $rows = $this->selectDevices();
                        if (empty($rows)) {
                            $result_text[] = '옵션을 선택하세요:';

                        } else {
                            $result_text[] = '옵션이나 장치를 선택하세요:';
                            foreach ($rows as $row) {
                                $keyboard->addRow($row['name'] . ' [' . $row['mac'] . ']');
                            }
                        }

                        $data['reply_markup'] = $keyboard
                                ->setOneTimeKeyboard(true)
                                ->setResizeKeyboard(true)
                                ->setSelective(true);
                        break;
                    }

                    $notes['method'] = strtolower($text);
                    $text            = '';
                case 1:
                    $method = $notes['method'];
                    if (empty($text) || ($mac = $this->hasMac($text)) === false) {
                        $notes['state'] = 1;
                        $this->conversation->update();

                        if (strcmp($method, 'add') === 0) {
                            $keyboard = new Keyboard();
                            $output = preg_split('/[\r\n]+/', shell_exec('arp -a'));
                            $device_count = 0;
                            foreach ($output as $line) {
                                if ($this->hasMac($line) !== false) {
                                    $values = preg_split('/\s+/', $line);
                                    $keyboard->addRow(sprintf('%s %s [%s]', $values[0], $values[1], strtoupper($values[3])));
                                    $device_count++;
                                }
                            }
                            if ($device_count === 0) {
                                $result_text[] = '네트워크에 장치가 없습니다.';
                                $this->conversation->stop();

                            } else {
                                $result_text[] = '추가할 장치를 선택하세요:';
                                $data['reply_markup'] = $keyboard
                                        ->setOneTimeKeyboard(true)
                                        ->setResizeKeyboard(true)
                                        ->setSelective(true);
                            }

                        } else if (strcmp($method, 'delete') === 0) {
                            $rows = $this->selectDevices();
                            if (empty($rows)) {
                                $result_text[] = '등록된 장치가 존재하지 않습니다.';
                                $this->conversation->stop();

                            } else {
                                $result_text[] = '삭제할 장치를 선택하세요:';
                                $keyboard = new Keyboard();
                                foreach ($rows as $row) {
                                    $keyboard->addRow($row['name'] . ' [' . $row['mac'] . ']');
                                }
                                $data['reply_markup'] = $keyboard
                                        ->setOneTimeKeyboard(true)
                                        ->setResizeKeyboard(true)
                                        ->setSelective(true);
                            }
                        }
                        break;
                    }

                    $notes['values'] = $text;
                    $text        = '';
                case 2:
                    $this->conversation->update();
                    $wol = new WakeOnLan();
                    $mac = $wol->checkMac($mac);
                    if ($mac !== false) {
                        if (strcmp($method, 'add') === 0) {
                            try {
                                $values = preg_split('/\s+/', $notes['values']);
                                $pdo = DB::getPdo();
                                $sth = $pdo->prepare('
                                    INSERT INTO `wol_device`
                                    (`mac`, `name`)
                                    VALUES
                                    (:mac, :name)
                                ');
                                $sth->bindParam(':mac', $mac, PDO::PARAM_STR, 50);
                                $sth->bindParam(':name', $values[0], PDO::PARAM_STR, 255);
                                if ($sth->execute()) {
                                    $result_text[] = '장치가 추가되었습니다.';
                                } else {
                                    $result_text[] = '장치 추가에 실패했습니다.';
                                }
                            } catch (PDOException $e) {
                                throw new TelegramException($e->getMessage());
                            }
                        } else if (strcmp($method, 'delete') === 0) {
                            try {
                                $pdo = DB::getPdo();
                                $sth = $pdo->prepare('
                                    DELETE FROM `wol_device`
                                    WHERE `mac` = :mac
                                ');
                                $sth->bindParam(':mac', $mac, PDO::PARAM_STR, 50);
                                if ($sth->execute()) {
                                    $result_text[] = '장치가 삭제되었습니다.';
                                } else {
                                    $result_text[] = '장치 삭제에 실패했습니다.';
                                }
                            } catch (PDOException $e) {
                                throw new TelegramException($e->getMessage());
                            }
                        }
                    } else {
                        $result_text[] = 'Mac address 형식이 올바르지 않습니다.';
                    }

                    $this->conversation->stop();
                    break;
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

    private function selectDevices() {
        try {
            $pdo = DB::getPdo();
            $sth = $pdo->prepare('
                SELECT *
                FROM `wol_device`
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

    private function hasMac($data) {
        // 01:23:45:67:89:ab
        if (preg_match('/([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}/', $data, $match))
            return $match[0];
        // 01-23-45-67-89-ab
        else if (preg_match('/([a-fA-F0-9]{2}\-){5}[a-fA-F0-9]{2}/', $data, $match))
            return $match[0];
        // 0123456789ab
        else if (preg_match('/[a-fA-F0-9]{12}/', $data, $match))
            return $match[0];
        // 0123.4567.89ab
        else if (preg_match('/([a-fA-F0-9]{4}\.){2}[a-fA-F0-9]{4}/', $data, $match))
            return $match[0];
        else
            return false;
    }
}

/*
DB structure

CREATE TABLE `wol_device` (
  `mac` CHAR(50) COMMENT 'Unique mac address identifier',
  `name` CHAR(255) COMMENT 'device name',

  PRIMARY KEY (`mac`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/
?>
