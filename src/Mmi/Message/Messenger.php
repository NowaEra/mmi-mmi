<?php

/**
 * Mmi Framework (https://github.com/milejko/mmi.git)
 * 
 * @link       https://github.com/milejko/mmi.git
 * @copyright  Copyright (c) 2010-2015 Mariusz Miłejko (http://milejko.com)
 * @license    http://milejko.com/new-bsd.txt New BSD License
 */

namespace Mmi\Message;

/**
 * Klasa wiadomości
 */
class Messenger {

	/**
	 * Przestrzeń w sesji zarezerwowana dla wiadomości
	 * @var \Mmi\Session\Space
	 */
	private $_session;

	/**
	 * Konstruktor pozwala zdefiniować nazwę przestrzeni w sesji
	 * @param string $namespace
	 */
	public function __construct($namespace) {
		$this->_session = new \Mmi\Session\Space($namespace);
	}

	/**
	 * Dodaje wiadomość
	 * @param string $message wiadomość
	 * @param bool $type true - pozytywna, false - negatywna, brak - neutralna
	 * @param array $vars zmienne
	 * @return \Mmi\Message\Messenger
	 */
	public function addMessage($message, $type = null, array $vars = []) {
		if ($type) {
			$type = 'success';
		} elseif (false === $type) {
			$type = 'error';
		}
		if (!is_array($this->_session->messages)) {
			$this->_session->messages = [];
		}
		$messages = $this->_session->messages;
		$messages[] = ['message' => $message, 'type' => $type, 'vars' => $vars];
		$this->_session->messages = $messages;
		return $this;
	}

	/**
	 * Czy są jakieś wiadomości
	 * @return boolean
	 */
	public function hasMessages() {
		return (is_array($this->_session->messages) && !empty($this->_session->messages));
	}

	/**
	 * Pobiera wiadomości
	 * @return array
	 */
	public function getMessages($clear = true) {
		$messages = [];
		if (is_array($this->_session->messages)) {
			$messages = $this->_session->messages;
		}
		//czyszczenie
		if ($clear) {
			$this->clearMessages();
		}
		return $messages;
	}

	/**
	 * Czyści wiadomości
	 * @return \Mmi\Message\Messenger
	 */
	public function clearMessages() {
		$this->_session->unsetAll();
		return $this;
	}

	/**
	 * Zlicza wiadomości
	 * @return int
	 */
	public function count() {
		return count($this->_session->messages);
	}

}