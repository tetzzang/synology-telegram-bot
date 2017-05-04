<?php
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Exception\TelegramException;
use PDO;
use PDOException;
use Longman\TelegramBot\TelegramLog;

class Common {

	public static function isApproved($chat_id) {
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
			return $result['approval_of_bot_use'] == 2;
		} catch (PDOException $e) {
			throw new TelegramException($e->getMessage());
		}
		return false;
	}

    public static function getTimestamp($time = null) {
    	if ($time === null) {
    		$time = time();
    	}

    	return date('Y-m-d H:i:s', $time);
    }

	public static function startsWith($haystack, $needle) {
		$length = strlen($needle);
		return (substr($haystack, 0, $length) === $needle);
	}
}
?>
