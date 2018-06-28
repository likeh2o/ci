<?php

require_once dirname(__DIR__) . '/LogicBase.php';

class Demo extends LogicBase
{

    public function __construct()
    {
        parent::__construct();
    }

    public function getById($id)
    {
        $params = compact('id');
        $result = $this->doRequest(API_HOST_DEMO, 'article/index.json', $params, METHOD_GET);
        return $this->getDataFromResult($result);
    }

    public function list_by_page($page = 1)
    {
        $params = compact('page');
        $result = $this->doRequest(API_HOST_DEMO, 'article/list_by_page.json', $params, METHOD_GET);
        return $this->getDataFromResult($result);
    }

}
