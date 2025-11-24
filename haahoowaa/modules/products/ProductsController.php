<?php
class ProductsController extends Controller
{
    public function list(): void
    {
        $data = [
            'title' => t('products_title'),
            'items' => [
                ['name' => 'Producto A', 'slug' => 'producto-a'],
                ['name' => 'Producto B', 'slug' => 'producto-b'],
            ],
        ];
        $this->view->render('products', 'list', $data);
    }

    public function detail(string $slug): void
    {
        $data = [
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'description' => 'Descripción de ' . $slug,
        ];
        $this->view->render('products', 'detail', $data);
    }
}
