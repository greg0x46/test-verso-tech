<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/api/docs');

Route::view('/api/docs', 'api-docs');
