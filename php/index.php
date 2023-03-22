<?php

// mysql 
$host1 = 'mysql';
$user1 = 'root';
$pass1 = 'qazSedcS123';
$dbname1 = 'prjctr';
$port1 = 3306;

//postgresql
$host2 = 'postgres';
$port2 = 5432;
$dbname2 = 'prjctr';
$user2 = 'prjctr';
$password2 = 'qwerty123@';

// Create mysql connection
$conn = new mysqli($host1, $user1, $pass1, $dbname1, $port1);

// Check mysql connection
if ($conn->connect_error) {
    die("Connection to Mysql failed: " . $conn->connect_error);
}
else {
    echo "Connection to Mysql success \n";
}

// Create postgresql connection
$dsn = "pgsql:host=$host2;port=$port2;dbname=$dbname2;user=$user2;password=$password2";

$pdo = null;

// Check postgresql connection
if ($host2 && $port2 && $dbname2 && $user2 && $password2) {
    // create a new PDO connection to the database
    $pdo = new PDO($dsn);
    
    // set the error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connection to PostgreSQL success \n";
    // close the connection
    $pdo = null;
} else {
    echo "Connection to PostgreSQL failed: Missing required credentials.";
}

$tx_isolations = array("READ-UNCOMMITTED","READ-COMMITTED","REPEATABLE-READ","SERIALIZABLE");

$mysql_conn1 = false;
$mysql_conn2 = false;
$postgres_conn1 = false;
$postgres_conn2 = false;

function checkResult($type,$status) {
        echo $type.": ".($status? "Yes" : "No")."\n";
}

function connectToDb() {
    global $mysql_conn1, $mysql_conn2, $postgres_conn1, $postgres_conn2;
 //   $mysql_conn1 = new mysqli($host1, $user1, $pass1, $dbname1, $port1);
 //   $mysql_conn2 = new mysqli($host1, $user1, $pass1, $dbname1, $port1);
 //   $postgres_conn1 = "pgsql:host=$host2;port=$port2;dbname=$dbname2;user=$user2;password=$password2";
 //   $postgres_conn2 = "pgsql:host=$host2;port=$port2;dbname=$dbname2;user=$user2;password=$password2";
    $mysql_conn1 = new PDO("mysql:host=host.docker.internal;port=3306;dbname=prjctr;", "root", "qazSedcS123");
    $mysql_conn2 = new PDO("mysql:host=host.docker.internal;port=3306;dbname=prjctr;", "root", "qazSedcS123");
    $postgres_conn1 = new PDO("pgsql:host=host.docker.internal;port=5432;dbname=prjctr;", "prjctr", "qwerty123@");
    $postgres_conn2 = new PDO("pgsql:host=host.docker.internal;port=5432;dbname=prjctr;", "prjctr", "qwerty123@");
}

function reconectToDb() {
        global $mysql_conn1,$mysql_conn2, $postgres_conn1, $postgres_conn2;
        if ($mysql_conn1) { unset($mysql_conn1); }
        if ($mysql_conn2) { unset($mysql_conn2); }
        if ($postgres_conn1) { unset($postgres_conn1); }
        if ($postgres_conn2) { unset($postgres_conn2); }
        connectToDb();
}

function MySQLLostUpdate($tx_isolation) {
    global $mysql_conn1,$mysql_conn2;
    reconectToDb();

    $mysql_conn1->beginTransaction();
    $mysql_conn1->query("SELECT age FROM users WHERE id = 1");

    $mysql_conn2->beginTransaction();
    $mysql_conn2->query("UPDATE users SET age = 40 WHERE id = 1");
    $mysql_conn2->commit();

    $mysql_conn1->query("UPDATE users SET age = 42 WHERE id = 1");
    $mysql_conn1->commit();

    $res = $mysql_conn1->query("SELECT age FROM users WHERE id = 1");
    checkResult("$tx_isolation MySQL Lost update", $res->fetchAll()[0]["age"] == 42);
}

function MySQLDirtyRead($tx_isolation) {
    global $mysql_conn1,$mysql_conn2;
    reconectToDb();

    $mysql_conn1->beginTransaction();
    $mysql_conn1->query("SELECT age FROM users WHERE id = 1");

    $mysql_conn2->beginTransaction();
    $mysql_conn2->query("UPDATE users SET age = 21 WHERE id = 1");

    $res = $mysql_conn1->query("SELECT age FROM users WHERE id = 1");
    checkResult("$tx_isolation MySQL Dirty read", $res->fetchAll()[0]["age"] == 21);

    $mysql_conn1->commit();
    $mysql_conn2->rollBack();
}

function MySQLNonRepeatableRead($tx_isolation) {
    global $mysql_conn1,$mysql_conn2;
    reconectToDb();

    $mysql_conn1->beginTransaction();
    $mysql_conn1->query("SELECT age FROM users WHERE id = 1");

    $mysql_conn2->beginTransaction();
    $mysql_conn2->query("UPDATE users SET age = 21 WHERE id = 1");
    $mysql_conn2->commit();

    $res = $mysql_conn1->query("SELECT age FROM users WHERE id = 1");
    checkResult("$tx_isolation MySQL Non-repeatable reads", $res->fetchAll()[0]["age"] == 21);

    $mysql_conn1->commit();
}

