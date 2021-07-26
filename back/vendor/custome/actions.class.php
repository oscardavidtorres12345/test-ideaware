<?php

namespace Custome;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
 * Actions for this develop
 */
class Actions
{

    /** @var string config data to ini file */
    private $account_id;

    /** @var string config data to ini file */
    private $api_url;

    /** @var string config data to ini file */
    private $authorization_code;

    /** @var string config data to ini file */
    private $client_id;

    /** @var string config data to ini file */
    private $client_secret;

    /** @var string config data to ini file */
    private $list_id;

    /** @var string config data to ini file */
    private $token_url;

    /** @var PDO String connection to DB (ORM PDO) */
    static private $_db;


    public function __construct()
    {
        // Get data config of ini file
        $config_ini = parse_ini_file("../back/enviroment.ini", true);

        // Section to connect DB
        $host = $config_ini['database']['host'];
        $user = $config_ini['database']['user'];
        $password = $config_ini['database']['password'];
        $port = $config_ini['database']['port'];
        $db = $config_ini['database']['db'];

        // If connect to db is empty, go to connect
        if (empty(self::$_db)) {
            $this->_connectDB($host, $user, $password, $port, $db);
        }

        // Section to get or refresh token
        $this->account_id = $config_ini['client']['account_id'];
        $this->api_url = $config_ini['client']['api_url'];
        $this->authorization_code = $config_ini['client']['authorization_code'];
        $this->client_id = $config_ini['client']['client_id'];
        $this->client_secret = $config_ini['client']['client_secret'];
        $this->list_id = $config_ini['client']['list_id'];
        $this->token_url = $config_ini['client']['token_url'];

        $this->_tokens();
    }

    /**
     * Connect to database
     */
    private function _connectDB($host, $user, $password, $port, $db)
    {
        try {
            self::$_db = new \PDO("mysql:host=".$host.";port=".$port.";dbname=".$db, $user, $password, array(
                \PDO::ATTR_AUTOCOMMIT => true
            ));
            self::$_db->query("set names utf8");
        } catch (\PDOException $e) {
            error_log ( "Error PDO: " . $e->getMessage () );
        }
    }

    /**
     * Execute any query in DB
     * @param string  $query
     * @param array   $params
     * @param boolean $es_row
     * @param integer $fetch
     * @return array
     */
    private function _execQuery($query, $params = array(), $es_row = false, $fetch = \PDO::FETCH_ASSOC)
    {
        $arrDatos = array();
        $cont = 0;
        $rs = self::$_db->prepare($query);
        
        if ($rs->execute($params)) {
            $cont = $rs->rowCount();
        }

        if ($cont > 0) {
            if ($es_row) {
                $arrDatos = $rs->fetch($fetch);
            } else {
                $arrDatos = $rs->fetchAll($fetch);
            }
        }

        return $arrDatos;
    }

    /**
     * Generete or refresh token of Aweber
     */
    private function _tokens()
    {
        try {
            // Create a new GuzzleHTTP Client and define scopes
            $client = new Client();

            // Use the authorization code to fetch an access token
            $tokenQuery = array(
                "grant_type" => "authorization_code",
                "code" => $this->authorization_code,
                "client_id" => $this->client_id
            );

            $tokenUrl = $this->token_url."?".http_build_query($tokenQuery);
            $response = $client->post(
                $tokenUrl, 
                [
                    'auth' => [$this->client_id, $this->client_secret],
                    'curl'   => array(CURLOPT_SSL_VERIFYPEER => false),
                    'verify' => false
                ]
            );

            // Insert in DB the new access token of Aweber
            $body = $response->getBody();
            $creds = json_decode($body, true);

            $qry = "INSERT INTO tokens (access_token, refresh_token, token_type) VALUES (?, ?, ?);";
            $this->_execQuery($qry, array($creds['access_token'], $creds['refresh_token'], $creds['token_type']));
        } catch (ClientException $e) {
            $response = $e->getResponse();
            error_log($response->getBody()->getContents());

            // If we have any error, try to refresh token
            $this->refreshToken();
        }
    }

