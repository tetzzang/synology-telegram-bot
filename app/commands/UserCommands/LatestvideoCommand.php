<?php
namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Exception\TelegramException;
use PDO;
use PDOException;
use Common;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Longman\TelegramBot\TelegramLog;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\Keyboard;

class LatestvideoCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'latestvideo';

    /**
     * @var string
     */
    protected $description = '최근 2주 이내 추가된 비디오 목록 보기';

    /**
     * @var string
     */
    protected $usage = '/latestvideo <category>';

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

        if ($is_bot || Common::isApproved($chat_id)) {
            if (!empty($text) && count($args = preg_split('/\s+/', $text)) === 2 &&
                    (in_array($args[0], array_keys($GLOBALS['synology_video_category']), true) && is_numeric($args[1]))) {
                $results = $this->getLatestVideos($args[0], $args[1]);
                if (count($results) <= 3)
                    $result_text[] = '최근에 추가된 비디오가 없습니다.';
                else
                    $result_text = array_merge($result_text, $results);

            } else {
                $this->conversation = new Conversation($user_id, $chat_id, $this->getName());

                if (empty($text) || !in_array($text, array_keys($GLOBALS['synology_video_category']), true)) {
                    $result_text[] = '카테고리를 선택해 주세요:';
                    $keyboard = new Keyboard([]);
                    foreach (array_keys($GLOBALS['synology_video_category']) as $label) {
                        $keyboard->addRow($label);
                    }
                    $data['reply_markup'] = $keyboard
                            ->setOneTimeKeyboard(true)
                            ->setResizeKeyboard(true)
                            ->setSelective(true);
                } else {
                    $results = $this->getLatestVideos($text, 14);
                    if (count($results) <= 3 && !$is_bot)
                        $result_text[] = '최근에 추가된 비디오가 없습니다.';
                    else
                        $result_text = array_merge($result_text, $results);

                    $this->conversation->stop();
                }
            }
        } else {
            $result_text[] = '봇이 승인되지 않았습니다.' . PHP_EOL . '/approval';
        }

        if (!$is_bot) {
            if ($message->getChat()->isGroupChat() || $message->getChat()->isSuperGroup()) {
                $data['reply_to_message_id'] = $message->getMessageId();
            }
            if (empty($data['reply_markup'])) {
                $data['reply_markup'] = Keyboard::remove(['selective' => true]);
            }
            $data['parse_mode'] = 'Markdown';
            $data['text'] = implode(PHP_EOL, $result_text);
            return Request::sendMessage($data);
        } else {
            if (count($result_text) > 3) {
                if (DB::isDbConnected()) {
                    try {
                        $pdo = DB::getPdo();

                        $sth = $pdo->prepare('
                            SELECT `id`
                            FROM `' . TB_CHAT . '`
                            WHERE `latest_video_notification` = 1
                        ');
                        $sth->execute();

                        array_unshift($result_text, '*최신 비디오 알림*', '_--------------------------------------------------------------_');
                        $rows = $sth->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($rows as $row) {
                            $data = [
                                'chat_id'             => $row['id'],
                                'parse_mode'          => 'Markdown',
                                'text'                => implode(PHP_EOL, $result_text),
                            ];
                            Request::sendMessage($data);
                        }
                    } catch (PDOException $e) {
                        throw new TelegramException($e->getMessage());
                    }
                } else {
                    TelegramLog::error('LatestVideo DB does not connected');
                }
            }
            return Request::emptyResponse();
        }
    }

    private function getLatestVideos($category, $days) {
        $path = SYNOLOGY_VIDEO_PATH . $GLOBALS['synology_video_category'][$category];
        $latest_videos = [];
        if (!empty($path)) {
            $latest_videos[] = sprintf('*%s*', $category);

            $standard_time = strtotime(sprintf('-%d days', $days));

            $latest_videos[] = '```';
            if (!in_array($category, $GLOBALS['hard_scan_video_category'], true)) {
                $files = scandir($path);
                foreach ($files as $file) {
                    if (preg_match('/^[^.@#]/', $file)) {
                        if (filemtime($path . '/' . $file) > $standard_time) {
                            $latest_videos[] = $file;
                        }
                    }
                }
            } else {
                $reg_video = '/.+\.(mp4|mkv|avi|m2ts)$/';
                $files = new RegexIterator(new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($path)), $reg_video);
                $sorted_files = [];
                foreach ($files as $file) {
                    $sorted_files[$file->getFilename()] = $file;
                }
                ksort($sorted_files);
                foreach ($sorted_files as $file) {
                    if (strpos($file->getPathname(), '@eaDir') === false &&
                            $file->getMTime() > $standard_time) {
                        $latest_videos[] = sprintf('%s', preg_replace('/\.(mp4|mkv|avi|m2ts)$/', '', $file->getFilename()));
                    }
                }

            }
            $latest_videos[] = '```';
        }
        return $latest_videos;
    }
}
?>
