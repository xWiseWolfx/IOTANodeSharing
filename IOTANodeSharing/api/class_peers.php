<?php
    class Peers{
    
        // database connection and table name
        private $conn;
        private $table_name = "Peers";
    
        // object properties
        public $id;
        public $peerAdress;
        public $peerID;
        public $port;
        public $apiPort;
        public $eMail;
        public $availability;
        public $dateAdded;
        public $lastAvailable;
        public $network;
    
        // constructor with $db as database connection
        public function __construct($db){
            $this->conn = $db;
        }

        // check existent peers
        function exists(){
            $encryption = new Encryption();

            $query = "SELECT * FROM " . $this->table_name . " WHERE PeerAdress=:peerAdress AND Port=:port";
        
            // prepare query statement
            $stmt = $this->conn->prepare($query);

            // bind values
            $stmt->bindParam(":peerAdress", $encryption->cryptify($this->peerAdress));
            $stmt->bindParam(":port", $encryption->cryptify($this->port));
        
            // execute query
            $stmt->execute();
            $num = $stmt->rowCount();
            
            if($num > 0){
                $query = "UPDATE " . $this->table_name . "
                            SET APIPort=:apiPort, PeerID=:peerID, eMail=:eMail, LastAvailable=:lastAvailable, Network=:network, Availability=:availability
                            WHERE PeerAdress=:peerAdress AND Port=:port";

                // prepare query statement
                $stmt = $this->conn->prepare($query);

                // bind values
                $stmt->bindParam(":peerAdress", $encryption->cryptify($this->peerAdress));
                $stmt->bindParam(":port", $encryption->cryptify($this->port));
                $stmt->bindParam(":apiPort", $encryption->cryptify($this->apiPort));
                $stmt->bindParam(":peerID", $encryption->cryptify($this->peerID));
                $stmt->bindParam(":eMail", $encryption->cryptify($this->eMail));
                $stmt->bindParam(":availability", $this->availability);
                $stmt->bindParam(":lastAvailable", $this->lastAvailable);
                $stmt->bindParam(":network", $this->network);

                // execute query
                $stmt->execute();

                return true;
            } else {
                return false;
            }
        }

        // read peers
        function read(){
            $encryption = new Encryption();

            // select all query
            $query = "SELECT p1.ID, p1.PeerAdress, p1.Port, p1.APIPort, p1.PeerID, p1.eMail, COUNT(*)
                        FROM " . $this->table_name . " p1
                        LEFT JOIN PeeringStatus s1 ON s1.PeersID = p1.ID
                        LEFT JOIN PeeringStatus s2 ON s2.MatchedPeersID = p1.ID
                        WHERE p1.Availability = 1
                            AND p1.Network=:network
                            AND p1.ID != (SELECT ID FROM Peers WHERE PeerAdress=:peerAdress AND Port=:port)
                            AND IFNULL(s1.MatchedPeersID, 0) != (SELECT ID FROM Peers WHERE PeerAdress=:peerAdress AND Port=:port)
                            AND IFNULL(s2.PeersID, 0) != (SELECT ID FROM Peers WHERE PeerAdress=:peerAdress AND Port=:port)
                        GROUP BY p1.ID, p1.PeerAdress, p1.Port, p1.APIPort, p1.PeerID, p1.eMail, p1.DateAdded
                        ORDER BY COUNT(*) ASC, p1.DateAdded ASC
                        LIMIT 3";
        
            // prepare query statement
            $stmt = $this->conn->prepare($query);
        
            // bind values
            $stmt->bindParam(":peerAdress", $encryption->cryptify($this->peerAdress));
            $stmt->bindParam(":port", $encryption->cryptify($this->port));
            $stmt->bindParam(":network", $this->network);

            // execute query
            $stmt->execute();
        
            return $stmt;
        }

        // create peer
        function create(){
            $encryption = new Encryption();

            // query to insert record
            $query = "INSERT INTO
                        " . $this->table_name . "
                    SET
                        PeerAdress=:peerAdress, Port=:port, APIPort=:apiPort, PeerID=:peerID, eMail=:eMail, DateAdded=:dateAdded, Availability=:availability, LastAvailable=:lastAvailable, Network=:network";
        
            // prepare query
            $stmt = $this->conn->prepare($query);

            // bind values
            $stmt->bindParam(":peerAdress", $encryption->cryptify($this->peerAdress));
            $stmt->bindParam(":port", $encryption->cryptify($this->port));
            $stmt->bindParam(":apiPort", $encryption->cryptify($this->apiPort));
            $stmt->bindParam(":peerID", $encryption->cryptify($this->peerID));
            $stmt->bindParam(":eMail", $encryption->cryptify($this->eMail));
            $stmt->bindParam(":availability", $this->availability);
            $stmt->bindParam(":dateAdded", $this->dateAdded);
            $stmt->bindParam(":lastAvailable", $this->lastAvailable);
            $stmt->bindParam(":network", $this->network);
        
            // execute query
            if($stmt->execute()){
                return true;
            }
        
            return false;          
        }

        // check for node existence
        function healthCheck() {
            $url = $this->peerAdress . ':' . $this->apiPort . '/health';
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
            $output = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
    
            return $httpcode;
        }

        function updateAvailability(){
            $encryption = new Encryption();

            $query = "UPDATE " . $this->table_name . " SET Availability=1, LastAvailable=:lastAvailable WHERE PeerAdress=:peerAdress AND Port=:port";
        
            // prepare query statement
            $stmt = $this->conn->prepare($query);

            // bind values
            $stmt->bindParam(":peerAdress", $encryption->cryptify($this->peerAdress));
            $stmt->bindParam(":port", $encryption->cryptify($this->port));
            $stmt->bindParam(":lastAvailable", date('Y-m-d H:i:s'));
        
            // execute query
            $stmt->execute();
        }

        function disable(){
            $encryption = new Encryption();

            $query = "UPDATE " . $this->table_name . " SET Availability = 0 WHERE PeerAdress=:peerAdress AND Port=:port";
        
            // prepare query statement
            $stmt = $this->conn->prepare($query);

            // bind values
            $stmt->bindParam(":peerAdress", $encryption->cryptify($this->peerAdress));
            $stmt->bindParam(":port", $encryption->cryptify($this->port));
        
            // execute query
            $stmt->execute();
        }

        function match($matchedPeersID){
            $encryption = new Encryption();

            $query = "INSERT INTO PeeringStatus SET PeersID=(SELECT ID FROM " . $this->table_name . " WHERE PeerAdress=:peerAdress AND Port=:port), MatchedPeersID=:matchedPeersID, DateMatched=:dateMatched";
        
            // prepare query statement
            $stmt = $this->conn->prepare($query);

            // bind values
            $stmt->bindParam(":peerAdress", $encryption->cryptify($this->peerAdress));
            $stmt->bindParam(":port", $encryption->cryptify($this->port));
            $stmt->bindParam(":matchedPeersID", $matchedPeersID);
            $stmt->bindParam(":dateMatched", date('Y-m-d H:i:s'));
        
            // execute query
            $stmt->execute();
        }

        function getRecentMatches(){
            $encryption = new Encryption();

            // select all query
            $query = "SELECT p1.PeerAdress, p1.Port, p1.APIPort, p1.PeerID FROM " . $this->table_name . " p1
                        INNER JOIN PeeringStatus p2 ON p2.MatchedPeersID = p1.ID
                        WHERE DATE_ADD(p2.DateMatched, INTERVAL 48 HOUR) > NOW()
                        AND p2.PeersID = (SELECT ID FROM Peers WHERE PeerAdress=:peerAdress AND Port=:port)";
        
            // prepare query statement
            $stmt = $this->conn->prepare($query);
        
            // bind values
            $stmt->bindParam(":peerAdress", $encryption->cryptify($this->peerAdress));
            $stmt->bindParam(":port", $encryption->cryptify($this->port));

            // execute query
            $stmt->execute();
        
            return $stmt;
        }

        // read all peers
        function readAll($available){
            $encryption = new Encryption();

            // select all query
            $query = "SELECT p1.ID, p1.PeerAdress, p1.APIPort, DATEDIFF(NOW(), p1.LastAvailable) AS DateDifference
                        FROM " . $this->table_name . " p1
                        WHERE p1.Availability=:available";
        
            // prepare query statement
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":available", $available);

            // execute query
            $stmt->execute();
        
            return $stmt;
        }

        function delete($id){

            $query = "DELETE FROM " . $this->table_name . " WHERE ID=:id";
        
            // prepare query statement
            $stmt = $this->conn->prepare($query);

            // bind values
            $stmt->bindParam(":id", $id);
        
            // execute query
            $stmt->execute();

            $query = "DELETE FROM PeeringStatus WHERE PeersID=:id OR MatchedPeersID=:id";
        
            // prepare query statement
            $stmt = $this->conn->prepare($query);

            // bind values
            $stmt->bindParam(":id", $id);
        
            // execute query
            $stmt->execute();
        }
    }
?>