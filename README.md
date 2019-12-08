[![Latest Stable Version](https://poser.pugx.org/f2re/aero/v/stable)](https://packagist.org/packages/f2re/aero)
[![Latest Unstable Version](https://poser.pugx.org/f2re/aero/v/unstable)](https://packagist.org/packages/f2re/aero)
[![License](https://poser.pugx.org/f2re/aero/license)](https://packagist.org/packages/f2re/aero)

# aero-package
Package for Laravel. Chart aero sounding data

# Installation  
You may install package via Composer

    composer require f2re/aero
    
# Routes
Package has some routes:
    
      Route::get('/aero', 'F2re\Aero\Controllers\AeroController@index');
      Route::get('/aero/{stantion}', 'F2re\Aero\Controllers\AeroController@bystantion');

# How to use
All available stations accesible on url: **/aero** in JSON format:
```javascript
{
  "stantions": {
    "10035": {
      "id": "10035",
      "name": "Шлесвиг",
      "name_en": "Schleswig",
      "country": "Германия",
      "lat": "54,52",
      "lon": "9,54"
    },
    "10113": {
      "id": "10113",
      "name": "Норденой",
      "name_en": "Norderney",
      "country": "Германия",
      "lat": "53,71",
      "lon": "7,15"
    },
    ...
}
```

To get aerologic diagramm you can do request to url **/aero/{stantion.id}**, where `{stantion.id}` is number of station. The response in JSON format will be:
```javascript
{
  "path": "/png/10035-2019-12-08-12.png"
}
```

# Data providers
If you want to chge data sources of codes, you may implements class `F2re\Aero\AeroDataProvider`. Now sounding data downloads from **http://www.rap.ucar.edu/weather/upper/current.rawins**.

# Charts
You can set up chart by reoder functions in `F2re\Aero\Controllers\AeroController` controller. Function `getchart()` include draw order pipeline:
```php
$drawer->init()
     ->drawInversions()
     ->drawSost()
     ->drawTemp()
     ->drawIsoterm()
     ->drawWind()
     ->drawEnegry()
     ->drawUK()
     ->drawClouds()
     ->drawIndexes()
     ->saveImage();
```
This pipeline generate this chart: [http://ivanf2re.tmweb.ru/png/27038-2019-12-08-12.png](Vologda chart)
