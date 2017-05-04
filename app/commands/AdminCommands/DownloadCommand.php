<?php
namespace Longman\TelegramBot\Commands\AdminCommands;

use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Request;
use Synology;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Exception\TelegramException;
use PDO;
use PDOException;
use Longman\TelegramBot\TelegramLog;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\Keyboard;

class DownloadCommand extends AdminCommand
{
    /**
     * @var string
     */
    protected $name = 'download';

    /**
     * @var string
     */
    protected $description = '다운로드 스테이션 작업목록 보기/추가/삭제';

    /**
     * @var string
     */
    protected $usage = '/download <list 또는 create 또는 delete>';

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
        $is_bot = (strcmp($message->getFrom()->getUsername(), $this->getTelegram()->getBotUsername()) === 0) ? true : false;

        $data = [
            'chat_id' => $chat_id,
        ];
        $result_text = [];

        if (!empty($text) && count($args = preg_split('/\s+/', $text)) >= 2 &&
                in_array(strtolower($args[0]), ['list', 'create', 'delete'], true)) {
            $synology = new Synology(SYNOLOGY_URL);
            if ($synology->login($GLOBALS['synology_credentials'])) {
                $method = strtolower(array_shift($args));

                if (strcmp($method, 'list') === 0) {
                    $search_string = strtolower(implode(' ', $args));
                    $results = $this->getDownloadList($synology, $is_bot, $search_string);
                    if (count($results) === 0)
                        $result_text[] = '다운로드 스테이션 작업목록이 없습니다.';
                    else
                        $result_text = array_merge($result_text, $results);

                } else if (strcmp($method, 'create') === 0) {
                    $url = $args[0];
                    if ($this->isDownloadUrl($url)) {
                        if ($synology->createDownloadUrl($url)) {
                            $result_text[] = '항목이 추가되었습니다.';
                        } else {
                            $result_text[] = '추가에 실패했습니다.';
                        }
                    } else {
                        $result_text[] = '지원하는 URL이 아닙니다.';
                    }

                } else if (strcmp($method, 'delete') === 0) {
                    if (strcmp(strtolower($args[0]), 'all') === 0) {
                        $results = $this->deleteAll($synology);
                        if (count($results) === 0) {
                            $result_text[] = '다운로드 스테이션 작업목록이 없습니다.';
                        } else {
                            $result_text = array_merge($result_text, $results);
                        }
                    } else if ($this->isDownloadId($args[0])) {
                        if ($synology->deleteDownload($args[0])) {
                            $result_text[] = '항목이 삭제되었습니다.';
                        } else {
                            $result_text[] = '삭제에 실패했습니다.';
                        }
                    } else {
                        $result_text[] = 'Id 형식이 올바르지 않습니다.';
                    }
                }

                $synology->logout();
            } else {
                $result_text[] = '서버 연결에 실패했습니다.';
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
                    if (empty($text) || !in_array(strtolower($text), ['list', 'create', 'delete'], true)) {
                        $notes['state'] = 0;
                        $this->conversation->update();

                        $result_text[] = '옵션을 선택하세요:';
                        $data['reply_markup'] = (new Keyboard(['List', 'Create', 'Delete']))
                                ->setOneTimeKeyboard(true)
                                ->setResizeKeyboard(true)
                                ->setSelective(true);
                        break;
                    }

                    $notes['method'] = strtolower($text);
                    $text        = '';
                case 1:
                    $method = $notes['method'];
                    if ((in_array($method, ['list', 'delete']) && empty($text)) ||
                            (strcmp($method, 'create') === 0 && empty($text) && strcmp($message->getType(), 'document') !== 0)) {
                        $notes['state'] = 1;
                        $this->conversation->update();

                        if (strcmp($method, 'list') === 0) {
                            $result_text[] = 'All 또는 검색어를 입력하여 주세요:';
                            $data['reply_markup'] = (new Keyboard(['All']))
                                    ->setOneTimeKeyboard(true)
                                    ->setResizeKeyboard(true)
                                    ->setSelective(true);

                        } else if (strcmp($method, 'create') === 0) {
                            $result_text[] = 'URL을 입력하거나 파일을 전송하여 주세요:';
                            $result_text[] = '(url eg. http://, https://, magnet:, ftp://, ftps://)';
                            $result_text[] = '(file eg. download.torrent, download.nzb, urls.txt)';

                        } else if (strcmp($method, 'delete') === 0) {
                            $synology = new Synology(SYNOLOGY_URL);
                            if ($synology->login($GLOBALS['synology_credentials'])) {
                                $list = $synology->getDownloadList();
                                if (!empty($list)) {
                                    $keyboard = new Keyboard(['All']);
                                    foreach ($list as $item) {
                                        $keyboard->addRow($item->title . ' [' . $item->id . ']');
                                    }
                                    $result_text[] = '삭제할 항목을 선택하세요:';
                                    $data['reply_markup'] = $keyboard
                                            ->setOneTimeKeyboard(true)
                                            ->setResizeKeyboard(true)
                                            ->setSelective(true);
                                } else
                                    $result_text[] = '다운로드 스테이션 작업목록이 없습니다.';

                                $synology->logout();
                            } else {
                                $result_text[] = '서버 연결에 실패했습니다.';
                            }
                        }
                        break;
                    }

                    if (strcmp($method, 'list') === 0) {
                        $notes['search_string'] = strtolower($text);

                    } else if (strcmp($method, 'create') === 0) {
                        if (strcmp($message->getType(), 'document') === 0)
                            $notes['file_id'] = $message->getDocument()->getFileId();
                        else
                            $notes['url'] = $text;

                    } else if (strcmp($method, 'delete') === 0) {
                        $notes['id'] = $text;
                    }
                case 2:
                    $this->conversation->update();

                    $synology = new Synology(SYNOLOGY_URL);
                    if ($synology->login($GLOBALS['synology_credentials'])) {
                        if (strcmp($method, 'list') === 0) {
                            $search_string = $notes['search_string'];
                            $results = $this->getDownloadList($synology, $is_bot, $search_string);
                            if (count($results) === 0)
                                $result_text[] = '다운로드 스테이션 작업목록이 없습니다.';
                            else
                                $result_text = array_merge($result_text, $results);

                        } else if (strcmp($method, 'create') === 0) {
                            if (strcmp($message->getType(), 'document') === 0) {
                                $file_id = $notes['file_id'];
                                $response = Request::getFile(['file_id' => $file_id]);
                                if ($response->isOk()) {
                                    $file = $response->getResult();
                                    Request::downloadFile($file);

                                    $file_path = $this->telegram->getDownloadPath() . '/' . $file->getFilePath();
                                    if ($this->isDownloadFile($file_path)) {
                                        if ($synology->createDownloadFile($file_path)) {
                                            $result_text[] = '항목이 추가되었습니다.';
                                        } else {
                                            $result_text[] = '추가에 실패했습니다.';
                                        }
                                    } else {
                                        $result_text[] = '지원하는 파일이 아닙니다.';
                                    }
                                }

                            } else {
                                $url = $notes['url'];
                                if ($this->isDownloadUrl($url)) {
                                    if ($synology->createDownloadUrl($url)) {
                                        $result_text[] = '항목이 추가되었습니다.';
                                    } else {
                                        $result_text[] = '추가에 실패했습니다.';
                                    }
                                } else {
                                    $result_text[] = '지원하는 URL이 아닙니다.';
                                }
                            }

                        } else if (strcmp($method, 'delete') === 0) {
                            if (strcmp(strtolower($notes['id']), 'all') === 0) {
                                $results = $this->deleteAll($synology);
                                if (count($results) === 0) {
                                    $result_text[] = '다운로드 스테이션 작업목록이 없습니다.';
                                } else {
                                    $result_text = array_merge($result_text, $results);
                                }
                            } else {
                                $id = $this->getDownloadId($notes['id']);
                                if ($this->isDownloadId($id)) {
                                    if ($synology->deleteDownload($id)) {
                                        $result_text[] = '항목이 삭제되었습니다.';
                                    } else {
                                        $result_text[] = '삭제에 실패했습니다.';
                                    }
                                } else {
                                    $result_text[] = 'Id 형식이 올바르지 않습니다.';
                                }
                            }
                        }

                        $synology->logout();
                    } else {
                        $result_text[] = '서버 연결에 실패했습니다.';
                    }

                    $this->conversation->stop();
                    break;
            }
        }

