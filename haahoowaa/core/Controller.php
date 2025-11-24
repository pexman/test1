<?php
abstract class Controller
{
    protected array $config;
    protected View $view;
    protected PDO $db;

    public function __construct(array $config, View $view, PDO $db)
    {
        $this->config = $config;
        $this->view = $view;
        $this->db = $db;
    }
}
