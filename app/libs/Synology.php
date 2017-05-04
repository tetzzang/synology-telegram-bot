<?php
define('API_QUERY', '/webapi/query.cgi');
define('API_AUTH', '/webapi/auth.cgi');
define('API_DSM_CONNECTION', '/webapi/dsm/connection.cgi');		// for DMS 5.X
define('API_ENTRY', '/webapi/entry.cgi');						// for DSM 6.X

define('API_DOWNLOAD_STATION_TASK', '/webapi/DownloadStation/task.cgi');
define('API_DOWNLOAD_STATION_BTSEARCH', '/webapi/DownloadStation/btsearch.cgi');

class Synology {

	private $server_url;
	private $sid;

	public function __construct($server_url) {
		if (empty($server_url)) {
			throw new Exception("Server URL not defined!");
		}
		$this->server_url = $server_url;
	}

	private function callAPIWithGET($api, $args) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->server_url . $api . '?' . http_build_query($args));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

	private function callAPIWithPOST($api, $args) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->server_url . $api);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
		curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

	public function getApiInfo($query = 'all') {
		$args = [
			'api'		=> 'SYNO.API.Info',
			'version'	=> 1,
			'method'	=> 'query',
			'query'		=> $query,
		];
		$json = $this->callAPIWithGET(API_QUERY, $args);
		$obj = json_decode($json);
		if ($obj->success)
			return $obj->data;
		return null;
	}

	public function login($credentials) {
		if (!empty($credentials)) {
			$args = [
				'api'		=> 'SYNO.API.Auth',
				'version'	=> 6,
				'method'	=> 'login',
				'session'	=> 'Bot',
				'account'	=> $credentials['account'],
				'passwd'	=> $credentials['password'],
			];
			$json = $this->callAPIWithGET(API_AUTH, $args);
			$obj = json_decode($json);
			if ($obj->success)
				$this->sid = $obj->data->sid;
			return $obj->success;
		}
		return false;
	}

	public function logout() {
		if (!empty($this->sid)) {
			$args = [
				'_sid'		=> $this->sid,
				'api'		=> 'SYNO.API.Auth',
				'version'	=> 6,
				'method'	=> 'logout',
				'session'	=> 'Bot',
			];
			$json = $this->callAPIWithGET(API_AUTH, $args);
			$obj = json_decode($json);
			if ($obj->success) {
				unset($this->sid);
			}
			return $obj->success;
		}
		return false;
	}

	public function getSID() {
		return $this->sid;
	}

	public function getConnectedUsers() {
		if (!empty($this->sid)) {
			/* for DSM 5.X
			$args = [
				'_sid'		=> $this->sid,
				'api'		=> 'SYNO.DSM.Connection',
				'version'	=> 1,
				'method'	=> 'list',
			];
			$json = $this->callAPIWithGET(API_DSM_CONNECTION, $args);
			$obj = json_decode($json);
			if ($obj->success) {
				return $obj->data->connections;
			}
			*/
			/* for DSM 6.X */
			$args = [
				'_sid'		=> $this->sid,
				'api'		=> 'SYNO.Core.CurrentConnection',
				'version'	=> 1,
				'method'	=> 'list',
			];
			$json = $this->callAPIWithGET(API_ENTRY, $args);
			$obj = json_decode($json);
			if ($obj->success) {
				return $obj->data->items;
			}
		}
		return null;
	}

	public function getDownloadList() {
		if (!empty($this->sid)) {
			$args = [
				'_sid'		=> $this->sid,
				'api'		=> 'SYNO.DownloadStation.Task',
				'version'	=> 1,
				'method'	=> 'list',
			];
			$json = $this->callAPIWithGET(API_DOWNLOAD_STATION_TASK, $args);
			$obj = json_decode($json);
			if ($obj->success && $obj->data->total > 0) {
				return $obj->data->tasks;
			}
		}
		return null;
	}

	public function deleteDownload($id) {
		if (!empty($this->sid) && !empty($id)) {
			$args = [
				'_sid'		=> $this->sid,
				'api'		=> 'SYNO.DownloadStation.Task',
				'version'	=> 1,
				'method'	=> 'delete',
				'id'		=> $id,
			];
			$json = $this->callAPIWithGET(API_DOWNLOAD_STATION_TASK, $args);
			$obj = json_decode($json);
			return $obj->success;
		}
		return null;
	}

	public function createDownloadUrl($url, $destination = 'downloads') {
		if (!empty($this->sid) && !empty($url)) {
			$args = [
				'_sid'			=> $this->sid,
				'api'			=> 'SYNO.DownloadStation.Task',
				'version'		=> 1,
				'method'		=> 'create',
				'destination'	=> $destination,
				'uri'			=> $url,
			];
			$json = $this->callAPIWithGET(API_DOWNLOAD_STATION_TASK, $args);
			$obj = json_decode($json);
			return $obj->success;
		}
		return null;
	}

	public function createDownloadFile($file, $destination = 'downloads') {
		if (!empty($this->sid) && !empty($file)) {
			$args = array(
				'_sid'			=> $this->sid,
				'api'			=> 'SYNO.DownloadStation.Task',
				'version'		=> 1,
				'method'		=> 'create',
				'destination'	=> $destination,
				'file'			=> curl_file_create($file),
			);
			$json = $this->callAPIWithPOST(API_DOWNLOAD_STATION_TASK, $args);
			$obj = json_decode($json);
			return $obj->success;
		}
		return null;
	}

	public function searchTorrent($keyword) {
		if (!empty($this->sid) && !empty($keyword)) {
			$args = [
				'_sid'		=> $this->sid,
				'api'		=> 'SYNO.DownloadStation.BTSearch',
				'version'	=> 1,
				'method'	=> 'start',
				'module'	=> 'enabled',
				'keyword'	=> $keyword,
			];
			$json = $this->callAPIWithGET(API_DOWNLOAD_STATION_BTSEARCH, $args);
			$obj = json_decode($json);
			if ($obj->success) {
				$result;
				for ($i = 0; $i < 10; $i++) {
					sleep(1);
					$result = $this->searchResult($obj->data->taskid);
					if (!empty($result) && $result->finished)
						break;
				}
				$this->searchClear($obj->data->taskid);
				if (!empty($result))
					return $result->items;
			}
		}
		return null;
	}

	private function searchClear($taskid) {
		$args = [
			'_sid'		=> $this->sid,
			'api'		=> 'SYNO.DownloadStation.BTSearch',
			'version'	=> 1,
			'method'	=> 'clean',
			'taskid'	=> $taskid,
		];
		$this->callAPIWithGET(API_DOWNLOAD_STATION_BTSEARCH, $args);
	}

	private function searchResult($taskid) {
		if (!empty($this->sid) && !empty($taskid)) {
			$args = [
				'_sid'				=> $this->sid,
				'api'				=> 'SYNO.DownloadStation.BTSearch',
				'version'			=> 1,
				'method'			=> 'list',
				'taskid'			=> $taskid,
				'offset'			=> 0,
				'limit'				=> 10,
				'sort_by'			=> 'seeds',
				'sort_direction'	=> 'desc',
			];
			$json = $this->callAPIWithGET(API_DOWNLOAD_STATION_BTSEARCH, $args);
			$obj = json_decode($json);
			if ($obj->success)
				return $obj->data;
			return $json;
		}
		return null;
	}
}

?>
