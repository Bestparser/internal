<?php

ini_set('display_errors', 0);

include_once(dirname(dirname(__DIR__)) . "/cron/settings.php"); // Файл с настройками
//include_once(dirname(dirname(__DIR__)) . "/cron/class.db.php"); // Файл с настройками Hermine (ликвидировано)

$link = mysqli_connect(MYSQL_SERVER, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB) or die ("Error:" . mysqli_error($link));

$incoming_request = trim(file_get_contents(('php://input'), true)); // Получаем данные в формате XML

// Получаем ns1
$dom = new DOMDocument();
$dom->loadXML($incoming_request);
$root = $dom->documentElement;
$request_name = $root->localName;
$request_logs = [
    'request_name' => $request_name,
    'request' => $incoming_request,
];
libxml_use_internal_errors(true);

$xml = simplexml_load_string($incoming_request); // Преобразуем строку XML в объект



if ($xml !== FALSE) {
	
	$clientID = 'Dikiy';
	include_once('class.injection.php'); // Класс Кирилла (для предупреждения инъекции)
	$AI = new antiInjection();
	


	if (!empty($xml->token)){
		$AI->value = $xml->token;
		$AI->index();		
		if ($AI->keywords == 1) signalAI('token', 'token', 'basic_token', $clientID, $AI->value);		
	}		

	
	
	
	
    $token = $xml->token;	
    $request_logs['token'] = $token;
    $result = new stdClass();
	
	$respQty_0 = 0;
    if ($token) {        
        $result = mysqli_query($link, "SELECT * FROM `internal_partners` WHERE `token` = '$token'");
		$respQty_0 = mysqli_num_rows($result);
    }
	
    if ($respQty_0 > 0) { // Если такого token нет в БД, то останавливаем работу скрипта и отдаём в ответ сообщение
        $partnerData = mysqli_fetch_assoc($result); // Массив с данными о клиенте				
        $testUser = $partnerData['test']; // Тестовый юзер или нет  (1 - тестовый, 0 - продуктив.)        
        // Записываем в БД дату и время обращения к API партнёра. Так же записываем ip.
        $lastRequest = date('d.m.Y H:i:s') . ' - ' . $_SERVER['REMOTE_ADDR'];        
        mysqli_query($link, "UPDATE `internal_partners` SET `lastRequest` = '$lastRequest' WHERE `token` = '$token'");

        // Проверка партнёра: 1 - тестовый, 0 - продуктив.
        if ($testUser == 1) { // Если тестовый, то подключаемся к тестовой БД.
            mysqli_select_db($link, 'webapi_test');
            $request_logs['is_test'] = 1;
        }
		
		include_once("class.api.php"); // Класс Кирилла (для построения методов)
		$apiBuilder = new apiBuilderClass();
		$apiBuilder->link = $link; // В класс сразу передаем подключение к sql, чтобы потом внутри класса достучаться до него
		$apiBuilder->xml = $xml; // Точно также передаем xml в класс, чтобы потом внутри него доставать из xml что надо
		
		
		
		
		
		if ($request_name == 'mt_supplyGetInfo_req') { // Чтение штрихкода поставки.
			
			/*
			|-----------------------------------------
			|
			|	Входящие параметры:
			|		<?xml version="1.0" encoding="UTF-8"?>
			|		<ns1:mt_supplyGetInfo_req xmlns:ns1="urn://tfnopt.ru">
			|		   	<token>$ha2@%wu$yqVO3n%$*E{b@Z</token>
			|		   	<externalSupplyID>7</externalSupplyID>
			|			<warehouseID>87654321</warehouseID>
			|		</ns1:mt_supplyGetInfo_req>		
			*/
			
			// VAR
			$apiBuilder->methodName = 'mt_supplyGetInfo_resp'; // Название метода просто сюда вставь, а шапка и footer сами сгенерируются			

			// VALID (сообщения об ошибках публикуем)
			$apiBuilder->externalSupplyID = 'идентификатор поставки externalSupplyID. '; // Порядковый номер в системе TFN API
			$apiBuilder->rowSupplyID = 'и только один идентификатор поставки externalSupplyID. ';
			$apiBuilder->warehouseID = 'номер склада warehouseID. ';
			
			if (!empty($xml->externalSupplyID)){
				$AI->value = $xml->externalSupplyID;
				$AI->index();
				if (($AI->numeric != 1) or ($AI->keywords == 1)) signalAI($apiBuilder->methodName, 'externalSupplyID', $token, $clientID, $AI->value);
			}
			
			if (!empty($xml->warehouseID)){
				$AI->value = $xml->warehouseID;
				$AI->index();
				if (($AI->numeric != 1) or ($AI->keywords == 1)) signalAI($apiBuilder->methodName, 'warehouseID', $token, $clientID, $AI->value);
			}
			
			// Проверка externalSupplyID
			$apiBuilder->validOnce('externalSupplyID'); // Проверка на уникальность externalSupplyID			
			$result = mysqli_query($link, "SELECT * FROM `shipments_consolidating` WHERE `id` = '".$xml->externalSupplyID."' "); // Собираем массив данных из основной таблицы и заодно узнаем, есть ли там (в системе TFN API) вообще входящий externalSupplyID
			$arr = mysqli_fetch_array($result);
			if (count($arr) == 0){				
				$apiBuilder->addError('externalSupplyID'); // Если нет, то склад нет смысла проверять. Тормозим на externalSupplyID
			} else {
				// Проверяем склад												
				if ($apiBuilder->validWarehouseID('warehouseID') == 1){// Если он вообще найден в системе TFN API					
					$apiBuilder->validOnce('warehouseID'); // Проверка на уникальность на первом уровне			
					// Проверка принадлежности склада к поставке								
					$result = mysqli_query($link, "SELECT `warehouseID`, `id` FROM `shipments_consolidating` WHERE `warehouseID` = '".$xml->warehouseID."' and `id` = '".$xml->externalSupplyID."' ");
					$row = mysqli_fetch_array($result); // Здесь используем массив чтобы не дублировать столбы (а так пришлось бы дважды прописывать)
					if (count($row) == 0) $apiBuilder->addError('warehouseID');
				}				
			}	
			// END VALID
			
			
			// PROCESS
			if (strlen($apiBuilder->errorMessage) == 0){
				$apiBuilder->putOut(array(
					'clientID' => $arr['clientID'],
					'warehouseID' => $arr['warehouseID'], // Склад
					'supplyID' => $apiBuilder->externalSupplyID, // Непосредственно сам айдишник строки (id) из общей таблицы -> он же $xml->externalSupplyID
					'externalSupplyID' => $arr['supplyID'], // Идентификатор поставки supplyID. Если Дерябин вводил, значит поставка целенаправлена создавалась пользователем
					'date' => $arr['date'], // Дата, вводимая либо Дерябиным, либо (если Дерябин не вводил) текущая NOW() -> вообще-то это дата insert / update поставки
					'supplyFile' => $arr['supplyFile'], // Штрихкод для комплектации поставки в формате pdf, закодированный в base64
					'supplyEncoded' => $arr['supplyEncoded'] // Вариант строки, закодированной в штрихкоде
				));

				// Вывод позиций
				$i = 0;
				$result = mysqli_query($link, "SELECT * FROM `shipments_consolidating_items` WHERE `externalSupplyID` = '".$arr['id']."' ");				
				while ($row = mysqli_fetch_assoc($result)) {
					$i++;
					$apiBuilder->putOut(array(
						'BEGIN' => 'order',
							'stickerID' => $row['id'], // Непосредственно сам айдишник строки (id) из внутренней таблицы -> порядковый номер позиции					
							'externalID' => $row['orderExternalID'], // Идентификатор вводимый Дерябиным для комлпектации поставки. Если он есть в базе, значит однозначно формировали поставку					
							'stickerEncoded' => $row['stickerEncoded'], // Вариант строки, закодированной в штрихкоде для позиций					
							'stickerFile' => $row['stickerFile'], // стикер в формате pdf, закодированный в base64					
							'externalStickerID' => $row['stickerID'], // номер этикетки					
							'assemblyTask' => $row['assemblyTask'], // Номер сборочного задания					
						'END' => 'order'
					));
				}
				if ($i == 0) $apiBuilder->putOut(array('order' => ''));
			}
			
			// END PROCESS
			
		
			// SHOW OUT
			$out = $apiBuilder->showOut();
			
        } elseif ($request_name == 'mt_labelGetInfo_req') { // Чтение стикера к каждому заказу
			/*
			|-----------------------------------------
			|
			|	Входящие параметры:
			|		<?xml version="1.0" encoding="UTF-8"?>
			|		<ns1:mt_labelGetInfo_req xmlns:ns1="urn://tfnopt.ru">
			|			<token>$ha2@%wu$yqVO3n%$*E{b@Z</token>	
			|			<warehouseID>87654321</warehouseID>
			|			<sapOrderID>2130005459</sapOrderID>
			|			<sapOrderID>2130005449</sapOrderID>
			|		</ns1:mt_labelGetInfo_req>
			*/

			// VAR
			$apiBuilder->methodName = 'mt_labelGetInfo_resp'; // Название метода просто сюда вставь, а шапка и footer сами сгенерируются

			// VALID			
			$apiBuilder->warehouseID = 'номер склада warehouseID. ';
			$apiBuilder->sapOrderID = 'номер заказа sapOrderID. '; // Номер заказа
			
			if (!empty($xml->sapOrderID)){			
				$AI->value = $xml->sapOrderID;
				$AI->index();
				if (($AI->numeric != 1) or ($AI->keywords == 1)) signalAI($apiBuilder->methodName, 'sapOrderID', $token, $clientID, $AI->value);
			}

			if (!empty($xml->warehouseID)){			
				$AI->value = $xml->warehouseID;
				$AI->index();
				if (($AI->numeric != 1) or ($AI->keywords == 1)) signalAI($apiBuilder->methodName, 'warehouseID', $token, $clientID, $AI->value);
			}
			
			$apiBuilder->validOnce('warehouseID'); // Проверка на уникальность параметров на первом уровне
			
			// PROCESS			
			if ($apiBuilder->validWarehouseID('warehouseID') == 1){ // Если склад найден в системе TFN API
				$i = 0;
				foreach($xml->sapOrderID as $rowSapOrderID){ // Проходимся по входящим sapOrderID - номерам заказа в системе TFN API
					$i++;
					$errorText = '';
					$createStickerStatus = 1;					
					if ($apiBuilder->validOrderID($rowSapOrderID, $xml->warehouseID) == 2){ // Проверка на наличие номера заказа sapOrderID в системе TFN API
						$errorText = 'Номер заказа sapOrderID "'.$rowSapOrderID.'" не найден на складе "'.$xml->warehouseID.'". ';
						$createStickerStatus = 2;
					} else {
						$respQty = 0;						
						$result = mysqli_query($link, "SELECT `id`, `warehouseID` FROM `orders` WHERE `id` = '".$rowSapOrderID."' and `warehouseID` = '".$xml->warehouseID."' ");
						$respQty = mysqli_num_rows($result);			
						if ($respQty == 0){
							$errorText = 'Номер заказа sapOrderID "'.$rowSapOrderID.'" не относится к складу "'.$xml->warehouseID.'" ';
							$createStickerStatus = 2;							
						}						
					}
					// Работаем по позициям (второй уровень)
					$result = mysqli_query($link, "SELECT * FROM `shipments_consolidating_items` WHERE `orderExternalID` = '".$rowSapOrderID."' ");
					$row = mysqli_fetch_array($result); // используем array, потому-что ниже можно в ответ на запрос можно пустышки записать (а с rows пришлось бы дважды прописывать xml)
					if (mysqli_num_rows($result) == 0){
						$createStickerStatus = 2;
						$errorText = 'Заказ не найден';
					} elseif ($row['stickerFile'] == '') {
						$createStickerStatus = 2;
						$errorText = 'Этикетка не найдена';						
					}
					
					$apiBuilder->putOut(array(
						'BEGIN' => 'order',
							'supplyID' => $row['id'], // Порядковый номер стикера в системе TFN API							
							'orderID' => $rowSapOrderID, // Номер заказа
							'stickerEncoded' => $row['stickerEncoded'], // Вариант строки, закодированной в штрихкоде для позиций
							'stickerFile' => $row['stickerFile'], // стикер в формате pdf, закодированный в base64
							'stickerID' => $row['stickerID'], // номер этикетки
							'assemblyTask' => $row['assemblyTask'], // Номер сборочного задания							
							'createStickerStatus' => $createStickerStatus,
							'errorText' => $errorText,
						'END' => 'order'
					));
					// end Работаем по позициям (второй уровень)
				}			
				if ($i == 0) $apiBuilder->addError('sapOrderID'); // На тот случай, если вообще ничего не ввели
			}	
			// END PROCESS
		
		
			// SHOW OUT			
			$out = $apiBuilder->showOut();
			
		} elseif ($request_name == 'mt_supplyList_req') { // Вытащить по дате порядковые ID записей из общей таблицы ШК и второй таблицы стикеров
			/*
			|-----------------------------------------
			|
			|	Входящие параметры:
			|		<?xml version="1.0" encoding="UTF-8"?>
			|		<ns1:mt_supplyList_req xmlns:ns1="urn://tfnopt.ru">
			|			<token>$ha2@%wu$yqVO3n%$*E{b@Z</token> <!-- обязательный параметр -->
			|			<clientID>0000000002</clientID> <!-- обязательный параметр -->
			|			<warehouseID>87654321</warehouseID> <!-- обязательный параметр -->
			|			
			|			<externalOrderID></externalOrderID> <!-- необязательный параметр: номер заказа -->
			|			
			|			<dateFrom>2022-07-13</dateFrom> <!-- обязательный параметр -->
			|			<dateTo>2022-07-13</dateTo> <!-- обязательный параметр -->
			|			
			|			<billingStatus>1</billingStatus> <!-- необязательный параметр: 1 – все заказы, 2 – только неотфактурированные; -->
			|			<createSupplyStatus>1</createSupplyStatus> <!-- необязательный параметр: 1 – объединенные в поставки, 2 - необъединенные. -->
			|		</ns1:mt_supplyList_req>
			|
			|
			*/
		
			// VAR
			$apiBuilder->methodName = 'mt_supplyList_resp'; // Название метода просто сюда вставь, а шапка и footer сами сгенерируются

			// VALID (сообщения об ошибках публикуем)
			$apiBuilder->clientID = 'идентификатор клиента clientID. ';
			$apiBuilder->warehouseID = 'номер склада warehouseID. ';
			$apiBuilder->externalOrderID = 'номер заказа externalOrderID. ';			
			$apiBuilder->dateFrom = 'параметр dateFrom. ';
			$apiBuilder->dateTo = 'параметр dateTo. ';
			$apiBuilder->billingStatus = 'параметр billingStatus. ';
			$apiBuilder->createSupplyStatus = 'параметр createSupplyStatus. ';
			
			$apiBuilder->validClientID('clientID'); // clientID проверка
			$apiBuilder->validOnce('clientID');
			
			$apiBuilder->validWarehouseID('warehouseID'); // warehouseID проверка			
			$apiBuilder->validOnce('warehouseID');

			

			if (!empty($xml->clientID)){			
				$AI->value = $xml->clientID;
				$AI->index();
				if (($AI->numeric != 1) or ($AI->keywords == 1)) signalAI($apiBuilder->methodName, 'clientID', $token, $clientID, $AI->value);
			}
			
			if (!empty($xml->warehouseID)){			
				$AI->value = $xml->warehouseID;
				$AI->index();
				if (($AI->numeric != 1) or ($AI->keywords == 1)) signalAI($apiBuilder->methodName, 'warehouseID', $token, $clientID, $AI->value);
			}
			
			if (!empty($xml->dateFrom)){						
				$AI->value = $xml->dateFrom;
				$AI->index();
				if (($AI->space == 1) or ($AI->quotes == 1) or ($AI->keywords == 1)) signalAI($apiBuilder->methodName, 'dateFrom', $token, $clientID, $AI->value);
			}
			
			if (!empty($xml->dateTo)){						
				$AI->value = $xml->dateTo;
				$AI->index();
				if (($AI->space == 1) or ($AI->quotes == 1) or ($AI->keywords == 1)) signalAI($apiBuilder->methodName, 'dateTo', $token, $clientID, $AI->value);
			}
			
			if (!empty($xml->externalOrderID)){
				$AI->value = $xml->externalOrderID;
				$AI->index();
				if (($AI->numeric != 1) or ($AI->keywords == 1)) signalAI($apiBuilder->methodName, 'externalOrderID', $token, $clientID, $AI->value);
			}
			
			if (!empty($xml->billingStatus)){
				$AI->value = $xml->billingStatus;
				$AI->index();
				if (($AI->numeric != 1) or ($AI->keywords == 1)) signalAI($apiBuilder->methodName, 'billingStatus', $token, $clientID, $AI->value);
			}
			
			if (!empty($xml->createSupplyStatus)){
				$AI->value = $xml->createSupplyStatus;
				$AI->index();
				if (($AI->numeric != 1) or ($AI->keywords == 1)) signalAI($apiBuilder->methodName, 'createSupplyStatus', $token, $clientID, $AI->value);
			}
			
			
			
			$WHERE1 = '';
			$WHERE2 = '';
			if (!empty($xml->externalOrderID)){ // Проверка номера заказа (если ввели таковой)
				$apiBuilder->validOnce('externalOrderID');
				if ($apiBuilder->validOrderID($apiBuilder->externalOrderID, $xml->warehouseID) == 2){
					$apiBuilder->errorMessage .= $apiBuilder->beginError . $apiBuilder->externalOrderID;
				} else {
					$WHERE1 = $WHERE1 .= "`orderExternalID` = '".$xml->externalOrderID."' and ";
					$WHERE2 = $WHERE2 .= "`orderExternalId` = '".$xml->externalOrderID."' and ";
				}
			}
			
			// Проверка дат
			$apiBuilder->validOnce('dateFrom'); if (empty($xml->dateFrom)) $apiBuilder->errorMessage .= $apiBuilder->beginError . $apiBuilder->dateFrom;
			$apiBuilder->validOnce('dateTo'); if (empty($xml->dateTo)) $apiBuilder->errorMessage .= $apiBuilder->beginError . $apiBuilder->dateTo;
			
			if (!empty($xml->billingStatus)) $apiBuilder->validOnce('billingStatus');
			if (!empty($xml->createSupplyStatus)) $apiBuilder->validOnce('createSupplyStatus');
			// END VALID
			
			
			// PROCESS
			if (strlen($apiBuilder->errorMessage) == 0){
				
				/* выходящие данные
					<supply>
						<supplyID>7</supplyID>
						<date>2022-10-09</date>
						<warehouseID>87654321</warehouseID>
						<orders>
							<order>
								<stickerID>10</stickerID>
								<billingStatus>1</billingStatus>
								<date>2022-07-06</date>
							</order>
							<order>
								<stickerID>11</stickerID>
								<billingStatus>1</billingStatus>
								<date>2022-07-06</date>
							</order>
						</orders>						
					</supply>
				*/
				
				
				$i = 0;
				$result = mysqli_query($link, "SELECT * FROM `shipments_consolidating` WHERE `clientID` = '".$xml->clientID."' and `warehouseID` = '".$xml->warehouseID."' and `date` BETWEEN '".$xml->dateFrom."' and '".$xml->dateTo."' ");				
				while ($row = mysqli_fetch_assoc($result)) {
					
					/*					
					все заказы (с фактурой и без)
					только с поставкой
					*/
					if ((!empty($xml->billingStatus)) and ($xml->billingStatus == 1) and (!empty($xml->createSupplyStatus)) and ($xml->createSupplyStatus == 1)){
						if ((($row['operationStatus'] == 1) or ($row['operationStatus'] == 3)) and (strlen($row['supplyID']) > 0)){ // только с поставкой															
							$apiBuilder->addOut('BEGIN', 'supply');
								$apiBuilder->addOut('supplyID', $row['id']);
								$apiBuilder->addOut('date', $row['date']);
								$apiBuilder->addOut('warehouseID', $xml->warehouseID);
								
								$apiBuilder->addOut('BEGIN', 'orders');									
									$result_inner = mysqli_query($link, "SELECT * FROM `shipments_consolidating_items` WHERE ".$WHERE1."`externalSupplyID` = '".$row['id']."' ");									
									while ($row_inner = mysqli_fetch_assoc($result_inner)) {								
											$i++;
											$apiBuilder->addOut('BEGIN', 'order');
												$apiBuilder->addOut('stickerID', $row_inner['id']);
												$apiBuilder->addOut('billingStatus', 1);
												$apiBuilder->addOut('date', $row_inner['date']);
											$apiBuilder->addOut('END', 'order');
									}
								$apiBuilder->addOut('END', 'orders');						
							$apiBuilder->addOut('END', 'supply');														
						}
					/*
					все заказы (с фактурой и без)
					только без поставки
					*/						
					} elseif ((!empty($xml->billingStatus)) and ($xml->billingStatus == 1) and (!empty($xml->createSupplyStatus)) and ($xml->createSupplyStatus == 2)){						
						if (!strlen($row['supplyID']) > 1){ // без поставки														
							$apiBuilder->addOut('BEGIN', 'supply');
								$apiBuilder->addOut('supplyID', $row['id']);
								$apiBuilder->addOut('date', $row['date']);
								$apiBuilder->addOut('warehouseID', $xml->warehouseID);
								
								$apiBuilder->addOut('BEGIN', 'orders');									
									$result_inner = mysqli_query($link, "SELECT * FROM `shipments_consolidating_items` WHERE ".$WHERE1."`externalSupplyID` = '".$row['id']."' ");									
									while ($row_inner = mysqli_fetch_assoc($result_inner)) {								
											$i++;
											$apiBuilder->addOut('BEGIN', 'order');
												$apiBuilder->addOut('stickerID', $row_inner['id']);
												$apiBuilder->addOut('billingStatus', 1);
												$apiBuilder->addOut('date', $row_inner['date']);
											$apiBuilder->addOut('END', 'order');
									}
								$apiBuilder->addOut('END', 'orders');						
							$apiBuilder->addOut('END', 'supply');														
						}
						
					/*
					только неотфактурированные
					только с поставкой
					*/
					} elseif ((!empty($xml->billingStatus)) and ($xml->billingStatus == 2) and (!empty($xml->createSupplyStatus)) and ($xml->createSupplyStatus == 1)){
						if ((($row['operationStatus'] == 1) or ($row['operationStatus'] == 3)) and (strlen($row['supplyID']) > 0)){ // только с поставкой														
							
							$result_inner = mysqli_query($link, "SELECT * FROM `shipments_consolidating_items` WHERE ".$WHERE1."`externalSupplyID` = '".$row['id']."' ");																		
							while ($row_inner = mysqli_fetch_assoc($result_inner)) {																		
								$respQty_billing = 0;											
								$result_billing = mysqli_query($link, "SELECT `orderExternalId` FROM `billing` WHERE ".$WHERE2."`orderExternalId` = '".$row_inner['orderExternalID']."' ");
								$respQty_billing = mysqli_num_rows($result_billing);									
								if ($respQty_billing == 0) $i++;
							}

							if ($i > 0){
								$apiBuilder->addOut('BEGIN', 'supply');
									$apiBuilder->addOut('supplyID', $row['id']);
									$apiBuilder->addOut('date', $row['date']);
									$apiBuilder->addOut('warehouseID', $xml->warehouseID);
									
									$apiBuilder->addOut('BEGIN', 'orders');									
										$result_inner = mysqli_query($link, "SELECT * FROM `shipments_consolidating_items` WHERE ".$WHERE1."`externalSupplyID` = '".$row['id']."' ");																		
										while ($row_inner = mysqli_fetch_assoc($result_inner)) {																		
											$respQty_billing = 0;											
											$result_billing = mysqli_query($link, "SELECT `orderExternalId` FROM `billing` WHERE ".$WHERE2."`orderExternalId` = '".$row_inner['orderExternalID']."' ");
											$respQty_billing = mysqli_num_rows($result_billing);									
											if ($respQty_billing == 0){													
												$apiBuilder->addOut('BEGIN', 'order');
													$apiBuilder->addOut('stickerID', $row_inner['id']);
													$apiBuilder->addOut('billingStatus', 2);
													$apiBuilder->addOut('date', $row_inner['date']);
												$apiBuilder->addOut('END', 'order');										
											}
										}
									$apiBuilder->addOut('END', 'orders');						
								$apiBuilder->addOut('END', 'supply');														
							}
							
						}
					/*
					только неотфактурированные
					только без поставки
					*/
					} elseif ((!empty($xml->billingStatus)) and ($xml->billingStatus == 2) and (!empty($xml->createSupplyStatus)) and ($xml->createSupplyStatus == 2)){
						if (!strlen($row['supplyID']) > 1){// без поставки			
								
							$result_inner = mysqli_query($link, "SELECT * FROM `shipments_consolidating_items` WHERE ".$WHERE1."`externalSupplyID` = '".$row['id']."' ");									
							while ($row_inner = mysqli_fetch_assoc($result_inner)) {																		
								$respQty_billing = 0;											
								$result_billing = mysqli_query($link, "SELECT `orderExternalId` FROM `billing` WHERE ".$WHERE2."`orderExternalId` = '".$row_inner['orderExternalID']."' ");
								$respQty_billing = mysqli_num_rows($result_billing);									
								if ($respQty_billing == 0) $i++;
							}
							
							if ($i > 0){
								$apiBuilder->addOut('BEGIN', 'supply');
									$apiBuilder->addOut('supplyID', $row['id']);
									$apiBuilder->addOut('date', $row['date']);
									$apiBuilder->addOut('warehouseID', $xml->warehouseID);
									
									$apiBuilder->addOut('BEGIN', 'orders');									
										$result_inner = mysqli_query($link, "SELECT * FROM `shipments_consolidating_items` WHERE ".$WHERE1."`externalSupplyID` = '".$row['id']."' ");									
										while ($row_inner = mysqli_fetch_assoc($result_inner)) {																		
											$respQty_billing = 0;											
											$result_billing = mysqli_query($link, "SELECT `orderExternalId` FROM `billing` WHERE ".$WHERE2."`orderExternalId` = '".$row_inner['orderExternalID']."' ");
											$respQty_billing = mysqli_num_rows($result_billing);									
											if ($respQty_billing == 0){																						
												$apiBuilder->addOut('BEGIN', 'order');
													$apiBuilder->addOut('stickerID', $row_inner['id']);
													$apiBuilder->addOut('billingStatus', 2);
													$apiBuilder->addOut('date', $row_inner['date']);
												$apiBuilder->addOut('END', 'order');										
											}
										}
									$apiBuilder->addOut('END', 'orders');						
								$apiBuilder->addOut('END', 'supply');													
							}
							
						}
					// асболютно все
					} else {	
						$result_inner = mysqli_query($link, "SELECT * FROM `shipments_consolidating_items` WHERE ".$WHERE1."`externalSupplyID` = '".$row['id']."' ");								
						while ($row_inner = mysqli_fetch_assoc($result_inner)) {								
							if ((!empty($xml->billingStatus)) and ($xml->billingStatus == 2)){ // Если неотфактурированные									
								$respQty_billing = 0;											
								$result_billing = mysqli_query($link, "SELECT `orderExternalId` FROM `billing` WHERE ".$WHERE2."`orderExternalId` = '".$row_inner['orderExternalID']."' ");
								$respQty_billing = mysqli_num_rows($result_billing);									
								if ($respQty_billing == 0) $i++;
							} else { // Все заказы		
								$i++;
							}
						}
						
						if ($i > 0){	
							$apiBuilder->addOut('BEGIN', 'supply');
								$apiBuilder->addOut('supplyID', $row['id']);
								$apiBuilder->addOut('date', $row['date']);
								$apiBuilder->addOut('warehouseID', $xml->warehouseID);
								
								$apiBuilder->addOut('BEGIN', 'orders');								
									$result_inner = mysqli_query($link, "SELECT * FROM `shipments_consolidating_items` WHERE ".$WHERE1."`externalSupplyID` = '".$row['id']."' ");								
									while ($row_inner = mysqli_fetch_assoc($result_inner)) {								
										if ((!empty($xml->billingStatus)) and ($xml->billingStatus == 2)){ // Если неотфактурированные									
											$respQty_billing = 0;											
											$result_billing = mysqli_query($link, "SELECT `orderExternalId` FROM `billing` WHERE ".$WHERE2."`orderExternalId` = '".$row_inner['orderExternalID']."' ");
											$respQty_billing = mysqli_num_rows($result_billing);									
											if ($respQty_billing == 0){														
												$apiBuilder->addOut('BEGIN', 'order');
													$apiBuilder->addOut('stickerID', $row_inner['id']);
													$apiBuilder->addOut('billingStatus', 2);
													$apiBuilder->addOut('date', $row_inner['date']);
												$apiBuilder->addOut('END', 'order');										
											}
										} else { // Все заказы													
											$apiBuilder->addOut('BEGIN', 'order');
												$apiBuilder->addOut('stickerID', $row_inner['id']);
												$apiBuilder->addOut('billingStatus', 1);
												$apiBuilder->addOut('date', $row_inner['date']);
											$apiBuilder->addOut('END', 'order');
										}
									}
								$apiBuilder->addOut('END', 'orders');						
							$apiBuilder->addOut('END', 'supply');						
						}
						
					}	
				}
			}
			if ($i == 0) $apiBuilder->errorMessage .= 'По Вашему запросу не найдены искомые позиции.';
			
			// SHOW OUT			
			$out = $apiBuilder->showOut();
			
		} elseif ($request_name == 'mt_marketplace_req') { // Узнать МП и магазин по номеру заказа
			
			/*
			|-----------------------------------------
			|
			|	Входящие параметры:
			|		<?xml version="1.0" encoding="UTF-8"?>
			|		<ns1:mt_marketplace_req xmlns:ns1="urn://tfnopt.ru">
			|		   	<token>$hal@%wu$yqVO3n%$*E{b@Z</token>
			|		   	<orderID>7</orderID>			
			|		</ns1:mt_marketplace_req>
			|
			*/
			
			// VAR
			$apiBuilder->methodName = 'mt_marketplace_resp'; // Название метода
			
			$arr_mp = array( // Список маркетплейсов + доступы от sql (пришлось через массив сделать, потому что доступы разные). Создано для рекурсии (перебор)
				1 => [
					'user' => 'userapi',
					'pas' => 'Z1dTcQaJZj2yTUlU',
					'db' => 'ali',
					'mp' => 'Ali Express',
				],
				2 => [
					'user' => 'userapi',
					'pas' => 'Z1dTcQaJZj2yTUlU',
					'db' => 'goods',
					'mp' => 'Sber',
				],
				3 => [
					'user' => 'userapi',
					'pas' => 'Z1dTcQaJZj2yTUlU',
					'db' => 'ozon',
					'mp' => 'Ozon',
				],
				4 => [
					'user' => 'userapi',
					'pas' => 'Z1dTcQaJZj2yTUlU',
					'db' => 'wildberries',
					'mp' => 'Wildberries',
				],
				5 => [
					'user' => 'bagdasaryan',
					'pas' => 'qxBPz5IeJLnTNUf1',
					'db' => 'yandexMarket',
					'mp' => 'Yandex Market',
				],	
				6 => [
					'user' => 'userapi',
					'pas' => 'Z1dTcQaJZj2yTUlU',
					'db' => 'webapi',
					'mp' => 'TFN API',
				],
			);
			
			
			
			// VALID (сообщения об ошибках публикуем)
			$apiBuilder->orderID = 'параметр orderID. '; // Номер заказа в системе "МП - TFN API"
			
			if (empty($xml->orderID)) $apiBuilder->errorMessage .= $apiBuilder->beginError . $apiBuilder->orderID; // Ошибка, если вообще не передали orderID
			
			$apiBuilder->validOnce('orderID'); // Проверка на уникальность orderID
			
			if (!empty($xml->orderID)){			
				$AI->value = $xml->orderID; // Защита от sql инъекций
				$AI->index();
				if (($AI->space == 1) or ($AI->quotes == 1) or ($AI->keywords == 1)) signalAI($apiBuilder->methodName, 'orderID', $token, $clientID, $AI->value);
			}	

			// PROCESS			
			function findMP($arr_mp, $orderID, $i){	// Короче через рекурсию перебираем каждую БД от МП
				$link = mysqli_connect('127.0.0.1', $arr_mp[$i]['user'], $arr_mp[$i]['pas'], $arr_mp[$i]['db']) or die ('Error connect db: '.$arr_mp[$i]['db'].' ');
				if ($arr_mp[$i]['db'] == 'ozon'){
					$result = mysqli_query($link, "SELECT `id`, `shopName`, `orderId`, `postingNumber` FROM `orders` WHERE `orderId` = '".$orderID."' or `postingNumber` = '".$orderID."' "); // В ozon Дикий почему-то ищет по postingNumber
				} elseif ($arr_mp[$i]['db'] == 'webapi'){
					$result = mysqli_query($link, "SELECT `id`, `orderId`, `marketplaceName` FROM `orders` WHERE `orderId` = '".$orderID."' "); // в webapi shopName отсутствует
				} else {
					$result = mysqli_query($link, "SELECT `id`, `shopName`, `orderId` FROM `orders` WHERE `orderId` = '".$orderID."' "); // стандартный поиск по orderId
				}
				
				if (mysqli_num_rows($result) > 0){
					$arr = mysqli_fetch_array($result);					
					
					if ($arr_mp[$i]['mp'] == 'TFN API'){ // Если зафиксирован номер в webapi
						$arr['shopName'] = 'TFN API';
						$arr_mp[$i]['mp'] = $arr['marketplaceName']; // Переча МП Дерябина (как он вводил при merged)
					}
					
					$res = array( // Нам надо из функции сразу три параметра вытаскивать. Можно через класс, но это нагромождение. Проще запихнуть их в массив и вытащить потом сам массив.
						'externalOrderID' => $arr['id'],
						'marketplaceName' => $arr_mp[$i]['mp'],
						'shopName' => $arr['shopName'],
					);	
					return $res; // В случае нахождения - тормозит рекурсию и весь процесс заканчивается. Тупо вытаскиваем первое нахождение (ну а зачем перебирать дальше если уже нашли?).					
				} elseif ($i < count($arr_mp)) {
					$i++;					
					return findMP($arr_mp, $orderID, $i); // Непосредственно сама рекурсия
				} else {					
					$res = array(
						'error' => 'По Вашему запросу не найдены искомые позиции.', // Выводим ошибку в случае перебора всех пяти МП
					);
					return $res;
				}
			}
			
			if (strlen($apiBuilder->errorMessage) == 0){
				$result = findMP($arr_mp, $xml->orderID, 1);

				if ((count($result) > 1) and (strlen($result['marketplaceName']) > 0)){
					$apiBuilder->putOut(array(						
						'externalOrderID' => $result['externalOrderID'],
						'marketplaceName' => $result['marketplaceName'],
						'shopName' => $result['shopName']
					));
				} else {
					$apiBuilder->errorMessage .= 'По Вашему запросу не найдены искомые позиции.';
				}
			}
			// end PROCESS	

				
			// SHOW OUT
			$out = $apiBuilder->showOut();
			
			include_once(dirname(dirname(__DIR__)) . "/cron/settings.php");
			$link = mysqli_connect(MYSQL_SERVER, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB) or die ("Error:" . mysqli_error($link));			
			
		} else {   $out = '<?xml version="1.0" encoding="UTF-8"?>
<errorText>Тип запроса не определён.</errorText>';
        }

    } else {
        $out = '<?xml version="1.0" encoding="UTF-8"?>
<errorText>Пользователь не найден.</errorText>';
    }

} else {
    $out = "XML parse error.\r\n";
    $errors = libxml_get_errors();
    foreach ($errors as $error) {
        switch ($error->level) {
            case LIBXML_ERR_WARNING:
                $out .= "Warning $error->code: ";
                break;
            case LIBXML_ERR_ERROR:
                $out .= "Error $error->code: ";
                break;
            case LIBXML_ERR_FATAL:
                $out .= "Fatal Error $error->code: ";
                break;
        }
        $out .= trim($error->message) .
            "\r\n  Line: $error->line" .
            "\r\n  Column: $error->column \n";
    }
    libxml_clear_errors();
}

echo $out; // Отдаём ответ

// Пишем логи в обход класса "class.db"
if (isset($request_logs['request_name']) && !in_array($request_logs['request_name'], ['mt_getFilters_req', 'mt_getProductInfo_req'])) {
    $request_logs['response'] = $out;
    if (!empty($request_logs)) {
			if ($testUser == 1) mysqli_select_db($link, 'webapi');
			$query = "INSERT INTO `request_logs_internal` (`id`, `request`, `response`, `request_name`, `token`, `is_test`, `created_at`) VALUES (null, '".$request_logs['request']."', '".$request_logs['response']."', '".$request_logs['request_name']."', '".$request_logs['token'][0]."', '".$request_logs['is_test']."', NOW())";
			mysqli_query($link, $query);
    }
}

/* // Код Эрмине (ликвидирован)
if (isset($request_logs['request_name']) &&
    !in_array($request_logs['request_name'], ['mt_getFilters_req', 'mt_getProductInfo_req'])) {
    $request_logs['response'] = $out;

    if (!empty($request_logs)) {
        $request_logs['created_at'] = date('Y-m-d H:i:s');
        webapi_db::getInstance()->doArray('request_logs', $request_logs);
    }
}
*/


?>