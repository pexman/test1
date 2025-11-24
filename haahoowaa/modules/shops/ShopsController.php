<?php
class ShopsController extends Controller
{
    public function detail(string $slug): void
    {
        $data = [
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'address' => '123 Demo Street',
            'description' => 'Información de la tienda',
        ];
        $this->view->render('shops', 'shop_detail', $data);
    }
}
