<?php

class Database {
  /**
   * L'objet unique Database : Singleton
   *
   * @var Database
   * @access private
   * @static
   */
    private static $instance = null;

    /**
    * Constructeur de la classe
    *
    * @access private
    */
    private function __construct() {}

    /**
    * Méthode qui crée l'unique instance de la classe
    * si elle n'existe pas encore puis la retourne.
    *
    * @return Database
    */
    static function getInstance() {

        if(is_null(self::$instance)) {
            try {
                global $config;
                $dsn = 'mysql:dbname='.$config['dbname'].';host='.$config['dbhost'].';charset=utf8';
                $db = new PDO($dsn, $config['dbuser'], $config['dbpassword']);
                $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$instance = $db;
            } catch(PDOException $e) {
                throw new Exception('Erreur de connexion à la base de données: '.$e->getMessage());
            }
        }

        return self::$instance;
    }
}

class Collection implements Iterator {

    public $models_per_page = 5;

    /**
     * @var Model $models  une liste d’objets Model
     */
    protected $models = [];
    protected $db;
    protected $model_table = 'table';
    protected $model_class = 'Model';
    protected $pointer = 0;

    public function __construct() {
        $this->db = Database::get_instance();
    }

    protected function get_models($models) {
        if(!$models) {
            $error = $this->db->errorInfo();
            $error = $error[2];
            throw new Exception('Erreur de requête : '.$error);
        }
        foreach($models as $model) {
            $model_object = new $this->model_class($model['id']);
            $this->models[] = $model_object;
        }
    }

    /**
     * Récupère l’intégralité des modèles et les stocke dans $this->models.
     */
    public function get_all_models() {
        $models = $this->db->query('SELECT id FROM '.$this->model_table);
        $this->get_models($models);
    }

    /**
     * Récupère les modèles correspondant à une page donnée.
     */
    public function get_models_for_page($page) {
        $page = (int)$page;
        if($page < 0) $page = 0;
        $offset = $page * $this->models_per_page;
        $models = $this->db->query('SELECT id FROM '.$this->model_table.'
                                    LIMIT '.$offset.', '.$this->models_per_page);
        $this->get_models($models);
    }

    public function get_models_count() {
        $count = $this->db->query('SELECT COUNT(id) FROM '.$this->model_table)->fetchColumn();
        return $count;
    }

    public function get_page_count() {
        return ceil($this->get_models_count() / $this->models_per_page);
    }

    public function current() {
        return $this->models[$this->pointer];
    }

    public function key() {
        return $this->pointer;
    }

    public function next() {
        $this->pointer++;
    }

    public function rewind() {
        $this->pointer = 0;
    }

    public function valid() {
        return isset($this->models[$this->pointer]);
    }
}

class Model {

    protected $db;
    protected $model_table = 'table';

    public function __construct($id) {
        $this->db = Database::get_instance();
        $query = $this->db->prepare('SELECT * FROM '.$this->model_table.' WHERE id = :id');
        $query_result = $query->execute(['id' => $id]);
        if(!$query_result) {
            $error = $this->db->errorInfo();
            $error = $error[2];
            throw new Exception('Erreur de requête : '.$error);
        }
        $values = $query->fetch();
        if(!$values) {
            throw new Exception('Élément introuvable');
        }
        foreach($values as $key => $value) {
            $this->$key = $value;
        }
    }

}
