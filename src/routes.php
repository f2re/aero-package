<?php

  Route::get('/aero', 'F2re\Aero\Controllers\AeroController@index');

  Route::get('/aero/{stantion}', 'F2re\Aero\Controllers\AeroController@bystantion');