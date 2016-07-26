<?php

/**
 * Mmi Framework (https://github.com/milejko/mmi.git)
 * 
 * @link       https://github.com/milejko/mmi.git
 * @copyright  Copyright (c) 2010-2016 Mariusz Miłejko (http://milejko.com)
 * @license    http://milejko.com/new-bsd.txt New BSD License
 */

namespace Mmi\Cache;

/**
 * Backend memcache
 */
class RedisBackend implements CacheBackendInterface {

	/**
	 * Przechowuje obiekt Redisa
	 * @var \Redis
	 */
	private $_server;

	/**
	 * Konfiguracja
	 * @var \Mmi\Cache\CacheConfig
	 */
	private $_config;

	/**
	 * Cache namespace
	 * @var string
	 */
	private $_namespace;

	/**
	 * Ustawia obiekt Memcache
	 * @param \Mmi\Cache\CacheConfig $config konfiguracja
	 */
	public function __construct(\Mmi\Cache\CacheConfig $config) {
		$this->_namespace = crc32(BASE_PATH);
		$this->_config = $config;
		$this->_connect();
	}

	/**
	 * Łączenie z serwerem
	 */
	private function _connect() {
		$this->_server = new \Redis;
		//format host:port
		if (strpos($this->_config->path, ':') !== false) {
			$srv = explode(':', $this->_config->path);
			//połączenie na port
			$this->_server->pconnect($srv['host'], $srv['port'], 1, null, 100);
			$this->_server->select($this->_namespace);
			return;
		}
		//połączenie na socket np. /tmp/redis.sock
		$this->_server->pconnect($this->_config->path);
		$this->_server->select($this->_namespace);
	}

	/**
	 * Ładuje dane o podanym kluczu
	 * @param string $key klucz
	 */
	public function load($key) {
		return $this->_server->get($key);
	}

	/**
	 * Zapisuje dane pod podanym kluczem
	 * @param string $key klucz
	 * @param string $data
	 * @param int $lifeTime wygaśnięcie danych w buforze (informacja dla bufora)
	 */
	public function save($key, $data, $lifeTime) {
		if ($lifeTime > 2592000) {
			//memcache bug ta wartość nie może być większa
			$lifeTime = 2592000;
		}
		return $this->_server->set($key, $data, 0, $lifeTime);
	}

	/**
	 * Kasuje dane o podanym kluczu
	 * @param string $key klucz
	 */
	public function delete($key) {
		return $this->_server->delete($key);
	}

	/**
	 * Kasuje wszystkie dane
	 */
	public function deleteAll() {
		return $this->_server->flushDB();
	}

}