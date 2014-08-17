<?php

header('Access-Control-Allow-Origin: *');

require 'Slim/Slim.php';

$app = new Slim();

// ROUTES /////////////////////////////////////////////////////////////////////
$app->get('/', 'index'); //instructions

$app->get('/cars', 'getCars'); //returns array of car objects
$app->get('/cars/:id', 'getCar'); //returns one car object
$app->post('/cars', 'addCar'); //accepts JSON object of car data; returns new record id wrapped in object: `{"id":xxx}`
$app->put('/cars/:id', 'updateCar'); //accepts JSON object of car data; returns nothing
$app->delete('/cars/:id', 'deleteCar'); //returns nothing

$app->get('/manufacturers', 'getManufacturers'); //returns array of manufacturer objects
$app->get('/manufacturers/:id', 'getManufacturer'); //returns one manufacturer object
$app->post('/manufacturers', 'addManufacturer'); //accepts JSON object of manufacturer data; returns new record id wrapped in object: `{"id":xxx}`
$app->put('/manufacturers/:id', 'updateManufacturer'); //accepts JSON object of manufacturer data; returns nothing
$app->delete('/manufacturers/:id', 'deleteManufacturer'); //returns nothing. Note that manufacturer will not be deleted if any cars are assigned to it!

$app->run();


function index() {
	echo '<h2>Mithril Cars API</h2>';
	echo '<ul>';
	echo '<li><code>GET</code> <b>/cars</b> - returns array of car objects (or empty array if no car records exist)</li>';
	echo '<li><code>GET</code> <b>/cars/:id</b> - returns one car object (or empty object if nothing exists for the given id)</li>';
	echo '<li><code>POST</code> <b>/cars</b> - accepts JSON object of car data and inserts it as a new record; returns new record</li>';
	echo '<li><code>PUT</code> <b>/cars/:id</b> - accepts JSON object of car data and updates the existing record with the given id; returns nothing</li>';
	echo '<li style="padding-bottom: 10px;"><code>DELETE</code> <b>/cars/:id</b> - deletes the car record with the given id; returns nothing</li>';
	echo '<li><code>GET</code> <b>/manufacturers</b> - returns array of manufacturer objects (or empty array if no manufacturer records exist)</li>';
	echo '<li><code>GET</code> <b>/manufacturers/:id</b> - returns one manufacturer object (or empty object if nothing exists for the given id)</li>';
	echo '<li><code>POST</code> <b>/manufacturers</b> - accepts JSON object of manufacturer data and inserts it as a new record; returns new record</li>';
	echo '<li><code>PUT</code> <b>/manufacturers/:id</b> - accepts JSON object of manufacturer data and updates the existing record with the given id; returns nothing</li>';
	echo '<li><code>DELETE</code> <b>/manufacturers/:id</b> - deletes the manufacturer record with the given id (but only if the id is not currently assigned to any car records); returns nothing</li>';
}

// CARS REST API FUNCTIONS ////////////////////////////////////////////////////
function getCars() {
	$sql = 'SELECT c.*, m.name AS manufacturer_name'
	     . ' FROM cars c INNER JOIN manufacturers m'
	     . ' ON c.manufacturer_id = m.id'
	     . ' ORDER BY manufacturer_name, model_name, model_year';
	$records = dbQuery($sql);
	$json = json_encode($records);
	echo $json;
}

function getCar($id) {
	$id = (int)$id;
	if (empty($id)) {
		exitWithError('invalid or missing id');
	}

	$sql = 'SELECT c.*, m.name AS manufacturer_name'
	     . ' FROM cars c INNER JOIN manufacturers m'
	     . ' ON c.manufacturer_id = m.id'
	     . ' WHERE c.id=:id'
	    . ' LIMIT 1';
	$params = array('id' => $id);
	$records = dbQuery($sql, $params);
	$json = (empty($records) ? '{}' : json_encode($records[0]));
	echo $json;
}

function addCar() {
	$data = getRequestDataAsObject();
	$error = validateCarData($data);
	if ($error) {
		exitWithError($error);
	} else {
		$data->id = dbInsertFromObject('cars', $data);
		$json = json_encode($data);
		echo $json;
	}
}

function updateCar($id) {
	$id = (int)$id;
	if (empty($id)) {
		exitWithError('invalid or missing id');
	}

	$data = getRequestDataAsObject();
	$error = validateCarData($data);
	if ($error) {
		exitWithError($error);
	} else {
		dbUpdateFromObject('cars', $data, $id);
	}
}

function deleteCar($id) {
	$id = (int)$id;
	if (empty($id)) {
		exitWithError('invalid or missing id');
	}

	dbDelete('cars', $id);
}


// MANUFACTURERS REST API FUNCTIONS ///////////////////////////////////////////
function getManufacturers() {
	$sql = 'SELECT * FROM manufacturers ORDER BY name';
	$records = dbQuery($sql);
	$json = json_encode($records);
	echo $json;
}

function getManufacturer($id) {
	$id = (int)$id;
	if (empty($id)) {
		exitWithError('invalid or missing id');
	}

	$sql = 'SELECT * FROM manufacturers WHERE id=:id LIMIT 1';
	$params = array('id' => $id);
	$records = dbQuery($sql, $params);
	$json = (empty($records) ? '{}' : json_encode($records[0]));
	echo $json;
}

