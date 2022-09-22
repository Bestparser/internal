<?php
class apiBuilderClass{	
	/*
	|-----------------------------------------
	|
	|	Кодовая база (архитектура):
	|	Обработка API запросов построена по следующей архитектуре:
	|		1. VAR - объявляем необходимые переменные, свойства, объекты
	|		2. VALID - проверка параметров в входящем запросе, генерация вывода ошибок
	|		3. PROCESS - непосредственно обработка API - ядро функционала
	|		4. SHOW OUT - генерация ответа на API запрос
	|
	|	Основная задача архитектуры:			
	|		1. Сделать функционал долгосрочным
	|		2. А если надо потом что-то изменить, то чтобы я или еще кто потом без труда разобрался здесь
	|		3. Читабельный скопмонованый по порядку код (единая системная архитектура)
	|		4. Самое прикольное: чтобы одно и тоже не прописывать в каждом методе, а просто использовать класс
	|
	*/
	
	
	/*========= VAR ===========*/
	public $beginError = 'Укажите правильный '; // Первая половина сообщения-ошибки
	
	public function xmlHeader(){
		return "<?xml version=\"1.0\" encoding=\"UTF-8\"?><ns1:{$this->methodName} xmlns:ns1=\"urn://tfnopt.ru\">";
	}
	public function xmlFooter(){
		return "</ns1:{$this->methodName}>";
	}
	/*======= END VAR =========*/
	
	
	
	/*========= VALID =========*/
	public function validOnce($parameter){ // Проверка на дублирование параметров на первом уровне (на корне)
		$xml = $this->xml;
		$i = 0;
		foreach($xml->$parameter as $row) $i++; // Смотрим: сколько раз пользователь ввел один и тот же параметр
		if ($i > 1){
			$this->errorMessage .= $this->beginError . ' и только в одном количестве ' . $this->$parameter;
		}
	}
	
	public function validClientID($parametr){ // clientID проверка		
		$xml = $this->xml;		
		$respQty = 0;		
		$result = mysqli_query($this->link, "SELECT `clientID` FROM `partners` WHERE `clientID` = '".$xml->$parametr."' ");
		$respQty = mysqli_num_rows($result);				
		if (($respQty == 0) or (empty($xml->$parametr))){
			$this->addError($parametr);
			return 2;
		} else {
			return 1;
		}
	}
	
	public function validWarehouseID($parametr){ // warehouseID проверка склада
		$xml = $this->xml;		
		$respQty = 0;		
		$result = mysqli_query($this->link, "SELECT `id` FROM `warehouses` WHERE `warehouseID` = '".$xml->$parametr."' and `publish` = 'Y'");
		$respQty = mysqli_num_rows($result);			
		if (($respQty == 0) or (empty($xml->$parametr))){
			$this->addError($parametr);
			return 2;
		} else {
			return 1;
		}
	}
	
	public function validOrderID($parametr, $warehouseID){ // (значение) orderID проверка номера заказа
		$xml = $this->xml;		
		$respQty = 0;		
		$result = mysqli_query($this->link, "SELECT `id` FROM `orders` WHERE `id` = '".$parametr."' and `warehouseID` = '".$warehouseID."' ");
		$respQty = mysqli_num_rows($result);			
		if (($respQty == 0) or (empty($parametr))){			
			//$this->errorMessage .= $this->beginError . 'номер заказа'; (я пока закоментировал)
			return 2;
		} else {
			return 1;
		}
	}
	/*======= END VALID =======*/
	
	
	
	/*====== PROCESS =======*/	
	public function addError($parameter){ // Добавление сообщения к ошибкам (сделано для того, чтобы не прописывать многократно эту формулу)
		$this->errorMessage .= $this->beginError . $this->$parameter;
	}
	public function addError2($parameter){ // (Для переменных) Добавление сообщения к ошибкам (сделано для того, чтобы не прописывать многократно эту формулу)
		$this->errorMessage .= $this->beginError . $parameter;
	}
	
	public function putOut($arr){ // (Через массив) Добавление ответа на запрос к out (Присвоение к out) (Значительно сокращает архитектуру. Например, по-деревенски решались раньше вопросы с самозакрывающимися тегами)		
		$key = array_keys($arr);		
		$i = -1;
		foreach($key as $row){
			$i++;
			if ($key[$i] == 'BEGIN'){
				$this->out .= '<'.$arr[$key[$i]].'>';
			} elseif ($key[$i] == 'END'){
				$this->out .= '</'.$arr[$key[$i]].'>';
			} elseif ($arr[$key[$i]] == ''){
				$this->out .= '<'.$key[$i].' />';
			} else {
				$this->out .= '<'.$key[$i].'>'.$arr[$key[$i]].'</'.$key[$i].'>';
			}
		}		
	}
	
	public function addOut($xmlTeg, $xmlTegValue){ // (для сложных ответов) Добавление ответа на запрос к out (Присвоение к out) (Значительно сокращает архитектуру. Например, по-деревенски решались раньше вопросы с самозакрывающимися тегами)	
		if ($xmlTeg == 'BEGIN'){
			$this->out .= '<'.$xmlTegValue.'>';
		} elseif ($xmlTeg == 'END'){
			$this->out .= '</'.$xmlTegValue.'>';
		} elseif ($xmlTegValue == ''){
			$this->out .= '<'.$xmlTeg.' />';
		} else {
			$this->out .= '<'.$xmlTeg.'>'.$xmlTegValue.'</'.$xmlTeg.'>';
		}
	}
	/*==== END PROCESS =====*/
	
	
	
	/*======= SHOW OUT ========*/
	public function showOut(){
		$out = $this->xmlHeader() . $this->out;	
		if (strlen($this->errorMessage) > 0){
			$out .= '<errorText>'.$this->errorMessage.'</errorText>';
		} else {
			$out .= '<errorText />';
		}	
		$out .= $this->xmlFooter();
		return $out;
	}
	/*====== END SHOW OUT ======*/
}


?>