function MySQLPhantomReads($tx_isolation) {
    global $mysql_conn1,$mysql_conn2;
    reconectToDb();

    $mysql_conn1->beginTransaction();
    $mysql_conn1->query("SELECT count(*) as users_count FROM users WHERE age > 17");

    $mysql_conn2->beginTransaction();
    $mysql_conn2->query("INSERT INTO `users` (`id`,`name`,`age`) VALUES (3,'Carol',26)");
    $mysql_conn2->commit();

    $res = $mysql_conn1->query("SELECT count(*) as users_count FROM users WHERE age > 17");
    checkResult("$tx_isolation MySQL Phantom reads", $res->fetchAll()[0]["users_count"] == 3);

    $mysql_conn1->commit();
}

function PostgresLostUpdate($tx_isolation) {
    global $postgres_conn1, $postgres_conn2;
    reconectToDb();

    $postgres_conn1->beginTransaction();
    $postgres_conn1->query("SET TRANSACTION ISOLATION LEVEL ".str_replace("-"," ",$tx_isolation));
    $postgres_conn1->query("SELECT age FROM users WHERE id = 1");

    $postgres_conn2->beginTransaction();
    $postgres_conn2->query("SET TRANSACTION ISOLATION LEVEL ".str_replace("-"," ",$tx_isolation));
    $postgres_conn2->query("UPDATE users SET age = 40 WHERE id = 1");
    $postgres_conn2->commit();

    $postgres_conn1->query("UPDATE users SET age = 42 WHERE id = 1");
    $postgres_conn1->commit();

    $res = $postgres_conn1->query("SELECT age FROM users WHERE id = 1");
    checkResult("$tx_isolation Postgres Lost update", $res->fetchAll()[0]["age"] == 42);
}

function PostgresDirtyRead($tx_isolation) {
    global $postgres_conn1, $postgres_conn2;
    reconectToDb();

    $postgres_conn1->beginTransaction();
    $postgres_conn1->query("SET TRANSACTION ISOLATION LEVEL ".str_replace("-"," ",$tx_isolation));
    $postgres_conn1->query("SELECT age FROM users WHERE id = 1");

    $postgres_conn2->beginTransaction();
    $postgres_conn2->query("SET TRANSACTION ISOLATION LEVEL ".str_replace("-"," ",$tx_isolation));
    $postgres_conn2->query("UPDATE users SET age = 21 WHERE id = 1");

    $res = $postgres_conn1->query("SELECT age FROM users WHERE id = 1");

    checkResult("$tx_isolation Postgres Dirty read", $res->fetchAll()[0]["age"] == 21);

    $postgres_conn1->commit();
    $postgres_conn2->rollBack();
}

function PostgresNonRepeatableRead($tx_isolation) {
    global $postgres_conn1, $postgres_conn2;
    reconectToDb();

    $postgres_conn1->beginTransaction();
    $postgres_conn1->query("SET TRANSACTION ISOLATION LEVEL ".str_replace("-"," ",$tx_isolation));
    $postgres_conn1->query("SELECT age FROM users WHERE id = 1");

    $postgres_conn2->beginTransaction();
    $postgres_conn2->query("SET TRANSACTION ISOLATION LEVEL ".str_replace("-"," ",$tx_isolation));
    $postgres_conn2->query("UPDATE users SET age = 21 WHERE id = 1");
    $postgres_conn2->commit();

    $res = $postgres_conn1->query("SELECT age FROM users WHERE id = 1");
    checkResult("$tx_isolation Postgres Non-repeatable read", $res->fetchAll()[0]["age"] == 21);

    $postgres_conn1->commit();
}

function PostgresPhantomReads($tx_isolation) {
    global $postgres_conn1, $postgres_conn2;
    reconectToDb();

    $postgres_conn1->beginTransaction();
    $postgres_conn1->query("SET TRANSACTION ISOLATION LEVEL ".str_replace("-"," ",$tx_isolation));
    $postgres_conn1->query("SELECT count(*) as users_count FROM users WHERE age > 17");

    $postgres_conn2->beginTransaction();
    $postgres_conn2->query("SET TRANSACTION ISOLATION LEVEL ".str_replace("-"," ",$tx_isolation));
    $postgres_conn2->query("INSERT INTO users (id,name,age) VALUES (3,'Carol',26)");
    $postgres_conn2->commit();

    $res = $postgres_conn1->query("SELECT count(*) as users_count FROM users WHERE age > 17");
    checkResult("$tx_isolation Postgres Phantom reads", $res->fetchAll()[0]["users_count"] == 3);

    $postgres_conn1->commit();
}

function prepareTest($tx_isolation) {
    global $mysql_conn1,$mysql_conn2, $postgres_conn1, $postgres_db2;
    reconectToDb();

    $mysql_conn1->beginTransaction();
    $mysql_conn1->query("SET GLOBAL `tx_isolation` = '$tx_isolation'");
    $mysql_conn1->query("TRUNCATE `users`");
    $mysql_conn1->query("INSERT INTO `users` (`id`,`name`,`age`) VALUES (1,'Alice',20), (2, 'Bob', 25)");
    $mysql_conn1->commit();

    $postgres_conn1->query("TRUNCATE users");
    $postgres_conn1->query("INSERT INTO users (id,name,age) VALUES (1,'Alice',20), (2, 'Bob', 25)");
}

foreach ($tx_isolations as $tx_isolation) {


    prepareTest($tx_isolation);

    // Dirty read

    MySQLDirtyRead($tx_isolation);
    PostgresDirtyRead($tx_isolation);


    //Non-repeatable reads

    MySQLNonRepeatableRead($tx_isolation);
    PostgresNonRepeatableRead($tx_isolation);


    //Phantom reads

    MySQLPhantomReads($tx_isolation);
    PostgresPhantomReads($tx_isolation);

    //Lost update

    MySQLLostUpdate($tx_isolation);
    PostgresLostUpdate($tx_isolation);

}

?>