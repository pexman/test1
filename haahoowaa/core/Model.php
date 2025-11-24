<?php
class Model
{
    protected PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
}