        if (!$is_bot) {
            if (empty($data['reply_markup'])) {
                $data['reply_markup'] = Keyboard::remove(['selective' => true]);
            }
            $data['parse_mode'] = 'Markdown';
            $data['text'] = implode(PHP_EOL, $result_text);
            return Request::sendMessage($data);
        } else {
            if (count($result_text) > 1) {
                array_unshift($result_text, '*다운로드 알림*', '_--------------------------------------------------------------_');
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

    private function getDownloadList($synology, $is_bot, $search_string) {
        $list = $synology->getDownloadList();
        $download_list = [];
        if (!empty($list)) {
            if (!$is_bot) {
                foreach ($list as $item) {
                    if (empty($search_string) || strcmp(strtolower($search_string), 'all') === 0 ||
                            strpos(strtolower($item->title), $search_string) !== false) {
                        $download_list[] = sprintf('*%s:* `%s`', $item->title, $item->status);
                        $download_list[] = sprintf('```' . PHP_EOL . 'Id: %s, Size: %s, User: %s' . PHP_EOL . '```',
                                $item->id, $this->getHumanFileSize($item->size), $item->username);
                    }
                }
            } else {
                if (DB::isDbConnected()) {
                    try {
                        $pdo = DB::getPdo();

                        foreach ($list as $item) {
                            if (strcmp($item->status, 'finished') === 0) {
                                $sth = $pdo->prepare('
                                    SELECT `status`
                                    FROM `down_list`
                                    WHERE `id` = :id
                                ');
                                $sth->bindParam(':id', $item->id, PDO::PARAM_STR, 50);
                                $sth->execute();

                                if ((($result = $sth->fetch(PDO::FETCH_ASSOC)) === false || strcmp($result['status'], 'finished') !== 0) &&
                                        (empty($search_string) || strcmp(strtolower($search_string), 'all') === 0 || strpos(strtolower($item->title), $search_string) !== false)) {
                                    $download_list[] = sprintf('*%s:* `%s`', $item->title, $item->status);
                                    $download_list[] = sprintf('```' . PHP_EOL . 'Id: %s, Size: %s, User: %s' . PHP_EOL . '```',
                                            $item->id, $this->getHumanFileSize($item->size), $item->username);
                                }
                            }
                        }

                        // replace downlist table
                        $pdo->query('DELETE FROM `down_list`');
                        foreach ($list as $item) {
                            $sth = $pdo->prepare('
                                INSERT INTO `down_list`
                                (`id`, `title`, `account`, `status`)
                                VALUES
                                (:id, :title, :account, :status)
                            ');
                            $sth->bindParam(':id', $item->id, PDO::PARAM_STR, 50);
                            $sth->bindParam(':title', $item->title, PDO::PARAM_STR);
                            $sth->bindParam(':account', $item->username, PDO::PARAM_STR, 255);
                            $sth->bindParam(':status', $item->status, PDO::PARAM_STR, 255);
                            $sth->execute();
                        }
                    } catch (PDOException $e) {
                        throw new TelegramException($e->getMessage());
                    }
                } else {
                    TelegramLog::error('DownList DB does not connected');
                }
            }
        }
        return $download_list;
    }

    private function getHumanFileSize($bytes, $decimals = 2) {
        $size = array('B','KB','MB','GB','TB','PB','EB','ZB','YB');
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }

    private function isDownloadId($id) {
        return preg_match('/^(dbid_)[0-9]{4}$/', $id);
    }

    private function getDownloadId($data) {
        preg_match('/\[(dbid_)[0-9]{4}\]/', $data, $match);
        return substr($match[0], 1, -1);
    }

    private function isDownloadFile($file) {
        return preg_match('/(.torrent|.nbz|.txt)$/', strtolower($file));
    }

    private function isDownloadUrl($url) {
        return preg_match('/^(http:\/\/|https:\/\/|magnet:|ftp:\/\/|ftps:\/\/|sftp:\/\/)/', strtolower($url));
    }

    private function deleteAll($synology) {
        $list = $synology->getDownloadList();
        $delete_list = [];
        if (!empty($list)) {
            foreach ($list as $item) {
                if ($synology->deleteDownload($item->id)) {
                    $delete_list[] = sprintf('*%s:* `success`', $item->title);
                } else {
                    $delete_list[] = sprintf('*%s:* `failure`', $item->title);
                }
            }
        }
        return $delete_list;
    }
}

/*
DB structure

CREATE TABLE `down_list` (
  `id` CHAR(50) COMMENT 'Unique download station item identifier',
  `title` text COMMENT 'download station item title',
  `account` CHAR(255) COMMENT 'download station item user',
  `status` CHAR(255) COMMENT 'download station item status',

  PRIMARY KEY (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/
?>
