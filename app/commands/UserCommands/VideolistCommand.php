<?php
namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Commands\UserCommand;
use Common;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\Keyboard;

class VideolistCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'videolist';

    /**
     * @var string
     */
    protected $description = '비디오 목록 보기';

    /**
     * @var string
     */
    protected $usage = '/videolist <category> <all 또는  search string>';

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
            if (!empty($text) && count($args = preg_split('/\s+/', $text)) === 2 &&
                    (in_array($args[0], array_keys($GLOBALS['synology_video_category']), true))) {
                $category = array_shift($args);
                $search_string = implode(' ', $args);
                $results = $this->getVideoList($category, strtolower($search_string));
                if (count($results) <= 3)
                    $result_text[] = '검색 결과가 없습니다: ' . $search_string;
                else
                    $result_text = array_merge($result_text, $results);
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
                        if (empty($text) || !in_array($text, array_keys($GLOBALS['synology_video_category']), true)) {
                            $notes['state'] = 0;
                            $this->conversation->update();

                            $result_text[] = '카테고리를 선택해 주세요:';
                            $keyboard = new Keyboard([]);
                            foreach (array_keys($GLOBALS['synology_video_category']) as $label) {
                                $keyboard->addRow($label);
                            }
                            $data['reply_markup'] = $keyboard
                                    ->setOneTimeKeyboard(true)
                                    ->setResizeKeyboard(true)
                                    ->setSelective(true);
                            break;
                        }

                        $notes['category'] = $text;
                        $text              = '';
                    case 1:
                        if (empty($text)) {
                            $notes['state'] = 1;
                            $this->conversation->update();

                            $result_text[] = 'All 또는 검색어를 입력하여 주세요:';
                            $data['reply_markup'] = (new Keyboard(['All']))
                                    ->setOneTimeKeyboard(true)
                                    ->setResizeKeyboard(true)
                                    ->setSelective(true);
                            break;
                        }

                        $notes['searchString'] = $text;
                        $text                  = '';
                    case 2:
                        $this->conversation->update();

                        $category = $notes['category'];
                        $search_string = $notes['searchString'];
                        $results = $this->getVideoList($category, strtolower($search_string));
                        if (count($results) <= 3)
                            $result_text[] = '검색 결과가 없습니다: ' . $search_string;
                        else
                            $result_text = array_merge($result_text, $results);

                        $this->conversation->stop();
                        break;
                }
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

    private function getVideoList($category, $search_string) {
        $path = SYNOLOGY_VIDEO_PATH . $GLOBALS['synology_video_category'][$category];
        $video_list = [];
        if (!empty($path)) {
            $video_list[] = sprintf('*%s*', $category);
            $video_list[] = '```';
            if (!in_array($category, $GLOBALS['hard_scan_video_category'], true)) {
                $files = scandir($path);
                foreach ($files as $file) {
                    if (preg_match('/^[^.@#]/', $file) && (empty($search_string) ||
                            strcmp($search_string, 'all') === 0 || strpos(strtolower($file), $search_string) !== false)) {
                        $video_list[] = sprintf('%s', $file);
                    }
                }
            } else {
                $files = new RegexIterator(new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($path)), '/.+\.(mp4|mkv|avi|m2ts)$/');
                $sorted_files = [];
                foreach ($files as $file) {
                    $sorted_files[$file->getFilename()] = $file;
                }
                ksort($sorted_files);
                foreach ($sorted_files as $file) {
                    if (strpos($file->getPathname(), '@eaDir') === false && (empty($search_string) ||
                            strcmp($search_string, 'all') === 0 || strpos(strtolower($file->getFilename()), $search_string) !== false)) {
                        $video_list[] = sprintf('%s', preg_replace('/\.(mp4|mkv|avi|m2ts)$/', '', $file->getFilename()));
                    }
                }
            }
            $video_list[] = '```';
        }
        return $video_list;
    }

    private function getVideoPath($category) {
        $path = null;
        if (strcmp($category, 'movie') === 0) {
            $path = SYNOLOGY_VIDEO_PATH . 'movie';
        } else if (strcmp($category, 'animation') === 0) {
            $path = SYNOLOGY_VIDEO_PATH . 'animation';
        } else if (strcmp($category, 'tvshow') === 0) {
            $path = SYNOLOGY_VIDEO_PATH . 'TV show';
        } else if (strcmp($category, 'tvdrama') === 0) {
            $path = SYNOLOGY_VIDEO_PATH . 'TV drama';
        }
        return $path;
    }

    private function getDirectoryName($path) {
        $pos = strrpos($path, '/');
        if ($pos !== false) {
            return substr($path, $pos + 1);
        }
    }
}
?>
