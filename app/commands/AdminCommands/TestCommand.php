<?php
namespace Longman\TelegramBot\Commands\AdminCommands;

use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Request;

class TestCommand extends AdminCommand
{
    /**
     * If this command is enabled
     *
     * @var boolean
     */
    protected $enabled = false;

    /**
     * @var string
     */
    protected $name = 'test';

    /**
     * @var string
     */
    protected $description = '테스트용';

    /**
     * @var string
     */
    protected $usage = '/test';

    /**
     * @var string
     */
    protected $version = '1.0.0';

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

        $result_text = [];

        // $data = preg_split('/\s+/', shell_exec('arp -a'));
        $output = preg_split('/[\r\n]+/', trim(shell_exec('arp -a')));
        foreach ($output as $line) {
            if ($this->hasMac($line) !== false) {
                $values = preg_split('/\s+/', $line);
                $result_text[] = sprintf('%s %s [%s]', $values[0], $values[1], $values[3]);
            }
        }
        $result_text[] = ' line count '. count($output);

        $data = [
            'chat_id'             => $chat_id,
            'text'                => implode(PHP_EOL, $result_text),
        ];
        return Request::sendMessage($data);
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

    function getHumanFileSize($bytes, $decimals = 2) {
        $size = array('B','KB','MB','GB','TB','PB','EB','ZB','YB');
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }
}
?>
