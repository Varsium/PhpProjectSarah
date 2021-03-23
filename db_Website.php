
<?php
//maken pdo verbinding met db
//$verbinding gaan we overal in ons project gebruiken als db-object
DEFINE("USER", "root");
DEFINE("PASSWORD", "");

try {
    $verbinding = new
    PDO("mysql:host=localhost;dbname=sarahbooking",USER,PASSWORD);

   $verbinding->setAttribute (PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION );
    echo "Verbinding met database gemaakt<br>";
}catch(PDOException $e) {
    echo $e->getMessage();
    echo "Kon geen verbinding maken.<br>";
}
?>
<?php
class pdo_dblib_mssql{

    private $db;
       private $cTransID;
       private $childTrans = array();

    public function __construct($hostname, $port, $dbname, $username, $pwd){

        $this->hostname = $hostname;
        $this->port = $port;
        $this->dbname = $dbname;
        $this->username = $username;
        $this->pwd = $pwd;

        $this->connect();

    }

    public function beginTransaction(){

        $cAlphanum = "AaBbCc0Dd1EeF2fG3gH4hI5iJ6jK7kLlM8mN9nOoPpQqRrSsTtUuVvWwXxYyZz";
        $this->cTransID = "T".substr(str_shuffle($cAlphanum), 0, 7);

        array_unshift($this->childTrans, $this->cTransID);

        $stmt = $this->db->prepare("BEGIN TRAN [$this->cTransID];");
        return $stmt->execute();

    }

    public function rollBack(){

        while(count($this->childTrans) > 0){
            $cTmp = array_shift($this->childTrans);
            $stmt = $this->db->prepare("ROLLBACK TRAN [$cTmp];");
            $stmt->execute();
        }

        return $stmt;
    }

    public function commit(){

        while(count($this->childTrans) > 0){
            $cTmp = array_shift($this->childTrans);
            $stmt = $this->db->prepare("COMMIT TRAN [$cTmp];");
            $stmt->execute();
        }

        return  $stmt;
    }

    public function close(){
        $this->db = null;
    }

    public function connect(){

        try {
            $this->db = new PDO ("dblib:host=$this->hostname:$this->port;dbname=$this->dbname", "$this->username", "$this->pwd");



        } catch (PDOException $e) {
            $this->logsys .= "Failed to get DB handle: " . $e->getMessage() . "\n";
        }

    }

}

?>
