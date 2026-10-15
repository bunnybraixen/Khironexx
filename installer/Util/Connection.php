<?php

class InstallerConnection
{
    public static function connect($host, $port, $user, $pass, $db = null)
    {
        $mysqli = @new mysqli($host, $user, $pass, $db, (int)$port);
        
        if ($mysqli->connect_error) {
            return [null, $mysqli->connect_error];
        }
        $mysqli->set_charset('utf8mb4');
        return [$mysqli, null];
    }

    public static function databaseExists($host, $port, $user, $pass, $dbName)
    {
        if ($dbName === '') {
            return false;
        }
        [$db, $err] = self::connect($host, $port, $user, $pass, null);
        if ($err || !$db) {
            return false;
        }
        $safe = $db->real_escape_string($dbName);
        $result = $db->query("SHOW DATABASES LIKE '" . $safe . "'");
        return $result && $result->num_rows > 0;
    }
}