    /**
     * Refresh token of OAuth 2 Aweber
     */
    private function refreshToken()
    {
        try {
            $tokens = $this->getLastToken();

            // Create a new GuzzleHTTP Client and define scopes
            $client = new Client();

            // Use the authorization code to fetch an access token
            $tokenQuery = array(
                "grant_type" => "refresh_token",
                "refresh_token" => $tokens["refresh_token"]
            );

            $tokenUrl = $this->token_url."?".http_build_query($tokenQuery);
            $response = $client->post(
                $tokenUrl, 
                [
                    'auth' => [$this->client_id, $this->client_secret],
                    'curl'   => array(CURLOPT_SSL_VERIFYPEER => false),
                    'verify' => false
                ]
            );

            // Insert in DB the new access token of Aweber
            $body = $response->getBody();
            $creds = json_decode($body, true);

            $qry = "INSERT INTO tokens (access_token, refresh_token, token_type) VALUES (?, ?, ?);";
            $this->_execQuery($qry, array($creds['access_token'], $creds['refresh_token'], $creds['token_type']));
        } catch (ClientException $e) {
            $response = $e->getResponse();
            error_log($response->getBody()->getContents());
        }
    }

    /**
     * Get the last token in DB
     * @return array
     */
    private function getLastToken()
    {
        $qry = "SELECT * FROM tokens ORDER BY id DESC LIMIT 1;";
        return $this->_execQuery($qry, array(), true);
    }

    /**
     * This method receive the subscriber's info and send to Aweber API and update DB
     */
    public function receiveDataSubscriber($name, $email, $check, $notes = "") {
        try {
            $qry = "SELECT * FROM subscribers WHERE email = ?";
            $subscriber = $this->_execQuery($qry, array($email));

            $tag = count($subscriber) > 0 ? "test_existing_sub" : "test_new_sub";
            $url_sub = $ip_address = $datetime = "";
            if ($check) {
                $url_sub = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $datetime = date("Y-m-d H:i:s");
            }

            $token = $this->getLastToken();

            // Create a new GuzzleHTTP Client and define scopes
            $client = new Client();
            $body = [
                'custom_fields' => [
                    'url' => $url_sub,
                    'datetime' => $datetime,
                    'ip_address' => $ip_address
                ],
                'email' => $email,
                'last_followup_message_number_sent' => 0,
                'misc_notes' => $notes,
                'name' => $name,
                'strict_custom_fields' => false,
                'update_existing' => true,
                'tags' => [$tag]
            ];
            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'AWeber-PHP-code-sample/1.0',
                'Authorization' => ucfirst($token["token_type"])." ".$token["access_token"]
            ];
            $url = $this->api_url."/accounts/".$this->account_id."/lists/".$this->list_id."/subscribers";
            if (count($subscriber) > 0) {
                $type_request = "PATCH";
                $params = ['email' => $email];
                $url .= "?".http_build_query($params);

                $qry = "UPDATE subscribers SET name = ?, tag = ?, ip_address = ?, url = ?, hash = ?, notes = ? WHERE id = ?;";
                $this->_execQuery($qry, array($name, $tag, $ip_address, $url_sub, $datetime, $notes, $subscriber[0]["id"]));
            } else {
                $type_request = "POST";

                $qry = "INSERT INTO subscribers (name, email, tag, ip_address, url, hash, notes) VALUES (?, ?, ?, ?, ?, ?, ?);";
                $this->_execQuery($qry, array($name, $email, $tag, $ip_address, $url_sub, $datetime, $notes));
            }

            $client->request(
                $type_request,
                $url,
                [
                    'json' => $body,
                    'headers' => $headers,
                    'curl'   => array(CURLOPT_SSL_VERIFYPEER => false),
                    'verify' => false
                ]
            );
            file_put_contents("logs/log.log", $email." - ".date("m/d/y H:i:s")." - Agregado exitosamente\n", FILE_APPEND);
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $msg = $response->getBody()->getContents();

            file_put_contents("logs/log.log", $email." - ".date("m/d/y H:i:s")." - Fallo envío ".$msg."\n", FILE_APPEND);

        }
    }
}
