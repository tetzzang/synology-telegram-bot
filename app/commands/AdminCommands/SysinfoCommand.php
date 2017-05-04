<?php
namespace Longman\TelegramBot\Commands\AdminCommands;

use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Request;

class SysinfoCommand extends AdminCommand
{
    /**
     * @var string
     */
    protected $name = 'sysinfo';

    /**
     * @var string
     */
    protected $description = '시스템 정보 보기';

    /**
     * @var string
     */
    protected $usage = '/sysinfo';

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

        // cpu and memory
        $top_data = preg_split('/\s+/', shell_exec("top -b -n1 | grep -A 4 'load average'"));
        $uptime = '';
        $i = 4;
        while (strpos($top_data[$i+1], 'user') !== 0) {
            $uptime .= $top_data[$i] . ' ';
            $i++;
        }
        $i += 4;
        $result_text[] = sprintf('*Uptime:* `%s`', substr($uptime, 0, -2));
        $result_text[] = sprintf('*CPU:* `사용자 %s%%, 시스템 %s%%, I/O대기 %s%%`', $top_data[$i+15], $top_data[$i+17], $top_data[$i+23]);
        $result_text[] = sprintf('*LoadAvg:* `1분 %s 5분 %s 15분 %s`', $top_data[$i], $top_data[$i+1], $top_data[$i+2]);
	      $result_text[] = sprintf('*Memory:* `%s%% (사용 %sMB, 버퍼/캐시 %sGB, 가용 %sMB)`', round($top_data[$i+38]/$top_data[$i+34]*100, 2), round($top_data[$i+38]*1024), round($top_data[$i+40], 2), round($top_date[$i+36]*1024));

        // network
        $network_data = preg_split('/\s+/', shell_exec("ifconfig eth0 | grep 'RX bytes'"));
	      $rx1 = substr($network_data[2], 6);
	      $tx1 = substr($network_data[6], 6);
        sleep(1);
	      $network_data = preg_split('/\s+/', shell_exec("ifconfig eth0 | grep 'RX bytes'"));
	      $rx2 = substr($network_data[2], 6);
	      $tx2 = substr($network_data[6], 6);
	      $result_text[] = sprintf('*Network:* `RX %sKB/s, TX %sKB/s`', round(($rx2-$rx1)/1024, 2), round(($tx2-$tx1)/1024, 2));

        // storage
        $volume_data = explode(PHP_EOL, shell_exec("df -B1 | grep 'volume'"));
        sort($volume_data);
        $result_text[] = '*Disk:*';
	      foreach ($volume_data as $volume) {
            if (!empty($volume)) {
                $values = preg_split('/\s+/', $volume);
                $volume_name = substr($values[5], 1);
                if (strpos($volume_name, '/') !== false) {
                    $volume_name = substr($volume_name, 0, strpos($volume_name, '/'));
                }
                $result_text[] = sprintf('    `%s %s (사용 %s, 가용 %s)`', $volume_name, $values[4],
                        $this->getHumanFileSize($values[2]), $this->getHumanFileSize($values[3]));
            }
        }

        $data = [
            'chat_id'             => $chat_id,
            'parse_mode'          => 'Markdown',
            'text'                => implode(PHP_EOL, $result_text),
        ];
        return Request::sendMessage($data);
    }

    function getHumanFileSize($bytes, $decimals = 2) {
        $size = array('B','KB','MB','GB','TB','PB','EB','ZB','YB');
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }
}
?>
