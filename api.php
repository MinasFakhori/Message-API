<?php
class MessageAPI
{
    private $db = null;

    // Default status code is 500 (Internal Server Error). 
    private $status = 500;


    public function __construct($host, $username, $password, $db)
    {
        // connect to database
        $this->db = new mysqli($host, $username, $password, $db);
        if ($this->db->connect_errno) {
            $this->status = 500;
        }

    }

    public function __destruct()
    {
        if (!($this->db->connect_errno)) {
            // close database
            $this->db->close();
        }

    }

    public function check($source, $target)
    {
        if ($source !== null && $target === null) {
            if (ctype_alnum($source) && strlen($source) >= 4 && strlen($source) <= 32) {
                return true;
            } else {
                return false;
            }
        } else if ($source === null && $target !== null) {
            if (ctype_alnum($target) && strlen($target) >= 4 && strlen($target) <= 32) {
                return true;
            } else {
                return false;
            }
        } else {
            // check if both strings are alphanumeric and between 4 and 32 characters
            if (ctype_alnum($source) && ctype_alnum($target) && strlen($source) >= 4 && strlen($target) >= 4 && strlen($source) <= 32 && strlen($target) <= 32) {
                return true;
            } else {
                return false;
            }
        }
    }


    public function sql_maker($source, $target)
    {
        if ($source !== null && $target !== null) {
            $sql1 = "SELECT * FROM messages WHERE source = '$source' AND target = '$target' ORDER BY id";
            // If the users exist by they haven't sent any messages to each other
            $sql2 = "SELECT * FROM messages WHERE source ='$source' ORDER BY id";
            $sql3 = "SELECT * FROM messages WHERE target = '$target' ORDER BY id";
            return array($sql1, $sql2, $sql3);
        } else if ($source !== null && $target === null) {
            $sql1 = "SELECT * FROM messages WHERE source = '$source' ORDER BY id";
            return $sql1;
        } else if ($source === null && $target !== null) {
            $sql1 = "SELECT * FROM messages WHERE target = '$target' ORDER BY id";
            return $sql1;
        }
    }


    public function handle_get_source()
    {
        // get source from GET request
        $source = $_GET['source'];

        if ($this->check($source, null)) {
            // escape string for SQL
            $source = $this->db->real_escape_string($source);
            $sql = $this->sql_maker($source, null);
            $this->status = 200;
            return $sql;
        } else {
            $this->status = 400;
        }
    }

    public function handle_get_target()
    {
        $target = $_GET['target'];

        if ($this->check(null, $target)) {
            $target = $this->db->real_escape_string($target);
            $sql = $this->sql_maker(null, $target);
            $this->status = 200;
            return $sql;
        } else {
            $this->status = 400;
        }
    }

    public function handle_get_source_target()
    {
        $source = $_GET['source'];
        $target = $_GET['target'];

        if ($this->check($source, $target)) {
            $source = $this->db->real_escape_string($source);
            $target = $this->db->real_escape_string($target);
            $this->status = 200;
            $sql = $this->sql_maker($source, $target);
            return $sql;
        } else {
            $this->status = 400;
        }
    }

    public function handle_get()
    {
        $sql = '';
        $result = '';
        $isBoth = false;
        $zeroResults = true;

        if (isset($_GET['source']) && isset($_GET['target'])) {
            $sqlArr = $this->handle_get_source_target();
            // If the users are valid it will return an array with 3 SQL statements, to check it I use the status code
            if ($this->status === 200) {
                // This sql checks the messages between source and target
                $sql = $sqlArr[0];
                $isBoth = true;
            }
        } else if (isset($_GET['source'])) {
            $sql = $this->handle_get_source();
        } else if (isset($_GET['target'])) {
            $sql = $this->handle_get_target();
        } else {
            $this->status = 400;
        }

        if ($this->status === 200) {
            $result = $this->db->query($sql);
            $outputs = array();
            if ($result->num_rows !== 0 && $result !== false) {
                $zeroResults = false;
                while ($row = $result->fetch_assoc()) {
                    // push each row into array
                    array_push($outputs, $row);
                }
                // If there are no results, but both users are put in the url
            } else if ($isBoth && $zeroResults) {
                $result1 = $this->db->query($sqlArr[1]);
                $result2 = $this->db->query($sqlArr[2]);
                if ($result1->num_rows !== 0 && $result1 !== false && $result2->num_rows !== 0 && $result2 !== false) {
                    $this->status = 204;
                } else {
                    // In the assignment brief it say 400, it should be 404
                    $this->status = 404;
                }
            } else {
                // In the assignment brief it say 400, it should be 404
                $this->status = 404;
            }

            if ($this->status === 200) {
                $messages = array('messages' => $outputs);
                return json_encode($messages);
            }
        }
    }


    public function handle_post()
    {
        $id = 0;
        // check parameters
        if (isset($_POST['source']) && isset($_POST['target']) && isset($_POST['message'])) {
            $source = $_POST['source'];
            $target = $_POST['target'];
            $message = $_POST['message'];

            if ($this->check($source, $target)) {
                // protect against SQL injection attacks
                $source = $this->db->real_escape_string($source);
                $target = $this->db->real_escape_string($target);
                $message = $this->db->real_escape_string($message);

                // build SQL statement
                $sql = "INSERT INTO messages (source, target, message) VALUES ('$source', '$target', '$message')";

                // execute SQL statement                     
                $result = $this->db->query($sql);
                if ($result !== false) {
                    $this->status = 201;
                    $id = $this->db->insert_id;
                }
            } else {
                $this->status = 400;
            }
        } else {
            $this->status = 400;
        }

        if ($this->status === 201) {
            // return the id of the new message, if the request was successful
            return '{ "id": ' . $id . '}';
        }
    }


    public function handle_request()
    {
        $postId = '';
        $messageJson = '';
        if (!($this->db->connect_errno)) {
            // check request method
            if (!strcmp($_SERVER['REQUEST_METHOD'], 'POST')) {
                $postId = $this->handle_post();
            } else if (!strcmp($_SERVER['REQUEST_METHOD'], 'GET')) {
                $messageJson = $this->handle_get();
            } else {
                $this->status = 405;
            }
        } else {
            $this->status = 500;
        }
        http_response_code($this->status);

        // if the request was a POST request and it was successful, return the id of the new message
        if ($this->status === 201) {
            echo $postId;
            // if the request was a GET request and it was successful, return the messages
        } else if ($this->status === 200) {
            header('Content-type: application/json');
            echo $messageJson;
        }
    }
}

$username = '';
$password = '';
$db = '';
$api = new MessageAPI('localhost', $username, $password, $db);
$api->handle_request();