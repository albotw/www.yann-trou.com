<?php
require_once(__DIR__ . "./config.php");
require_once(__DIR__ . "./logger.php");

class db
{
    private static $instance = null;
    private PDO $PDO;
    private bool $error;    //mis a true s'il y a eu une erreur => VOIR LOGS POUR + DETAIL.

    private function __construct()
    {
        try
        {
            $this->PDO = new PDO(
                "mysql:dbname=" . config::$dbname . ";host=" . config::$hostname,
                config::$login,
                config::$password,
                array(PDO::ATTR_PERSISTENT)
            );
        }
        catch (PDOException $e)
        {
            echo "Erreur de connexion: " . $e->getMessage();
            logger::log("Impossible de se connecter à la base de données");
        }
    }

    /*
     * Méthode d'initialisation de db.
     * pattern singleton donc méthode statique
     */
    public static function getInstance()
    {
        if (!isset(self::$instance))
        {
            self::$instance = new db();
        }

        return self::$instance;
    }

    /*
     * méthode pour effectuer un appel SQL brut.
     *  En cas d'erreur, l'attribut error devient vrai l'erreur est enregistrée dans les logs.
     * @param $sql : string, contient la requête préparée
     * @param $params : array, contient les paramètres a placer dans la requête.
     * @param $callback : bool, indique si on doit fetch et return un résultat.
     */
    public function query($sql, $params = array(), $callback = true)
    {
        $this->error = false;
        $query = $this->PDO->prepare($sql);

        if (count($params)) //si params il y a -> bind
        {
            $x = 1;
            foreach ($params as $param)
            {
                $query->bindvalue($x, $param);
                $x++;
            }
        }

        try
        {
            $query->execute();
        }
        catch (PDOException $e)
        {
            logger::log($e->getMessage());
            $this->error = true;
            return null;
        }

        if (!$this->error)
        {
            if ($callback)
            {
                return $query->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }

    /*
     * Méthode simplifiée pour faire des appels SQL.
     * @param $action : string, 1ere partie de la requête "SELECT colonne1, colonne2"
     * @param $table : string, table ou sera effectuée la requête
     * @param $where : string, clause where sous la forme "user = {$var}"
     * @param $single : bool, retourne un élément unique ou un set d'éléments
     */
    public function call($action, $table, $where, $single = true)
    {
        $option = explode(" ", $where);
        if (count($option) == 3)
        {
            $validOperators = ["=", ">", "<", ">=", "<="];

            $field = $option[0];
            $operator = $option[1];
            $value = $option[2];

            if (in_array($operator, $validOperators))
            {
                $sql = "{$action} FROM {$table} WHERE {$field} {$operator} ?";
                if ($single)
                {
                    $result = $this->query($sql, array($value));
                    return $result == null ? [] : current($result);
                }
                else
                {
                    return $this->query($sql, array($value));
                }

            }
        }
        else
        {
            $sql = "{$action} FROM {$table}";
            return $this->query($sql, []);
        }
    }

    public function get($table, $where, $single = true)
    {
        return $this->call("SELECT *", $table, $where, $single);
    }

    public function getID($table, $id)
    {
        return $this->call("SELECT *", $table, "id = {$id}");
    }

    public function getAll($table)
    {
        return $this->call("SELECT *", $table, "", false);
    }

    public function insert($table, $fields)
    {
        if (count($fields))
        {
            $keys = array_keys($fields);
            $values = array_values($fields);

            $keys_string = implode(", ", $keys);
            $values_string = "";

            $i = 1;
            foreach ($values as $value)
            {
                $values_string .= "?";
                if ($i < count($values)) $values_string .= ", ";

                $i++;
            }

            $sql = "INSERT INTO {$table} (" . $keys_string . ") VALUES (" . $values_string . ");";

            $this->query($sql, $values, false);

            if(strcmp($table, "conversations") == 0) { $sql .= ""; }
            return $this->query('SELECT LAST_INSERT_ID();');
        }


    }
    /*
     * fonction pour mettre a jour un élément de la base de données.
     * @param $table : string, indique la table ou effectuer la maj
     * @param $whereid : string, clause where de la forme "<colonne identifiant> = <valeur unique>"
     * @param $fields : array(assoc), tableau regroupant les noms de colonne et les nouvelles valeurs.
     */
    public function update($table, $whereid, $fields)
    {
        $where = explode(" ", $whereid);
        if (count($fields) && count($where) == 3)
        {
            $keys = array_keys($fields);
            $values = array_values($fields);

            $keys_string = "";
            $i = 1;
            foreach($keys as $key)
            {
                $keys_string .= "{$key} = ?";
                if ($i < count($values)) $keys_string .= ", ";
                $i++;
            }

            //TODO: sanitisation where
            $sql = "UPDATE {$table} SET {$keys_string} WHERE {$where[0]} = {$where[2]}";
            $this->query($sql, $values, false);
        }
    }

    public function delete($table, $id)
    {
        return $this->call("DELETE", $table, "id = {$id}");
    }

    public function hasError()
    {
        return $this->error;
    }
}