<?php
class PagesController extends Controller
{
    public function home(): void
    {
        $data = [
            'title' => t('home_title'),
            'hero' => t('welcome'),
        ];
        $this->view->render('pages', 'home', $data);
    }
}
