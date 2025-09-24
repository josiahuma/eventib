<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Avatar extends Component
{
    public $model;
    public $size;

    public function __construct($model, $size = 'w-10 h-10')
    {
        $this->model = $model;
        $this->size = $size;
    }

    public function render()
    {
        return view('components.avatar');
    }
}