function addManufacturer() {
	$data = getRequestDataAsObject();
	$error = validateManufacturerData($data);
	if ($error) {
		exitWithError($error);
	} else {
		$data->id = dbInsertFromObject('manufacturers', $data);
		$json = json_encode($data);
		echo $json;
	}
}

function updateManufacturer($id) {
	$id = (int)$id;
	if (empty($id)) {
		exitWithError('invalid or missing id');
	}

	$data = getRequestDataAsObject();
	$error = validateManufacturerData($data);
	if ($error) {
		exitWithError($error);
	} else {
		dbUpdateFromObject('manufacturers', $data, $id);
	}
}

function deleteManufacturer($id) {
	$id = (int)$id;
	if (empty($id)) {
		exitWithError('invalid or missing id');
	}

	if (carExistsWithManufacturer($id)) {
		exitWithError('cannot delete manufacturer because it is assigned to one or more cars');
	} else {
		dbDelete('manufacturers', $id);
	}
}


// UTILITY FUNCTIONS //////////////////////////////////////////////////////////
function getRequestDataAsObject() {
	$request = Slim::getInstance()->request();
	$json = $request->getBody();
	$object = json_decode($json);
	return $object;
}

function validateCarData($data) {
	$error = '';

	if (empty($data->manufacturer_id) || empty($data->model_name) || empty($data->model_year)) {
		$error = 'missing required data (you must provide manufacturer_id, model_name, and model_year)';
	} else if (!ctype_digit($data->model_year) || (strlen($data->model_year) != 4)) {
		$error = 'model year is invalid (it must be a 4-digit number';
	} else if (!manufacturerExists($data->manufacturer_id)) {
		$error = 'manufacturer_id is invalid';
	}

	return $error;
}

function validateManufacturerData($data) {
	$error = '';

	if (empty($data->name)) {
		$error = 'missing required data (you must provide a name)';
	}

	return $error;
}

function manufacturerExists($id) {
	$id = (int)$id;
	$records = dbQuery('SELECT COUNT(*) AS cnt FROM manufacturers WHERE id=:id', array('id' => $id));
	$cnt = $records[0]->cnt;
	return (bool)$cnt;
}

function carExistsWithManufacturer($manufacturer_id) {
	$manufacturer_id = (int)$manufacturer_id;
	$records = dbQuery('SELECT COUNT(*) AS cnt FROM cars WHERE manufacturer_id=:manufacturer_id', array('manufacturer_id' => $manufacturer_id));
	$cnt = $records[0]->cnt;
	return (bool)$cnt;
}

function exitWithError($text) {
	$error = array('error' => array('text' => $text));
	$json = json_encode($error);
	die($json);
}


// DB ACCESS LAYER FUNCTIONS //////////////////////////////////////////////////
function dbQuery($sql, $params = array()) {
	try {
		$db = dbConnection();
		$stmt = $db->prepare($sql);
		foreach ($params as $key => $val) {
			$stmt->bindValue($key, $val);
		}
		$stmt->execute();
		$records = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		return $records;
	} catch(PDOException $e) {
		exitWithError($e->getMessage());
	}
}

function dbInsertFromObject($table, $object) {
	if (empty($table) || empty($object)) {
		return null;
	}

	$fields = get_object_vars($object);
	$field_names = array_keys($fields);
	$sql = "INSERT INTO {$table} (" . implode(', ', $field_names) . ") VALUES (:" . implode(', :', $field_names) . ")";
	try {
		$db = dbConnection();
		$stmt = $db->prepare($sql);
		foreach ($fields as $key => $val) {
			$stmt->bindValue($key, $val);
		}
		$stmt->execute();
		$id = $db->lastInsertId();
		$db = null;
		return $id;
	} catch(PDOException $e) {
		exitWithError($e->getMessage());
	}
}

function dbUpdateFromObject($table, $object, $id, $id_field_name = 'id') {
	if (empty($table) || empty($object) || empty($id)) {
		return;
	}

	$fields = get_object_vars($object);
	$field_names = array_keys($fields);
	$field_pairs = array();
	foreach ($field_names as $field_name) {
		$field_pairs[] = "{$field_name}=:{$field_name}";
	}
	$sql = "UPDATE {$table} SET " . implode(', ', $field_pairs) . " WHERE {$id_field_name}=:{$id_field_name}";
	try {
		$db = dbConnection();
		$stmt = $db->prepare($sql);
		foreach ($fields as $key => $val) {
			$stmt->bindValue($key, $val);
		}
		$stmt->bindValue($id_field_name, $id);
		$stmt->execute();
		$db = null;
		return;
	} catch(PDOException $e) {
		exitWithError($e->getMessage());
	}
}

function dbDelete($table, $id, $id_field_name = 'id') {
	if (empty($table) || empty($id)) {
		return;
	}

	$sql = "DELETE FROM {$table} WHERE {$id_field_name}=:{$id_field_name}";
	try {
		$db = dbConnection();
		$stmt = $db->prepare($sql);
		$stmt->bindValue($id_field_name, $id);
		$stmt->execute();
		$db = null;
		return;
	} catch(PDOException $e) {
		exitWithError($e->getMessage());
	}
}

function dbConnection() {
	$dbh = new PDO('sqlite:' . __DIR__ . '/cars.sqlite3');
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	return $dbh;
}
