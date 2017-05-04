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

class LatestvideonotiCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'latestvideonoti';

    /**
     * @var string
     */
    protected $description = '최신 비디오 알림 설정 (매일 오전 9시)';

    /**
     * @var string
     */
    protected $usage = '/latestvideonoti <value>' . PHP_EOL .
            'value: on-활성화, off-비활성화';

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

                if (empty($text) && !in_array(strtolower($args[0]), ['on', 'off'], true)) {
                    $result_text[] = 'On 또는 Off를 선택하여 주세요:';
                    $data['reply_markup'] = (new Keyboard(['On', 'Off']))
                            ->setOneTimeKeyboard(true)
                            ->setResizeKeyboard(true)
                            ->setSelective(true);
                } else {
                    $noti = (strcmp(strtolower($text), 'on') === 0) ? 1 : 0;
                    try {
                        $pdo = DB::getPdo();
                        $sth = $pdo->prepare('
                            UPDATE `' . TB_CHAT . '`
                            SET `latest_video_notification` = :noti
                            WHERE `id` = :id
                        ');
                        $sth->bindParam(':id', $chat_id, PDO::PARAM_INT);
                        $sth->bindParam(':noti', $noti, PDO::PARAM_INT);
                        if ($sth->execute()) {
                            $result_text[] = '변경되었습니다.';
                        } else {
                            $result_text[] = '변경에 실패했습니다.';
                        }
                    } catch (PDOException $e) {
                        throw new TelegramException($e->getMessage());
                    }

                    $this->conversation->stop();
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
}
?>
