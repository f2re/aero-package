<?php

  Route::get('/aero', 'F2re\Aero\Controllers\AeroController@index');
  
  Route::post('/aero', 'F2re\Aero\Controllers\AeroController@getchart_post');

  Route::get('/aero/{stantion}', 'F2re\Aero\Controllers\AeroController@bystantion');