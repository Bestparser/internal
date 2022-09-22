<?php
	/*
	|-----------------------------------------
	|	
	|	Author: Kirill
	|	Класс создан для предупреждения sql-инъекций
	|	Применимость ООП необходима для того, чтобы:
	|										1. в одной функции положить в несколько данных значения
	|										2. особо не перелапатить чужой код. Тупо перед кодом проверяем значения и вперед (по сути: простая валидация, как в моих методах)
	|	
	|	Принцип простой: в разных от необходимости случаях (смотря какой запрос в sql: по номеру (int) или по тексту (text, varchar)->	
	|		осуществляем проверку значения из API на: 
	|										1. цифры (select where 'цифра')
	|										2. наличие пробелов (select where 'не должно')
	|		
	|	Содержит 3 функции:
	|										1. Раскадровка
	|										2. Отцифровка
	|										3. Пробел
	|										4. Кавычки
	|
	*/
	
	class antiInjection{
		
		// Датчики (0 - не нашли; 1 - нашли)
		public $value; // Значение из API
		public $string; // Переделаное значение через раскадровку из API ()
		public $numeric; // Отцифровка
		public $space; // Пробелы
		public $quotes; // Кавычки
		public $keywords; // Зарезервированные ключевые слова sql
		
		
		public $arr_keywords = array( // Список зарезервированных ключевых слов sql
			1 => " ADD ",
			2 => " EXTERNAL ",
			3 => " PROCEDURE ",
			4 => " ALL ",
			5 => " FETCH ",
			6 => " PUBLIC ",
			7 => " ALTER ",
			8 => " FILE ",
			9 => " RAISERROR ",
			10 => " AND ",
			11 => " FILLFACTOR ",
			12 => " READ ",
			13 => " ANY ",
			14 => " FOR ",
			15 => " READTEXT ",
			16 => " AS ",
			17 => " FOREIGN ",
			18 => " RECONFIGURE ",
			19 => " ASC ",
			20 => " FREETEXT ",
			21 => " REFERENCES ",
			22 => " AUTHORIZATION ",
			23 => " FREETEXTTABLE ",
			24 => " REPLICATION ",
			25 => " BACKUP ",
			26 => " FROM ",
			27 => " RESTORE ",
			28 => " BEGIN ",
			29 => " FULL ",
			30 => " RESTRICT ",
			31 => " BETWEEN ",
			32 => " FUNCTION ",
			33 => " RETURN ",
			34 => " BREAK ",
			35 => " GOTO ",
			36 => " REVERT ",
			37 => " BROWSE ",
			38 => " GRANT ",
			39 => " REVOKE ",
			40 => " BULK ",
			41 => " GROUP ",
			42 => " RIGHT ",
			43 => " BY ",
			44 => " HAVING ",
			45 => " ROLLBACK ",
			46 => " CASCADE ",
			47 => " HOLDLOCK ",
			48 => " ROWCOUNT ",
			49 => " CASE ",
			50 => " IDENTITY ",
			51 => " ROWGUIDCOL ",
			52 => " CHECK ",
			53 => " IDENTITY_INSERT ",
			54 => " RULE ",
			55 => " CHECKPOINT ",
			56 => " IDENTITYCOL ",
			57 => " SAVE ",
			58 => " CLOSE ",
			59 => " IF ",
			60 => " SCHEMA ",
			61 => " CLUSTERED ",
			62 => " IN ",
			63 => " SECURITYAUDIT ",
			64 => " COALESCE ",
			65 => " INDEX ",
			66 => " SELECT ",
			67 => " COLLATE ",
			68 => " INNER ",
			69 => " SEMANTICKEYPHRASETABLE ",
			70 => " COLUMN ",
			71 => " INSERT ",
			72 => " SEMANTICSIMILARITYDETAILSTABLE ",
			73 => " COMMIT ",
			74 => " INTERSECT ",
			75 => " SEMANTICSIMILARITYTABLE ",
			76 => " COMPUTE ",
			77 => " INTO ",
			78 => " SESSION_USER ",
			79 => " CONSTRAINT ",
			80 => " IS ",
			81 => " SET ",
			82 => " CONTAINS ",
			83 => " JOIN ",
			84 => " SETUSER ",
			85 => " CONTAINSTABLE ",
			86 => " KEY ",
			87 => " SHUTDOWN ",
			88 => " CONTINUE ",
			89 => " KILL ",
			90 => " SOME ",
			91 => " CONVERT ",
			92 => " LEFT ",
			93 => " STATISTICS ",
			94 => " CREATE ",
			95 => " LIKE ",
			96 => " SYSTEM_USER ",
			97 => " CROSS ",
			98 => " LINENO ",
			99 => " TABLE ",
			100 => " CURRENT ",
			101 => " LOAD ",
			102 => " TABLESAMPLE ",
			103 => " CURRENT_DATE ",
			104 => " MERGE ",
			105 => " TEXTSIZE ",
			106 => " CURRENT_TIME ",
			107 => " NATIONAL ",
			108 => " THEN ",
			109 => " CURRENT_TIMESTAMP ",
			110 => " NOCHECK ",
			111 => " TO ",
			112 => " CURRENT_USER ",
			113 => " NONCLUSTERED ",
			114 => " TOP ",
			115 => " CURSOR ",
			116 => " NOT ",
			117 => " TRAN ",
			118 => " DATABASE ",
			119 => " NULL ",
			120 => " TRANSACTION ",
			121 => " DBCC ",
			122 => " NULLIF ",
			123 => " TRIGGER ",
			124 => " DEALLOCATE ",
			125 => " OF ",
			126 => " TRUNCATE ",
			127 => " DECLARE ",
			128 => " OFF ",
			129 => " TRY_CONVERT ",
			130 => " DEFAULT ",
			131 => " OFFSETS ",
			132 => " TSEQUAL ",
			133 => " DELETE ",
			134 => " ON ",
			135 => " UNION ",
			136 => " DENY ",
			137 => " OPEN ",
			138 => " UNIQUE ",
			139 => " DESC ",
			140 => " OPENDATASOURCE ",
			141 => " UNPIVOT ",
			142 => " DISK ",
			143 => " OPENQUERY ",
			144 => " UPDATE ",
			145 => " DISTINCT ",
			146 => " OPENROWSET ",
			147 => " UPDATETEXT ",
			148 => " DISTRIBUTED ",
			149 => " OPENXML ",
			150 => " USE ",
			151 => " DOUBLE ",
			152 => " OPTION ",
			153 => " USER ",
			154 => " DROP ",
			155 => " OR ",
			156 => " VALUES ",
			157 => " DUMP ",
			158 => " ORDER ",
			159 => " VARYING ",
			160 => " ELSE ",
			161 => " OUTER ",
			162 => " VIEW ",
			163 => " END ",
			164 => " OVER ",
			165 => " WAITFOR ",
			166 => " ERRLVL ",
			167 => " PERCENT ",
			168 => " WHEN ",
			169 => " ESCAPE ",
			170 => " PIVOT ",
			171 => " WHERE ",
			172 => " EXCEPT ",
			173 => " PLAN ",
			174 => " WHILE ",
			175 => " EXEC ",
			176 => " PRECISION ",
			177 => " WITH ",
			178 => " EXECUTE ",
			179 => " PRIMARY ",
			180 => " WITHIN GROUP ",
			181 => " EXISTS ",
			182 => " PRINT ",
			183 => " WRITETEXT ",
			184 => " EXIT ",
			185 => " PROC ",
			186 => "=",
		);		
		
		public function index(){

			$this->string = '';
			$this->numeric = '';
			$this->space = '';
			$this->quotes = '';	
			$this->keywords = '';	
			
			
			// Раскадровка
			$i = -1;
			while ($i < count($this->value)){
				$i++;
				$this->string .= $this->value[$i];								
			}
			
			// Отцифровка			
			if (is_numeric($this->string)) { // ! без раскадровки "is_numeric" работать не будет
				$this->numeric = 1;
			} else {
				$this->numeric = 0;
			}
			
			// Пробелы			
			if (stripos($this->value, ' ') != '') $this->space = 1;
			
			// Кавычки			
			if ((stripos($this->value, '"') != '') or (stripos($this->value, '\'') != '')) $this->quotes = 1;
			
			// Зарезервированные ключевые слова sql
			$i = 0;
			$d = 0;
			while ($i < count($this->arr_keywords)){
				$i++;				
				if (stripos(mb_strtolower($this->value), mb_strtolower($this->arr_keywords[$i])) != '')	$d++;
			}
			if ($d > 0) $this->keywords = 1;
			
			
			
			
			// На случай, если пользователь не ввел параметр (не блокировать же из-за этого, а просто выводить ему ошибку или пустышку)
			if ($this->string == ''){ 
				$this->numeric = ''; 
				$this->space = '';
				$this->quotes = '';
				$this->keywords = '';				
			}
			
			
			return $this->string;
			return $this->numeric;
			return $this->space;
			return $this->quotes;			
			return $this->keywords;
			
		}
	}
	
	
	
	
	function signalAI($methodName, $parametrName, $token, $clientID, $request){
		$message = 'Метод: '.$methodName.'</br> Параметр: '.$parametrName.'</br>Токен: '.$token.'</br>clientID: '.$clientID.'</br>Запрос: '.$request.'';
		$headers .= "Content-Type: text/html; charset=utf-8\r\n";
		$headers .= "From: <webapi@tfnopt.ru>\r\n";
		$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";		
		mail('sidorchenko@tfnopt.ru', 'AI проблема', $message, $headers);
		
		
			$methodName = str_replace('что_reqменяем', '_resp', $methodName);
			echo '<?xml version="1.0" encoding="UTF-8"?>
											<ns1:'.$methodName.' xmlns:ns1="urn://tfnopt.ru">
<errorText>Операция не может быть выполнена! Проверьте параметр "'.$parametrName.'"</errorText>
											</ns1:'.$methodName.'>';
		
			exit;
		
			
	}	
	
?>